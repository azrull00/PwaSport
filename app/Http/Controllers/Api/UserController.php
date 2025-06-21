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
            $sportRatings = $user->sportRatings()
                ->with('sport')
                ->orderBy('mmr', 'desc')
                ->get();

            // Transform data to match frontend expectations
            $transformedRatings = $sportRatings->map(function ($rating) {
                return [
                    'id' => $rating->id,
                    'sport' => [
                        'id' => $rating->sport->id,
                        'name' => $rating->sport->name,
                        'code' => $rating->sport->code
                    ],
                    'skill_rating' => $rating->mmr / 100, // Convert MMR to 0-10 scale for display
                    'skill_level' => $this->getSkillLevelFromMMR($rating->mmr),
                    'matches_played' => $rating->matches_played,
                    'wins' => $rating->wins,
                    'losses' => $rating->losses,
                    'win_rate' => $rating->win_rate,
                    'last_match_at' => $rating->last_match_at
                ];
            });

            return response()->json([
                'status' => 'success',
                'data' => [
                    'sport_ratings' => $transformedRatings
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
     * Helper method to convert MMR to skill level
     */
    private function getSkillLevelFromMMR($mmr)
    {
        if ($mmr >= 1800) return 'profesional';
        if ($mmr >= 1500) return 'ahli';
        if ($mmr >= 1200) return 'mahir';
        if ($mmr >= 900) return 'menengah';
        return 'pemula';
    }

    /**
     * Get user's blocked list
     */
    public function getBlockedUsers()
    {
        try {
            $user = Auth::user();
            
            $blockedUsers = UserBlock::with('blockedUser.profile')
                ->where('blocking_user_id', $user->id)
                ->paginate(20);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'blocked_users' => $blockedUsers
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil daftar pengguna yang diblokir.',
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

    /**
     * Get user's events (for MyEventsPage)
     */
    public function getMyEvents(Request $request)
    {
        try {
            $user = Auth::user();
            $query = $user->participatedEvents()
                ->with(['sport', 'host.profile', 'participants'])
                ->withPivot('status');

            // Filter by status (upcoming/past)
            if ($request->has('status')) {
                if ($request->status === 'upcoming') {
                    $query->where('event_date', '>=', now()->toDateString());
                } elseif ($request->status === 'past') {
                    $query->where('event_date', '<', now()->toDateString());
                }
            }

            // Filter by participation status
            if ($request->has('participation_status')) {
                $query->wherePivot('status', $request->participation_status);
            }

            $events = $query->orderBy('event_date', 'asc')
                ->paginate($request->get('per_page', 50));

            // Add participant status to each event
            foreach ($events as $event) {
                $participant = $event->participants->where('user_id', $user->id)->first();
                $event->participant_status = $participant ? $participant->status : 'unknown';
                
                // Add status display
                if ($event->event_date === now()->toDateString()) {
                    $event->status_display = 'Hari Ini';
                } elseif ($event->event_date > now()->toDateString()) {
                    $daysUntil = now()->diffInDays($event->event_date);
                    $event->status_display = $daysUntil . ' hari lagi';
                } else {
                    $event->status_display = 'Selesai';
                }
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'events' => $events
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil event Anda.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user QR code for event check-in
     */
    public function getMyQRCode(Request $request)
    {
        try {
            $user = Auth::user();
            
            // Generate QR code data
            $qrData = [
                'user_id' => $user->id,
                'timestamp' => now()->timestamp,
                'hash' => hash('sha256', $user->id . $user->email . now()->timestamp)
            ];

            return response()->json([
                'status' => 'success',
                'data' => [
                    'qr_data' => base64_encode(json_encode($qrData)),
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat membuat QR code.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload profile picture
     */
    public function uploadProfilePicture(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'profile_picture' => 'required|image|mimes:jpeg,jpg,png,webp|max:2048', // Max 2MB
        ]);

        try {
            // Delete old profile picture if exists
            if ($user->profile && $user->profile->profile_photo_url) {
                $oldPicturePath = storage_path('app/public/' . $user->profile->profile_photo_url);
                if (file_exists($oldPicturePath)) {
                    unlink($oldPicturePath);
                }
            }

            // Store new profile picture
            $uploadedFile = $request->file('profile_picture');
            $fileName = 'profile_' . $user->id . '_' . time() . '.' . $uploadedFile->getClientOriginalExtension();
            $filePath = $uploadedFile->storeAs('profile-pictures', $fileName, 'public');

            // Update user profile with new picture path
            $user->profile()->updateOrCreate(
                ['user_id' => $user->id],
                ['profile_photo_url' => $filePath]
            );

            $user->load('profile');

            return response()->json([
                'status' => 'success',
                'message' => 'Foto profile berhasil diupload!',
                'data' => [
                    'user' => $user,
                    'profile_picture_url' => asset('storage/' . $filePath)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengupload foto profile.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete profile picture
     */
    public function deleteProfilePicture(Request $request)
    {
        try {
            $user = Auth::user();

            if (!$user->profile || !$user->profile->profile_photo_url) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tidak ada foto profile untuk dihapus.'
                ], 404);
            }

            // Delete file from storage
            $picturePath = storage_path('app/public/' . $user->profile->profile_photo_url);
            if (file_exists($picturePath)) {
                unlink($picturePath);
            }

            // Update profile to remove picture reference
            $user->profile->update(['profile_photo_url' => null]);
            $user->load('profile');

            return response()->json([
                'status' => 'success',
                'message' => 'Foto profile berhasil dihapus!',
                'data' => [
                    'user' => $user
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat menghapus foto profile.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get profile picture URL
     */
    public function getProfilePictureUrl(User $user)
    {
        try {
            if (!$user->profile || !$user->profile->profile_photo_url) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User tidak memiliki foto profile.'
                ], 404);
            }

            $profilePictureUrl = asset('storage/' . $user->profile->profile_photo_url);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'profile_picture_url' => $profilePictureUrl,
                    'user_id' => $user->id,
                    'full_name' => $user->profile->full_name
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil foto profile.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get current user's matchmaking status
     */
    public function getMyMatchmakingStatus(Request $request)
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not authenticated.'
                ], 401);
            }

            // Get ongoing/upcoming events where user is participating
            $ongoingMatches = \App\Models\MatchHistory::with(['event.sport', 'player1.profile', 'player2.profile'])
                ->where(function($q) use ($user) {
                    $q->where('player1_id', $user->id)
                      ->orWhere('player2_id', $user->id);
                })
                ->where('match_status', 'ongoing')
                ->orderBy('created_at', 'desc')
                ->get();

            // Get scheduled matches (from events with matchmaking)
            $scheduledMatches = \App\Models\MatchHistory::with(['event.sport', 'player1.profile', 'player2.profile'])
                ->where(function($q) use ($user) {
                    $q->where('player1_id', $user->id)
                      ->orWhere('player2_id', $user->id);
                })
                ->where('match_status', 'scheduled')
                ->orderBy('match_date', 'asc')
                ->get();

            // Get events where user is confirmed and matchmaking might happen
            $upcomingEvents = \App\Models\Event::with(['sport', 'participants'])
                ->whereHas('participants', function($q) use ($user) {
                    $q->where('user_id', $user->id)
                      ->whereIn('status', ['confirmed', 'checked_in']);
                })
                ->where('status', 'published')
                ->where('event_date', '>=', now())
                ->orderBy('event_date', 'asc')
                ->get();

            $matchmakingStatus = [];
            foreach ($upcomingEvents as $event) {
                $hasActiveMatchmaking = \App\Models\MatchHistory::where('event_id', $event->id)
                    ->where(function($q) use ($user) {
                        $q->where('player1_id', $user->id)
                          ->orWhere('player2_id', $user->id);
                    })
                    ->whereIn('match_status', ['ongoing', 'scheduled'])
                    ->exists();

                $confirmedParticipants = $event->participants()->where('status', 'confirmed')->count();
                
                $matchmakingStatus[] = [
                    'event' => $event,
                    'has_matchmaking' => $hasActiveMatchmaking,
                    'confirmed_participants' => $confirmedParticipants,
                    'can_start_matchmaking' => $confirmedParticipants >= 2,
                    'status' => $hasActiveMatchmaking ? 'Matchmaking Aktif' : 
                               ($confirmedParticipants >= 2 ? 'Siap Matchmaking' : 'Menunggu Peserta')
                ];
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'ongoing_matches' => $ongoingMatches,
                    'scheduled_matches' => $scheduledMatches,
                    'upcoming_events' => $matchmakingStatus,
                    'summary' => [
                        'ongoing_count' => $ongoingMatches->count(),
                        'scheduled_count' => $scheduledMatches->count(),
                        'upcoming_events_count' => $upcomingEvents->count(),
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
     * Get current user's match history
     */
    public function getMyMatchHistory(Request $request)
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not authenticated.'
                ], 401);
            }
            
            $query = \App\Models\MatchHistory::with([
                'event.sport', 
                'player1.profile', 
                'player2.profile'
            ])
            ->where(function($q) use ($user) {
                $q->where('player1_id', $user->id)
                  ->orWhere('player2_id', $user->id);
            });

            // Filter by sport
            if ($request->has('sport_id') && $request->sport_id) {
                $query->whereHas('event', function($q) use ($request) {
                    $q->where('sport_id', $request->sport_id);
                });
            }

            // Filter by result
            if ($request->has('result') && $request->result) {
                if ($request->result === 'wins') {
                    $query->where(function($q) use ($user) {
                        $q->where('player1_id', $user->id)->where('result', 'player1_win')
                          ->orWhere('player2_id', $user->id)->where('result', 'player2_win');
                    });
                } elseif ($request->result === 'losses') {
                    $query->where(function($q) use ($user) {
                        $q->where('player1_id', $user->id)->where('result', 'player2_win')
                          ->orWhere('player2_id', $user->id)->where('result', 'player1_win');
                    });
                } elseif ($request->result === 'draws') {
                    $query->where('result', 'draw');
                }
            }

            // Filter by date range
            if ($request->has('date_from') && $request->date_from) {
                $query->where('match_date', '>=', $request->date_from);
            }
            if ($request->has('date_to') && $request->date_to) {
                $query->where('match_date', '<=', $request->date_to);
            }

            $query->orderBy('match_date', 'desc')
                  ->orderBy('created_at', 'desc');

            $perPage = $request->get('per_page', 15);
            $matches = $query->paginate($perPage);

            // Add match result from user's perspective
            foreach ($matches as $match) {
                $isPlayer1 = $match->player1_id === $user->id;
                $opponent = $isPlayer1 ? $match->player2 : $match->player1;
                
                // Handle potentially null scores safely
                $myScore = null;
                $opponentScore = null;
                
                if ($match->match_score && is_array($match->match_score)) {
                    $myScore = $isPlayer1 ? 
                        ($match->match_score['player1_score'] ?? null) : 
                        ($match->match_score['player2_score'] ?? null);
                    $opponentScore = $isPlayer1 ? 
                        ($match->match_score['player2_score'] ?? null) : 
                        ($match->match_score['player1_score'] ?? null);
                }

                if ($match->result === 'draw') {
                    $matchResult = 'draw';
                } elseif (
                    ($isPlayer1 && $match->result === 'player1_win') ||
                    (!$isPlayer1 && $match->result === 'player2_win')
                ) {
                    $matchResult = 'win';
                } else {
                    $matchResult = 'loss';
                }

                $match->user_perspective = [
                    'opponent' => $opponent,
                    'my_score' => $myScore,
                    'opponent_score' => $opponentScore,
                    'result' => $matchResult,
                    'result_text' => $matchResult === 'win' ? 'Menang' : 
                                    ($matchResult === 'loss' ? 'Kalah' : 'Seri')
                ];
            }

            // Get statistics
            $totalMatches = \App\Models\MatchHistory::where(function($q) use ($user) {
                $q->where('player1_id', $user->id)
                  ->orWhere('player2_id', $user->id);
            })->whereNotNull('result')->count();

            $wins = \App\Models\MatchHistory::where(function($q) use ($user) {
                $q->where('player1_id', $user->id)->where('result', 'player1_win')
                  ->orWhere('player2_id', $user->id)->where('result', 'player2_win');
            })->count();

            $losses = \App\Models\MatchHistory::where(function($q) use ($user) {
                $q->where('player1_id', $user->id)->where('result', 'player2_win')
                  ->orWhere('player2_id', $user->id)->where('result', 'player1_win');
            })->count();

            $draws = \App\Models\MatchHistory::where(function($q) use ($user) {
                $q->where('player1_id', $user->id)
                  ->orWhere('player2_id', $user->id);
            })->where('result', 'draw')->count();

            $winRate = $totalMatches > 0 ? round(($wins / $totalMatches) * 100, 1) : 0;

            return response()->json([
                'status' => 'success',
                'data' => [
                    'matches' => $matches,
                    'statistics' => [
                        'total_matches' => $totalMatches,
                        'wins' => $wins,
                        'losses' => $losses,
                        'draws' => $draws,
                        'win_rate' => $winRate
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Match History Error: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil riwayat pertandingan.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
