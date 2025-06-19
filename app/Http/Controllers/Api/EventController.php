<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateEventRequest;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\Community;
use App\Services\EmailNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class EventController extends Controller
{
    protected $notificationService;

    public function __construct(EmailNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Get list of events with filtering
     */
    public function index(Request $request)
    {
        try {
            $query = Event::with(['host.profile', 'sport', 'community', 'participants'])
                ->active()
                ->upcoming();

            // Filter by sport
            if ($request->has('sport_id')) {
                $query->where('sport_id', $request->sport_id);
            }

            // Filter by event type
            if ($request->has('event_type')) {
                $query->byType($request->event_type);
            }

            // Filter by skill level
            if ($request->has('skill_level')) {
                $query->where('skill_level_required', $request->skill_level);
            }

            // Filter by location
            if ($request->has('city')) {
                $query->where('location_address', 'like', '%' . $request->city . '%');
            }

            // Filter by date range
            if ($request->has('date_from')) {
                $query->where('event_date', '>=', $request->date_from);
            }
            if ($request->has('date_to')) {
                $query->where('event_date', '<=', $request->date_to);
            }

            // Filter by premium
            if ($request->has('premium_only')) {
                $query->where('is_premium_only', $request->boolean('premium_only'));
            }

            // Filter available events only
            if ($request->boolean('available_only')) {
                $query->whereRaw('(SELECT COUNT(*) FROM event_participants WHERE event_id = events.id AND status IN ("confirmed", "registered")) < events.max_participants');
            }

            // Exclude blocked users' events
            $user = Auth::user();
            if ($user) {
                $blockedUserIds = $user->blockingUsers()->pluck('blocked_user_id')->toArray();
                $blockedByUserIds = $user->blockedByUsers()->pluck('blocking_user_id')->toArray();
                $allBlockedIds = array_merge($blockedUserIds, $blockedByUserIds);
                
                if (!empty($allBlockedIds)) {
                    $query->whereNotIn('host_id', $allBlockedIds);
                }
            }

            // Order by date and time
            $query->orderBy('event_date', 'asc')
                  ->orderBy('start_time', 'asc');

            // Pagination
            $perPage = $request->get('per_page', 15);
            $events = $query->paginate($perPage);

            // Add additional data for each event
            $events->getCollection()->transform(function ($event) {
                $event->available_slots = $event->available_slots;
                $event->is_full = $event->isFull();
                return $event;
            });

            return response()->json([
                'status' => 'success',
                'data' => [
                    'events' => $events
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil data event.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new event
     */
    public function store(CreateEventRequest $request)
    {
        try {
            $user = Auth::user();

            // Create datetime objects
            $startDateTime = Carbon::parse($request->event_date . ' ' . $request->start_time);
            $endDateTime = Carbon::parse($request->event_date . ' ' . $request->end_time);

            // Create event
            $event = Event::create([
                'community_id' => $request->community_id,
                'sport_id' => $request->sport_id,
                'host_id' => $user->id,
                'title' => $request->title,
                'description' => $request->description,
                'event_type' => $request->event_type,
                'event_date' => $request->event_date,
                'start_time' => $startDateTime,
                'end_time' => $endDateTime,
                'max_participants' => $request->max_participants,
                'location_name' => $request->location_name,
                'location_address' => $request->location_address,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'entry_fee' => $request->entry_fee ?? 0,
                'skill_level_required' => $request->skill_level_required,
                'status' => 'active',
                'is_premium_only' => $request->boolean('is_premium_only', false),
                'auto_confirm_participants' => $request->boolean('auto_confirm_participants', true),
            ]);

            // Auto-register host if it's a mabar or friendly match
            if (in_array($event->event_type, ['mabar', 'friendly_match'])) {
                EventParticipant::create([
                    'event_id' => $event->id,
                    'user_id' => $user->id,
                    'status' => 'confirmed',
                    'registered_at' => now(),
                ]);
            }

            // Load relationships
            $event->load(['host.profile', 'sport', 'community', 'participants']);

            return response()->json([
                'status' => 'success',
                'message' => 'Event berhasil dibuat!',
                'data' => [
                    'event' => $event
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat membuat event.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific event details
     */
    public function show(Event $event)
    {
        try {
            $event->load([
                'host.profile', 
                'sport', 
                'community', 
                'participants.user.profile',
                'matches'
            ]);

            $event->available_slots = $event->available_slots;
            $event->is_full = $event->isFull();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'event' => $event
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil detail event.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Join event
     */
    public function joinEvent(Request $request, Event $event)
    {
        try {
            $user = Auth::user();

            // Check if user can join
            if (!$event->canUserJoin($user)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Anda tidak dapat bergabung dengan event ini.'
                ], 403);
            }

            // Check if event is full
            $confirmedCount = $event->confirmedParticipants()->count();
            $status = 'confirmed';
            $queuePosition = null;

            if ($confirmedCount >= $event->max_participants) {
                $status = 'waiting';
                $queuePosition = $event->waitingParticipants()->count() + 1;
            }

            // Create participation record
            $participation = EventParticipant::create([
                'event_id' => $event->id,
                'user_id' => $user->id,
                'status' => $event->auto_confirm_participants ? $status : 'registered',
                'queue_position' => $queuePosition,
                'registered_at' => now(),
                'is_premium_protected' => $user->subscription_tier === 'premium',
            ]);

            $message = $status === 'waiting' 
                ? 'Anda telah ditambahkan ke waiting list.'
                : 'Anda berhasil terdaftar untuk event ini!';

            return response()->json([
                'status' => 'success',
                'message' => $message,
                'data' => [
                    'participation' => $participation,
                    'queue_position' => $queuePosition
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mendaftar event.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Leave event
     */
    public function leaveEvent(Request $request, Event $event)
    {
        try {
            $user = Auth::user();
            
            $participation = EventParticipant::where([
                'event_id' => $event->id,
                'user_id' => $user->id
            ])->first();

            if (!$participation) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Anda tidak terdaftar untuk event ini.'
                ], 404);
            }

            // Check if it's too late to cancel (24 hours before event)
            $eventStart = Carbon::parse($event->event_date . ' ' . $event->start_time);
            $hoursUntilEvent = now()->diffInHours($eventStart, false);

            if ($hoursUntilEvent < 24 && $hoursUntilEvent > 0) {
                // Late cancellation penalty
                $user->credit_score -= 10;
                $user->save();

                // Log credit score change
                $user->creditScoreLogs()->create([
                    'action_type' => 'late_cancel',
                    'points_change' => -10,
                    'previous_score' => $user->credit_score + 10,
                    'new_score' => $user->credit_score,
                    'reason' => 'Pembatalan mendadak (kurang dari 24 jam)',
                    'event_id' => $event->id,
                ]);

                // Send notification
                $this->notificationService->sendCreditScoreChange($user, [
                    'new_score' => $user->credit_score,
                    'change' => -10,
                    'reason' => 'Late cancellation'
                ]);
            }

            // Remove participation
            $participation->delete();

            // Promote waiting list if needed
            $this->promoteFromWaitingList($event);

            return response()->json([
                'status' => 'success',
                'message' => 'Anda berhasil keluar dari event.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat keluar dari event.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get event participants
     */
    public function getParticipants(Event $event)
    {
        try {
            $participants = EventParticipant::where('event_id', $event->id)
                ->with(['user.profile'])
                ->orderBy('status')
                ->orderBy('queue_position')
                ->orderBy('registered_at')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'participants' => $participants
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil data peserta.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check-in participant
     */
    public function checkInParticipant(Request $request, Event $event, EventParticipant $participant)
    {
        try {
            // Only host can check-in participants
            if ($event->host_id !== Auth::id()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Hanya host yang dapat melakukan check-in.'
                ], 403);
            }

            $participant->update([
                'status' => 'checked_in',
                'checked_in_at' => now(),
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Peserta berhasil di-check-in.',
                'data' => [
                    'participant' => $participant
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat check-in peserta.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check-in participant via QR scan
     */
    public function checkInParticipantByQR(Request $request, Event $event)
    {
        try {
            $request->validate([
                'qr_code' => 'required|string',
            ]);

            $user = Auth::user();

            // Only host can check-in participants
            if ($event->host_id !== $user->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Hanya host yang dapat melakukan check-in.'
                ], 403);
            }

            // Find user by QR code
            $userProfile = \App\Models\UserProfile::where('qr_code', $request->qr_code)->first();
            if (!$userProfile) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'QR code tidak valid atau tidak ditemukan.'
                ], 404);
            }

            // Find participant record
            $participant = EventParticipant::where([
                'event_id' => $event->id,
                'user_id' => $userProfile->user_id,
            ])->first();

            if (!$participant) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Player tidak terdaftar untuk event ini.',
                    'data' => [
                        'player_name' => $userProfile->full_name,
                        'player_id' => $userProfile->user_id,
                    ]
                ], 404);
            }

            // Check if already checked in
            if ($participant->status === 'checked_in') {
                return response()->json([
                    'status' => 'warning',
                    'message' => 'Player sudah di-check-in sebelumnya.',
                    'data' => [
                        'participant' => $participant->load('user.profile'),
                        'checked_in_at' => $participant->checked_in_at,
                    ]
                ], 200);
            }

            // Check if participant can be checked in
            if (!$participant->canCheckIn()) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Player tidak dapat di-check-in. Status saat ini: {$participant->status}",
                    'data' => [
                        'participant' => $participant->load('user.profile'),
                        'current_status' => $participant->status,
                    ]
                ], 422);
            }

            // Perform check-in
            $participant->update([
                'status' => 'checked_in',
                'checked_in_at' => now(),
            ]);

            // Send real-time notification to event participants
            broadcast(new \App\Events\EventUpdated($event, 'participant_checked_in', [
                'participant' => $participant->load('user.profile'),
                'message' => "{$userProfile->full_name} telah check-in ke event."
            ]));

            return response()->json([
                'status' => 'success',
                'message' => 'Player berhasil di-check-in via QR scan!',
                'data' => [
                    'participant' => $participant->load('user.profile'),
                    'check_in_method' => 'qr_scan',
                    'checked_in_at' => $participant->checked_in_at,
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e; // Let validation exceptions bubble up for proper 422 response
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat QR check-in.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk check-in multiple participants (manual mode)
     */
    public function bulkCheckInParticipants(Request $request, Event $event)
    {
        try {
            $request->validate([
                'participant_ids' => 'required|array',
                'participant_ids.*' => 'exists:event_participants,id',
            ]);

            $user = Auth::user();

            // Only host can check-in participants
            if ($event->host_id !== $user->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Hanya host yang dapat melakukan check-in.'
                ], 403);
            }

            $participants = EventParticipant::whereIn('id', $request->participant_ids)
                ->where('event_id', $event->id)
                ->get();

            $checkedIn = [];
            $errors = [];

            foreach ($participants as $participant) {
                if ($participant->canCheckIn()) {
                    $participant->update([
                        'status' => 'checked_in',
                        'checked_in_at' => now(),
                    ]);
                    $checkedIn[] = $participant->load('user.profile');
                } else {
                    $errors[] = [
                        'participant_id' => $participant->id,
                        'user_name' => $participant->user->profile->full_name,
                        'reason' => "Status tidak valid: {$participant->status}",
                    ];
                }
            }

            // Send real-time notification
            if (count($checkedIn) > 0) {
                broadcast(new \App\Events\EventUpdated($event, 'bulk_check_in', [
                    'checked_in_count' => count($checkedIn),
                    'message' => count($checkedIn) . ' peserta berhasil di-check-in.'
                ]));
            }

            return response()->json([
                'status' => 'success',
                'message' => count($checkedIn) . ' peserta berhasil di-check-in.',
                'data' => [
                    'checked_in' => $checkedIn,
                    'errors' => $errors,
                    'total_checked_in' => count($checkedIn),
                    'total_errors' => count($errors),
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e; // Let validation exceptions bubble up for proper 422 response
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat bulk check-in.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get event check-in statistics
     */
    public function getCheckInStats(Event $event)
    {
        try {
            $user = Auth::user();

            // Only host can view check-in stats
            if ($event->host_id !== $user->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Hanya host yang dapat melihat statistik check-in.'
                ], 403);
            }

            $stats = [
                'total_registered' => $event->participants()->count(),
                'checked_in' => $event->participants()->where('status', 'checked_in')->count(),
                'confirmed_not_checked' => $event->participants()->where('status', 'confirmed')->count(),
                'waiting_list' => $event->participants()->where('status', 'waiting')->count(),
                'no_show' => $event->participants()->where('status', 'no_show')->count(),
                'cancelled' => $event->participants()->where('status', 'cancelled')->count(),
            ];

            $stats['check_in_rate'] = $stats['total_registered'] > 0 
                ? round(($stats['checked_in'] / $stats['total_registered']) * 100, 2) 
                : 0;

            // Get recent check-ins (last 10)
            $recentCheckIns = $event->participants()
                ->where('status', 'checked_in')
                ->with('user.profile')
                ->orderBy('checked_in_at', 'desc')
                ->take(10)
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'statistics' => $stats,
                    'recent_check_ins' => $recentCheckIns,
                    'event_info' => [
                        'id' => $event->id,
                        'title' => $event->title,
                        'event_date' => $event->event_date,
                        'max_participants' => $event->max_participants,
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil statistik check-in.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Promote users from waiting list
     */
    private function promoteFromWaitingList(Event $event)
    {
        $availableSlots = $event->max_participants - $event->confirmedParticipants()->count();
        
        if ($availableSlots > 0) {
            $waitingParticipants = $event->waitingParticipants()
                ->orderBy('is_premium_protected', 'desc')
                ->orderBy('queue_position')
                ->take($availableSlots)
                ->get();

            foreach ($waitingParticipants as $participant) {
                $participant->update([
                    'status' => 'confirmed',
                    'queue_position' => null,
                ]);

                // Send promotion notification
                $this->notificationService->sendWaitlistPromotion($participant->user, $event);
            }

            // Update queue positions for remaining waiting participants
            $this->updateWaitingListPositions($event);
        }
    }

    /**
     * Update queue positions for waiting list
     */
    private function updateWaitingListPositions(Event $event)
    {
        $waitingParticipants = $event->waitingParticipants()
            ->orderBy('is_premium_protected', 'desc')
            ->orderBy('registered_at')
            ->get();

        foreach ($waitingParticipants as $index => $participant) {
            $participant->update(['queue_position' => $index + 1]);
        }
    }

    /**
     * Update event (host only)
     */
    public function update(Request $request, Event $event)
    {
        try {
            $user = Auth::user();

            // Only host can update event
            if ($event->host_id !== $user->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Hanya host yang dapat mengubah event.'
                ], 403);
            }

            $event->update($request->only([
                'title', 'description', 'max_participants', 'location_name', 
                'location_address', 'entry_fee', 'skill_level_required'
            ]));

            return response()->json([
                'status' => 'success',
                'message' => 'Event berhasil diperbarui!',
                'data' => [
                    'event' => $event
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat memperbarui event.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete event (host only)
     */
    public function destroy(Event $event)
    {
        try {
            $user = Auth::user();

            // Only host can delete event
            if ($event->host_id !== $user->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Hanya host yang dapat menghapus event.'
                ], 403);
            }

            $event->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Event berhasil dihapus.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat menghapus event.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Confirm participant (host only)
     */
    public function confirmParticipant(Request $request, Event $event, EventParticipant $participant)
    {
        try {
            // Only host can confirm participants
            if ($event->host_id !== Auth::id()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Hanya host yang dapat mengkonfirmasi peserta.'
                ], 403);
            }

            $participant->update(['status' => 'confirmed']);

            return response()->json([
                'status' => 'success',
                'message' => 'Peserta berhasil dikonfirmasi.',
                'data' => [
                    'participant' => $participant
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengkonfirmasi peserta.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject participant (host only)
     */
    public function rejectParticipant(Request $request, Event $event, EventParticipant $participant)
    {
        try {
            // Only host can reject participants
            if ($event->host_id !== Auth::id()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Hanya host yang dapat menolak peserta.'
                ], 403);
            }

            $participant->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Peserta berhasil ditolak.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat menolak peserta.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload event thumbnail
     */
    public function uploadThumbnail(Request $request, Event $event)
    {
        try {
            $user = Auth::user();
            
            // Check if user is the host
            if ($event->host_id !== $user->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Hanya host yang dapat mengupload thumbnail event.'
                ], 403);
            }

            // Validate file
            $request->validate([
                'thumbnail' => 'required|image|mimes:jpeg,jpg,png,webp|max:5120' // 5MB max
            ]);

            // Delete old thumbnail if exists
            if ($event->getRawOriginal('thumbnail_url')) {
                $oldPath = str_replace('storage/', '', parse_url($event->getRawOriginal('thumbnail_url'), PHP_URL_PATH));
                if (Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
            }

            // Store new thumbnail
            $file = $request->file('thumbnail');
            $filename = 'event_' . $event->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('event-thumbnails', $filename, 'public');

            // Update event
            $event->update(['thumbnail_url' => $path]);

            return response()->json([
                'status' => 'success',
                'message' => 'Thumbnail event berhasil diupload!',
                'data' => [
                    'thumbnail_url' => $event->fresh()->thumbnail_url,
                    'has_thumbnail' => true
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
                'message' => 'Terjadi kesalahan saat mengupload thumbnail.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete event thumbnail
     */
    public function deleteThumbnail(Event $event)
    {
        try {
            $user = Auth::user();
            
            // Check if user is the host
            if ($event->host_id !== $user->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Hanya host yang dapat menghapus thumbnail event.'
                ], 403);
            }

            // Check if thumbnail exists
            if (!$event->getRawOriginal('thumbnail_url')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Event tidak memiliki thumbnail.'
                ], 404);
            }

            // Delete thumbnail file
            $oldPath = str_replace('storage/', '', parse_url($event->getRawOriginal('thumbnail_url'), PHP_URL_PATH));
            if (Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }

            // Update event
            $event->update(['thumbnail_url' => null]);

            return response()->json([
                'status' => 'success',
                'message' => 'Thumbnail event berhasil dihapus!',
                'data' => [
                    'thumbnail_url' => null,
                    'has_thumbnail' => false
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat menghapus thumbnail.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get event thumbnail URL
     */
    public function getThumbnail(Event $event)
    {
        try {
            return response()->json([
                'status' => 'success',
                'data' => [
                    'thumbnail_url' => $event->thumbnail_url,
                    'has_thumbnail' => $event->has_thumbnail
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil thumbnail.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
