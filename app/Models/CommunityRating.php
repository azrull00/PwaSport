<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommunityRating extends Model
{
    use HasFactory;

    protected $fillable = [
        'community_id',
        'user_id',
        'event_id',
        'skill_rating',
        'hospitality_rating',
        'review',
    ];

    // Relationships
    public function community()
    {
        return $this->belongsTo(Community::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    // Scopes
    public function scopeForCommunity($query, $communityId)
    {
        return $query->where('community_id', $communityId);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForEvent($query, $eventId)
    {
        return $query->where('event_id', $eventId);
    }

    // Helper methods
    public function getAverageRating()
    {
        return round(($this->skill_rating + $this->hospitality_rating) / 2, 2);
    }
}
