<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PlayerRating extends Model
{
    use HasFactory;

    protected $fillable = [
        'rating_user_id',
        'rated_user_id',
        'event_id',
        'skill_rating',
        'sportsmanship_rating',
        'punctuality_rating',
        'comment',
        'is_disputed',
        'dispute_reason',
        'dispute_type',
        'disputed_at',
    ];

    protected $casts = [
        'skill_rating' => 'decimal:1',
        'sportsmanship_rating' => 'decimal:1',
        'punctuality_rating' => 'decimal:1',
        'is_disputed' => 'boolean',
        'disputed_at' => 'datetime',
    ];

    // Relationships
    public function ratingUser()
    {
        return $this->belongsTo(User::class, 'rating_user_id');
    }

    public function ratedUser()
    {
        return $this->belongsTo(User::class, 'rated_user_id');
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    // Helper methods
    public function getOverallRating()
    {
        return round(($this->skill_rating + $this->sportsmanship_rating + $this->punctuality_rating) / 3, 1);
    }

    public function getRatingBreakdown()
    {
        return [
            'skill' => $this->skill_rating,
            'sportsmanship' => $this->sportsmanship_rating,
            'punctuality' => $this->punctuality_rating,
            'overall' => $this->getOverallRating(),
        ];
    }

    // Scopes
    public function scopeNotDisputed($query)
    {
        return $query->where('is_disputed', false);
    }

    public function scopeDisputed($query)
    {
        return $query->where('is_disputed', true);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('rated_user_id', $userId);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('rating_user_id', $userId);
    }

    public function scopeHighRating($query, $threshold = 4.0)
    {
        return $query->whereRaw('(skill_rating + sportsmanship_rating + punctuality_rating) / 3 >= ?', [$threshold]);
    }

    public function scopeLowRating($query, $threshold = 2.0)
    {
        return $query->whereRaw('(skill_rating + sportsmanship_rating + punctuality_rating) / 3 <= ?', [$threshold]);
    }
}
