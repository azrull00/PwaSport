<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserSportRating extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'sport_id',
        'mmr',
        'level',
        'matches_played',
        'wins',
        'losses',
        'win_rate',
        'last_match_at'
    ];

    protected $casts = [
        'last_match_at' => 'datetime',
        'win_rate' => 'decimal:2'
    ];

    /**
     * Get the user that owns the rating.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the sport for this rating.
     */
    public function sport()
    {
        return $this->belongsTo(Sport::class);
    }

    /**
     * Calculate win percentage
     */
    public function getWinPercentageAttribute()
    {
        if ($this->matches_played == 0) {
            return 0;
        }
        
        return round(($this->wins / $this->matches_played) * 100, 1);
    }

    /**
     * Get skill level based on MMR
     */
    public function getSkillLevelAttribute()
    {
        if ($this->mmr >= 1800) return 'Expert';
        if ($this->mmr >= 1500) return 'Advanced';
        if ($this->mmr >= 1200) return 'Intermediate';
        if ($this->mmr >= 900) return 'Beginner';
        return 'Novice';
    }

    /**
     * Get skill rating (alias for MMR for backward compatibility)
     */
    public function getSkillRatingAttribute()
    {
        return $this->mmr;
    }

    /**
     * Get matches won (alias for wins for backward compatibility)
     */
    public function getMatchesWonAttribute()
    {
        return $this->wins;
    }
}
