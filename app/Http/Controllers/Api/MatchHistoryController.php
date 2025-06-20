<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MatchHistory;
use App\Models\Event;
use App\Models\User;
use App\Models\UserSportRating;
use App\Services\EmailNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MatchHistoryController extends Controller
{
    protected $notificationService;

    public function __construct(EmailNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Get match history with filtering
     */
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            $query = MatchHistory::with(['event.sport', 'player1.profile', 'player2.profile']);

            // Filter by user role
            if ($user->hasRole('admin')) {
                // Admin can see all matches
            } elseif ($user->hasRole('host')) {
                // Host can see matches from their events
                $query->whereHas('event', function($q) use ($user) {
                    $q->where('host_user_id', $user->id);
                });
            } else {
                // Player can only see their own matches
                $query->where(function($q) use ($user) {
                    $q->where('player1_id', $user->id)
                      ->orWhere('player2_id', $user->id);
                });
            }

            // Filter by sport
            if ($request->has('sport_id')) {
                $query->whereHas('event', function($q) use ($request) {
                    $q->where('sport_id', $request->sport_id);
                });
            }

            // Filter by event
            if ($request->has('event_id')) {
                $query->where('event_id', $request->event_id);
            }

            // Filter by date range
            if ($request->has('date_from')) {
                $query->where('match_date', '>=', $request->date_from);
            }
            if ($request->has('date_to')) {
                $query->where('match_date', '<=', $request->date_to);
            }

            // Filter by result
            if ($request->has('result')) {
                $query->where('result', $request->result);
            }

            // Order by newest first
            $query->orderBy('match_date', 'desc')
                  ->orderBy('created_at', 'desc');

            // Pagination
            $perPage = $request->get('per_page', 15);
            $matches = $query->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'matches' => $matches
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil riwayat pertandingan.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create match result (host only)
     */
    public function store(Request $request)
    {
        try {
            $user = Auth::user();

            // Validate request
            $request->validate([
                'event_id' => 'required|exists:events,id',
                'player1_id' => 'required|exists:users,id',
                'player2_id' => 'required|exists:users,id|different:player1_id',
                'player1_score' => 'required|integer|min:0|max:99',
                'player2_score' => 'required|integer|min:0|max:99',
                'match_duration_minutes' => 'nullable|integer|min:1|max:300',
                'notes' => 'nullable|string|max:1000',
            ]);

            $event = Event::findOrFail($request->event_id);

            // Only host can create match results
            if ($event->host_user_id !== $user->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Hanya host yang dapat menginput hasil pertandingan.'
                ], 403);
            }

            // Check if event is completed
            if ($event->status !== 'completed') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Event harus dalam status completed untuk input hasil.'
                ], 422);
            }

            // Determine result and winner
            $result = $this->determineResult($request->player1_score, $request->player2_score);
            $winner_id = null;
            if ($result === 'player1_wins') {
                $winner_id = $request->player1_id;
            } elseif ($result === 'player2_wins') {
                $winner_id = $request->player2_id;
            }

            DB::beginTransaction();

            // Create match history
            $match = MatchHistory::create([
                'event_id' => $request->event_id,
                'player1_id' => $request->player1_id,
                'player2_id' => $request->player2_id,
                'player1_score' => $request->player1_score,
                'player2_score' => $request->player2_score,
                'result' => $result,
                'winner_id' => $winner_id,
                'match_date' => now()->toDateString(),
                'match_duration_minutes' => $request->match_duration_minutes,
                'notes' => $request->notes,
                'recorded_by' => $user->id,
            ]);

            // Update player ratings (MMR/ELO calculation)
            $this->updatePlayerRatings($match, $event->sport_id);

            // Send notifications to players
            $player1 = User::find($request->player1_id);
            $player2 = User::find($request->player2_id);
            
            $this->notificationService->sendMatchResult($player1, $match);
            $this->notificationService->sendMatchResult($player2, $match);

            DB::commit();

            $match->load(['event.sport', 'player1.profile', 'player2.profile', 'winner.profile']);

            return response()->json([
                'status' => 'success',
                'message' => 'Hasil pertandingan berhasil dicatat!',
                'data' => [
                    'match' => $match
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat menyimpan hasil pertandingan.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific match details
     */
    public function show(MatchHistory $match)
    {
        try {
            $user = Auth::user();

            // Check access permissions
            if (!$this->canAccessMatch($user, $match)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Anda tidak memiliki akses ke pertandingan ini.'
                ], 403);
            }

            $match->load([
                'event.sport', 
                'player1.profile', 
                'player2.profile', 
                'winner.profile',
                'recordedBy.profile'
            ]);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'match' => $match
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil detail pertandingan.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update match result (host only, within 24 hours)
     */
    public function update(Request $request, MatchHistory $match)
    {
        try {
            $user = Auth::user();

            // Only host can update
            if ($match->event->host_user_id !== $user->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Hanya host yang dapat mengubah hasil pertandingan.'
                ], 403);
            }

            // Check if within 24 hours
            if ($match->created_at->diffInHours(now()) > 24) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Hasil pertandingan hanya dapat diubah dalam 24 jam.'
                ], 422);
            }

            // Validate request
            $request->validate([
                'player1_score' => 'sometimes|integer|min:0|max:99',
                'player2_score' => 'sometimes|integer|min:0|max:99',
                'match_duration_minutes' => 'sometimes|integer|min:1|max:300',
                'notes' => 'sometimes|string|max:1000',
            ]);

            DB::beginTransaction();

            $oldResult = $match->result;
            $oldWinner = $match->winner_id;

            // Update match data
            if ($request->has('player1_score') || $request->has('player2_score')) {
                $player1Score = $request->get('player1_score', $match->player1_score);
                $player2Score = $request->get('player2_score', $match->player2_score);
                
                $result = $this->determineResult($player1Score, $player2Score);
                $winner_id = null;
                if ($result === 'player1_wins') {
                    $winner_id = $match->player1_id;
                } elseif ($result === 'player2_wins') {
                    $winner_id = $match->player2_id;
                }

                $match->update([
                    'player1_score' => $player1Score,
                    'player2_score' => $player2Score,
                    'result' => $result,
                    'winner_id' => $winner_id,
                ]);
            }

            // Update other fields
            $match->update($request->only(['match_duration_minutes', 'notes']));

            // Recalculate ratings if result changed
            if ($oldResult !== $match->result || $oldWinner !== $match->winner_id) {
                $this->updatePlayerRatings($match, $match->event->sport_id, true);
            }

            DB::commit();

            $match->load(['event.sport', 'player1.profile', 'player2.profile', 'winner.profile']);

            return response()->json([
                'status' => 'success',
                'message' => 'Hasil pertandingan berhasil diperbarui!',
                'data' => [
                    'match' => $match
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat memperbarui hasil pertandingan.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete match result (host only, within 24 hours)
     */
    public function destroy(MatchHistory $match)
    {
        try {
            $user = Auth::user();

            // Only host can delete
            if ($match->event->host_user_id !== $user->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Hanya host yang dapat menghapus hasil pertandingan.'
                ], 403);
            }

            // Check if within 24 hours
            if ($match->created_at->diffInHours(now()) > 24) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Hasil pertandingan hanya dapat dihapus dalam 24 jam.'
                ], 422);
            }

            DB::beginTransaction();

            // Revert rating changes before deleting
            $this->revertPlayerRatings($match, $match->event->sport_id);

            $match->delete();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Hasil pertandingan berhasil dihapus.'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat menghapus hasil pertandingan.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get match statistics for user
     */
    public function getStats(Request $request, User $user = null)
    {
        try {
            $targetUser = $user ?? Auth::user();
            $currentUser = Auth::user();

            // Privacy check
            if ($targetUser->id !== $currentUser->id && !$targetUser->profile?->is_match_history_public) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Statistik pertandingan tidak dapat diakses.'
                ], 403);
            }

            $query = MatchHistory::where(function($q) use ($targetUser) {
                $q->where('player1_id', $targetUser->id)
                  ->orWhere('player2_id', $targetUser->id);
            });

            // Filter by sport if provided
            if ($request->has('sport_id')) {
                $query->whereHas('event', function($q) use ($request) {
                    $q->where('sport_id', $request->sport_id);
                });
            }

            $stats = [
                'total_matches' => $query->count(),
                'wins' => $query->where('winner_id', $targetUser->id)->count(),
                'losses' => $query->where('winner_id', '!=', $targetUser->id)
                                 ->whereNotNull('winner_id')->count(),
                'draws' => $query->where('result', 'draw')->count(),
                'recent_matches' => $query->with(['event.sport', 'player1.profile', 'player2.profile'])
                                         ->orderBy('match_date', 'desc')
                                         ->take(5)
                                         ->get(),
            ];

            $stats['win_rate'] = $stats['total_matches'] > 0 
                ? round(($stats['wins'] / $stats['total_matches']) * 100, 2) 
                : 0;

            return response()->json([
                'status' => 'success',
                'data' => [
                    'stats' => $stats
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil statistik.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Determine match result based on scores
     */
    private function determineResult($player1Score, $player2Score)
    {
        if ($player1Score > $player2Score) {
            return 'player1_wins';
        } elseif ($player2Score > $player1Score) {
            return 'player2_wins';
        } else {
            return 'draw';
        }
    }

    /**
     * Update player ratings using ELO system
     */
    private function updatePlayerRatings(MatchHistory $match, $sportId, $isUpdate = false)
    {
        $player1Rating = UserSportRating::firstOrCreate(
            ['user_id' => $match->player1_id, 'sport_id' => $sportId],
            ['skill_rating' => 1500, 'matches_played' => 0]
        );

        $player2Rating = UserSportRating::firstOrCreate(
            ['user_id' => $match->player2_id, 'sport_id' => $sportId],
            ['skill_rating' => 1500, 'matches_played' => 0]
        );

        // ELO calculation
        $k = 32; // K-factor
        $expectedPlayer1 = 1 / (1 + pow(10, ($player2Rating->skill_rating - $player1Rating->skill_rating) / 400));
        $expectedPlayer2 = 1 - $expectedPlayer1;

        $actualPlayer1 = 0.5; // Default for draw
        $actualPlayer2 = 0.5;

        if ($match->result === 'player1_wins') {
            $actualPlayer1 = 1;
            $actualPlayer2 = 0;
        } elseif ($match->result === 'player2_wins') {
            $actualPlayer1 = 0;
            $actualPlayer2 = 1;
        }

        $newRatingPlayer1 = $player1Rating->skill_rating + $k * ($actualPlayer1 - $expectedPlayer1);
        $newRatingPlayer2 = $player2Rating->skill_rating + $k * ($actualPlayer2 - $expectedPlayer2);

        $player1Rating->update([
            'skill_rating' => round($newRatingPlayer1),
            'matches_played' => $isUpdate ? $player1Rating->matches_played : $player1Rating->matches_played + 1,
        ]);

        $player2Rating->update([
            'skill_rating' => round($newRatingPlayer2),
            'matches_played' => $isUpdate ? $player2Rating->matches_played : $player2Rating->matches_played + 1,
        ]);
    }

    /**
     * Revert player ratings when match is deleted
     */
    private function revertPlayerRatings(MatchHistory $match, $sportId)
    {
        // This is a simplified revert - in production, you might want to store the previous ratings
        $player1Rating = UserSportRating::where(['user_id' => $match->player1_id, 'sport_id' => $sportId])->first();
        $player2Rating = UserSportRating::where(['user_id' => $match->player2_id, 'sport_id' => $sportId])->first();

        if ($player1Rating && $player1Rating->matches_played > 0) {
            $player1Rating->decrement('matches_played');
        }
        if ($player2Rating && $player2Rating->matches_played > 0) {
            $player2Rating->decrement('matches_played');
        }
    }

    /**
     * Check if user can access match
     */
    private function canAccessMatch($user, $match)
    {
        // Admin can access all
        if ($user->hasRole('admin')) {
            return true;
        }

        // Host can access matches from their events
        if ($user->hasRole('host') && $match->event->host_user_id === $user->id) {
            return true;
        }

        // Players can access their own matches
        if ($match->player1_id === $user->id || $match->player2_id === $user->id) {
            return true;
        }

        return false;
    }
}
