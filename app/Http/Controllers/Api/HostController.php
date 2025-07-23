<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\Event;
use App\Models\User;
use App\Models\CommunityMember;
use App\Models\EventParticipant;
use App\Models\Venue;
use App\Models\Court;
use App\Models\MatchHistory;
use App\Services\MatchmakingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\GuestPlayer;
use Carbon\Carbon;

class HostController extends Controller
{
    protected $matchmakingService;

    public function __construct(MatchmakingService $matchmakingService)
    {
        $this->matchmakingService = $matchmakingService;
        $this->middleware('auth:sanctum');
    }

    public function getDashboardStats()
    {
        $user = Auth::user();

        // Get communities where user is host
        $hostedCommunities = Community::where('host_id', $user->id)->get();
        $communityIds = $hostedCommunities->pluck('id');

        // Get events where user is host
        $hostedEvents = Event::where('host_id', $user->id)->get();
        $eventIds = $hostedEvents->pluck('id');

        // Calculate statistics
        $stats = [
            'totalMembers' => CommunityMember::whereIn('community_id', $communityIds)->count(),
            'activeEvents' => Event::where('host_id', $user->id)
                ->where('status', 'active')
                ->count(),
            'totalCommunities' => $hostedCommunities->count(),
            'pendingRequests' => CommunityMember::whereIn('community_id', $communityIds)
                ->where('status', 'pending')
                ->count() +
                EventParticipant::whereIn('event_id', $eventIds)
                ->where('status', 'pending')
                ->count()
        ];

        return response()->json([
            'status' => 'success',
            'data' => [
                'stats' => $stats
            ]
        ]);
    }

    public function getHostAnalytics(Request $request)
    {
        try {
            $user = Auth::user();
            $timeframe = $request->input('timeframe', '30days');

            // Get communities and events
            $communities = Community::where('host_id', $user->id)->get();
            $events = Event::where('host_id', $user->id);

            // Apply timeframe filter
            switch ($timeframe) {
                case '7days':
                    $events = $events->where('created_at', '>=', now()->subDays(7));
                    break;
                case '30days':
                    $events = $events->where('created_at', '>=', now()->subDays(30));
                    break;
                case '90days':
                    $events = $events->where('created_at', '>=', now()->subDays(90));
                    break;
            }

            $events = $events->get();

            // Calculate analytics
            $analytics = [
                'eventStats' => [
                    'total' => $events->count(),
                    'active' => $events->where('status', 'active')->count(),
                    'completed' => $events->where('status', 'completed')->count(),
                    'cancelled' => $events->where('status', 'cancelled')->count(),
                ],
                'communityStats' => [
                    'total' => $communities->count(),
                    'totalMembers' => CommunityMember::whereIn('community_id', $communities->pluck('id'))->count(),
                    'averageRating' => $communities->avg('rating') ?? 0,
                ],
                'participationRate' => $this->calculateParticipationRate($events),
                'growth' => [
                    'events' => $this->calculateGrowthRate($events, $timeframe),
                    'members' => $this->calculateMemberGrowthRate($communities, $timeframe),
                ]
            ];

            return response()->json([
                'status' => 'success',
                'data' => [
                    'analytics' => $analytics,
                    'timeframe' => $timeframe
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengambil data analytics: ' . $e->getMessage()
            ], 500);
        }
    }

    private function calculateParticipationRate($events)
    {
        if ($events->isEmpty()) {
            return 0;
        }

        $totalParticipants = EventParticipant::whereIn('event_id', $events->pluck('id'))->count();
        $totalCapacity = $events->sum('capacity');

        return $totalCapacity > 0 ? ($totalParticipants / $totalCapacity) * 100 : 0;
    }

    private function calculateGrowthRate($events, $timeframe)
    {
        if ($events->isEmpty()) {
            return 0;
        }

        $periods = [
            '7days' => 7,
            '30days' => 30,
            '90days' => 90
        ];

        $period = $periods[$timeframe] ?? 30;
        $now = now();
        $periodStart = $now->copy()->subDays($period);
        $halfPeriod = $now->copy()->subDays($period / 2);

        $firstHalf = $events->whereBetween('created_at', [$periodStart, $halfPeriod])->count();
        $secondHalf = $events->whereBetween('created_at', [$halfPeriod, $now])->count();

        return $firstHalf > 0 ? (($secondHalf - $firstHalf) / $firstHalf) * 100 : 0;
    }

    private function calculateMemberGrowthRate($communities, $timeframe)
    {
        if ($communities->isEmpty()) {
            return 0;
        }

        $periods = [
            '7days' => 7,
            '30days' => 30,
            '90days' => 90
        ];

        $period = $periods[$timeframe] ?? 30;
        $now = now();
        $periodStart = $now->copy()->subDays($period);
        $halfPeriod = $now->copy()->subDays($period / 2);

        $communityIds = $communities->pluck('id');

        $firstHalf = CommunityMember::whereIn('community_id', $communityIds)
            ->whereBetween('created_at', [$periodStart, $halfPeriod])
            ->count();

        $secondHalf = CommunityMember::whereIn('community_id', $communityIds)
            ->whereBetween('created_at', [$halfPeriod, $now])
            ->count();

        return $firstHalf > 0 ? (($secondHalf - $firstHalf) / $firstHalf) * 100 : 0;
    }

    // Venue Management
    public function getVenues()
    {
        $user = Auth::user();
        
        // Get venues owned by the host
        $venues = Venue::where('owner_id', $user->id)
            ->with(['events' => function($query) {
                $query->where('event_date', '>=', Carbon::today())
                      ->orderBy('event_date', 'asc');
            }])
            ->get()
            ->map(function($venue) {
                return [
                    'id' => $venue->id,
                    'name' => $venue->name,
                    'address' => $venue->address,
                    'latitude' => $venue->latitude,
                    'longitude' => $venue->longitude,
                    'capacity' => $venue->capacity,
                    'courts_count' => $venue->courts_count,
                    'operating_hours' => $venue->operating_hours,
                    'amenities' => $venue->amenities,
                    'status' => $venue->status,
                    'upcoming_events' => $venue->events->count(),
                    'events' => $venue->events->map(function($event) {
                        return [
                            'id' => $event->id,
                            'title' => $event->title,
                            'event_date' => $event->event_date,
                            'start_time' => $event->start_time,
                            'participants_count' => $event->participants()->count(),
                            'max_participants' => $event->max_participants
                        ];
                    })
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => $venues
        ]);
    }

    public function createVenue(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:500',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'capacity' => 'required|integer|min:1|max:1000',
            'courts_count' => 'required|integer|min:1|max:50',
            'operating_hours' => 'nullable|json',
            'amenities' => 'nullable|json',
            'status' => 'required|in:active,inactive,maintenance'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $venue = Venue::create([
                'owner_id' => Auth::id(),
                'name' => $request->name,
                'address' => $request->address,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'capacity' => $request->capacity,
                'courts_count' => $request->courts_count,
                'operating_hours' => $request->operating_hours ? json_decode($request->operating_hours, true) : null,
                'amenities' => $request->amenities ? json_decode($request->amenities, true) : null,
                'status' => $request->status
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Venue created successfully',
                'data' => ['venue' => $venue]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create venue'
            ], 500);
        }
    }

    public function updateVenue(Request $request, $venueId)
    {
        $venue = Venue::where('owner_id', Auth::id())->findOrFail($venueId);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'address' => 'sometimes|required|string|max:500',
            'latitude' => 'sometimes|required|numeric|between:-90,90',
            'longitude' => 'sometimes|required|numeric|between:-180,180',
            'capacity' => 'sometimes|required|integer|min:1|max:1000',
            'courts_count' => 'sometimes|required|integer|min:1|max:50',
            'operating_hours' => 'nullable|json',
            'amenities' => 'nullable|json',
            'status' => 'sometimes|required|in:active,inactive,maintenance'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $updateData = $request->only(['name', 'address', 'latitude', 'longitude', 'capacity', 'courts_count', 'status']);
            
            if ($request->has('operating_hours')) {
                $updateData['operating_hours'] = $request->operating_hours ? json_decode($request->operating_hours, true) : null;
            }
            
            if ($request->has('amenities')) {
                $updateData['amenities'] = $request->amenities ? json_decode($request->amenities, true) : null;
            }

            $venue->update($updateData);

            return response()->json([
                'status' => 'success',
                'message' => 'Venue updated successfully',
                'data' => ['venue' => $venue]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update venue'
            ], 500);
        }
    }

    public function deleteVenue($venueId)
    {
        $venue = Venue::where('owner_id', Auth::id())->findOrFail($venueId);

        // Check if venue has upcoming events
        $upcomingEvents = Event::where('venue_id', $venue->id)
            ->where('event_date', '>=', Carbon::today())
            ->count();

        if ($upcomingEvents > 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot delete venue with upcoming events'
            ], 400);
        }

        try {
            $venue->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Venue deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete venue'
            ], 500);
        }
    }

    // Court Management
    public function getCourts(Venue $venue)
    {
        // This functionality is deprecated - use venue stats instead
        return response()->json([
            'status' => 'error',
            'message' => 'Use /api/host/venues/{venue}/stats endpoint instead'
        ], 410);
    }

    public function updateCourtStatus(Request $request, $courtId)
    {
        // This functionality is deprecated - use matchmaking endpoints instead
        return response()->json([
            'status' => 'error',
            'message' => 'Use /api/matchmaking/{event}/assign-court endpoint instead'
        ], 410);
    }

    public function assignMatch(Request $request, $courtId)
    {
        // This functionality is deprecated - use matchmaking endpoints instead
        return response()->json([
            'status' => 'error',
            'message' => 'Use /api/matchmaking/{event}/assign-court endpoint instead'
        ], 410);
    }

    // Matchmaking Management
    public function getMatchmakingStatus($venueId)
    {
        // This functionality is now handled by the venue matchmaking status endpoint
        return $this->getVenueMatchmakingStatus($venueId);
    }

    public function overrideMatch(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'venue_id' => 'required|exists:venues,id',
            'player1_id' => 'required|exists:users,id',
            'player2_id' => 'required|exists:users,id|different:player1_id',
            'reason' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // This functionality is now handled by MatchmakingController
            return response()->json([
                'status' => 'error',
                'message' => 'Use /api/matchmaking/{event}/override-player endpoint instead'
            ], 410);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to override match'
            ], 500);
        }
    }

    public function getGuestPlayers(Event $event)
    {
        $this->authorize('manage', $event);

        $guests = GuestPlayer::where('event_id', $event->id)
            ->where('is_active', true)
            ->where('valid_until', '>', now())
            ->get();

        return response()->json([
            'guests' => $guests
        ]);
    }

    public function addGuestPlayer(Request $request, Event $event)
    {
        $this->authorize('manage-guests', $event);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'skill_level' => 'required|integer|min:0|max:5',
            'estimated_mmr' => 'required|integer|min:0|max:3000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $guestPlayer = new GuestPlayer($request->all());
            $guestPlayer->event_id = $event->id;
            
            // Save the guest player
            $guestPlayer->save();
            
            // Automatically check them in since they're added by the host
            $guestPlayer->markAsCheckedIn();

            // Create event participant entry
            $event->participants()->create([
                'guest_player_id' => $guestPlayer->id,
                'status' => 'checked_in',
                'checked_in_at' => Carbon::now(),
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Guest player added and checked in successfully',
                'data' => [
                    'guest_player' => $guestPlayer,
                    'temporary_id' => $guestPlayer->temporary_id
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to add guest player',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function removeGuestPlayer(Event $event, GuestPlayer $guestPlayer)
    {
        $this->authorize('manage-guests', $event);

        if ($guestPlayer->event_id !== $event->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Guest player does not belong to this event'
            ], 403);
        }

        try {
            // Remove from event participants
            $event->participants()
                ->where('guest_player_id', $guestPlayer->id)
                ->delete();

            // Soft delete the guest player
            $guestPlayer->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Guest player removed successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to remove guest player',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateGuestPlayer(Request $request, Event $event, GuestPlayer $guestPlayer)
    {
        $this->authorize('manage-guests', $event);

        if ($guestPlayer->event_id !== $event->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Guest player does not belong to this event'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|nullable|string|max:20',
            'skill_level' => 'sometimes|integer|min:0|max:5',
            'estimated_mmr' => 'sometimes|integer|min:0|max:3000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $guestPlayer->update($request->all());

            return response()->json([
                'status' => 'success',
                'message' => 'Guest player updated successfully',
                'data' => ['guest_player' => $guestPlayer]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update guest player',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function listGuestPlayers(Event $event)
    {
        $this->authorize('manage-guests', $event);

        $guestPlayers = $event->guestPlayers()
            ->with(['eventParticipations'])
            ->get()
            ->map(function ($guestPlayer) {
                return [
                    'id' => $guestPlayer->id,
                    'name' => $guestPlayer->name,
                    'phone' => $guestPlayer->phone,
                    'temporary_id' => $guestPlayer->temporary_id,
                    'skill_level' => $guestPlayer->skill_level,
                    'estimated_mmr' => $guestPlayer->estimated_mmr,
                    'checked_in' => !is_null($guestPlayer->checked_in_at),
                    'checked_in_at' => $guestPlayer->checked_in_at,
                    'expires_at' => $guestPlayer->expires_at,
                    'status' => $guestPlayer->eventParticipations->first()->status ?? 'pending'
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => ['guest_players' => $guestPlayers]
        ]);
    }

    /**
     * Get venue statistics
     */
    public function getVenueStats($venueId)
    {
        $venue = Venue::where('owner_id', Auth::id())->findOrFail($venueId);

        $today = Carbon::today();
        $thisWeek = Carbon::now()->startOfWeek();
        $thisMonth = Carbon::now()->startOfMonth();

        // Get events statistics
        $totalEvents = Event::where('venue_id', $venue->id)->count();
        $eventsThisWeek = Event::where('venue_id', $venue->id)
            ->where('event_date', '>=', $thisWeek)
            ->count();
        $eventsThisMonth = Event::where('venue_id', $venue->id)
            ->where('event_date', '>=', $thisMonth)
            ->count();

        // Get participants statistics
        $totalParticipants = EventParticipant::whereHas('event', function($query) use ($venue) {
            $query->where('venue_id', $venue->id);
        })->count();

        // Get matches statistics
        $totalMatches = MatchHistory::whereHas('event', function($query) use ($venue) {
            $query->where('venue_id', $venue->id);
        })->count();

        $completedMatches = MatchHistory::whereHas('event', function($query) use ($venue) {
            $query->where('venue_id', $venue->id);
        })->where('match_status', 'completed')->count();

        // Get revenue statistics (if you have pricing)
        $revenueThisMonth = Event::where('venue_id', $venue->id)
            ->where('event_date', '>=', $thisMonth)
            ->sum('price') ?? 0;

        // Get upcoming events
        $upcomingEvents = Event::where('venue_id', $venue->id)
            ->where('event_date', '>=', $today)
            ->with(['sport', 'participants'])
            ->orderBy('event_date', 'asc')
            ->limit(5)
            ->get()
            ->map(function($event) {
                return [
                    'id' => $event->id,
                    'title' => $event->title,
                    'sport' => $event->sport->name,
                    'event_date' => $event->event_date,
                    'start_time' => $event->start_time,
                    'participants_count' => $event->participants->count(),
                    'max_participants' => $event->max_participants
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => [
                'venue' => [
                    'id' => $venue->id,
                    'name' => $venue->name,
                    'capacity' => $venue->capacity,
                    'courts_count' => $venue->courts_count
                ],
                'statistics' => [
                    'total_events' => $totalEvents,
                    'events_this_week' => $eventsThisWeek,
                    'events_this_month' => $eventsThisMonth,
                    'total_participants' => $totalParticipants,
                    'total_matches' => $totalMatches,
                    'completed_matches' => $completedMatches,
                    'completion_rate' => $totalMatches > 0 ? round(($completedMatches / $totalMatches) * 100, 1) : 0,
                    'revenue_this_month' => $revenueThisMonth
                ],
                'upcoming_events' => $upcomingEvents,
                'utilization' => [
                    'capacity_utilization' => $venue->capacity > 0 ? round(($totalParticipants / ($venue->capacity * $totalEvents)) * 100, 1) : 0,
                    'court_utilization' => $venue->courts_count > 0 ? round(($totalMatches / ($venue->courts_count * $totalEvents)) * 100, 1) : 0
                ]
            ]
        ]);
    }

    /**
     * Get matchmaking status for a venue
     */
    public function getVenueMatchmakingStatus($venueId)
    {
        $venue = Venue::where('owner_id', Auth::id())->findOrFail($venueId);

        $activeEvents = Event::where('venue_id', $venue->id)
            ->where('event_date', Carbon::today())
            ->where('status', 'active')
            ->with(['participants', 'sport'])
            ->get();

        $matchmakingStatus = $activeEvents->map(function($event) {
            $totalParticipants = $event->participants()->where('status', 'confirmed')->count();
            $activeMatches = MatchHistory::where('event_id', $event->id)
                ->whereIn('match_status', ['scheduled', 'ongoing'])
                ->count();
            $waitingPlayers = $totalParticipants - ($activeMatches * 2);

            return [
                'event_id' => $event->id,
                'event_title' => $event->title,
                'sport' => $event->sport->name,
                'start_time' => $event->start_time,
                'total_participants' => $totalParticipants,
                'active_matches' => $activeMatches,
                'waiting_players' => max(0, $waitingPlayers),
                'courts_available' => max(0, $event->venue->courts_count - $activeMatches),
                'can_create_matches' => $waitingPlayers >= 2
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'venue' => [
                    'id' => $venue->id,
                    'name' => $venue->name,
                    'courts_count' => $venue->courts_count
                ],
                'events' => $matchmakingStatus
            ]
        ]);
    }

    /**
     * Get community management statistics
     */
    public function getCommunityStats($communityId)
    {
        $community = Community::where('host_id', Auth::id())->findOrFail($communityId);

        $today = Carbon::today();
        $thisWeek = Carbon::now()->startOfWeek();
        $thisMonth = Carbon::now()->startOfMonth();

        // Get member statistics
        $totalMembers = $community->members()->count();
        $activeMembers = $community->members()
            ->whereHas('eventParticipations', function($query) use ($thisMonth) {
                $query->where('created_at', '>=', $thisMonth);
            })->count();

        // Get event statistics
        $totalEvents = $community->events()->count();
        $upcomingEvents = $community->events()
            ->where('event_date', '>=', $today)
            ->count();

        // Get participation statistics
        $participationRate = $community->events()
            ->whereHas('participants', function($query) {
                $query->where('status', 'confirmed');
            })
            ->avg(DB::raw('(SELECT COUNT(*) FROM event_participants WHERE event_participants.event_id = events.id) / max_participants * 100')) ?? 0;

        return response()->json([
            'status' => 'success',
            'data' => [
                'community' => [
                    'id' => $community->id,
                    'name' => $community->name,
                    'created_at' => $community->created_at
                ],
                'statistics' => [
                    'total_members' => $totalMembers,
                    'active_members' => $activeMembers,
                    'total_events' => $totalEvents,
                    'upcoming_events' => $upcomingEvents,
                    'participation_rate' => round($participationRate, 1),
                    'member_growth' => $this->calculateMemberGrowthRate(collect([$community]), '30days')
                ]
            ]
        ]);
    }

    /**
     * Update community settings
     */
    public function updateCommunitySettings(Request $request, $communityId)
    {
        $community = Community::where('host_id', Auth::id())->findOrFail($communityId);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string|max:1000',
            'rules' => 'sometimes|string|max:2000',
            'privacy' => 'sometimes|in:public,private',
            'join_approval_required' => 'sometimes|boolean',
            'max_members' => 'sometimes|integer|min:1',
            'allowed_sports' => 'sometimes|array',
            'allowed_sports.*' => 'exists:sports,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $community->update($request->all());

            if ($request->has('allowed_sports')) {
                $community->allowedSports()->sync($request->allowed_sports);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Community settings updated successfully',
                'data' => ['community' => $community->fresh(['allowedSports'])]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update community settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Manage member requests
     */
    public function manageMemberRequest(Request $request, $communityId, $memberId)
    {
        $community = Community::where('host_id', Auth::id())->findOrFail($communityId);
        $member = CommunityMember::where('community_id', $communityId)
            ->where('id', $memberId)
            ->firstOrFail();

        $validator = Validator::make($request->all(), [
            'action' => 'required|in:approve,reject'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            if ($request->action === 'approve') {
                $member->update(['status' => 'active']);
                $message = 'Member request approved';
            } else {
                $member->delete();
                $message = 'Member request rejected';
            }

            return response()->json([
                'status' => 'success',
                'message' => $message
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to process member request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process QR code check-in
     */
    public function processQRCheckIn(Request $request, Event $event)
    {
        $this->authorize('manage-event', $event);

        $validator = Validator::make($request->all(), [
            'qr_code' => 'required|string',
            'check_in_type' => 'required|in:participant,guest'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Decode QR code (format: "type:id:event_id:timestamp:hash")
            $qrData = explode(':', $request->qr_code);
            if (count($qrData) !== 5) {
                throw new \Exception('Invalid QR code format');
            }

            [$type, $id, $eventId, $timestamp, $hash] = $qrData;

            // Verify QR code integrity
            $calculatedHash = hash('sha256', $type . $id . $eventId . $timestamp . config('app.key'));
            if ($calculatedHash !== $hash) {
                throw new \Exception('Invalid QR code signature');
            }

            // Verify event match
            if ($eventId != $event->id) {
                throw new \Exception('QR code is for a different event');
            }

            // Check expiration (QR codes valid for 24 hours)
            if (Carbon::createFromTimestamp($timestamp)->addDay()->isPast()) {
                throw new \Exception('QR code has expired');
            }

            // Process check-in based on type
            if ($type === 'participant') {
                $participant = EventParticipant::where('event_id', $event->id)
                    ->where('user_id', $id)
                    ->first();

                if (!$participant) {
                    throw new \Exception('Participant not found');
                }

                if ($participant->checked_in_at) {
                    throw new \Exception('Participant already checked in');
                }

                $participant->update([
                    'checked_in_at' => now(),
                    'checked_in_by' => Auth::id()
                ]);

                $message = 'Participant checked in successfully';
                $data = ['participant' => $participant];
            } else {
                $guestPlayer = GuestPlayer::where('event_id', $event->id)
                    ->where('id', $id)
                    ->first();

                if (!$guestPlayer) {
                    throw new \Exception('Guest player not found');
                }

                if ($guestPlayer->checked_in_at) {
                    throw new \Exception('Guest player already checked in');
                }

                $guestPlayer->update([
                    'checked_in_at' => now(),
                    'checked_in_by' => Auth::id()
                ]);

                $message = 'Guest player checked in successfully';
                $data = ['guest_player' => $guestPlayer];
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => $message,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Generate QR code for check-in
     */
    public function generateCheckInQR(Request $request, Event $event)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:participant,guest',
            'id' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $timestamp = now()->timestamp;
        $hash = hash('sha256', $request->type . $request->id . $event->id . $timestamp . config('app.key'));
        
        // Format: "type:id:event_id:timestamp:hash"
        $qrCode = implode(':', [
            $request->type,
            $request->id,
            $event->id,
            $timestamp,
            $hash
        ]);

        return response()->json([
            'status' => 'success',
            'data' => [
                'qr_code' => $qrCode,
                'expires_at' => now()->addDay()->toISOString()
            ]
        ]);
    }
}