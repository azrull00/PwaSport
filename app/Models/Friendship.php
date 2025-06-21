<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Friendship extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'friend_id',
        'status',
        'accepted_at',
        'blocked_at',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
        'blocked_at' => 'datetime',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function friend()
    {
        return $this->belongsTo(User::class, 'friend_id');
    }

    // Scopes
    public function scopeAccepted($query)
    {
        return $query->where('status', 'accepted');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeBlocked($query)
    {
        return $query->where('status', 'blocked');
    }

    // Helper methods
    public function isAccepted()
    {
        return $this->status === 'accepted';
    }

    public function isPending()
    {
        return $this->status === 'pending';
    }

    public function isBlocked()
    {
        return $this->status === 'blocked';
    }

    public function accept()
    {
        $this->update([
            'status' => 'accepted',
            'accepted_at' => Carbon::now(),
        ]);
    }

    public function block()
    {
        $this->update([
            'status' => 'blocked',
            'blocked_at' => Carbon::now(),
        ]);
    }

    // Static methods
    public static function areFriends($userId, $friendId)
    {
        return self::where(function ($query) use ($userId, $friendId) {
            $query->where('user_id', $userId)->where('friend_id', $friendId);
        })->orWhere(function ($query) use ($userId, $friendId) {
            $query->where('user_id', $friendId)->where('friend_id', $userId);
        })->where('status', 'accepted')->exists();
    }

    public static function getFriendshipStatus($userId, $friendId)
    {
        $friendship = self::where(function ($query) use ($userId, $friendId) {
            $query->where('user_id', $userId)->where('friend_id', $friendId);
        })->orWhere(function ($query) use ($userId, $friendId) {
            $query->where('user_id', $friendId)->where('friend_id', $userId);
        })->first();

        return $friendship ? $friendship->status : null;
    }
}
