<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlayerRating;
use App\Models\MatchHistory;
use App\Models\Event;
use App\Models\User;
use App\Services\EmailNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PlayerRatingController extends Controller
{
    protected $notificationService;

    public function __construct(EmailNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Get player ratings with filtering
     */
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            $query = PlayerRating::with(['ratingUser.profile', 'ratedUser.profile', 'event.sport']);

            // Filter by context (given or received)
            if ($request->get('context') === 'given') {
                $query->where('rating_user_id', $user->id);
            } elseif ($request->get('context') === 'received') {
                $query->where('rated_user_id', $user->id);
            } else {
                // Default: show ratings related to current user
                $query->where(function($q) use ($user) {
                    $q->where('rating_user_id', $user->id)
                      ->orWhere('rated_user_id', $user->id);
                });
            }

            // Filter by rated user
            if ($request->has('rated_user_id')) {
                $query->where('rated_user_id', $request->rated_user_id);
            }

            // Filter by event
            if ($request->has('event_id')) {
                $query->where('event_id', $request->event_id);
            }

            // Filter by sport
            if ($request->has('sport_id')) {
                $query->whereHas('event', function($q) use ($request) {
                    $q->where('sport_id', $request->sport_id);
                });
            }

            // Filter by date range
            if ($request->has('date_from')) {
                $query->where('created_at', '>=', $request->date_from);
            }
            if ($request->has('date_to')) {
                $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
            }

            // Order by newest first
            $query->orderBy('created_at', 'desc');

            // Pagination
            $perPage = $request->get('per_page', 15);
            $ratings = $query->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'ratings' => $ratings
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil rating.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rate a player after an event
     */
    public function store(Request $request)
    {
        try {
            $user = Auth::user();

            // Validate request
            $request->validate([
                'rated_user_id' => 'required|exists:users,id',
                'event_id' => 'required|exists:events,id',
                'skill_rating' => 'required|numeric|min:1|max:5',
                'sportsmanship_rating' => 'required|numeric|min:1|max:5',
                'punctuality_rating' => 'required|numeric|min:1|max:5',
                'comment' => 'nullable|string|max:500',
            ]);

            // Cannot rate yourself
            if ($request->rated_user_id == $user->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Anda tidak dapat memberikan rating untuk diri sendiri.'
                ], 422);
            }

            $event = Event::findOrFail($request->event_id);
            $ratedUser = User::findOrFail($request->rated_user_id);

            // Check if both users participated in the event
            $userParticipated = $event->participants()
                ->where('user_id', $user->id)
                ->where('status', 'checked_in')
                ->exists();

            $ratedUserParticipated = $event->participants()
                ->where('user_id', $request->rated_user_id)
                ->where('status', 'checked_in')
                ->exists();

            if (!$userParticipated || !$ratedUserParticipated) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Kedua pemain harus telah berpartisipasi dalam event untuk memberikan rating.'
                ], 422);
            }

            // Check if event is completed
            if ($event->status !== 'completed') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Rating hanya dapat diberikan setelah event selesai.'
                ], 422);
            }

            // Check if rating already exists
            $existingRating = PlayerRating::where([
                'rating_user_id' => $user->id,
                'rated_user_id' => $request->rated_user_id,
                'event_id' => $request->event_id,
            ])->first();

            if ($existingRating) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Anda sudah memberikan rating untuk pemain ini di event tersebut.'
                ], 422);
            }

            // Check rating window (e.g., within 7 days after event)
            $eventEnd = $event->event_date . ' ' . $event->end_time;
            if (now()->diffInDays($eventEnd) > 7) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Rating hanya dapat diberikan dalam 7 hari setelah event selesai.'
                ], 422);
            }

            DB::beginTransaction();

            // Create rating
            $rating = PlayerRating::create([
                'rating_user_id' => $user->id,
                'rated_user_id' => $request->rated_user_id,
                'event_id' => $request->event_id,
                'skill_rating' => $request->skill_rating,
                'sportsmanship_rating' => $request->sportsmanship_rating,
                'punctuality_rating' => $request->punctuality_rating,
                'comment' => $request->comment,
            ]);

            // Update user's average ratings
            $this->updateUserAverageRatings($ratedUser);

            // Send notification to rated user
            $this->notificationService->sendPlayerRating($ratedUser, $rating);

            DB::commit();

            $rating->load(['ratingUser.profile', 'ratedUser.profile', 'event.sport']);

            return response()->json([
                'status' => 'success',
                'message' => 'Rating berhasil diberikan!',
                'data' => [
                    'rating' => $rating
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat memberikan rating.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific rating details
     */
    public function show(PlayerRating $rating)
    {
        try {
            $user = Auth::user();

            // Check if user can access this rating
            if ($rating->rating_user_id !== $user->id && 
                $rating->rated_user_id !== $user->id && 
                !$user->hasRole('admin')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Anda tidak memiliki akses ke rating ini.'
                ], 403);
            }

            $rating->load(['ratingUser.profile', 'ratedUser.profile', 'event.sport']);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'rating' => $rating
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil detail rating.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update rating (within 24 hours)
     */
    public function update(Request $request, PlayerRating $rating)
    {
        try {
            $user = Auth::user();

            // Only rating giver can update
            if ($rating->rating_user_id !== $user->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Anda hanya dapat mengubah rating yang Anda berikan.'
                ], 403);
            }

            // Check if within 24 hours
            if ($rating->created_at->diffInHours(now()) > 24) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Rating hanya dapat diubah dalam 24 jam.'
                ], 422);
            }

            // Validate request
            $request->validate([
                'skill_rating' => 'sometimes|numeric|min:1|max:5',
                'sportsmanship_rating' => 'sometimes|numeric|min:1|max:5',
                'punctuality_rating' => 'sometimes|numeric|min:1|max:5',
                'comment' => 'sometimes|string|max:500',
            ]);

            DB::beginTransaction();

            $rating->update($request->only([
                'skill_rating', 'sportsmanship_rating', 'punctuality_rating', 'comment'
            ]));

            // Update user's average ratings
            $this->updateUserAverageRatings($rating->ratedUser);

            DB::commit();

            $rating->load(['ratingUser.profile', 'ratedUser.profile', 'event.sport']);

            return response()->json([
                'status' => 'success',
                'message' => 'Rating berhasil diperbarui!',
                'data' => [
                    'rating' => $rating
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat memperbarui rating.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete rating (within 24 hours)
     */
    public function destroy(PlayerRating $rating)
    {
        try {
            $user = Auth::user();

            // Only rating giver can delete
            if ($rating->rating_user_id !== $user->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Anda hanya dapat menghapus rating yang Anda berikan.'
                ], 403);
            }

            // Check if within 24 hours
            if ($rating->created_at->diffInHours(now()) > 24) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Rating hanya dapat dihapus dalam 24 jam.'
                ], 422);
            }

            DB::beginTransaction();

            $ratedUser = $rating->ratedUser;
            $rating->delete();

            // Update user's average ratings
            $this->updateUserAverageRatings($ratedUser);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Rating berhasil dihapus.'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat menghapus rating.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's overall rating statistics
     */
    public function getUserStats(Request $request, User $targetUser)
    {
        try {
            $user = Auth::user();

            // Privacy check
            if ($targetUser->id !== $user->id && !$targetUser->profile?->is_rating_public) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Rating pengguna ini tidak dapat diakses.'
                ], 403);
            }

            $query = PlayerRating::where('rated_user_id', $targetUser->id);

            // Filter by sport if provided
            if ($request->has('sport_id')) {
                $query->whereHas('event', function($q) use ($request) {
                    $q->where('sport_id', $request->sport_id);
                });
            }

            $ratings = $query->get();
            
            if ($ratings->count() === 0) {
                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'stats' => [
                            'total_ratings' => 0,
                            'average_skill' => 0,
                            'average_sportsmanship' => 0,
                            'average_punctuality' => 0,
                            'overall_average' => 0,
                        ]
                    ]
                ]);
            }

            $avgSkill = $ratings->avg('skill_rating');
            $avgSportsmanship = $ratings->avg('sportsmanship_rating');
            $avgPunctuality = $ratings->avg('punctuality_rating');
            $overallAvg = ($avgSkill + $avgSportsmanship + $avgPunctuality) / 3;

            $stats = [
                'total_ratings' => $ratings->count(),
                'average_skill' => round($avgSkill, 2),
                'average_sportsmanship' => round($avgSportsmanship, 2),
                'average_punctuality' => round($avgPunctuality, 2),
                'overall_average' => round($overallAvg, 2),
                'rating_distribution' => [
                    'skill' => $this->getRatingDistribution($ratings, 'skill_rating'),
                    'sportsmanship' => $this->getRatingDistribution($ratings, 'sportsmanship_rating'),
                    'punctuality' => $this->getRatingDistribution($ratings, 'punctuality_rating'),
                ],
                'recent_ratings' => $query->with(['ratingUser.profile', 'event.sport'])
                                         ->orderBy('created_at', 'desc')
                                         ->take(5)
                                         ->get(),
            ];

            return response()->json([
                'status' => 'success',
                'data' => [
                    'stats' => $stats
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil statistik rating.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Report inappropriate rating
     */
    public function reportRating(Request $request, PlayerRating $rating)
    {
        try {
            $user = Auth::user();

            // Only rated user can report
            if ($rating->rated_user_id !== $user->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Hanya penerima rating yang dapat melaporkan.'
                ], 403);
            }

            $request->validate([
                'reason' => 'required|string|max:500',
                'type' => 'required|in:inappropriate_comment,unfair_rating,spam,harassment',
            ]);

            // Store report (you might want to create a separate RatingReport model)
            // For now, we'll mark the rating as disputed
            $rating->update([
                'is_disputed' => true,
                'dispute_reason' => $request->reason,
                'dispute_type' => $request->type,
                'disputed_at' => now(),
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Rating berhasil dilaporkan dan akan ditinjau.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat melaporkan rating.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user's average ratings
     */
    private function updateUserAverageRatings(User $user)
    {
        $ratings = PlayerRating::where('rated_user_id', $user->id)
                              ->where('is_disputed', false)
                              ->get();

        if ($ratings->count() > 0) {
            $avgSkill = $ratings->avg('skill_rating');
            $avgSportsmanship = $ratings->avg('sportsmanship_rating');
            $avgPunctuality = $ratings->avg('punctuality_rating');

            // Update user profile with average ratings
            $user->profile()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'average_skill_rating' => round($avgSkill, 2),
                    'average_sportsmanship_rating' => round($avgSportsmanship, 2),
                    'average_punctuality_rating' => round($avgPunctuality, 2),
                    'total_ratings_received' => $ratings->count(),
                ]
            );
        }
    }

    /**
     * Get rating distribution for statistics
     */
    private function getRatingDistribution($ratings, $field)
    {
        $distribution = [];
        for ($i = 1; $i <= 5; $i++) {
            $count = $ratings->where($field, $i)->count();
            $percentage = $ratings->count() > 0 ? round(($count / $ratings->count()) * 100, 1) : 0;
            $distribution[$i] = [
                'count' => $count,
                'percentage' => $percentage
            ];
        }
        return $distribution;
    }
}
