<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sport;
use App\Models\Event;
use App\Models\Community;
use Illuminate\Http\Request;

class SportController extends Controller
{
    /**
     * Get all active sports
     */
    public function index()
    {
        try {
            $sports = Sport::active()
                ->orderBy('name')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'sports' => $sports
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil data olahraga.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific sport details
     */
    public function show(Sport $sport)
    {
        try {
            return response()->json([
                'status' => 'success',
                'data' => [
                    'sport' => $sport
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil detail olahraga.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get events for specific sport
     */
    public function getEvents(Request $request, Sport $sport)
    {
        try {
            $query = Event::where('sport_id', $sport->id)
                ->with(['host.profile', 'community', 'participants'])
                ->active()
                ->upcoming();

            // Filter by event type if provided
            if ($request->has('type')) {
                $query->byType($request->type);
            }

            // Filter by location if provided
            if ($request->has('city')) {
                $query->whereHas('community', function($q) use ($request) {
                    $q->where('city', 'like', '%' . $request->city . '%');
                });
            }

            // Filter by skill level if provided
            if ($request->has('skill_level')) {
                $query->where('skill_level_required', $request->skill_level);
            }

            // Pagination
            $perPage = $request->get('per_page', 15);
            $events = $query->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'sport' => $sport,
                    'events' => $events
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil event olahraga.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get communities for specific sport
     */
    public function getCommunities(Request $request, Sport $sport)
    {
        try {
            $query = Community::where('sport_id', $sport->id)
                ->with(['host.profile'])
                ->where('is_active', true);

            // Filter by location if provided
            if ($request->has('city')) {
                $query->where('city', 'like', '%' . $request->city . '%');
            }

            // Filter by community type if provided
            if ($request->has('type')) {
                $query->where('community_type', $request->type);
            }

            // Order by rating and member count
            $query->orderByDesc('average_skill_rating')
                  ->orderByDesc('member_count');

            // Pagination
            $perPage = $request->get('per_page', 15);
            $communities = $query->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'sport' => $sport,
                    'communities' => $communities
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil komunitas olahraga.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}