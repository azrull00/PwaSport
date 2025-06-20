<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Venue extends Model
{
    use HasFactory;

    protected $fillable = [
        'sport_id',
        'owner_id',
        'name',
        'address',
        'city',
        'district',
        'province',
        'country',
        'latitude',
        'longitude',
        'total_courts',
        'court_type',
        'hourly_rate',
        'facilities',
        'operating_hours',
        'contact_phone',
        'contact_email',
        'description',
        'rules',
        'photos',
        'is_active',
        'is_verified',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'hourly_rate' => 'decimal:2',
        'facilities' => 'array',
        'operating_hours' => 'array',
        'rules' => 'array',
        'photos' => 'array',
        'average_rating' => 'decimal:2',
        'is_active' => 'boolean',
        'is_verified' => 'boolean',
    ];

    // Relationships
    public function sport()
    {
        return $this->belongsTo(Sport::class);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function events()
    {
        return $this->hasMany(Event::class);
    }

    public function upcomingEvents()
    {
        return $this->hasMany(Event::class)
            ->where('event_date', '>=', now())
            ->where('status', '!=', 'cancelled');
    }

    // public function reviews()
    // {
    //     return $this->hasMany(VenueReview::class);
    // }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    public function scopeBySport($query, $sportId)
    {
        return $query->where('sport_id', $sportId);
    }

    public function scopeByCity($query, $city)
    {
        return $query->where('city', 'like', "%{$city}%");
    }

    // Helper methods
    public function isAvailableOn($date, $courtsNeeded = 1)
    {
        $conflictingEvents = $this->events()
            ->whereDate('event_date', $date)
            ->where('status', '!=', 'cancelled')
            ->sum('courts_used');

        return ($this->total_courts - $conflictingEvents) >= $courtsNeeded;
    }

    public function getAvailableCourtsOn($date)
    {
        $usedCourts = $this->events()
            ->whereDate('event_date', $date)
            ->where('status', '!=', 'cancelled')
            ->sum('courts_used');

        return $this->total_courts - $usedCourts;
    }

    public function updateAverageRating()
    {
        // Note: VenueReview model not implemented yet
        $avgRating = 0;
        $totalReviews = 0;

        $this->update([
            'average_rating' => round($avgRating, 2),
            'total_reviews' => $totalReviews,
        ]);
    }

    public function getDistanceFrom($latitude, $longitude)
    {
        // Haversine formula for distance calculation
        $earthRadius = 6371; // Earth's radius in kilometers

        $latFrom = deg2rad($this->latitude);
        $lonFrom = deg2rad($this->longitude);
        $latTo = deg2rad($latitude);
        $lonTo = deg2rad($longitude);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos($latFrom) * cos($latTo) *
             sin($lonDelta / 2) * sin($lonDelta / 2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadius * $c, 2);
    }
}
