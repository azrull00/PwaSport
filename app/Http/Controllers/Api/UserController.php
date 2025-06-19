<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Sport;
use App\Models\UserSportRating;
use App\Models\UserBlock;
use App\Services\EmailNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    protected $notificationService;

    public function __construct(EmailNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Get list of users with filtering
     */
    public function index(Request $request)
    {
        try {
            $query = User::with(['profile', 'sportRatings.sport'])
                ->where('is_active', true);

            // Filter by sport if provided
            if ($request->has('sport_id')) {
                $query->whereHas('sportRatings', function($q) use ($request) {
                    $q->where('sport_id', $request->sport_id);
                });
            }

            // Filter by city if provided
            if ($request->has('city')) {
                $query->whereHas('profile', function($q) use ($request) {
                    $q->where('city', 'like', '%' . $request->city . '%');
                });
            }

            // Filter by skill level if provided
            if ($request->has('skill_level') && $request->has('sport_id')) {
                $query->whereHas('sportRatings', function($q) use ($request) {
                    $q->where('sport_id', $request->sport_id)
                      ->where('skill_level', $request->skill_level);
                });
            }

            // Exclude blocked users
            $user = Auth::user();
            if ($user) {
                $blockedUserIds = $user->blockingUsers()->pluck('blocked_user_id')->toArray();
                $blockedByUserIds = $user->blockedByUsers()->pluck('blocking_user_id')->toArray();
                $allBlockedIds = array_merge($blockedUserIds, $blockedByUserIds);
                
                if (!empty($allBlockedIds)) {
                    $query->whereNotIn('id', $allBlockedIds);
                }
                
                // Exclude current user
                $query->where('id', '!=', $user->id);
            }

            // Order by credit score and registration date
            $query->orderBy('credit_score', 'desc')
                  ->orderBy('created_at', 'desc');

            // Pagination
            $perPage = $request->get('per_page', 15);
            $users = $query->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'users' => $users
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil data user.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific user details
     */
    public function show(User $user)
    {
        try {
            $currentUser = Auth::user();

            // Check if user is blocked
            if ($currentUser && 
                ($currentUser->hasBlockedUser($user->id) || $currentUser->isBlockedByUser($user->id))) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User tidak dapat diakses.'
                ], 403);
            }

            $user->load([
                'profile',
                'sportRatings.sport',
                'hostedEvents' => function($query) {
                    $query->where('status', 'completed')
                          ->orderBy('event_date', 'desc')
                          ->take(5);
                },
                'participatedEvents' => function($query) {
                    $query->where('status', 'completed')
                          ->orderBy('event_date', 'desc')
                          ->take(5);
                }
            ]);

            // Hide sensitive information
            $user->makeHidden(['email_verified_at', 'phone_verified_at', 'created_at', 'updated_at']);
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'user' => $user
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil detail user.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user profile (self only)
     */
    public function update(Request $request, User $user)
    {
        try {
            $currentUser = Auth::user();

            // Only allow self-update
            if ($currentUser->id !== $user->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Anda hanya dapat mengubah profil sendiri.'
                ], 403);
            }

            // Validate request
            $request->validate([
                'name' => 'sometimes|string|max:255',
                'email' => [
                    'sometimes',
                    'email',
                    Rule::unique('users')->ignore($user->id)
                ],
                'phone_number' => [
                    'sometimes',
                    'string',
                    Rule::unique('users')->ignore($user->id)
                ],
                'password' => 'sometimes|string|min:8|confirmed',
                'profile' => 'sometimes|array',
                'profile.first_name' => 'sometimes|string|max:255',
                'profile.last_name' => 'sometimes|string|max:255',
                'profile.birth_date' => 'sometimes|date',
                'profile.gender' => 'sometimes|in:male,female',
                'profile.height' => 'sometimes|integer|min:100|max:250',
                'profile.weight' => 'sometimes|integer|min:30|max:200',
                'profile.city' => 'sometimes|string|max:255',
                'profile.district' => 'sometimes|string|max:255',
                'profile.province' => 'sometimes|string|max:255',
                'profile.country' => 'sometimes|string|max:255',
                'profile.bio' => 'sometimes|string|max:500',
                'profile.is_location_public' => 'sometimes|boolean',
            ]);

            // Update user basic info
            $userData = $request->only(['name', 'email', 'phone_number']);
            if ($request->has('password')) {
                $userData['password'] = Hash::make($request->password);
            }
            $user->update($userData);

            // Update profile if provided
            if ($request->has('profile')) {
                $user->profile()->updateOrCreate(
                    ['user_id' => $user->id],
                    $request->profile
                );
            }

            $user->load('profile');

            return response()->json([
                'status' => 'success',
                'message' => 'Profil berhasil diperbarui!',
                'data' => [
                    'user' => $user
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat memperbarui profil.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Deactivate user account (self only)
     */
    public function destroy(User $user)
    {
        try {
            $currentUser = Auth::user();

            // Only allow self-deactivation
            if ($currentUser->id !== $user->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Anda hanya dapat menonaktifkan akun sendiri.'
                ], 403);
            }

            // Deactivate instead of delete
            $user->update(['is_active' => false]);

            return response()->json([
                'status' => 'success',
                'message' => 'Akun berhasil dinonaktifkan.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat menonaktifkan akun.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Block a user
     */
    public function blockUser(Request $request, User $user)
    {
        try {
            $currentUser = Auth::user();

            // Cannot block yourself
            if ($currentUser->id === $user->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Anda tidak dapat memblokir diri sendiri.'
                ], 422);
            }

            // Check if already blocked
            $existingBlock = UserBlock::where([
                'blocking_user_id' => $currentUser->id,
                'blocked_user_id' => $user->id
            ])->first();

            if ($existingBlock) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User sudah diblokir sebelumnya.'
                ], 422);
            }

            // Create block record
            UserBlock::create([
                'blocking_user_id' => $currentUser->id,
                'blocked_user_id' => $user->id,
                'reason' => $request->get('reason', 'No reason provided'),
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'User berhasil diblokir.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat memblokir user.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Unblock a user
     */
    public function unblockUser(User $user)
    {
        try {
            $currentUser = Auth::user();

            $block = UserBlock::where([
                'blocking_user_id' => $currentUser->id,
                'blocked_user_id' => $user->id
            ])->first();

            if (!$block) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User tidak dalam daftar blokir.'
                ], 404);
            }

            $block->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'User berhasil di-unblock.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat meng-unblock user.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user sport ratings
     */
    public function getSportRatings(User $user)
    {
        try {
            $sportRatings = UserSportRating::with('sport')
                ->where('user_id', $user->id)
                ->orderBy('skill_rating', 'desc')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'sport_ratings' => $sportRatings
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil rating olahraga.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user sport rating (self only)
     */
    public function updateSportRating(Request $request, User $user, Sport $sport)
    {
        try {
            $currentUser = Auth::user();

            // Only allow self-update
            if ($currentUser->id !== $user->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Anda hanya dapat mengubah rating olahraga sendiri.'
                ], 403);
            }

            $request->validate([
                'skill_level' => 'required|in:pemula,menengah,mahir,ahli,profesional',
                'skill_rating' => 'required|numeric|min:0|max:10',
                'experience_years' => 'nullable|integer|min:0|max:50',
                'achievements' => 'nullable|string|max:1000',
            ]);

            $sportRating = UserSportRating::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'sport_id' => $sport->id
                ],
                $request->only(['skill_level', 'skill_rating', 'experience_years', 'achievements'])
            );

            $sportRating->load('sport');

            return response()->json([
                'status' => 'success',
                'message' => 'Rating olahraga berhasil diperbarui!',
                'data' => [
                    'sport_rating' => $sportRating
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat memperbarui rating olahraga.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's blocked list
     */
    public function getBlockedUsers()
    {
        try {
            $currentUser = Auth::user();

            $blockedUsers = UserBlock::with(['blockedUser.profile'])
                ->where('blocking_user_id', $currentUser->id)
                ->orderBy('created_at', 'desc')
                ->paginate(15);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'blocked_users' => $blockedUsers
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil daftar user yang diblokir.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's match history
     */
    public function getMatchHistory(Request $request, User $user)
    {
        try {
            $currentUser = Auth::user();

            // Privacy check - only show own match history or if user allows public viewing
            if ($currentUser->id !== $user->id && !$user->profile?->is_match_history_public) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Riwayat pertandingan tidak dapat diakses.'
                ], 403);
            }

            $query = $user->matchHistory()
                ->with(['event.sport', 'event.community', 'opponent.profile'])
                ->orderBy('created_at', 'desc');

            // Filter by sport if provided
            if ($request->has('sport_id')) {
                $query->whereHas('event', function($q) use ($request) {
                    $q->where('sport_id', $request->sport_id);
                });
            }

            // Pagination
            $perPage = $request->get('per_page', 10);
            $matchHistory = $query->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'match_history' => $matchHistory
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
}
