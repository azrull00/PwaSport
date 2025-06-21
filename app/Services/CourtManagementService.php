<?php

namespace App\Services;

use App\Models\Event;
use App\Models\MatchHistory;
use App\Models\EventParticipant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Services\NotificationSchedulerService;

class CourtManagementService
{
    protected $notificationService;

    public function __construct(NotificationSchedulerService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Get court assignments and queue for an event
     */
    public function getCourtStatus(Event $event)
    {
        $courts = [];
        $maxCourts = $event->max_courts ?? 4;

        // Get all active matches for this event
        $activeMatches = MatchHistory::with(['player1.profile', 'player2.profile'])
            ->where('event_id', $event->id)
            ->whereIn('match_status', ['ongoing', 'scheduled'])
            ->orderBy('court_number')
            ->get();

        // Organize matches by court
        for ($courtNum = 1; $courtNum <= $maxCourts; $courtNum++) {
            $courtMatch = $activeMatches->where('court_number', $courtNum)->first();
            
            $courts[] = [
                'court_number' => $courtNum,
                'status' => $courtMatch ? ($courtMatch->match_status === 'ongoing' ? 'playing' : 'scheduled') : 'available',
                'match' => $courtMatch ? [
                    'id' => $courtMatch->id,
                    'player1' => [
                        'id' => $courtMatch->player1->id,
                        'name' => $courtMatch->player1->name,
                        'profile_picture' => $courtMatch->player1->profile->profile_picture ?? null
                    ],
                    'player2' => [
                        'id' => $courtMatch->player2->id,
                        'name' => $courtMatch->player2->name,
                        'profile_picture' => $courtMatch->player2->profile->profile_picture ?? null
                    ],
                    'start_time' => $courtMatch->created_at,
                    'estimated_duration' => $courtMatch->estimated_duration,
                    'current_score' => $courtMatch->match_score
                ] : null
            ];
        }

        // Get queue information
        $queueInfo = $this->getQueueInformation($event);

        return [
            'courts' => $courts,
            'queue' => $queueInfo,
            'total_courts' => $maxCourts,
            'active_matches' => $activeMatches->where('match_status', 'ongoing')->count(),
            'scheduled_matches' => $activeMatches->where('match_status', 'scheduled')->count()
        ];
    }

    /**
     * Get detailed queue information
     */
    public function getQueueInformation(Event $event)
    {
        // Get confirmed participants who are not currently playing
        $allParticipants = EventParticipant::with(['user.profile', 'user.sportRatings'])
            ->where('event_id', $event->id)
            ->where('status', 'confirmed')
            ->get();

        $playingUserIds = MatchHistory::where('event_id', $event->id)
            ->whereIn('match_status', ['ongoing', 'scheduled'])
            ->get()
            ->flatMap(function($match) {
                return [$match->player1_id, $match->player2_id];
            })
            ->unique()
            ->values()
            ->toArray();

        $waitingParticipants = $allParticipants->filter(function($participant) use ($playingUserIds) {
            return !in_array($participant->user_id, $playingUserIds);
        });

        return [
            'total_waiting' => $waitingParticipants->count(),
            'can_create_match' => $waitingParticipants->count() >= 2,
            'waiting_players' => $waitingParticipants->map(function($participant) {
                $sportRating = $participant->user->sportRatings
                    ->where('sport_id', $participant->event->sport_id)
                    ->first();

                return [
                    'user_id' => $participant->user_id,
                    'name' => $participant->user->name,
                    'profile_picture' => $participant->user->profile->profile_picture ?? null,
                    'mmr' => $sportRating ? $sportRating->mmr : 1000,
                    'level' => $this->getSkillLevelFromMMR($sportRating ? $sportRating->mmr : 1000),
                    'waiting_since' => $participant->confirmed_at ?? $participant->created_at,
                    'waiting_minutes' => Carbon::now()->diffInMinutes($participant->confirmed_at ?? $participant->created_at),
                    'is_premium' => $participant->user->subscription_tier === 'premium'
                ];
            })->sortBy('waiting_minutes')->values()
        ];
    }

    /**
     * Assign players to a specific court
     */
    public function assignCourt(Event $event, $courtNumber, $player1Id, $player2Id, $hostUserId)
    {
        try {
            DB::beginTransaction();

            // Validate court availability
            $existingMatch = MatchHistory::where('event_id', $event->id)
                ->where('court_number', $courtNumber)
                ->whereIn('match_status', ['ongoing', 'scheduled'])
                ->first();

            if ($existingMatch) {
                return [
                    'success' => false,
                    'message' => "Court {$courtNumber} sudah terisi"
                ];
            }

            // Validate players are available
            $playingUserIds = MatchHistory::where('event_id', $event->id)
                ->whereIn('match_status', ['ongoing', 'scheduled'])
                ->get()
                ->flatMap(function($match) {
                    return [$match->player1_id, $match->player2_id];
                })
                ->toArray();

            if (in_array($player1Id, $playingUserIds) || in_array($player2Id, $playingUserIds)) {
                return [
                    'success' => false,
                    'message' => 'Salah satu pemain sedang bermain di court lain'
                ];
            }

            // Create match assignment
            $match = MatchHistory::create([
                'event_id' => $event->id,
                'sport_id' => $event->sport_id,
                'player1_id' => $player1Id,
                'player2_id' => $player2Id,
                'court_number' => $courtNumber,
                'match_date' => now()->toDateString(),
                'match_status' => 'scheduled',
                'estimated_duration' => 60,
                'match_notes' => "Assigned to Court {$courtNumber} by host",
                'recorded_by_host_id' => $hostUserId
            ]);

            // Send notifications to assigned players
            $this->notificationService->notifyMatchAssigned($player1Id, $match);
            $this->notificationService->notifyMatchAssigned($player2Id, $match);

            DB::commit();

            return [
                'success' => true,
                'message' => "Pemain berhasil ditempatkan di Court {$courtNumber}",
                'match' => $match->load(['player1.profile', 'player2.profile'])
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Terjadi kesalahan saat menempatkan pemain',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Override/switch free players (host only)
     */
    public function overridePlayer(Event $event, $matchId, $oldPlayerId, $newPlayerId, $hostUserId, $reason = null)
    {
        try {
            DB::beginTransaction();

            $match = MatchHistory::findOrFail($matchId);

            // Check if event belongs to host
            if ($event->host_id !== $hostUserId) {
                return [
                    'success' => false,
                    'message' => 'Hanya host yang dapat mengganti pemain'
                ];
            }

            // Check if match is still in scheduled status
            if ($match->match_status !== 'scheduled') {
                return [
                    'success' => false,
                    'message' => 'Hanya pemain pada match yang belum dimulai yang dapat diganti'
                ];
            }

            // Check premium protection
            $oldPlayer = User::findOrFail($oldPlayerId);
            if ($oldPlayer->subscription_tier === 'premium') {
                return [
                    'success' => false,
                    'message' => 'Pemain premium tidak dapat diganti secara paksa'
                ];
            }

            // Check if new player is available
            $newPlayerInMatch = MatchHistory::where('event_id', $event->id)
                ->where(function($query) use ($newPlayerId) {
                    $query->where('player1_id', $newPlayerId)
                          ->orWhere('player2_id', $newPlayerId);
                })
                ->whereIn('match_status', ['ongoing', 'scheduled'])
                ->exists();

            if ($newPlayerInMatch) {
                return [
                    'success' => false,
                    'message' => 'Pemain pengganti sedang bermain di court lain'
                ];
            }

            // Perform the switch
            if ($match->player1_id === $oldPlayerId) {
                $match->player1_id = $newPlayerId;
            } elseif ($match->player2_id === $oldPlayerId) {
                $match->player2_id = $newPlayerId;
            } else {
                return [
                    'success' => false,
                    'message' => 'Pemain tidak ditemukan dalam match ini'
                ];
            }

            $match->match_notes = ($match->match_notes ?? '') . 
                "\nPlayer override by host: {$oldPlayer->name} → " . User::find($newPlayerId)->name .
                ($reason ? " (Reason: {$reason})" : '');
            $match->save();

            // Send notifications
            $this->notificationService->notifyPlayerOverridden($oldPlayerId, $event, $reason);
            $this->notificationService->notifyMatchAssigned($newPlayerId, $match);

            DB::commit();

            return [
                'success' => true,
                'message' => 'Pemain berhasil diganti',
                'match' => $match->load(['player1.profile', 'player2.profile'])
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengganti pemain',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Start a match (change status from scheduled to ongoing)
     */
    public function startMatch($matchId, $hostUserId)
    {
        try {
            $match = MatchHistory::findOrFail($matchId);
            
            if ($match->match_status !== 'scheduled') {
                return [
                    'success' => false,
                    'message' => 'Match sudah dimulai atau selesai'
                ];
            }

            $match->update([
                'match_status' => 'ongoing',
                'match_notes' => ($match->match_notes ?? '') . "\nMatch started at " . now()->toTimeString()
            ]);

            return [
                'success' => true,
                'message' => 'Match dimulai',
                'match' => $match->load(['player1.profile', 'player2.profile'])
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Terjadi kesalahan saat memulai match',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get next round suggestions
     */
    public function getNextRoundSuggestions(Event $event)
    {
        $queueInfo = $this->getQueueInformation($event);
        $availableCourts = $this->getAvailableCourts($event);

        if ($queueInfo['waiting_players']->count() < 2 || empty($availableCourts)) {
            return [
                'suggestions' => [],
                'message' => 'Tidak cukup pemain atau court untuk round berikutnya'
            ];
        }

        // Use MatchmakingService for fair pairing
        $matchmakingService = app(MatchmakingService::class);
        $suggestions = [];

        $waitingPlayers = $queueInfo['waiting_players']->take(count($availableCourts) * 2);
        
        // Simple pairing for now - can be enhanced with MatchmakingService
        for ($i = 0; $i < $waitingPlayers->count() - 1; $i += 2) {
            if (isset($availableCourts[$i / 2])) {
                $suggestions[] = [
                    'court_number' => $availableCourts[$i / 2],
                    'player1' => $waitingPlayers[$i],
                    'player2' => $waitingPlayers[$i + 1],
                    'compatibility_score' => 75 // Placeholder - can use real algorithm
                ];
            }
        }

        return [
            'suggestions' => $suggestions,
            'message' => count($suggestions) . ' match suggestions untuk round berikutnya'
        ];
    }

    /**
     * Get available courts
     */
    private function getAvailableCourts(Event $event)
    {
        $maxCourts = $event->max_courts ?? 4;
        $occupiedCourts = MatchHistory::where('event_id', $event->id)
            ->whereIn('match_status', ['ongoing', 'scheduled'])
            ->pluck('court_number')
            ->toArray();

        $availableCourts = [];
        for ($i = 1; $i <= $maxCourts; $i++) {
            if (!in_array($i, $occupiedCourts)) {
                $availableCourts[] = $i;
            }
        }

        return $availableCourts;
    }

    /**
     * Get skill level from MMR
     */
    private function getSkillLevelFromMMR($mmr)
    {
        if ($mmr < 800) return 'beginner';
        if ($mmr < 1200) return 'intermediate';  
        if ($mmr < 1600) return 'advanced';
        return 'expert';
    }
} 