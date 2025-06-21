<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Friendship;
use App\Models\FriendRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class FriendController extends Controller
{
    /**
     * Get user's friends list
     */
    public function getFriends(Request $request)
    {
        try {
            $user = Auth::user();
            $perPage = $request->get('per_page', 20);

            // Get all friends with their profiles
            $friends = $user->friends();
            
            // Convert collection to paginated result
            $friendsArray = $friends->map(function ($friend) use ($user) {
                return [
                    'id' => $friend->id,
                    'name' => $friend->name,
                    'email' => $friend->email,
                    'profile' => $friend->profile,
                    'subscription_tier' => $friend->subscription_tier,
                    'friendship_date' => $this->getFriendshipDate($user->id, $friend->id),
                    'is_online' => false, // TODO: Implement online status
                    'mutual_friends_count' => $this->getMutualFriendsCount($user->id, $friend->id),
                ];
            });

            // Manual pagination
            $currentPage = $request->get('page', 1);
            $offset = ($currentPage - 1) * $perPage;
            $paginatedFriends = $friendsArray->slice($offset, $perPage)->values();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'friends' => $paginatedFriends,
                    'total' => $friendsArray->count(),
                    'per_page' => $perPage,
                    'current_page' => $currentPage,
                    'total_pages' => ceil($friendsArray->count() / $perPage),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil daftar teman.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send friend request
     */
    public function sendFriendRequest(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|integer|exists:users,id',
                'message' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Data tidak valid.',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = Auth::user();
            $targetUserId = $request->user_id;

            // Check if user can send friend request
            if (!$user->canSendFriendRequestTo($targetUserId)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tidak dapat mengirim permintaan pertemanan.'
                ], 403);
            }

            // Create friend request
            $friendRequest = FriendRequest::create([
                'sender_id' => $user->id,
                'receiver_id' => $targetUserId,
                'message' => $request->message,
                'status' => 'pending',
            ]);

            // Load relationships
            $friendRequest->load(['sender.profile', 'receiver.profile']);

            return response()->json([
                'status' => 'success',
                'message' => 'Permintaan pertemanan berhasil dikirim!',
                'data' => [
                    'friend_request' => $friendRequest
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengirim permintaan pertemanan.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get pending friend requests (received)
     */
    public function getPendingRequests(Request $request)
    {
        try {
            $user = Auth::user();
            $perPage = $request->get('per_page', 20);

            $requests = $user->pendingFriendRequests()
                ->with(['sender.profile'])
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'requests' => $requests
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil permintaan pertemanan.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get sent friend requests
     */
    public function getSentRequests(Request $request)
    {
        try {
            $user = Auth::user();
            $perPage = $request->get('per_page', 20);

            $requests = $user->sentFriendRequests()
                ->with(['receiver.profile'])
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'requests' => $requests
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil permintaan yang dikirim.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Accept friend request
     */
    public function acceptFriendRequest(Request $request, $requestId)
    {
        try {
            $user = Auth::user();
            
            $friendRequest = FriendRequest::where('id', $requestId)
                ->where('receiver_id', $user->id)
                ->where('status', 'pending')
                ->first();

            if (!$friendRequest) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Permintaan pertemanan tidak ditemukan.'
                ], 404);
            }

            DB::transaction(function () use ($friendRequest) {
                $friendRequest->accept();
            });

            // Load relationships
            $friendRequest->load(['sender.profile', 'receiver.profile']);

            return response()->json([
                'status' => 'success',
                'message' => 'Permintaan pertemanan diterima!',
                'data' => [
                    'friend_request' => $friendRequest
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat menerima permintaan pertemanan.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject friend request
     */
    public function rejectFriendRequest(Request $request, $requestId)
    {
        try {
            $user = Auth::user();
            
            $friendRequest = FriendRequest::where('id', $requestId)
                ->where('receiver_id', $user->id)
                ->where('status', 'pending')
                ->first();

            if (!$friendRequest) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Permintaan pertemanan tidak ditemukan.'
                ], 404);
            }

            $friendRequest->reject();

            return response()->json([
                'status' => 'success',
                'message' => 'Permintaan pertemanan ditolak.',
                'data' => [
                    'friend_request' => $friendRequest
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat menolak permintaan pertemanan.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel sent friend request
     */
    public function cancelFriendRequest(Request $request, $requestId)
    {
        try {
            $user = Auth::user();
            
            $friendRequest = FriendRequest::where('id', $requestId)
                ->where('sender_id', $user->id)
                ->where('status', 'pending')
                ->first();

            if (!$friendRequest) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Permintaan pertemanan tidak ditemukan.'
                ], 404);
            }

            $friendRequest->cancel();

            return response()->json([
                'status' => 'success',
                'message' => 'Permintaan pertemanan dibatalkan.',
                'data' => [
                    'friend_request' => $friendRequest
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat membatalkan permintaan pertemanan.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove friend
     */
    public function removeFriend(Request $request, $friendId)
    {
        try {
            $user = Auth::user();

            if (!$user->isFriendsWith($friendId)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Pengguna bukan teman Anda.'
                ], 404);
            }

            DB::transaction(function () use ($user, $friendId) {
                // Remove both friendship records
                Friendship::where(function ($query) use ($user, $friendId) {
                    $query->where('user_id', $user->id)->where('friend_id', $friendId);
                })->orWhere(function ($query) use ($user, $friendId) {
                    $query->where('user_id', $friendId)->where('friend_id', $user->id);
                })->delete();
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Teman berhasil dihapus.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat menghapus teman.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get friendship status with another user
     */
    public function getFriendshipStatus(Request $request, $userId)
    {
        try {
            $user = Auth::user();

            $friendshipStatus = $user->getFriendshipStatusWith($userId);
            $requestStatus = $user->getFriendRequestStatusWith($userId);
            $canSendRequest = $user->canSendFriendRequestTo($userId);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'friendship_status' => $friendshipStatus,
                    'request_status' => $requestStatus,
                    'can_send_request' => $canSendRequest,
                    'is_friends' => $user->isFriendsWith($userId),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil status pertemanan.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search users for friend requests
     */
    public function searchUsers(Request $request)
    {
        try {
            $user = Auth::user();
            $query = $request->get('query', '');
            $perPage = $request->get('per_page', 20);

            if (strlen($query) < 2) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Query pencarian minimal 2 karakter.'
                ], 422);
            }

            $users = User::where('id', '!=', $user->id)
                ->where(function ($q) use ($query) {
                    $q->where('name', 'LIKE', "%{$query}%")
                      ->orWhere('email', 'LIKE', "%{$query}%");
                })
                ->with(['profile'])
                ->paginate($perPage);

            // Add friendship status for each user
            $users->getCollection()->transform(function ($foundUser) use ($user) {
                $foundUser->friendship_status = $user->getFriendshipStatusWith($foundUser->id);
                $foundUser->request_status = $user->getFriendRequestStatusWith($foundUser->id);
                $foundUser->can_send_request = $user->canSendFriendRequestTo($foundUser->id);
                $foundUser->is_friends = $user->isFriendsWith($foundUser->id);
                return $foundUser;
            });

            return response()->json([
                'status' => 'success',
                'data' => [
                    'users' => $users
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mencari pengguna.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper method to get friendship date
     */
    private function getFriendshipDate($userId, $friendId)
    {
        $friendship = Friendship::where(function ($query) use ($userId, $friendId) {
            $query->where('user_id', $userId)->where('friend_id', $friendId);
        })->orWhere(function ($query) use ($userId, $friendId) {
            $query->where('user_id', $friendId)->where('friend_id', $userId);
        })->where('status', 'accepted')->first();

        return $friendship ? $friendship->accepted_at : null;
    }

    /**
     * Helper method to get mutual friends count
     */
    private function getMutualFriendsCount($userId, $friendId)
    {
        $userFriends = User::find($userId)->friends()->pluck('id');
        $friendFriends = User::find($friendId)->friends()->pluck('id');
        
        return $userFriends->intersect($friendFriends)->count();
    }
}
