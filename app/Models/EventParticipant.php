<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'user_id',
        'status',
        'queue_position',
        'is_premium_protected',
        'registered_at',
        'checked_in_at',
        'cancellation_reason',
        'credit_score_penalty',
    ];

    protected $casts = [
        'is_premium_protected' => 'boolean',
        'registered_at' => 'datetime',
        'checked_in_at' => 'datetime',
    ];

    // Relationships
    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }

    public function scopeWaiting($query)
    {
        return $query->where('status', 'waiting');
    }

    public function scopeCheckedIn($query)
    {
        return $query->where('status', 'checked_in');
    }

    public function scopeRegistered($query)
    {
        return $query->where('status', 'registered');
    }

    // Helper methods
    public function isConfirmed()
    {
        return $this->status === 'confirmed';
    }

    public function isWaiting()
    {
        return $this->status === 'waiting';
    }

    public function isCheckedIn()
    {
        return $this->status === 'checked_in';
    }

    public function canCheckIn()
    {
        return in_array($this->status, ['confirmed', 'registered']);
    }
}
