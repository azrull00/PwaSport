<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\User;
use App\Models\UserSportRating;
use App\Models\MatchHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class MatchmakingController extends Controller
{
    protected $matchmakingService;

    public function __construct(MatchmakingService $matchmakingService)
    {
        $this->matchmakingService = $matchmakingService;
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
     * Create fair matches using advanced algorithm
     */
    public function createFairMatches(Request $request, $eventId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'auto_save' => 'nullable|boolean',
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

            // Only host can create matchmaking
            if ($event->host_id !== $user->id && !$user->hasRole('admin')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Hanya host yang dapat membuat matchmaking.'
                ], 403);
            }

            // Use the new MatchmakingService with fair algorithm
            $result = $this->matchmakingService->createFairMatches($event);

            if (!$result['success']) {
                return response()->json([
                    'status' => 'error',
                    'message' => $result['message'],
                    'error' => $result['error'] ?? null
                ], 422);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Fair matchmaking berhasil dibuat!',
                'data' => [
                    'event_id' => $event->id,
                    'event_title' => $event->title,
                    'algorithm_used' => 'Fair Matchmaking Algorithm v2.0',
                    'total_matches' => $result['total_matches'],
                    'matched_players' => $result['matched_players'],
                    'waiting_players' => $result['waiting_players'],
                    'matches' => $result['matches'],
                    'queue_info' => $this->matchmakingService->getQueueInfo($event)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat membuat fair matchmaking.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
} 