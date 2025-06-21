<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PrivateMessage;
use App\Models\User;
use App\Models\Friendship;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class PrivateMessageController extends Controller
{
    /**
     * Get user's conversations list
     */
    public function getConversations(Request $request)
    {
        try {
            $user = Auth::user();
            $perPage = $request->get('per_page', 20);

            // Get all conversations for this user
            $subQuery = PrivateMessage::select([
                DB::raw('CASE 
                    WHEN sender_id = ' . $user->id . ' THEN receiver_id 
                    ELSE sender_id 
                END as other_user_id'),
                DB::raw('MAX(created_at) as last_message_time'),
                DB::raw('COUNT(CASE 
                    WHEN receiver_id = ' . $user->id . ' AND read_at IS NULL AND is_deleted_by_receiver = 0 
                    THEN 1 
                END) as unread_count')
            ])
            ->where(function ($query) use ($user) {
                $query->where('sender_id', $user->id)
                      ->where('is_deleted_by_sender', false);
            })->orWhere(function ($query) use ($user) {
                $query->where('receiver_id', $user->id)
                      ->where('is_deleted_by_receiver', false);
            })
            ->groupBy(DB::raw('CASE 
                WHEN sender_id = ' . $user->id . ' THEN receiver_id 
                ELSE sender_id 
            END'))
            ->orderBy('last_message_time', 'desc')
            ->get();

            // Get user details and last message for each conversation
            $conversationData = [];
            foreach ($subQuery as $conversation) {
                $otherUser = User::with('profile')->find($conversation->other_user_id);
                if (!$otherUser) continue;

                $lastMessage = PrivateMessage::getLastMessageBetween($user->id, $otherUser->id);
                
                $conversationData[] = [
                    'user' => [
                        'id' => $otherUser->id,
                        'name' => $otherUser->name,
                        'email' => $otherUser->email,
                        'profile' => $otherUser->profile,
                        'subscription_tier' => $otherUser->subscription_tier,
                    ],
                    'last_message' => $lastMessage ? [
                        'id' => $lastMessage->id,
                        'message' => $lastMessage->message,
                        'message_type' => $lastMessage->message_type,
                        'created_at' => $lastMessage->created_at,
                        'is_from_me' => $lastMessage->sender_id === $user->id,
                        'is_read' => $lastMessage->isRead(),
                    ] : null,
                    'unread_count' => $conversation->unread_count,
                    'last_activity' => $conversation->last_message_time,
                ];
            }

            // Manual pagination
            $currentPage = $request->get('page', 1);
            $offset = ($currentPage - 1) * $perPage;
            $paginatedConversations = array_slice($conversationData, $offset, $perPage);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'conversations' => $paginatedConversations,
                    'total' => count($conversationData),
                    'per_page' => $perPage,
                    'current_page' => $currentPage,
                    'total_pages' => ceil(count($conversationData) / $perPage),
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in getConversations: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil daftar percakapan.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get conversation messages with specific user
     */
    public function getConversation(Request $request, $userId)
    {
        try {
            $user = Auth::user();
            $limit = $request->get('limit', 50);

            // Validate that user exists
            $otherUser = User::find($userId);
            if (!$otherUser) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Pengguna tidak ditemukan.'
                ], 404);
            }

            // Check if users are friends (optional - you can remove this if you want open messaging)
            if (!Friendship::areFriends($user->id, $userId)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Anda hanya dapat mengirim pesan ke teman.'
                ], 403);
            }

            // Get conversation messages
            $messages = PrivateMessage::getConversation($user->id, $userId, $limit);

            // Mark messages as read
            PrivateMessage::markAllAsRead($user->id, $userId);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'messages' => $messages,
                    'other_user' => [
                        'id' => $otherUser->id,
                        'name' => $otherUser->name,
                        'email' => $otherUser->email,
                        'profile' => $otherUser->profile,
                        'subscription_tier' => $otherUser->subscription_tier,
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil percakapan.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send private message
     */
    public function sendMessage(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'receiver_id' => 'required|integer|exists:users,id',
                'message' => 'required|string|max:2000',
                'message_type' => 'in:text,image,file',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Data tidak valid.',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = Auth::user();
            $receiverId = $request->receiver_id;

            // Can't send message to self
            if ($user->id == $receiverId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tidak dapat mengirim pesan ke diri sendiri.'
                ], 400);
            }

            // Check if receiver exists
            $receiver = User::find($receiverId);
            if (!$receiver) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Penerima pesan tidak ditemukan.'
                ], 404);
            }

            // Check if users are friends (optional)
            if (!Friendship::areFriends($user->id, $receiverId)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Anda hanya dapat mengirim pesan ke teman.'
                ], 403);
            }

            // Create message
            $message = PrivateMessage::create([
                'sender_id' => $user->id,
                'receiver_id' => $receiverId,
                'message' => $request->message,
                'message_type' => $request->message_type ?: 'text',
            ]);

            // Load relationships
            $message->load(['sender.profile', 'receiver.profile']);

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
     * Mark message as read
     */
    public function markAsRead(Request $request, $messageId)
    {
        try {
            $user = Auth::user();
            
            $message = PrivateMessage::where('id', $messageId)
                ->where('receiver_id', $user->id)
                ->first();

            if (!$message) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Pesan tidak ditemukan.'
                ], 404);
            }

            $message->markAsRead();

            return response()->json([
                'status' => 'success',
                'message' => 'Pesan ditandai sudah dibaca.',
                'data' => [
                    'message' => $message
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat menandai pesan.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark all messages from user as read
     */
    public function markAllAsRead(Request $request, $userId)
    {
        try {
            $user = Auth::user();
            
            $count = PrivateMessage::markAllAsRead($user->id, $userId);

            return response()->json([
                'status' => 'success',
                'message' => "Semua pesan ditandai sudah dibaca.",
                'data' => [
                    'marked_count' => $count
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat menandai pesan.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete message for current user
     */
    public function deleteMessage(Request $request, $messageId)
    {
        try {
            $user = Auth::user();
            
            $message = PrivateMessage::where('id', $messageId)
                ->where(function ($query) use ($user) {
                    $query->where('sender_id', $user->id)
                          ->orWhere('receiver_id', $user->id);
                })
                ->first();

            if (!$message) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Pesan tidak ditemukan.'
                ], 404);
            }

            $message->deleteForUser($user->id);

            return response()->json([
                'status' => 'success',
                'message' => 'Pesan berhasil dihapus.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat menghapus pesan.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete entire conversation for current user
     */
    public function deleteConversation(Request $request, $userId)
    {
        try {
            $user = Auth::user();

            // Get all messages in conversation
            $messages = PrivateMessage::betweenUsers($user->id, $userId)->get();

            foreach ($messages as $message) {
                $message->deleteForUser($user->id);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Percakapan berhasil dihapus.',
                'data' => [
                    'deleted_count' => $messages->count()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat menghapus percakapan.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get unread messages count
     */
    public function getUnreadCount(Request $request)
    {
        try {
            $user = Auth::user();
            $fromUserId = $request->get('from_user_id');

            $count = PrivateMessage::getUnreadCount($user->id, $fromUserId);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'unread_count' => $count
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil jumlah pesan belum dibaca.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
