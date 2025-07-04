<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\User;
use App\Models\UserSportRating;
use App\Models\MatchHistory;
use App\Models\GuestPlayer;
use App\Services\MatchmakingService;
use App\Services\CourtManagementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class MatchmakingController extends Controller
{
    protected $matchmakingService;
    protected $courtManagementService;

    public function __construct(
        MatchmakingService $matchmakingService,
        CourtManagementService $courtManagementService
    ) {
        $this->matchmakingService = $matchmakingService;
        $this->courtManagementService = $courtManagementService;
    }
    /**
     * Get fair matchmaking pairs for an event
     */
    public function generateEventMatchmaking(Request $request, $eventId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'max_courts' => 'nullable|integer|min:1|max:20',
                'match_type' => 'nullable|in:singles,doubles',
                'skill_tolerance' => 'nullable|integer|min:0|max:500', // MMR tolerance
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $event = Event::with(['sport', 'confirmedParticipants.user.profile'])->findOrFail($eventId);
            $user = Auth::user();

            // Only host can generate matchmaking
            if ($event->host_id !== $user->id && !$user->hasRole('admin')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Hanya host yang dapat mengatur matchmaking.'
                ], 403);
            }

            $maxCourts = $request->get('max_courts', $event->max_courts ?? 4);
            $matchType = $request->get('match_type', 'singles');
            $skillTolerance = $request->get('skill_tolerance', 200);

            // Get confirmed participants with their skill ratings
            $participants = $event->confirmedParticipants()
                ->with(['user.sportRatings' => function($q) use ($event) {
                    $q->where('sport_id', $event->sport_id);
                }])
                ->get()
                ->map(function($participant) use ($event) {
                    $skillRating = $participant->user->sportRatings
                        ->where('sport_id', $event->sport_id)
                        ->first();
                    
                    return [
                        'user_id' => $participant->user_id,
                        'user' => $participant->user,
                        'skill_rating' => $skillRating ? $skillRating->skill_rating : 1000,
                        'matches_played' => $skillRating ? $skillRating->matches_played : 0,
                        'credit_score' => $participant->user->credit_score,
                    ];
                });

            if ($participants->count() < 2) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Minimal 2 peserta diperlukan untuk matchmaking.'
                ], 422);
            }

            // Generate fair matches based on type
            if ($matchType === 'singles') {
                $matches = $this->generateSinglesMatches($participants, $maxCourts, $skillTolerance);
            } else {
                $matches = $this->generateDoublesMatches($participants, $maxCourts, $skillTolerance);
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'event_id' => $event->id,
                    'event_title' => $event->title,
                    'match_type' => $matchType,
                    'total_participants' => $participants->count(),
                    'total_matches' => count($matches),
                    'courts_needed' => min($maxCourts, count($matches)),
                    'matches' => $matches,
                    'waiting_list' => $this->getWaitingParticipants($participants, $matches),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat membuat matchmaking.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Save matchmaking results and assign courts
     */
    public function saveMatchmaking(Request $request, $eventId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'matches' => 'required|array',
                'matches.*.court_number' => 'required|integer|min:1',
                'matches.*.player1_id' => 'required|exists:users,id',
                'matches.*.player2_id' => 'required|exists:users,id|different:matches.*.player1_id',
                'matches.*.estimated_duration' => 'nullable|integer|min:15|max:180',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $event = Event::findOrFail($eventId);
            $user = Auth::user();

            // Only host can save matchmaking
            if ($event->host_id !== $user->id && !$user->hasRole('admin')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Hanya host yang dapat menyimpan matchmaking.'
                ], 403);
            }

            DB::beginTransaction();

            // Update event status to ongoing
            $event->update(['status' => 'ongoing']);

            $savedMatches = [];
            foreach ($request->matches as $matchData) {
                // Verify participants are confirmed for this event
                $player1Confirmed = EventParticipant::where([
                    'event_id' => $event->id,
                    'user_id' => $matchData['player1_id'],
                    'status' => 'confirmed'
                ])->exists();

                $player2Confirmed = EventParticipant::where([
                    'event_id' => $event->id,
                    'user_id' => $matchData['player2_id'],
                    'status' => 'confirmed'
                ])->exists();

                if (!$player1Confirmed || !$player2Confirmed) {
                    throw new \Exception('Semua peserta harus terkonfirmasi untuk event ini.');
                }

                // Create match history entry (with temporary scores)
                $match = MatchHistory::create([
                    'event_id' => $event->id,
                    'sport_id' => $event->sport_id,
                    'player1_id' => $matchData['player1_id'],
                    'player2_id' => $matchData['player2_id'],
                    'result' => 'draw', // Temporary value until match is completed
                    'match_score' => json_encode([
                        'player1_score' => 0,
                        'player2_score' => 0
                    ]),
                    'player1_mmr_before' => 1000,
                    'player1_mmr_after' => 1000,
                    'player2_mmr_before' => 1000,
                    'player2_mmr_after' => 1000,
                    'recorded_by_host_id' => $user->id,
                    'match_date' => now(),
                    'court_number' => $matchData['court_number'],
                    'estimated_duration' => $matchData['estimated_duration'] ?? 60,
                    'match_status' => 'ongoing'
                ]);

                $savedMatches[] = $match->load(['player1.profile', 'player2.profile']);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Matchmaking berhasil disimpan dan pertandingan dimulai!',
                'data' => [
                    'event_id' => $event->id,
                    'total_matches' => count($savedMatches),
                    'matches' => $savedMatches
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get current matchmaking status for an event
     */
    public function getMatchmakingStatus($eventId)
    {
        try {
            $event = Event::with(['sport', 'confirmedParticipants.user.profile'])->findOrFail($eventId);
            $user = Auth::user();

            // Check if user has access to view matchmaking
            $isHost = $event->host_id === $user->id;
            $isParticipant = $event->participants()->where('user_id', $user->id)->exists();
            $isAdmin = $user->hasRole('admin');

            if (!$isHost && !$isParticipant && !$isAdmin) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Anda tidak memiliki akses untuk melihat matchmaking ini.'
                ], 403);
            }

            // Get current matches for this event
            $currentMatches = MatchHistory::where('event_id', $event->id)
                ->with(['player1.profile', 'player2.profile'])
                ->orderBy('court_number')
                ->get();

            // Get participants not yet matched
            $matchedUserIds = $currentMatches->flatMap(function($match) {
                return [$match->player1_id, $match->player2_id];
            })->unique();

            $unmatchedParticipants = $event->confirmedParticipants()
                ->whereNotIn('user_id', $matchedUserIds)
                ->with('user.profile')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'event' => [
                        'id' => $event->id,
                        'title' => $event->title,
                        'status' => $event->status,
                        'max_courts' => $event->max_courts ?? 4,
                    ],
                    'matches' => $currentMatches,
                    'unmatched_participants' => $unmatchedParticipants,
                    'statistics' => [
                        'total_participants' => $event->confirmedParticipants()->count(),
                        'matched_participants' => $matchedUserIds->count(),
                        'active_matches' => $currentMatches->where('result', 'ongoing')->count(),
                        'completed_matches' => $currentMatches->whereNotIn('result', ['ongoing'])->count(),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil status matchmaking.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate fair singles matches
     */
    private function generateSinglesMatches($participants, $maxCourts, $skillTolerance)
    {
        // Sort by skill rating for balanced matching
        $sortedParticipants = $participants->sortBy('skill_rating');
        $matches = [];
        $used = [];

        for ($i = 0; $i < $sortedParticipants->count() - 1; $i += 2) {
            if (in_array($i, $used) || in_array($i + 1, $used)) continue;
            
            $player1 = $sortedParticipants->values()[$i];
            $player2 = $sortedParticipants->values()[$i + 1];

            // Check skill difference is within tolerance
            $skillDiff = abs($player1['skill_rating'] - $player2['skill_rating']);
            if ($skillDiff <= $skillTolerance) {
                $matches[] = [
                    'court_number' => count($matches) + 1,
                    'player1' => $player1,
                    'player2' => $player2,
                    'skill_difference' => $skillDiff,
                    'match_quality' => $this->calculateMatchQuality($player1, $player2),
                    'estimated_duration' => 60
                ];

                $used[] = $i;
                $used[] = $i + 1;

                if (count($matches) >= $maxCourts) break;
            }
        }

        return $matches;
    }

    /**
     * Generate fair doubles matches (basic implementation)
     */
    private function generateDoublesMatches($participants, $maxCourts, $skillTolerance)
    {
        if ($participants->count() < 4) {
            return [];
        }

        $sortedParticipants = $participants->sortBy('skill_rating');
        $matches = [];
        $used = [];

        for ($i = 0; $i < $sortedParticipants->count() - 3; $i += 4) {
            if (in_array($i, $used) || in_array($i + 1, $used) || 
                in_array($i + 2, $used) || in_array($i + 3, $used)) continue;

            $team1 = [
                $sortedParticipants->values()[$i],
                $sortedParticipants->values()[$i + 1]
            ];
            
            $team2 = [
                $sortedParticipants->values()[$i + 2],
                $sortedParticipants->values()[$i + 3]
            ];

            $team1AvgSkill = ($team1[0]['skill_rating'] + $team1[1]['skill_rating']) / 2;
            $team2AvgSkill = ($team2[0]['skill_rating'] + $team2[1]['skill_rating']) / 2;
            $skillDiff = abs($team1AvgSkill - $team2AvgSkill);

            if ($skillDiff <= $skillTolerance) {
                $matches[] = [
                    'court_number' => count($matches) + 1,
                    'team1' => $team1,
                    'team2' => $team2,
                    'skill_difference' => $skillDiff,
                    'match_quality' => $this->calculateDoublesMatchQuality($team1, $team2),
                    'estimated_duration' => 75
                ];

                $used = array_merge($used, [$i, $i + 1, $i + 2, $i + 3]);

                if (count($matches) >= $maxCourts) break;
            }
        }

        return $matches;
    }

    /**
     * Calculate match quality score
     */
    private function calculateMatchQuality($player1, $player2)
    {
        $skillDiff = abs($player1['skill_rating'] - $player2['skill_rating']);
        $experienceDiff = abs($player1['matches_played'] - $player2['matches_played']);
        
        // Lower differences = higher quality
        $skillScore = max(0, 100 - ($skillDiff / 10));
        $experienceScore = max(0, 100 - ($experienceDiff / 5));
        
        return round(($skillScore + $experienceScore) / 2, 1);
    }

    /**
     * Calculate doubles match quality
     */
    private function calculateDoublesMatchQuality($team1, $team2)
    {
        $team1AvgSkill = ($team1[0]['skill_rating'] + $team1[1]['skill_rating']) / 2;
        $team2AvgSkill = ($team2[0]['skill_rating'] + $team2[1]['skill_rating']) / 2;
        
        $skillDiff = abs($team1AvgSkill - $team2AvgSkill);
        
        return max(0, round(100 - ($skillDiff / 10), 1));
    }

    /**
     * Get participants who couldn't be matched
     */
    private function getWaitingParticipants($allParticipants, $matches)
    {
        $matchedIds = collect($matches)->flatMap(function($match) {
            if (isset($match['player1']) && isset($match['player2'])) {
                return [$match['player1']['user_id'], $match['player2']['user_id']];
            } elseif (isset($match['team1']) && isset($match['team2'])) {
                return [
                    $match['team1'][0]['user_id'], $match['team1'][1]['user_id'],
                    $match['team2'][0]['user_id'], $match['team2'][1]['user_id']
                ];
            }
            return [];
        });

        return $allParticipants->filter(function($participant) use ($matchedIds) {
            return !$matchedIds->contains($participant['user_id']);
        })->values();
    }

    /**
     * Get event-specific matchmaking status for participants
     */
    public function getEventMatchmakingStatus(Request $request, $eventId)
    {
        try {
            $event = Event::with(['sport', 'participants.user'])->findOrFail($eventId);
            $user = Auth::user();

            // Check if user is participant or host
            $isParticipant = $event->participants()
                ->where('user_id', $user->id)
                ->whereIn('status', ['confirmed', 'checked_in'])
                ->exists();
            
            $isHost = $event->host_id === $user->id;

            if (!$isParticipant && !$isHost && !$user->hasRole('admin')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Anda tidak memiliki akses untuk melihat status matchmaking event ini.'
                ], 403);
            }

            // Get matches for this event
            $ongoingMatches = MatchHistory::with(['event.sport', 'player1.profile', 'player2.profile'])
                ->where('event_id', $eventId)
                ->where('match_status', 'ongoing')
                ->orderBy('created_at', 'desc')
                ->get();

            $scheduledMatches = MatchHistory::with(['event.sport', 'player1.profile', 'player2.profile'])
                ->where('event_id', $eventId)
                ->where('match_status', 'scheduled')
                ->orderBy('court_number', 'asc')
                ->get();

            $completedMatches = MatchHistory::with(['event.sport', 'player1.profile', 'player2.profile'])
                ->where('event_id', $eventId)
                ->where('match_status', 'completed')
                ->orderBy('created_at', 'desc')
                ->take(10)
                ->get();

            // Get event participants status
            $participants = $event->participants()
                ->with(['user.profile'])
                ->whereIn('status', ['confirmed', 'checked_in'])
                ->get();

            // Check who's playing and who's waiting
            $playingUserIds = collect()
                ->merge($ongoingMatches->pluck('player1_id'))
                ->merge($ongoingMatches->pluck('player2_id'))
                ->merge($scheduledMatches->pluck('player1_id'))
                ->merge($scheduledMatches->pluck('player2_id'))
                ->unique()
                ->values()
                ->toArray();

            $waitingParticipants = $participants->filter(function($participant) use ($playingUserIds) {
                return !in_array($participant->user_id, $playingUserIds);
            });

            return response()->json([
                'status' => 'success',
                'data' => [
                    'event' => [
                        'id' => $event->id,
                        'title' => $event->title,
                        'sport' => $event->sport,
                        'date' => $event->event_date,
                        'location' => $event->location_name
                    ],
                    'ongoing_matches' => $ongoingMatches,
                    'scheduled_matches' => $scheduledMatches,
                    'completed_matches' => $completedMatches,
                    'waiting_queue' => $waitingParticipants->map(function($participant) {
                        return [
                            'user_id' => $participant->user_id,
                            'name' => $participant->user->name,
                            'profile_picture' => $participant->user->profile->profile_picture ?? null,
                            'waiting_since' => $participant->confirmed_at ?? $participant->created_at
                        ];
                    }),
                    'summary' => [
                        'ongoing_count' => $ongoingMatches->count(),
                        'scheduled_count' => $scheduledMatches->count(),
                        'completed_count' => $completedMatches->count(),
                        'waiting_count' => $waitingParticipants->count(),
                        'total_participants' => $participants->count()
                    ],
                    'permissions' => [
                        'is_host' => $isHost,
                        'is_participant' => $isParticipant,
                        'can_manage' => $isHost || $user->hasRole('admin')
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil status matchmaking.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get court status and queue information for an event
     */
    public function getCourtStatus(Event $event)
    {
        $this->authorize('manage', $event);

        $courtStatus = $this->courtManagementService->getCourtStatus($event);

        return response()->json([
            'status' => 'success',
            'data' => [
                'event_id' => $event->id,
                'event_title' => $event->title,
                'courts' => $courtStatus['courts'] ?? [],
                'matches' => $courtStatus['matches'] ?? []
            ]
        ]);
    }

    /**
     * Start a match
     */
    public function startMatch(Request $request, Event $event, $matchId)
    {
        $this->authorize('manage', $event);

        $result = $this->courtManagementService->startMatch($matchId, Auth::id());

        return response()->json([
            'status' => $result['success'] ? 'success' : 'error',
            'message' => $result['message'],
            'data' => $result['success'] ? ['match' => $result['match']] : null
        ], $result['success'] ? 200 : 422);
    }

    /**
     * Get next round suggestions
     */
    public function getNextRoundSuggestions(Request $request, $eventId)
    {
        try {
            $event = Event::findOrFail($eventId);
            $user = Auth::user();

            // Only host can get suggestions
            if ($event->host_id !== $user->id && !$user->hasRole('admin')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Hanya host yang dapat melihat suggestions.'
                ], 403);
            }

            $suggestions = $this->courtManagementService->getNextRoundSuggestions($event);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'event_id' => $event->id,
                    'suggestions' => $suggestions['suggestions'],
                    'message' => $suggestions['message']
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil suggestions.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getEventParticipants($eventId)
    {
        $user = Auth::user();
        $event = Event::findOrFail($eventId);

        // Check if user is host
        if ($event->host_id !== $user->id && !$user->hasRole('admin')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 403);
        }

        $participants = EventParticipant::with('user')
            ->where('event_id', $eventId)
            ->where('status', 'checked_in')
            ->whereDoesntHave('activeMatch')
            ->get()
            ->map(function ($participant) {
                $user = $participant->user;
                $sportRating = $user->sportRatings()
                    ->where('sport_id', $participant->event->sport_id)
                    ->first();

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'profile_picture' => $user->profile_picture,
                    'mmr' => $sportRating ? $sportRating->mmr : 1000,
                    'level' => $sportRating ? $sportRating->level : 'beginner',
                    'win_rate' => $sportRating ? $sportRating->win_rate : 0,
                    'is_premium' => $user->is_premium
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => [
                'participants' => $participants
            ]
        ]);
    }

    public function getEventMatches($eventId)
    {
        $user = Auth::user();
        $event = Event::findOrFail($eventId);

        // Check if user is host
        if ($event->host_id !== $user->id && !$user->hasRole('admin')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 403);
        }

        $matches = MatchHistory::with(['player1', 'player2'])
            ->where('event_id', $eventId)
            ->whereIn('match_status', ['scheduled', 'ongoing'])
            ->get()
            ->map(function ($match) {
                return [
                    'id' => $match->id,
                    'player1' => [
                        'id' => $match->player1->id,
                        'name' => $match->player1->name,
                        'profile_picture' => $match->player1->profile_picture
                    ],
                    'player2' => [
                        'id' => $match->player2->id,
                        'name' => $match->player2->name,
                        'profile_picture' => $match->player2->profile_picture
                    ],
                    'court_number' => $match->court_number,
                    'status' => $match->match_status,
                    'locked' => $match->is_locked,
                    'created_at' => $match->created_at
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => [
                'matches' => $matches
            ]
        ]);
    }

    public function overrideMatch(Request $request, $eventId)
    {
        $user = Auth::user();
        $event = Event::findOrFail($eventId);

        // Check if user is host
        if ($event->host_id !== $user->id && !$user->hasRole('admin')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'player1_id' => 'required|exists:users,id',
            'player2_id' => 'required|exists:users,id|different:player1_id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid player selection',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Check if players are available
            $player1 = EventParticipant::where('event_id', $eventId)
                ->where('user_id', $request->player1_id)
                ->where('status', 'checked_in')
                ->firstOrFail();

            $player2 = EventParticipant::where('event_id', $eventId)
                ->where('user_id', $request->player2_id)
                ->where('status', 'checked_in')
                ->firstOrFail();

            // Create match
            $match = new MatchHistory([
                'event_id' => $eventId,
                'player1_id' => $request->player1_id,
                'player2_id' => $request->player2_id,
                'match_status' => 'scheduled',
                'is_override' => true,
                'override_by' => $user->id
            ]);

            $match->save();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Match created successfully',
                'data' => [
                    'match' => $match
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create match'
            ], 500);
        }
    }

    public function toggleMatchLock(Request $request, $eventId, $matchId)
    {
        $user = Auth::user();
        $event = Event::findOrFail($eventId);

        // Check if user is host
        if ($event->host_id !== $user->id && !$user->hasRole('admin')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'locked' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid request',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $match = MatchHistory::where('event_id', $eventId)
                ->findOrFail($matchId);

            $match->is_locked = $request->locked;
            $match->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Match lock status updated',
                'data' => [
                    'match' => $match
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update match lock status'
            ], 500);
        }
    }

    public function getStatus(Event $event)
    {
        $this->authorize('manage', $event);

        $matches = MatchHistory::with(['player1', 'player2', 'player1Guest', 'player2Guest'])
            ->where('event_id', $event->id)
            ->whereIn('match_status', ['pending', 'scheduled', 'ongoing'])
            ->get()
            ->map(function ($match) {
                return [
                    'id' => $match->id,
                    'player1' => $match->player1 ? [
                        'id' => $match->player1->id,
                        'name' => $match->player1->name,
                        'mmr' => $match->player1->userSportRatings()
                            ->where('sport_id', $match->event->sport_id)
                            ->first()?->mmr ?? 1000,
                        'win_rate' => $this->matchmakingService->calculateWinRate($match->player1, $match->event->sport_id),
                        'is_guest' => false
                    ] : [
                        'id' => 'guest_' . $match->player1Guest->id,
                        'name' => $match->player1Guest->name . ' (Guest)',
                        'mmr' => $match->player1Guest->estimated_mmr,
                        'win_rate' => null,
                        'is_guest' => true
                    ],
                    'player2' => $match->player2 ? [
                        'id' => $match->player2->id,
                        'name' => $match->player2->name,
                        'mmr' => $match->player2->userSportRatings()
                            ->where('sport_id', $match->event->sport_id)
                            ->first()?->mmr ?? 1000,
                        'win_rate' => $this->matchmakingService->calculateWinRate($match->player2, $match->event->sport_id),
                        'is_guest' => false
                    ] : [
                        'id' => 'guest_' . $match->player2Guest->id,
                        'name' => $match->player2Guest->name . ' (Guest)',
                        'mmr' => $match->player2Guest->estimated_mmr,
                        'win_rate' => null,
                        'is_guest' => true
                    ],
                    'court_number' => $match->court_number,
                    'status' => $match->match_status,
                    'scheduled_time' => $match->scheduled_time,
                ];
            });

        $waitingPlayers = $this->matchmakingService->getEligibleParticipants($event);

        return response()->json([
            'matches' => $matches,
            'waiting_players' => $waitingPlayers
        ]);
    }

    public function createFairMatches(Event $event)
    {
        $this->authorize('manage', $event);

        $result = $this->matchmakingService->createFairMatches($event);

        return response()->json($result);
    }

    public function overridePlayer(Request $request, Event $event)
    {
        $this->authorize('manage', $event);

        $validated = $request->validate([
            'match_id' => 'required|exists:match_history,id',
            'player_to_replace' => 'required|string', // Can be user ID or guest_ID
            'replacement_player' => 'required|string', // Can be user ID or guest_ID
        ]);

        try {
            DB::beginTransaction();

            $match = MatchHistory::findOrFail($validated['match_id']);

            // Determine if we're replacing player1 or player2
            $isPlayer1 = false;
            if ($match->player1_id && $match->player1_id == $validated['player_to_replace']) {
                $isPlayer1 = true;
            } elseif ($match->player1_guest_id && 'guest_' . $match->player1_guest_id == $validated['player_to_replace']) {
                $isPlayer1 = true;
            } elseif ($match->player2_id && $match->player2_id == $validated['player_to_replace']) {
                $isPlayer1 = false;
            } elseif ($match->player2_guest_id && 'guest_' . $match->player2_guest_id == $validated['player_to_replace']) {
                $isPlayer1 = false;
            } else {
                throw new \Exception('Player to replace not found in match');
            }

            // Handle replacement player (can be regular user or guest)
            if (str_starts_with($validated['replacement_player'], 'guest_')) {
                $guestId = substr($validated['replacement_player'], 6);
                $guest = GuestPlayer::findOrFail($guestId);
                
                if ($isPlayer1) {
                    $match->player1_id = null;
                    $match->player1_guest_id = $guest->id;
                } else {
                    $match->player2_id = null;
                    $match->player2_guest_id = $guest->id;
                }
            } else {
                $user = User::findOrFail($validated['replacement_player']);
                
                if ($isPlayer1) {
                    $match->player1_id = $user->id;
                    $match->player1_guest_id = null;
                } else {
                    $match->player2_id = $user->id;
                    $match->player2_guest_id = null;
                }
            }

            $match->save();

            DB::commit();

            return response()->json([
                'message' => 'Player override successful',
                'match' => $match
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to override player: ' . $e->getMessage()
            ], 400);
        }
    }

    public function assignCourt(Request $request, Event $event)
    {
        $this->authorize('manage', $event);

        $validated = $request->validate([
            'match_id' => 'required|exists:match_history,id',
            'court_number' => 'required|integer|min:1|max:20',
        ]);

        $match = MatchHistory::findOrFail($validated['match_id']);
        
        // Check if court is available
        $courtInUse = MatchHistory::where('event_id', $event->id)
            ->where('id', '!=', $match->id)
            ->where('court_number', $validated['court_number'])
            ->whereIn('match_status', ['scheduled', 'ongoing'])
            ->exists();

        if ($courtInUse) {
            return response()->json([
                'message' => 'Court is currently in use'
            ], 400);
        }

        $match->update([
            'court_number' => $validated['court_number'],
            'match_status' => 'scheduled',
            'scheduled_time' => now()
        ]);

        return response()->json([
            'message' => 'Court assigned successfully',
            'match' => $match
        ]);
    }

    public function endMatch(Request $request, Event $event, $matchId)
    {
        $this->authorize('manage', $event);

        try {
            $match = MatchHistory::where('event_id', $event->id)->findOrFail($matchId);
            
            $match->update([
                'match_status' => 'completed',
                'end_time' => now(),
                'updated_by' => Auth::id()
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Match ended successfully',
                'data' => ['match' => $match]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to end match'
            ], 500);
        }
    }
} 