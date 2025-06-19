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
        'community_type',
        'location_name',
        'city',
        'district',
        'province',
        'country',
        'latitude',
        'longitude',
        'venue_name',
        'venue_address',
        'max_members',
        'member_count',
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
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'average_skill_rating' => 'decimal:2',
        'hospitality_rating' => 'decimal:2',
        'total_ratings' => 'decimal:2',
        'is_active' => 'boolean',
        'is_public' => 'boolean',
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
    public function updateAverageRating()
    {
        $this->average_skill_rating = $this->ratings()->avg('skill_rating') ?? 0;
        $this->hospitality_rating = $this->ratings()->avg('hospitality_rating') ?? 0;
        $this->save();
    }
}
