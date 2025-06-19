<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserPreferredArea extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'area_name',
        'center_latitude',
        'center_longitude',
        'radius_km',
        'address',
        'city',
        'district',
        'province',
        'country',
        'is_active',
        'priority_order',
    ];

    protected $casts = [
        'center_latitude' => 'decimal:8',
        'center_longitude' => 'decimal:8',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeOrderedByPriority($query)
    {
        return $query->orderBy('priority_order', 'asc');
    }

    // Helper methods
    /**
     * Calculate distance from a point to this preferred area center
     */
    public function distanceFrom($latitude, $longitude)
    {
        return $this->calculateDistance(
            $this->center_latitude,
            $this->center_longitude,
            $latitude,
            $longitude
        );
    }

    /**
     * Check if a point is within this preferred area
     */
    public function containsPoint($latitude, $longitude)
    {
        $distance = $this->distanceFrom($latitude, $longitude);
        return $distance <= $this->radius_km;
    }

    /**
     * Calculate distance between two coordinates using Haversine formula
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371; // Earth's radius in kilometers

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon/2) * sin($dLon/2);

        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        $distance = $earthRadius * $c;

        return round($distance, 2);
    }

    /**
     * Get formatted location string
     */
    public function getLocationStringAttribute()
    {
        $parts = array_filter([$this->district, $this->city, $this->province]);
        return implode(', ', $parts);
    }
}
