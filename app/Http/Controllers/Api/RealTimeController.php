<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Events\ChatMessageSent;
use App\Events\EventUpdated;
use App\Events\RealTimeNotification;
use App\Models\Event;
use App\Models\Community;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class RealTimeController extends Controller
{
    /**
     * Send a chat message to event or community
     */
    public function sendChatMessage(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'message' => 'required|string|max:1000',
                'event_id' => 'nullable|exists:events,id',
                'community_id' => 'nullable|exists:communities,id',
                'message_type' => 'in:text,image,file',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = Auth::user();
            $eventId = $request->event_id;
            $communityId = $request->community_id;

            // Validate user access to event or community
            if ($eventId) {
                $event = Event::findOrFail($eventId);
                
                // Check if user is participant or host
                $isParticipant = $event->participants()->where('user_id', $user->id)->exists();
                $isHost = $event->host_id === $user->id;
                
                if (!$isParticipant && !$isHost) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Anda tidak memiliki akses ke chat event ini.'
                    ], 403);
                }
            }

            if ($communityId) {
                $community = Community::findOrFail($communityId);
                
                // Check if user is member of community (has joined events in this community)
                $isMember = Event::where('community_id', $community->id)
                    ->whereHas('participants', function($q) use ($user) {
                        $q->where('user_id', $user->id);
                    })->exists();
                $isHost = $community->host_user_id === $user->id;
                
                if (!$isMember && !$isHost) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Anda tidak memiliki akses ke chat community ini.'
                    ], 403);
                }
            }

            // Prepare message data
            $messageData = [
                'message' => $request->message,
                'message_type' => $request->message_type ?? 'text',
                'created_at' => now()->toISOString(),
            ];

            // Determine channel type
            $channelType = $eventId ? 'event' : 'community';
            $channelId = $eventId ?? $communityId;

            // Broadcast the chat message
            broadcast(new ChatMessageSent(
                $messageData,
                $user,
                $channelType,
                $channelId
            ))->toOthers();

            return response()->json([
                'status' => 'success',
                'message' => 'Pesan berhasil dikirim.',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'avatar' => $user->userProfile->avatar_url ?? null,
                    ],
                    'message' => $request->message,
                    'message_type' => $request->message_type ?? 'text',
                    'timestamp' => now()->toISOString(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Chat message error: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengirim pesan.'
            ], 500);
        }
    }

    /**
     * Broadcast event update to subscribers
     */
    public function broadcastEventUpdate(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'event_id' => 'required|exists:events,id',
                'update_type' => 'required|string|in:participant_joined,participant_left,status_changed,info_updated,match_completed',
                'data' => 'array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $event = Event::with(['sport', 'host'])->findOrFail($request->event_id);
            $user = Auth::user();

            // Check if user is authorized to broadcast updates (host or admin)
            if ($event->host_id !== $user->id && !$user->hasRole('admin')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Anda tidak memiliki izin untuk mengirim update event.'
                ], 403);
            }

            // Broadcast the event update
            broadcast(new EventUpdated(
                $event,
                $request->update_type,
                $request->data ?? []
            ));

            return response()->json([
                'status' => 'success',
                'message' => 'Event update berhasil dikirim.',
                'data' => [
                    'event_id' => $event->id,
                    'update_type' => $request->update_type,
                    'timestamp' => now()->toISOString(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Event update error: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengirim update event.'
            ], 500);
        }
    }

    /**
     * Send real-time notification to user
     */
    public function sendNotification(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|exists:users,id',
                'type' => 'required|string|in:event_invitation,match_result,credit_score_update,rating_received,system_announcement',
                'title' => 'required|string|max:100',
                'message' => 'required|string|max:500',
                'data' => 'array',
                'priority' => 'in:low,normal,high,urgent',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = Auth::user();
            $targetUser = User::findOrFail($request->user_id);

            // Admin can send to anyone, others can only send to specific relations
            if (!$user->hasRole('admin')) {
                // Check if sender has permission to send notification to target user
                // For example, only hosts can send event-related notifications
                $canSend = false;
                
                if ($request->type === 'event_invitation') {
                    // Check if sender hosts events that target user participates in
                    $sharedEvents = Event::where('host_id', $user->id)
                        ->whereHas('participants', function($q) use ($targetUser) {
                            $q->where('user_id', $targetUser->id);
                        })->exists();
                    $canSend = $sharedEvents;
                }
                
                if (!$canSend) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Anda tidak memiliki izin untuk mengirim notifikasi ke user ini.'
                    ], 403);
                }
            }

            // Broadcast the notification
            broadcast(new RealTimeNotification(
                $targetUser,
                $request->type,
                $request->title,
                $request->message,
                $request->data ?? [],
                $request->priority ?? 'normal'
            ));

            return response()->json([
                'status' => 'success',
                'message' => 'Notifikasi berhasil dikirim.',
                'data' => [
                    'recipient' => $targetUser->name,
                    'type' => $request->type,
                    'title' => $request->title,
                    'timestamp' => now()->toISOString(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Real-time notification error: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengirim notifikasi.'
            ], 500);
        }
    }

    /**
     * Get broadcast authentication for private channels
     */
    public function authenticate(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $channelName = $request->channel_name;
            
            // Parse channel name to determine access
            if (str_starts_with($channelName, 'private-user.')) {
                $userId = str_replace('private-user.', '', $channelName);
                
                // Users can only access their own private channels
                if ($userId != $user->id) {
                    return response()->json(['status' => 'error'], 403);
                }
            }
            
            if (str_starts_with($channelName, 'private-event-chat.')) {
                $eventId = str_replace('private-event-chat.', '', $channelName);
                $event = Event::find($eventId);
                
                if (!$event) {
                    return response()->json(['status' => 'error'], 404);
                }
                
                // Check if user is participant or host
                $isParticipant = $event->participants()->where('user_id', $user->id)->exists();
                $isHost = $event->host_id === $user->id;
                
                if (!$isParticipant && !$isHost) {
                    return response()->json(['status' => 'error'], 403);
                }
            }
            
            if (str_starts_with($channelName, 'private-community-chat.')) {
                $communityId = str_replace('private-community-chat.', '', $channelName);
                $community = Community::find($communityId);
                
                if (!$community) {
                    return response()->json(['status' => 'error'], 404);
                }
                
                // Check if user is member or host
                $isMember = $community->members()->where('user_id', $user->id)->exists();
                $isHost = $community->host_user_id === $user->id;
                
                if (!$isMember && !$isHost) {
                    return response()->json(['status' => 'error'], 403);
                }
            }

            return response()->json([
                'auth' => $user->id . ':' . hash_hmac('sha256', $channelName, config('app.key')),
                'channel_data' => json_encode([
                    'user_id' => $user->id,
                    'user_info' => [
                        'name' => $user->name,
                        'avatar' => $user->userProfile->avatar_url ?? null,
                    ]
                ])
            ]);

        } catch (\Exception $e) {
            Log::error('Channel authentication error: ' . $e->getMessage());
            
            return response()->json(['status' => 'error'], 500);
        }
    }

    /**
     * Get online users for a specific channel
     */
    public function getOnlineUsers(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'channel' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // This would typically integrate with Pusher's presence channel API
            // For now, return mock data
            $onlineUsers = [
                [
                    'id' => Auth::id(),
                    'name' => Auth::user()->name,
                    'status' => 'online',
                    'last_seen' => now()->toISOString(),
                ]
            ];

            return response()->json([
                'status' => 'success',
                'data' => [
                    'channel' => $request->channel,
                    'online_users' => $onlineUsers,
                    'total_online' => count($onlineUsers),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get online users error: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil data user online.'
            ], 500);
        }
    }
}
