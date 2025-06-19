<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'community_id',
        'sport_id',
        'host_id',
        'venue_id',
        'title',
        'description',
        'event_date',
        'registration_deadline',
        'max_participants',
        'courts_used',
        'max_courts',
        'skill_level',
        'event_type',
        'status',
        'entry_fee',
        'prizes',
        'event_rules',
        'tags',
        'latitude',
        'longitude',
        'location_name',
        'full_address',
        'auto_queue_enabled',
        'event_settings',
        'skill_level_required',
        'cancellation_reason',
        'is_premium_only',
        'auto_confirm_participants',
    ];

    protected $casts = [
        'event_date' => 'datetime',
        'registration_deadline' => 'datetime',
        'entry_fee' => 'decimal:2',
        'auto_queue_enabled' => 'boolean',
        'event_settings' => 'array',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'is_premium_only' => 'boolean',
        'auto_confirm_participants' => 'boolean',
    ];

    // Relationships
    public function community()
    {
        return $this->belongsTo(Community::class);
    }

    public function sport()
    {
        return $this->belongsTo(Sport::class);
    }

    public function host()
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function participants()
    {
        return $this->hasMany(EventParticipant::class);
    }

    public function confirmedParticipants()
    {
        return $this->hasMany(EventParticipant::class)->where('status', 'confirmed');
    }

    public function waitingParticipants()
    {
        return $this->hasMany(EventParticipant::class)->where('status', 'waiting');
    }

    public function matches()
    {
        return $this->hasMany(MatchHistory::class);
    }

    public function communityRatings()
    {
        return $this->hasMany(CommunityRating::class);
    }

    public function venue()
    {
        return $this->belongsTo(Venue::class);
    }

    // Scopes
    public function scopeUpcoming($query)
    {
        return $query->where('event_date', '>=', Carbon::today());
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['published', 'full', 'ongoing']);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('event_type', $type);
    }

    // Helper methods
    public function isFull()
    {
        return $this->confirmedParticipants()->count() >= $this->max_participants;
    }

    public function getAvailableSlotsAttribute()
    {
        return $this->max_participants - $this->confirmedParticipants()->count();
    }

    public function canUserJoin($user)
    {
        // Check if user is blocked or has blocked the host
        if ($user->hasBlockedUser($this->host_id) || $user->isBlockedByUser($this->host_id)) {
            return false;
        }

        // Check if premium only and user is not premium
        if ($this->is_premium_only && $user->subscription_tier !== 'premium') {
            return false;
        }

        // Check if already registered
        if ($this->participants()->where('user_id', $user->id)->exists()) {
            return false;
        }

        return true;
    }
}
