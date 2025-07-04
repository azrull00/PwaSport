<?php

namespace App\Services;

use App\Models\User;
use App\Models\Event;
use App\Models\Community;
use App\Models\UserPreferredArea;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class LocationTrackingService
{
    /**
     * Update user's current location
     */
    public function updateUserLocation(User $user, float $latitude, float $longitude)
    {
        try {
            // Store location in cache for 5 minutes
            $cacheKey = "user:{$user->id}:location";
            Cache::put($cacheKey, [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'updated_at' => now()
            ], now()->addMinutes(5));

            // Check if user is within any of their preferred areas
            $this->checkPreferredAreasProximity($user, $latitude, $longitude);

            return [
                'success' => true,
                'message' => 'Location updated successfully'
            ];
        } catch (\Exception $e) {
            Log::error("Failed to update user location: {$e->getMessage()}", [
                'user_id' => $user->id,
                'latitude' => $latitude,
                'longitude' => $longitude
            ]);

            return [
                'success' => false,
                'message' => 'Failed to update location'
            ];
        }
    }

    /**
     * Get user's current location
     */
    public function getUserLocation(User $user)
    {
        $cacheKey = "user:{$user->id}:location";
        return Cache::get($cacheKey);
    }

    /**
     * Check if user is within their preferred areas
     */
    private function checkPreferredAreasProximity(User $user, float $latitude, float $longitude)
    {
        $preferredAreas = UserPreferredArea::where('user_id', $user->id)
            ->where('is_active', true)
            ->get();

        foreach ($preferredAreas as $area) {
            $distance = $this->calculateDistance(
                $latitude,
                $longitude,
                $area->center_latitude,
                $area->center_longitude
            );

            // If user is within the area's radius
            if ($distance <= $area->radius_km) {
                $this->handleAreaEntry($user, $area);
            } else {
                $this->handleAreaExit($user, $area);
            }
        }
    }

    /**
     * Handle user entering a preferred area
     */
    private function handleAreaEntry(User $user, UserPreferredArea $area)
    {
        $cacheKey = "user:{$user->id}:area:{$area->id}:inside";
        
        // If user wasn't already marked as inside this area
        if (!Cache::has($cacheKey)) {
            Cache::put($cacheKey, true, now()->addHours(24));

            // Get nearby events and communities
            $nearbyEvents = $this->getNearbyEvents($area);
            $nearbyCommunities = $this->getNearbyCommunities($area);

            // Notify user if there are any nearby activities
            if ($nearbyEvents->isNotEmpty() || $nearbyCommunities->isNotEmpty()) {
                // TODO: Send notification through your notification system
                Log::info("User {$user->id} entered area {$area->area_name} with activities nearby");
            }
        }
    }

    /**
     * Handle user exiting a preferred area
     */
    private function handleAreaExit(User $user, UserPreferredArea $area)
    {
        $cacheKey = "user:{$user->id}:area:{$area->id}:inside";
        Cache::forget($cacheKey);
    }

    /**
     * Get nearby events for an area
     */
    private function getNearbyEvents(UserPreferredArea $area)
    {
        return Event::where('status', 'published')
            ->where('event_date', '>=', now())
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereRaw("
                ST_Distance_Sphere(
                    point(longitude, latitude),
                    point(?, ?)
                ) <= ? * 1000", [
                    $area->center_longitude,
                    $area->center_latitude,
                    $area->radius_km
                ])
            ->get();
    }

    /**
     * Get nearby communities for an area
     */
    private function getNearbyCommunities(UserPreferredArea $area)
    {
        return Community::where('status', 'active')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereRaw("
                ST_Distance_Sphere(
                    point(longitude, latitude),
                    point(?, ?)
                ) <= ? * 1000", [
                    $area->center_longitude,
                    $area->center_latitude,
                    $area->radius_km
                ])
            ->get();
    }

    /**
     * Calculate distance between two points using Haversine formula
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371; // Earth's radius in kilometers

        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($lonDelta / 2) * sin($lonDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
} 