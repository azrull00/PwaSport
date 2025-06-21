<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class PrivateMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'sender_id',
        'receiver_id',
        'message',
        'message_type',
        'file_url',
        'file_name',
        'file_size',
        'read_at',
        'is_deleted_by_sender',
        'is_deleted_by_receiver',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'is_deleted_by_sender' => 'boolean',
        'is_deleted_by_receiver' => 'boolean',
    ];

    // Relationships
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    // Scopes
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function scopeRead($query)
    {
        return $query->whereNotNull('read_at');
    }

    public function scopeNotDeletedBy($query, $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where(function ($subQ) use ($userId) {
                $subQ->where('sender_id', $userId)
                     ->where('is_deleted_by_sender', false);
            })->orWhere(function ($subQ) use ($userId) {
                $subQ->where('receiver_id', $userId)
                     ->where('is_deleted_by_receiver', false);
            });
        });
    }

    public function scopeBetweenUsers($query, $user1Id, $user2Id)
    {
        return $query->where(function ($q) use ($user1Id, $user2Id) {
            $q->where(function ($subQ) use ($user1Id, $user2Id) {
                $subQ->where('sender_id', $user1Id)
                     ->where('receiver_id', $user2Id);
            })->orWhere(function ($subQ) use ($user1Id, $user2Id) {
                $subQ->where('sender_id', $user2Id)
                     ->where('receiver_id', $user1Id);
            });
        });
    }

    // Helper methods
    public function isRead()
    {
        return !is_null($this->read_at);
    }

    public function markAsRead()
    {
        $this->update(['read_at' => Carbon::now()]);
    }

    public function deleteForUser($userId)
    {
        if ($this->sender_id == $userId) {
            $this->update(['is_deleted_by_sender' => true]);
        } elseif ($this->receiver_id == $userId) {
            $this->update(['is_deleted_by_receiver' => true]);
        }

        // If both users deleted, actually delete the record
        if ($this->is_deleted_by_sender && $this->is_deleted_by_receiver) {
            $this->delete();
        }
    }

    public function isDeletedBy($userId)
    {
        if ($this->sender_id == $userId) {
            return $this->is_deleted_by_sender;
        } elseif ($this->receiver_id == $userId) {
            return $this->is_deleted_by_receiver;
        }
        return false;
    }

    public function getOtherUser($userId)
    {
        if ($this->sender_id == $userId) {
            return $this->receiver;
        } elseif ($this->receiver_id == $userId) {
            return $this->sender;
        }
        return null;
    }

    // Static methods
    public static function getConversation($user1Id, $user2Id, $limit = 50)
    {
        return self::betweenUsers($user1Id, $user2Id)
            ->notDeletedBy($user1Id)
            ->with(['sender.profile', 'receiver.profile'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();
    }

    public static function getUnreadCount($userId, $fromUserId = null)
    {
        $query = self::where('receiver_id', $userId)
            ->unread()
            ->where('is_deleted_by_receiver', false);

        if ($fromUserId) {
            $query->where('sender_id', $fromUserId);
        }

        return $query->count();
    }

    public static function markAllAsRead($userId, $fromUserId)
    {
        return self::where('receiver_id', $userId)
            ->where('sender_id', $fromUserId)
            ->unread()
            ->update(['read_at' => Carbon::now()]);
    }

    public static function getLastMessageBetween($user1Id, $user2Id)
    {
        return self::betweenUsers($user1Id, $user2Id)
            ->where(function ($q) use ($user1Id) {
                $q->where(function ($subQ) use ($user1Id) {
                    $subQ->where('sender_id', $user1Id)
                         ->where('is_deleted_by_sender', false);
                })->orWhere(function ($subQ) use ($user1Id) {
                    $subQ->where('receiver_id', $user1Id)
                         ->where('is_deleted_by_receiver', false);
                });
            })
            ->latest()
            ->first();
    }
}
