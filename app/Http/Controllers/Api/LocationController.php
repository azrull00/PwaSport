<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserPreferredArea;
use App\Models\Event;
use App\Models\Community;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class LocationController extends Controller
{
    /**
     * Get user's preferred areas
     */
    public function getUserPreferredAreas()
    {
        try {
            $user = Auth::user();
            $preferredAreas = UserPreferredArea::where('user_id', $user->id)
                ->orderBy('priority_order', 'asc')
                ->get();

            $maxAreas = $this->getMaxAreasAllowed($user);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'preferred_areas' => $preferredAreas,
                    'total_areas' => $preferredAreas->count(),
                    'max_areas_allowed' => $maxAreas
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengambil area favorit: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add preferred area
     */
    public function addPreferredArea(Request $request)
    {
        try {
            $user = Auth::user();
            
            // Check current count
            $currentCount = UserPreferredArea::where('user_id', $user->id)->count();
            $maxAreas = $this->getMaxAreasAllowed($user);

            if ($currentCount >= $maxAreas) {
                return response()->json([
                    'status' => 'error',
                    'message' => $user->subscription_tier === 'premium' 
                        ? 'Sudah mencapai batas maksimal area favorit'
                        : 'Anda hanya dapat memiliki maksimal 3 area favorit. Upgrade ke premium untuk unlimited areas.'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'area_name' => 'required|string|max:100',
                'center_latitude' => 'required|numeric|between:-90,90',
                'center_longitude' => 'required|numeric|between:-180,180',
                'radius_km' => 'required|integer|min:1|max:50',
                'address' => 'nullable|string|max:500',
                'city' => 'nullable|string|max:100',
                'district' => 'nullable|string|max:100',
                'province' => 'nullable|string|max:100',
                'country' => 'nullable|string|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Data tidak valid',
                    'errors' => $validator->errors()
                ], 422);
            }

            $area = UserPreferredArea::create([
                'user_id' => $user->id,
                'area_name' => $request->area_name,
                'center_latitude' => $request->center_latitude,
                'center_longitude' => $request->center_longitude,
                'radius_km' => $request->radius_km,
                'address' => $request->address,
                'city' => $request->city,
                'district' => $request->district,
                'province' => $request->province,
                'country' => $request->country ?? 'Indonesia',
                'priority_order' => $currentCount + 1,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Area favorit berhasil ditambahkan',
                'data' => $area
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menambahkan area favorit: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update preferred area
     */
    public function updatePreferredArea(Request $request, UserPreferredArea $area)
    {
        try {
            $user = Auth::user();

            // Check ownership
            if ($area->user_id !== $user->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Anda tidak memiliki akses ke area ini'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'area_name' => 'sometimes|string|max:100',
                'center_latitude' => 'sometimes|numeric|between:-90,90',
                'center_longitude' => 'sometimes|numeric|between:-180,180',
                'radius_km' => 'sometimes|integer|min:1|max:50',
                'address' => 'nullable|string|max:500',
                'city' => 'nullable|string|max:100',
                'district' => 'nullable|string|max:100',
                'province' => 'nullable|string|max:100',
                'country' => 'nullable|string|max:100',
                'is_active' => 'sometimes|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Data tidak valid',
                    'errors' => $validator->errors()
                ], 422);
            }

            $area->update($request->only([
                'area_name', 'center_latitude', 'center_longitude', 'radius_km',
                'address', 'city', 'district', 'province', 'country', 'is_active'
            ]));

            return response()->json([
                'status' => 'success',
                'message' => 'Area favorit berhasil diperbarui',
                'data' => $area->fresh()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memperbarui area favorit: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete preferred area
     */
    public function deletePreferredArea(UserPreferredArea $area)
    {
        try {
            $user = Auth::user();

            // Check ownership
            if ($area->user_id !== $user->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Anda tidak memiliki akses ke area ini'
                ], 403);
            }

            $area->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Area favorit berhasil dihapus'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menghapus area favorit: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search events by location (200km radius)
     */
    public function searchEventsByLocation(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'latitude' => 'required|numeric|between:-90,90',
                'longitude' => 'required|numeric|between:-180,180',
                'radius_km' => 'nullable|integer|min:1|max:200',
                'sport_id' => 'nullable|exists:sports,id',
                'event_type' => 'nullable|string',
                'skill_level' => 'nullable|string',
                'date_from' => 'nullable|date',
                'date_to' => 'nullable|date|after:date_from',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Data tidak valid',
                    'errors' => $validator->errors()
                ], 422);
            }

            $latitude = $request->latitude;
            $longitude = $request->longitude;
            $radius = $request->radius_km ?? 200; // Default 200km

            // Get events with basic filters first
            $eventsQuery = Event::with(['sport', 'community', 'host'])
                ->where('status', 'published')
                ->where('event_date', '>=', now())
                ->whereNotNull('latitude')
                ->whereNotNull('longitude');

            // Apply additional filters
            if ($request->sport_id) {
                $eventsQuery->where('sport_id', $request->sport_id);
            }

            if ($request->event_type) {
                $eventsQuery->where('event_type', $request->event_type);
            }

            if ($request->skill_level) {
                $eventsQuery->where('skill_level_required', $request->skill_level);
            }

            if ($request->date_from) {
                $eventsQuery->whereDate('event_date', '>=', $request->date_from);
            }

            if ($request->date_to) {
                $eventsQuery->whereDate('event_date', '<=', $request->date_to);
            }

            // Get all events and calculate distances in PHP
            $allEvents = $eventsQuery->get();
            
            $eventsWithDistance = [];
            foreach ($allEvents as $event) {
                $distance = $this->haversineDistance(
                    $latitude, 
                    $longitude, 
                    $event->latitude, 
                    $event->longitude
                );
                
                if ($distance <= $radius) {
                    $eventArray = $event->toArray();
                    $eventArray['distance_km'] = round($distance, 2);
                    $eventsWithDistance[] = $eventArray;
                }
            }

            // Sort by distance
            usort($eventsWithDistance, function($a, $b) {
                return $a['distance_km'] <=> $b['distance_km'];
            });

            // Manual pagination
            $page = $request->input('page', 1);
            $perPage = 20;
            $total = count($eventsWithDistance);
            $offset = ($page - 1) * $perPage;
            $eventsForPage = array_slice($eventsWithDistance, $offset, $perPage);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'events' => $eventsForPage,
                    'pagination' => [
                        'current_page' => $page,
                        'last_page' => ceil($total / $perPage),
                        'per_page' => $perPage,
                        'total' => $total,
                    ],
                    'search_parameters' => [
                        'center_latitude' => $latitude,
                        'center_longitude' => $longitude,
                        'radius_km' => $radius,
                        'filters_applied' => array_filter([
                            'sport_id' => $request->sport_id,
                            'event_type' => $request->event_type,
                            'skill_level' => $request->skill_level,
                            'date_from' => $request->date_from,
                            'date_to' => $request->date_to,
                        ])
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mencari event: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search communities by location
     */
    public function searchCommunitiesByLocation(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'latitude' => 'required|numeric|between:-90,90',
                'longitude' => 'required|numeric|between:-180,180',
                'radius_km' => 'nullable|integer|min:1|max:200',
                'sport_id' => 'nullable|exists:sports,id',
                'community_type' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Data tidak valid',
                    'errors' => $validator->errors()
                ], 422);
            }

            $latitude = $request->latitude;
            $longitude = $request->longitude;
            $radius = $request->radius_km ?? 200;

            // Get communities with basic filters first
            $communitiesQuery = Community::with(['sport', 'host'])
                ->where('is_active', true)
                ->where('is_public', true)
                ->whereNotNull('latitude')
                ->whereNotNull('longitude');

            if ($request->sport_id) {
                $communitiesQuery->where('sport_id', $request->sport_id);
            }

            if ($request->community_type) {
                $communitiesQuery->where('community_type', $request->community_type);
            }

            // Get all communities and calculate distances in PHP
            $allCommunities = $communitiesQuery->get();
            
            $communitiesWithDistance = [];
            foreach ($allCommunities as $community) {
                $distance = $this->haversineDistance(
                    $latitude, 
                    $longitude, 
                    $community->latitude, 
                    $community->longitude
                );
                
                if ($distance <= $radius) {
                    $communityArray = $community->toArray();
                    $communityArray['distance_km'] = round($distance, 2);
                    $communitiesWithDistance[] = $communityArray;
                }
            }

            // Sort by distance
            usort($communitiesWithDistance, function($a, $b) {
                return $a['distance_km'] <=> $b['distance_km'];
            });

            // Manual pagination
            $page = $request->input('page', 1);
            $perPage = 20;
            $total = count($communitiesWithDistance);
            $offset = ($page - 1) * $perPage;
            $communitiesForPage = array_slice($communitiesWithDistance, $offset, $perPage);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'communities' => $communitiesForPage,
                    'pagination' => [
                        'current_page' => $page,
                        'last_page' => ceil($total / $perPage),
                        'per_page' => $perPage,
                        'total' => $total,
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mencari komunitas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate distance between two points
     */
    public function calculateDistance(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'lat1' => 'required|numeric|between:-90,90',
                'lon1' => 'required|numeric|between:-180,180',
                'lat2' => 'required|numeric|between:-90,90',
                'lon2' => 'required|numeric|between:-180,180',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Koordinat tidak valid',
                    'errors' => $validator->errors()
                ], 422);
            }

            $distance = $this->haversineDistance(
                $request->lat1, $request->lon1,
                $request->lat2, $request->lon2
            );

            return response()->json([
                'status' => 'success',
                'data' => [
                    'distance_km' => $distance,
                    'distance_m' => $distance * 1000,
                    'coordinates' => [
                        'from' => ['latitude' => $request->lat1, 'longitude' => $request->lon1],
                        'to' => ['latitude' => $request->lat2, 'longitude' => $request->lon2]
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menghitung jarak: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get events within user's preferred areas
     */
    public function getEventsInPreferredAreas()
    {
        try {
            $user = Auth::user();
            $preferredAreas = UserPreferredArea::where('user_id', $user->id)
                ->active()
                ->get();

            if ($preferredAreas->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'events' => [],
                        'preferred_areas_count' => 0,
                        'total_events_found' => 0,
                        'message' => 'Belum ada area favorit yang diatur'
                    ]
                ]);
            }

            $allEvents = collect();

            foreach ($preferredAreas as $area) {
                // Get all published events
                $events = Event::with(['sport', 'community', 'host'])
                    ->where('status', 'published')
                    ->where('event_date', '>=', now())
                    ->whereNotNull('latitude')
                    ->whereNotNull('longitude')
                    ->get();

                // Filter events within area radius using PHP
                foreach ($events as $event) {
                    $distance = $this->haversineDistance(
                        $area->center_latitude,
                        $area->center_longitude,
                        $event->latitude,
                        $event->longitude
                    );

                    if ($distance <= $area->radius_km) {
                        $eventArray = $event->toArray();
                        $eventArray['distance_km'] = round($distance, 2);
                        $eventArray['preferred_area'] = $area->area_name;
                        $allEvents->push($eventArray);
                    }
                }
            }

            // Remove duplicates based on event ID and sort by date
            $uniqueEvents = $allEvents->unique('id')->sortBy('event_date')->values();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'events' => $uniqueEvents,
                    'preferred_areas_count' => $preferredAreas->count(),
                    'total_events_found' => $uniqueEvents->count()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengambil event di area favorit: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper method to calculate distance using Haversine formula
     */
    private function haversineDistance($lat1, $lon1, $lat2, $lon2)
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
     * Get maximum allowed preferred areas based on subscription
     */
    private function getMaxAreasAllowed($user)
    {
        return $user->subscription_tier === 'premium' ? 999 : 3; // 3 for free, unlimited for premium
    }
}
