<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\Sport;
use App\Models\CommunityRating;
use App\Services\EmailNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use App\Models\Event;
use App\Models\EventParticipant;

class CommunityController extends Controller
{
    protected $notificationService;

    public function __construct(EmailNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Get list of communities with filtering
     */
    public function index(Request $request)
    {
        try {
            $query = Community::with(['host.profile', 'sport'])
                ->where('is_active', true);

            // Filter by sport
            if ($request->has('sport_id')) {
                $query->where('sport_id', $request->sport_id);
            }

            // Filter by community type
            if ($request->has('community_type')) {
                $query->where('community_type', $request->community_type);
            }

            // Filter by location
            if ($request->has('city')) {
                $query->where(function($q) use ($request) {
                    $q->where('venue_city', 'like', '%' . $request->city . '%')
                      ->orWhere('city', 'like', '%' . $request->city . '%');
                });
            }

            // Filter by skill level
            if ($request->has('skill_level')) {
                $query->where('skill_level_focus', $request->skill_level);
            }

            // Exclude blocked users' communities
            $user = Auth::user();
            if ($user) {
                $blockedUserIds = $user->blockingUsers()->pluck('blocked_user_id')->toArray();
                $blockedByUserIds = $user->blockedByUsers()->pluck('blocking_user_id')->toArray();
                $allBlockedIds = array_merge($blockedUserIds, $blockedByUserIds);
                
                if (!empty($allBlockedIds)) {
                    $query->whereNotIn('host_user_id', $allBlockedIds);
                }
            }

            // Order by rating and member count
            $query->orderBy('average_skill_rating', 'desc')
                  ->orderBy('member_count', 'desc')
                  ->orderBy('created_at', 'desc');

            // Pagination
            $perPage = $request->get('per_page', 15);
            $communities = $query->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'communities' => $communities
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil data komunitas.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new community
     */
    public function store(Request $request)
    {
        try {
            $user = Auth::user();

            // Validate request
            $request->validate([
                'name' => 'required|string|max:255|unique:communities',
                'description' => 'required|string|max:1000',
                'sport_id' => 'required|exists:sports,id',
                'community_type' => 'required|in:public,private,invitation_only',
                'skill_level_focus' => 'required|in:pemula,menengah,mahir,ahli,profesional,mixed',
                'venue_name' => 'required|string|max:255',
                'venue_address' => 'required|string|max:500',
                'venue_city' => 'required|string|max:255',
                'latitude' => 'nullable|numeric|between:-90,90',
                'longitude' => 'nullable|numeric|between:-180,180',
                'regular_schedule' => 'nullable|string|max:500',
                'membership_fee' => 'nullable|numeric|min:0|max:1000000',
                'max_members' => 'nullable|integer|min:2|max:200',
                'is_premium_required' => 'nullable|boolean',
            ]);

            // Create community
            $community = Community::create([
                'name' => $request->name,
                'description' => $request->description,
                'sport_id' => $request->sport_id,
                'host_user_id' => $user->id,
                'community_type' => $request->community_type,
                'skill_level_focus' => $request->skill_level_focus,
                'venue_name' => $request->venue_name,
                'venue_address' => $request->venue_address,
                'venue_city' => $request->venue_city,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'regular_schedule' => $request->regular_schedule,
                'membership_fee' => $request->membership_fee ?? 0,
                'max_members' => $request->max_members ?? 50,
                'member_count' => 1, // Host counts as first member
                'is_premium_required' => $request->boolean('is_premium_required', false),
                'is_active' => true,
                'average_skill_rating' => 0,
                'hospitality_rating' => 0,
            ]);

            // Load relationships
            $community->load(['host.profile', 'sport']);

            return response()->json([
                'status' => 'success',
                'message' => 'Komunitas berhasil dibuat!',
                'data' => [
                    'community' => $community
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat membuat komunitas.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific community details
     */
    public function show(Community $community)
    {
        try {
            $community->load([
                'host.profile',
                'sport',
                'events' => function($query) {
                    $query->upcoming()
                          ->orderBy('event_date', 'asc')
                          ->take(5);
                },
                'ratings' => function($query) {
                    $query->orderBy('created_at', 'desc')
                          ->take(10);
                }
            ]);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'community' => $community
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil detail komunitas.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update community (host only)
     */
    public function update(Request $request, Community $community)
    {
        try {
            $user = Auth::user();

            // Only host can update community
            if ($community->host_user_id !== $user->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Hanya host yang dapat mengubah komunitas.'
                ], 403);
            }

            // Validate request
            $request->validate([
                'name' => 'sometimes|string|max:255|unique:communities,name,' . $community->id,
                'description' => 'sometimes|string|max:1000',
                'community_type' => 'sometimes|in:public,private,invitation_only',
                'skill_level_focus' => 'sometimes|in:pemula,menengah,mahir,ahli,profesional,mixed',
                'venue_name' => 'sometimes|string|max:255',
                'venue_address' => 'sometimes|string|max:500',
                'venue_city' => 'sometimes|string|max:255',
                'regular_schedule' => 'sometimes|string|max:500',
                'membership_fee' => 'sometimes|numeric|min:0|max:1000000',
                'max_members' => 'sometimes|integer|min:2|max:200',
                'is_premium_required' => 'sometimes|boolean',
            ]);

            $community->update($request->only([
                'name', 'description', 'community_type', 'skill_level_focus',
                'venue_name', 'venue_address', 'venue_city', 'regular_schedule',
                'membership_fee', 'max_members', 'is_premium_required'
            ]));

            return response()->json([
                'status' => 'success',
                'message' => 'Komunitas berhasil diperbarui!',
                'data' => [
                    'community' => $community
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat memperbarui komunitas.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete community (host only)
     */
    public function destroy(Community $community)
    {
        try {
            $user = Auth::user();

            // Only host can delete community
            if ($community->host_user_id !== $user->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Hanya host yang dapat menghapus komunitas.'
                ], 403);
            }

            // Soft delete by deactivating
            $community->update(['is_active' => false]);

            return response()->json([
                'status' => 'success',
                'message' => 'Komunitas berhasil dinonaktifkan.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat menghapus komunitas.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rate a community
     */
    public function rateCommunity(Request $request, Community $community)
    {
        try {
            $user = Auth::user();

            // Validate request
            $request->validate([
                'event_id' => 'required|exists:events,id',
                'skill_rating' => 'required|numeric|min:1|max:5',
                'hospitality_rating' => 'required|numeric|min:1|max:5',
                'review' => 'nullable|string|max:500',
            ]);

            // Verify user participated in the event
            $eventParticipation = EventParticipant::where([
                'event_id' => $request->event_id,
                'user_id' => $user->id,
                'status' => 'checked_in' // User must have checked in to rate
            ])->first();

            if (!$eventParticipation) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Anda hanya dapat memberikan rating setelah mengikuti event komunitas.'
                ], 403);
            }

            // Verify the event belongs to this community
            $event = Event::where('id', $request->event_id)
                          ->where('community_id', $community->id)
                          ->first();

            if (!$event) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Event tidak ditemukan atau tidak terkait dengan komunitas ini.'
                ], 404);
            }

            // Check if user already rated this community for this event
            $existingRating = CommunityRating::where([
                'community_id' => $community->id,
                'user_id' => $user->id,
                'event_id' => $request->event_id
            ])->first();

            if ($existingRating) {
                // Update existing rating
                $existingRating->update([
                    'skill_rating' => $request->skill_rating,
                    'hospitality_rating' => $request->hospitality_rating,
                    'review' => $request->review,
                ]);
                $rating = $existingRating;
            } else {
                // Create new rating
                $rating = CommunityRating::create([
                    'community_id' => $community->id,
                    'user_id' => $user->id,
                    'event_id' => $request->event_id,
                    'skill_rating' => $request->skill_rating,
                    'hospitality_rating' => $request->hospitality_rating,
                    'review' => $request->review,
                ]);
            }

            // Update community average ratings
            $this->updateCommunityAverageRatings($community);

            return response()->json([
                'status' => 'success',
                'message' => 'Rating komunitas berhasil disimpan!',
                'data' => [
                    'rating' => $rating
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat memberikan rating.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get community ratings
     */
    public function getRatings(Community $community)
    {
        try {
            $ratings = CommunityRating::with(['user.profile', 'event'])
                ->where('community_id', $community->id)
                ->orderBy('created_at', 'desc')
                ->paginate(10);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'ratings' => $ratings
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil rating komunitas.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's past events in community (for rating)
     */
    public function getUserPastEvents(Community $community)
    {
        try {
            $user = Auth::user();

            // Get events where user participated and checked in, but haven't rated yet
            $pastEvents = Event::where('community_id', $community->id)
                ->where('status', 'completed')
                ->whereHas('participants', function($query) use ($user) {
                    $query->where('user_id', $user->id)
                          ->where('status', 'checked_in');
                })
                ->whereDoesntHave('communityRatings', function($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->with(['sport'])
                ->orderBy('event_date', 'desc')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'events' => $pastEvents
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil event.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get community events
     */
    public function getEvents(Request $request, Community $community)
    {
        try {
            $query = $community->events()
                ->with(['host.profile', 'sport', 'participants'])
                ->active();

            // Filter by upcoming or past
            if ($request->get('filter') === 'upcoming') {
                $query->upcoming();
            } elseif ($request->get('filter') === 'past') {
                $query->where('event_date', '<', now()->toDateString());
            }

            $events = $query->orderBy('event_date', $request->get('filter') === 'past' ? 'desc' : 'asc')
                           ->paginate(10);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'events' => $events
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil event komunitas.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update community average ratings
     */
    private function updateCommunityAverageRatings(Community $community)
    {
        $ratings = CommunityRating::where('community_id', $community->id);

        $avgSkillRating = $ratings->avg('skill_rating') ?? 0;
        $avgHospitalityRating = $ratings->avg('hospitality_rating') ?? 0;

        $community->update([
            'average_skill_rating' => round($avgSkillRating, 2),
            'hospitality_rating' => round($avgHospitalityRating, 2),
        ]);
    }

    /**
     * Upload community icon
     */
    public function uploadIcon(Request $request, Community $community)
    {
        try {
            $user = Auth::user();
            
            // Check if user is the host
            if ($community->host_user_id !== $user->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Hanya host yang dapat mengupload icon komunitas.'
                ], 403);
            }

            // Validate file
            $request->validate([
                'icon' => 'required|image|mimes:jpeg,jpg,png,webp,svg|max:2048'
            ]);

            // Delete old icon if exists
            if ($community->getRawOriginal('icon_url')) {
                $oldPath = str_replace('storage/', '', parse_url($community->getRawOriginal('icon_url'), PHP_URL_PATH));
                if (Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
            }

            // Store new icon
            $file = $request->file('icon');
            $filename = 'community_' . $community->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('community-icons', $filename, 'public');

            // Update community
            $community->update(['icon_url' => $path]);

            return response()->json([
                'status' => 'success',
                'message' => 'Icon komunitas berhasil diupload!',
                'data' => [
                    'icon_url' => $community->fresh()->icon_url,
                    'has_icon' => true
                ]
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data tidak valid.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengupload icon.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete community icon
     */
    public function deleteIcon(Community $community)
    {
        try {
            $user = Auth::user();
            
            // Check if user is the host
            if ($community->host_user_id !== $user->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Hanya host yang dapat menghapus icon komunitas.'
                ], 403);
            }

            // Check if icon exists
            if (!$community->getRawOriginal('icon_url')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Komunitas tidak memiliki icon.'
                ], 404);
            }

            // Delete icon file
            $oldPath = str_replace('storage/', '', parse_url($community->getRawOriginal('icon_url'), PHP_URL_PATH));
            if (Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }

            // Update community
            $community->update(['icon_url' => null]);

            return response()->json([
                'status' => 'success',
                'message' => 'Icon komunitas berhasil dihapus!',
                'data' => [
                    'icon_url' => null,
                    'has_icon' => false
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat menghapus icon.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get community icon URL
     */
    public function getIcon(Community $community)
    {
        try {
            return response()->json([
                'status' => 'success',
                'data' => [
                    'icon_url' => $community->icon_url,
                    'has_icon' => $community->has_icon
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil icon.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get my communities (communities I'm a member of or host)
     */
    public function getMyCommunities()
    {
        try {
            $user = Auth::user();
            
            // Get communities where user is host or member
            $communities = Community::with(['sport', 'host.profile'])
                ->where(function($query) use ($user) {
                    $query->where('host_user_id', $user->id)
                          ->orWhereHas('activeMembers', function($q) use ($user) {
                              $q->where('user_id', $user->id);
                          });
                })
                ->where('is_active', true)
                ->orderBy('updated_at', 'desc')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'communities' => $communities
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil komunitas.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get community messages
     */
    public function getMessages(Request $request, Community $community)
    {
        try {
            $user = Auth::user();
            
            // Check if user is member or host
            if (!$community->canUserAccess($user->id)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Anda tidak memiliki akses ke komunitas ini.'
                ], 403);
            }

            $messages = $community->messages()
                ->notDeleted()
                ->with(['user.profile'])
                ->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 50));

            return response()->json([
                'status' => 'success',
                'data' => [
                    'messages' => $messages->items(),
                    'pagination' => [
                        'current_page' => $messages->currentPage(),
                        'last_page' => $messages->lastPage(),
                        'per_page' => $messages->perPage(),
                        'total' => $messages->total()
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil pesan.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send message to community
     */
    public function sendMessage(Request $request, Community $community)
    {
        try {
            $user = Auth::user();
            
            // Check if user is member or host
            if (!$community->canUserAccess($user->id)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Anda tidak memiliki akses ke komunitas ini.'
                ], 403);
            }

            $request->validate([
                'message' => 'required|string|max:1000'
            ]);

            $message = $community->messages()->create([
                'user_id' => $user->id,
                'message' => $request->message,
                'message_type' => 'text'
            ]);

            $message->load(['user.profile']);

            return response()->json([
                'status' => 'success',
                'message' => 'Pesan berhasil dikirim!',
                'data' => [
                    'message' => $message
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengirim pesan.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get community members
     */
    public function getMembers(Community $community)
    {
        try {
            $members = $community->members()
                ->with(['user.profile'])
                ->orderBy('joined_at', 'desc')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'members' => $members
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil data anggota.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Join community (FREE for development)
     */
    public function joinCommunity(Community $community)
    {
        try {
            $user = Auth::user();

            // Check if user is already a member
            $existingMember = $community->members()->where('user_id', $user->id)->first();
            if ($existingMember) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Anda sudah menjadi anggota komunitas ini.'
                ], 400);
            }

            // Check if community has reached max members
            if ($community->max_members && $community->member_count >= $community->max_members) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Komunitas telah mencapai batas maksimal anggota.'
                ], 400);
            }

            // Create membership (FREE for development)
            $membership = $community->members()->create([
                'user_id' => $user->id,
                'status' => 'active', // Auto-approve for development
                'joined_at' => now(),
            ]);

            // Update community member count
            $community->increment('member_count');

            // Load relationships
            $membership->load('user.profile');

            return response()->json([
                'status' => 'success',
                'message' => 'Berhasil bergabung dengan komunitas!',
                'data' => [
                    'membership' => $membership
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat bergabung dengan komunitas.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Leave community
     */
    public function leaveCommunity(Community $community)
    {
        try {
            $user = Auth::user();

            // Check if user is the host
            if ($community->host_user_id == $user->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Host tidak dapat keluar dari komunitas. Silakan transfer kepemilikan atau hapus komunitas.'
                ], 400);
            }

            // Find membership
            $membership = $community->members()->where('user_id', $user->id)->first();
            if (!$membership) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Anda bukan anggota komunitas ini.'
                ], 400);
            }

            // Delete membership
            $membership->delete();

            // Update community member count
            $community->decrement('member_count');

            return response()->json([
                'status' => 'success',
                'message' => 'Berhasil keluar dari komunitas.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat keluar dari komunitas.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
