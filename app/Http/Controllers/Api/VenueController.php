<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Venue;
use App\Models\Event;
use App\Models\Community;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class VenueController extends Controller
{
    /**
     * Get all venues with filtering
     */
    public function index(Request $request)
    {
        try {
            $query = Venue::query();

            // Filter by city
            if ($request->has('city')) {
                $query->where('city', 'like', '%' . $request->city . '%');
            }

            // Filter by sport
            if ($request->has('sport_id')) {
                $query->where('sport_id', $request->sport_id);
            }

            // Filter by availability (not fully booked)
            if ($request->get('available_only', false)) {
                $query->where('total_courts', '>', 0);
            }

            // Search by name or location
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                      ->orWhere('address', 'like', '%' . $search . '%')
                      ->orWhere('district', 'like', '%' . $search . '%');
                });
            }

            // Distance-based search (simplified for SQLite compatibility)
            if ($request->has('latitude') && $request->has('longitude')) {
                $lat = (float) $request->latitude;
                $lng = (float) $request->longitude;
                $radius = (float) $request->get('radius_km', 10);

                // Simple bounding box approach for SQLite compatibility
                // This is less accurate but works for testing
                $latRange = $radius / 111.32; // 1 degree ≈ 111.32 km
                $lngRange = $radius / (111.32 * cos(deg2rad($lat)));

                $query->whereBetween('latitude', [$lat - $latRange, $lat + $latRange])
                      ->whereBetween('longitude', [$lng - $lngRange, $lng + $lngRange]);
            }

            $venues = $query->with(['sport'])
                ->orderBy('name')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'status' => 'success',
                'data' => [
                    'venues' => $venues->items(),
                    'pagination' => [
                        'current_page' => $venues->currentPage(),
                        'last_page' => $venues->lastPage(),
                        'per_page' => $venues->perPage(),
                        'total' => $venues->total(),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil data venue.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get venue details
     */
    public function show($id)
    {
        try {
            $venue = Venue::with(['sport', 'upcomingEvents'])
                ->findOrFail($id);

            // Calculate venue availability
            $availability = $this->calculateVenueAvailability($venue);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'venue' => $venue,
                    'availability' => $availability,
                    'statistics' => [
                        'total_events_hosted' => $venue->events()->count(),
                        'upcoming_events' => $venue->upcomingEvents()->count(),
                        'average_rating' => 0, // TODO: Implement venue reviews
                        'total_reviews' => 0, // TODO: Implement venue reviews
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Venue tidak ditemukan.',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Create new venue (host/admin only)
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'sport_id' => 'required|exists:sports,id',
                'name' => 'required|string|max:100',
                'address' => 'required|string|max:255',
                'city' => 'required|string|max:50',
                'district' => 'nullable|string|max:50',
                'province' => 'required|string|max:50',
                'latitude' => 'required|numeric|between:-90,90',
                'longitude' => 'required|numeric|between:-180,180',
                'total_courts' => 'required|integer|min:1|max:20',
                'court_type' => 'required|string|in:indoor,outdoor,covered',
                'hourly_rate' => 'nullable|numeric|min:0',
                'facilities' => 'nullable|array',
                'operating_hours' => 'nullable|array',
                'contact_phone' => 'nullable|string|max:20',
                'contact_email' => 'nullable|email|max:100',
                'description' => 'nullable|string|max:1000',
                'rules' => 'nullable|array',
                'photos' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = Auth::user();

            // Only hosts and admins can create venues
            if (!$user->hasRole('host') && !$user->hasRole('admin')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Hanya host dan admin yang dapat menambah venue.'
                ], 403);
            }

            $venue = Venue::create(array_merge($request->all(), [
                'owner_id' => $user->id,
                'is_active' => true,
                'is_verified' => $user->hasRole('admin'), // Auto-verify if created by admin
            ]));

            return response()->json([
                'status' => 'success',
                'message' => 'Venue berhasil ditambahkan!',
                'data' => [
                    'venue' => $venue->load('sport')
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat menambah venue.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update venue (owner/admin only)
     */
    public function update(Request $request, $id)
    {
        try {
            $venue = Venue::findOrFail($id);
            $user = Auth::user();

            // Only owner or admin can update
            if ($venue->owner_id !== $user->id && !$user->hasRole('admin')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Anda tidak memiliki izin untuk mengubah venue ini.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'string|max:100',
                'address' => 'string|max:255',
                'city' => 'string|max:50',
                'district' => 'nullable|string|max:50',
                'province' => 'string|max:50',
                'latitude' => 'numeric|between:-90,90',
                'longitude' => 'numeric|between:-180,180',
                'total_courts' => 'integer|min:1|max:20',
                'court_type' => 'string|in:indoor,outdoor,covered',
                'hourly_rate' => 'nullable|numeric|min:0',
                'facilities' => 'nullable|array',
                'operating_hours' => 'nullable|array',
                'contact_phone' => 'nullable|string|max:20',
                'contact_email' => 'nullable|email|max:100',
                'description' => 'nullable|string|max:1000',
                'rules' => 'nullable|array',
                'photos' => 'nullable|array',
                'is_active' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $venue->update($request->only([
                'name', 'address', 'city', 'district', 'province',
                'latitude', 'longitude', 'total_courts', 'court_type',
                'hourly_rate', 'facilities', 'operating_hours',
                'contact_phone', 'contact_email', 'description', 'rules',
                'photos', 'is_active'
            ]));

            return response()->json([
                'status' => 'success',
                'message' => 'Venue berhasil diperbarui!',
                'data' => [
                    'venue' => $venue->load('sport')
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat memperbarui venue.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete venue (owner/admin only)
     */
    public function destroy($id)
    {
        try {
            $venue = Venue::findOrFail($id);
            $user = Auth::user();

            // Only owner or admin can delete
            if ($venue->owner_id !== $user->id && !$user->hasRole('admin')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Anda tidak memiliki izin untuk menghapus venue ini.'
                ], 403);
            }

            // Check if venue has upcoming events
            $upcomingEvents = $venue->upcomingEvents()->count();
            if ($upcomingEvents > 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Venue tidak dapat dihapus karena masih memiliki {$upcomingEvents} event yang akan datang."
                ], 422);
            }

            $venue->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Venue berhasil dihapus!'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat menghapus venue.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check venue availability for specific date/time
     */
    public function checkAvailability(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'event_date' => 'required|date|after:now',
                'duration_hours' => 'required|integer|min:1|max:8',
                'courts_needed' => 'nullable|integer|min:1',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $venue = Venue::findOrFail($id);
            $eventDate = $request->event_date;
            $durationHours = $request->duration_hours;
            $courtsNeeded = $request->get('courts_needed', 1);

            // Check for conflicting events
            $conflictingEvents = Event::where('venue_id', $venue->id)
                ->whereDate('event_date', '=', $eventDate)
                ->where('status', '!=', 'cancelled')
                ->get();

            $availableCourts = $venue->total_courts;
            foreach ($conflictingEvents as $event) {
                $availableCourts -= $event->courts_used ?? 1;
            }

            $isAvailable = $availableCourts >= $courtsNeeded;

            return response()->json([
                'status' => 'success',
                'data' => [
                    'venue_id' => $venue->id,
                    'venue_name' => $venue->name,
                    'requested_date' => $eventDate,
                    'duration_hours' => $durationHours,
                    'courts_needed' => $courtsNeeded,
                    'is_available' => $isAvailable,
                    'available_courts' => $availableCourts,
                    'total_courts' => $venue->total_courts,
                    'conflicting_events' => $conflictingEvents->count(),
                    'estimated_cost' => $venue->hourly_rate ? 
                        ($venue->hourly_rate * $durationHours * $courtsNeeded) : null,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat cek ketersediaan venue.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get venue events schedule
     */
    public function getSchedule($id, Request $request)
    {
        try {
            $venue = Venue::findOrFail($id);
            
            $startDate = $request->get('start_date', now()->toDateString());
            $endDate = $request->get('end_date', now()->addDays(30)->toDateString());

            $events = Event::where('venue_id', $venue->id)
                ->whereBetween('event_date', [$startDate, $endDate])
                ->where('status', '!=', 'cancelled')
                ->with(['sport', 'host.profile', 'community'])
                ->orderBy('event_date')
                ->get();

            // Group events by date
            $schedule = $events->groupBy(function($event) {
                return $event->event_date->toDateString();
            });

            return response()->json([
                'status' => 'success',
                'data' => [
                    'venue' => $venue,
                    'schedule_period' => [
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                    ],
                    'schedule' => $schedule,
                    'total_events' => $events->count(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil jadwal venue.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate venue availability percentage
     */
    private function calculateVenueAvailability($venue)
    {
        $today = now();
        $nextMonth = $today->copy()->addMonth();

        $totalSlots = $venue->total_courts * 30; // Assume 30 days
        $bookedSlots = Event::where('venue_id', $venue->id)
            ->whereBetween('event_date', [$today, $nextMonth])
            ->where('status', '!=', 'cancelled')
            ->sum('courts_used');

        $availabilityPercentage = $totalSlots > 0 ? 
            round((($totalSlots - $bookedSlots) / $totalSlots) * 100, 1) : 0;

        return [
            'total_slots' => $totalSlots,
            'booked_slots' => $bookedSlots,
            'available_slots' => $totalSlots - $bookedSlots,
            'availability_percentage' => $availabilityPercentage,
        ];
    }
} 