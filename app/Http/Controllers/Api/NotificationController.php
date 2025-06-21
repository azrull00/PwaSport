<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Get user notifications with filtering and pagination
     */
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            
            $query = Notification::where('user_id', $user->id);

            // Filter by type
            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            // Filter by read status
            if ($request->has('read_status')) {
                if ($request->boolean('read_status')) {
                    $query->whereNotNull('read_at');
                } else {
                    $query->whereNull('read_at');
                }
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
            $perPage = $request->get('per_page', 20);
            $notifications = $query->paginate($perPage);
            
            // Get unread count
            $unreadCount = Notification::where('user_id', $user->id)
                ->whereNull('read_at')
                ->count();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'notifications' => $notifications->items(),
                    'unread_count' => $unreadCount,
                    'total' => $notifications->total(),
                    'per_page' => $notifications->perPage(),
                    'current_page' => $notifications->currentPage(),
                    'total_pages' => $notifications->lastPage(),
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in NotificationController index: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil notifikasi.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get specific notification
     */
    public function show(Notification $notification)
    {
        try {
            $user = Auth::user();

            // Check if notification belongs to user
            if ($notification->user_id !== $user->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Notifikasi tidak ditemukan.'
                ], 404);
            }

            // Mark as read if not already read
            if (!$notification->read_at) {
                $notification->update(['read_at' => now()]);
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'notification' => $notification
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil detail notifikasi.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(Notification $notification)
    {
        try {
            $user = Auth::user();

            // Check if notification belongs to user
            if ($notification->user_id !== $user->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Notifikasi tidak ditemukan.'
                ], 404);
            }

            $notification->update(['read_at' => now()]);

            return response()->json([
                'status' => 'success',
                'message' => 'Notifikasi berhasil ditandai sebagai dibaca.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat menandai notifikasi.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark notification as unread
     */
    public function markAsUnread(Notification $notification)
    {
        try {
            $user = Auth::user();

            // Check if notification belongs to user
            if ($notification->user_id !== $user->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Notifikasi tidak ditemukan.'
                ], 404);
            }

            $notification->update(['read_at' => null]);

            return response()->json([
                'status' => 'success',
                'message' => 'Notifikasi berhasil ditandai sebagai belum dibaca.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat menandai notifikasi.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead()
    {
        try {
            $user = Auth::user();

            $user->unreadNotifications()->update(['read_at' => now()]);

            return response()->json([
                'status' => 'success',
                'message' => 'Semua notifikasi berhasil ditandai sebagai dibaca.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat menandai semua notifikasi.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete notification
     */
    public function destroy(Notification $notification)
    {
        try {
            $user = Auth::user();

            // Check if notification belongs to user
            if ($notification->user_id !== $user->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Notifikasi tidak ditemukan.'
                ], 404);
            }

            $notification->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Notifikasi berhasil dihapus.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat menghapus notifikasi.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete all read notifications
     */
    public function deleteAllRead()
    {
        try {
            $user = Auth::user();

            $user->readNotifications()->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Semua notifikasi yang sudah dibaca berhasil dihapus.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat menghapus notifikasi.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get notification statistics
     */
    public function getStats()
    {
        try {
            $user = Auth::user();

            $stats = [
                'total_notifications' => $user->notifications()->count(),
                'unread_notifications' => $user->unreadNotifications()->count(),
                'read_notifications' => $user->readNotifications()->count(),
                'notifications_by_type' => $user->notifications()
                    ->selectRaw('type, COUNT(*) as count')
                    ->groupBy('type')
                    ->pluck('count', 'type'),
                'recent_notifications' => $user->notifications()
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
                'message' => 'Terjadi kesalahan saat mengambil statistik notifikasi.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update notification preferences
     */
    public function updatePreferences(Request $request)
    {
        try {
            $user = Auth::user();

            $request->validate([
                'email_notifications' => 'sometimes|boolean',
                'event_reminders' => 'sometimes|boolean',
                'match_results' => 'sometimes|boolean',
                'credit_score_changes' => 'sometimes|boolean',
                'waitlist_promotions' => 'sometimes|boolean',
                'new_events' => 'sometimes|boolean',
                'community_invites' => 'sometimes|boolean',
                'marketing_emails' => 'sometimes|boolean',
            ]);

            // Update user profile with notification preferences
            $user->profile()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'notification_preferences' => json_encode($request->only([
                        'email_notifications', 'event_reminders', 'match_results',
                        'credit_score_changes', 'waitlist_promotions', 'new_events',
                        'community_invites', 'marketing_emails'
                    ]))
                ]
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Preferensi notifikasi berhasil diperbarui!'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat memperbarui preferensi.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get notification preferences
     */
    public function getPreferences()
    {
        try {
            $user = Auth::user();
            
            $preferences = $user->profile?->notification_preferences 
                ? json_decode($user->profile->notification_preferences, true)
                : [
                    'email_notifications' => true,
                    'event_reminders' => true,
                    'match_results' => true,
                    'credit_score_changes' => true,
                    'waitlist_promotions' => true,
                    'new_events' => true,
                    'community_invites' => true,
                    'marketing_emails' => false,
                ];

            return response()->json([
                'status' => 'success',
                'data' => [
                    'preferences' => $preferences
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil preferensi notifikasi.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
