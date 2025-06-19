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
                    $query->whereNotIn('host_user_id', $allBlockedIds);
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
                'host_user_id' => $user->id,
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
            if ($event->host_user_id !== Auth::id()) {
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
            if ($event->host_user_id !== $user->id) {
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
            if ($event->host_user_id !== $user->id) {
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
            if ($event->host_user_id !== Auth::id()) {
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
            if ($event->host_user_id !== Auth::id()) {
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
}
