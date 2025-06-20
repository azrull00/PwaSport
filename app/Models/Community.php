<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Community extends Model
{
    use HasFactory;

    protected $fillable = [
        'sport_id',
        'host_user_id',
        'name',
        'description',
        'icon_url',
        'has_icon',
        'community_type',
        'skill_level_focus',
        'location_name',
        'city',
        'venue_city',
        'district',
        'province',
        'country',
        'latitude',
        'longitude',
        'venue_name',
        'venue_address',
        'max_members',
        'member_count',
        'membership_fee',
        'regular_schedule',
        'total_ratings',
        'average_skill_rating',
        'hospitality_rating',
        'total_events',
        'contact_phone',
        'contact_email',
        'contact_info',
        'rules',
        'is_active',
        'is_public',
        'is_premium_required',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'membership_fee' => 'decimal:2',
        'average_skill_rating' => 'decimal:2',
        'hospitality_rating' => 'decimal:2',
        'total_ratings' => 'decimal:2',
        'is_active' => 'boolean',
        'is_public' => 'boolean',
        'is_premium_required' => 'boolean',
        'has_icon' => 'boolean',
        'rules' => 'array',
        'contact_info' => 'array',
    ];

    // Relationships
    public function sport()
    {
        return $this->belongsTo(Sport::class);
    }

    public function host()
    {
        return $this->belongsTo(User::class, 'host_user_id');
    }

    public function events()
    {
        return $this->hasMany(Event::class);
    }

    public function ratings()
    {
        return $this->hasMany(CommunityRating::class);
    }

    public function members()
    {
        return $this->hasMany(CommunityMember::class);
    }

    public function activeMembers()
    {
        return $this->hasMany(CommunityMember::class)->active();
    }

    public function messages()
    {
        return $this->hasMany(CommunityMessage::class);
    }

    public function recentMessages()
    {
        return $this->hasMany(CommunityMessage::class)->notDeleted()->recent();
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('community_type', $type);
    }

    // Helper methods
    public function isMember($userId)
    {
        return $this->members()->where('user_id', $userId)->active()->exists();
    }

    public function isHost($userId)
    {
        return $this->host_user_id === $userId;
    }

    public function canUserAccess($userId)
    {
        return $this->isHost($userId) || $this->isMember($userId);
    }

    public function addMember($userId, $role = 'member', $status = 'active')
    {
        return $this->members()->create([
            'user_id' => $userId,
            'role' => $role,
            'status' => $status,
            'joined_at' => now()
        ]);
    }

    public function removeMember($userId)
    {
        return $this->members()->where('user_id', $userId)->delete();
    }

    public function updateMemberCount()
    {
        $this->update(['member_count' => $this->activeMembers()->count()]);
    }

    public function updateAverageRating()
    {
        $this->average_skill_rating = $this->ratings()->avg('skill_rating') ?? 0;
        $this->hospitality_rating = $this->ratings()->avg('hospitality_rating') ?? 0;
        $this->save();
    }

    // Image accessors
    public function getIconUrlAttribute($value)
    {
        if (!$value) {
            return null;
        }
        
        // If already a full URL, return as is
        if (str_starts_with($value, 'http')) {
            return $value;
        }
        
        // Otherwise prepend storage URL
        return asset('storage/' . $value);
    }

    public function getHasIconAttribute()
    {
        return !empty($this->attributes['icon_url']);
    }
}
