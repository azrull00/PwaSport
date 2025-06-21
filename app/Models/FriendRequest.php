<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class FriendRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'sender_id',
        'receiver_id',
        'status',
        'message',
        'responded_at',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
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
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeAccepted($query)
    {
        return $query->where('status', 'accepted');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    // Helper methods
    public function isPending()
    {
        return $this->status === 'pending';
    }

    public function isAccepted()
    {
        return $this->status === 'accepted';
    }

    public function isRejected()
    {
        return $this->status === 'rejected';
    }

    public function isCancelled()
    {
        return $this->status === 'cancelled';
    }

    public function accept()
    {
        $this->update([
            'status' => 'accepted',
            'responded_at' => Carbon::now(),
        ]);

        // Create mutual friendship records
        Friendship::create([
            'user_id' => $this->sender_id,
            'friend_id' => $this->receiver_id,
            'status' => 'accepted',
            'accepted_at' => Carbon::now(),
        ]);

        Friendship::create([
            'user_id' => $this->receiver_id,
            'friend_id' => $this->sender_id,
            'status' => 'accepted',
            'accepted_at' => Carbon::now(),
        ]);
    }

    public function reject()
    {
        $this->update([
            'status' => 'rejected',
            'responded_at' => Carbon::now(),
        ]);
    }

    public function cancel()
    {
        $this->update([
            'status' => 'cancelled',
            'responded_at' => Carbon::now(),
        ]);
    }

    // Static methods
    public static function hasPendingRequest($senderId, $receiverId)
    {
        return self::where('sender_id', $senderId)
            ->where('receiver_id', $receiverId)
            ->where('status', 'pending')
            ->exists();
    }

    public static function getRequestStatus($senderId, $receiverId)
    {
        $request = self::where('sender_id', $senderId)
            ->where('receiver_id', $receiverId)
            ->first();

        return $request ? $request->status : null;
    }
}
