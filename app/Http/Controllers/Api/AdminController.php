<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserReport;
use App\Models\AdminActivity;
use App\Models\Event;
use App\Models\Community;
use App\Models\MatchHistory;
use App\Models\PlayerRating;
use App\Models\CreditScoreLog;
use App\Models\Notification;
use App\Services\CreditScoreService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class AdminController extends Controller
{
    private $creditScoreService;

    public function __construct(CreditScoreService $creditScoreService)
    {
        $this->creditScoreService = $creditScoreService;
    }

    /**
     * Check if the authenticated user is an admin
     */
    private function checkAdminRole()
    {
        $user = Auth::user();
        if (!$user || !$user->hasRole('admin')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak. Hanya admin yang dapat mengakses endpoint ini.'
            ], 403);
        }
        return null;
    }

    /**
     * Admin Dashboard - System Overview
     */
    public function getDashboard()
    {
        $roleCheck = $this->checkAdminRole();
        if ($roleCheck) return $roleCheck;
        
        try {
            $admin = Auth::user();
            
            // Get key metrics
            $totalUsers = User::count();
            $activeUsers = User::where('is_active', true)->count();
            $totalEvents = Event::count();
            $activeEvents = Event::where('status', 'active')->where('event_date', '>=', now())->count();
            $totalCommunities = Community::count();
            $activeCommunities = Community::where('is_active', true)->count();
            $totalMatches = MatchHistory::count();
            $pendingReports = UserReport::pending()->count();
            $unassignedReports = UserReport::unassigned()->count();

            // Recent activities
            $recentActivities = AdminActivity::with('admin')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            // User growth (last 30 days)
            $userGrowth = User::selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->where('created_at', '>=', now()->subDays(30))
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            // System health indicators
            $systemHealth = [
                'average_credit_score' => round(User::avg('credit_score'), 2),
                'users_with_low_credit' => User::where('credit_score', '<', 60)->count(),
                'events_completion_rate' => $this->calculateEventCompletionRate(),
                'average_rating' => round(PlayerRating::avg('skill_rating'), 2),
            ];

            return response()->json([
                'status' => 'success',
                'data' => [
                    'user_metrics' => [
                        'total_users' => $totalUsers,
                        'active_users' => $activeUsers,
                        'inactive_users' => $totalUsers - $activeUsers,
                    ],
                    'event_metrics' => [
                        'total_events' => $totalEvents,
                        'active_events' => $activeEvents,
                        'completed_events' => Event::where('status', 'completed')->count(),
                    ],
                    'match_metrics' => [
                        'total_matches' => $totalMatches,
                        'matches_today' => MatchHistory::whereDate('created_at', today())->count(),
                        'matches_this_week' => MatchHistory::where('created_at', '>=', now()->subWeek())->count(),
                    ],
                    'system_health' => $systemHealth,
                    'recent_activities' => $recentActivities,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengambil data dashboard: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * User Management - Get all users with filtering
     */
    public function getUsers(Request $request)
    {
        $roleCheck = $this->checkAdminRole();
        if ($roleCheck) return $roleCheck;
        
        try {
            $query = User::query();

            // Filters
            if ($request->has('user_type')) {
                $query->where('user_type', $request->user_type);
            }

            if ($request->has('subscription_tier')) {
                $query->where('subscription_tier', $request->subscription_tier);
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            if ($request->has('status')) {
                if ($request->status === 'active') {
                    $query->where('is_active', true);
                } elseif ($request->status === 'inactive') {
                    $query->where('is_active', false);
                }
            }

            if ($request->has('credit_score_min')) {
                $query->where('credit_score', '>=', $request->credit_score_min);
            }

            if ($request->has('credit_score_max')) {
                $query->where('credit_score', '<=', $request->credit_score_max);
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhereHas('profile', function($profileQuery) use ($search) {
                          $profileQuery->where('first_name', 'like', "%{$search}%")
                                      ->orWhere('last_name', 'like', "%{$search}%");
                      });
                });
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            $users = $query->paginate($request->get('per_page', 20));

            // Transform users data to include necessary fields
            $usersData = $users->map(function($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'user_type' => $user->user_type,
                    'subscription_tier' => $user->subscription_tier,
                    'credit_score' => $user->credit_score,
                    'is_active' => (bool) $user->is_active,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ];
            });

            return response()->json([
                'status' => 'success',
                'data' => [
                    'users' => $usersData,
                    'pagination' => [
                        'current_page' => $users->currentPage(),
                        'total_pages' => $users->lastPage(),
                        'per_page' => $users->perPage(),
                        'total' => $users->total(),
                        'from' => $users->firstItem(),
                        'to' => $users->lastItem(),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengambil data pengguna: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * User Management - Get single user details
     */
    public function getUserDetails($userId)
    {
        try {
            $user = User::findOrFail($userId);

            // Get basic user data manually to avoid relationship errors
            $userData = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'user_type' => $user->user_type,
                'subscription_tier' => $user->subscription_tier,
                'credit_score' => $user->credit_score,
                'is_active' => (bool) $user->is_active,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                'profile' => null, // Placeholder for profile data
                'sport_ratings' => [] // Placeholder for sport ratings
            ];

            // Try to get credit score history if the table exists
            $creditScoreHistory = [];
            try {
                $creditScoreHistory = \DB::table('credit_score_logs')
                    ->where('user_id', $userId)
                    ->orderBy('created_at', 'desc')
                    ->limit(20)
                    ->get();
            } catch (\Exception $e) {
                // Credit score logs table might not exist
            }

            // Try to get reports if the table exists
            $reportsAgainst = [];
            $reportsMade = [];
            try {
                $reportsAgainst = \DB::table('user_reports')
                    ->where('reported_user_id', $userId)
                    ->orderBy('created_at', 'desc')
                    ->get();
                    
                $reportsMade = \DB::table('user_reports')
                    ->where('reporter_id', $userId)
                    ->orderBy('created_at', 'desc')
                    ->get();
            } catch (\Exception $e) {
                // User reports table might not exist
            }

            $statistics = [
                'total_events_hosted' => 0,
                'total_events_participated' => 0,
                'total_matches' => 0,
                'average_received_rating' => 0,
                'total_communities_hosted' => 0,
            ];

            // Try to get recent activities safely
            $recentActivities = [];
            try {
                $recentActivities = \DB::table('admin_activities')
                    ->where('target_user_id', $userId)
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get();
            } catch (\Exception $e) {
                // Admin activities table might not exist
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'user' => $userData,
                    'statistics' => $statistics,
                    'recent_activities' => $recentActivities
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengambil detail pengguna: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * User Management - Suspend/Unsuspend user
     */
    public function toggleUserStatus(Request $request, $userId)
    {
        $roleCheck = $this->checkAdminRole();
        if ($roleCheck) return $roleCheck;
        
        try {
            $admin = Auth::user();
            $user = User::findOrFail($userId);

            $validator = Validator::make($request->all(), [
                'reason' => 'required_if:action,suspend|string|max:500',
                'action' => 'required|in:suspend,unsuspend'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Data tidak valid',
                    'errors' => $validator->errors()
                ], 422);
            }

            $oldStatus = $user->is_active;
            $newStatus = $request->action === 'unsuspend' ? 1 : 0;

            $user->update(['is_active' => $newStatus]);

            // Log admin activity
            AdminActivity::logActivity(
                $admin->id,
                $request->action === 'suspend' ? 'user_suspended' : 'user_unsuspended',
                'user',
                $user->id,
                $request->action === 'suspend' 
                    ? "User suspended: {$request->reason}" 
                    : "User unsuspended",
                ['is_active' => $oldStatus],
                ['is_active' => $newStatus]
            );

            return response()->json([
                'status' => 'success',
                'message' => $request->action === 'suspend' 
                    ? 'Pengguna berhasil disuspend' 
                    : 'Pengguna berhasil diaktifkan kembali',
                'data' => $user->fresh()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengubah status pengguna: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * User Management - Adjust credit score
     */
    public function adjustCreditScore(Request $request, $userId)
    {
        $roleCheck = $this->checkAdminRole();
        if ($roleCheck) return $roleCheck;
        
        try {
            $admin = Auth::user();
            $user = User::findOrFail($userId);

            $validator = Validator::make($request->all(), [
                'adjustment_type' => 'required|in:add,subtract,set',
                'amount' => 'required|integer|min:1|max:100',
                'reason' => 'required|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Data tidak valid',
                    'errors' => $validator->errors()
                ], 422);
            }

            $oldScore = $user->credit_score;
            $amount = $request->amount;

            switch ($request->adjustment_type) {
                case 'add':
                    $newScore = min(100, $oldScore + $amount);
                    break;
                case 'subtract':
                    $newScore = max(0, $oldScore - $amount);
                    break;
                case 'set':
                    $newScore = min(100, max(0, $amount));
                    break;
            }

            $user->update(['credit_score' => $newScore]);

            // Log credit score change
            CreditScoreLog::create([
                'user_id' => $user->id,
                'type' => 'admin_adjustment',
                'change_amount' => $newScore - $oldScore,
                'old_score' => $oldScore,
                'new_score' => $newScore,
                'description' => "Admin adjustment: {$request->reason}",
                'metadata' => [
                    'admin_id' => $admin->id,
                    'admin_name' => $admin->name,
                    'adjustment_type' => $request->adjustment_type,
                ]
            ]);

            // Log admin activity
            AdminActivity::logActivity(
                $admin->id,
                'credit_score_adjusted',
                'user',
                $user->id,
                "Credit score adjusted from {$oldScore} to {$newScore}: {$request->reason}",
                ['credit_score' => $oldScore],
                ['credit_score' => $newScore]
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Credit score berhasil disesuaikan',
                'data' => [
                    'old_score' => $oldScore,
                    'new_score' => $newScore,
                    'change' => $newScore - $oldScore
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menyesuaikan credit score: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Dispute Management - Get all reports
     */
    public function getReports(Request $request)
    {
        $roleCheck = $this->checkAdminRole();
        if ($roleCheck) return $roleCheck;
        
        try {
            $query = UserReport::with(['reporter', 'reportedUser', 'assignedAdmin']);

            // Filters
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('priority')) {
                $query->where('priority', $request->priority);
            }

            if ($request->has('report_type')) {
                $query->where('report_type', $request->report_type);
            }

            if ($request->has('assigned_admin_id')) {
                $query->where('assigned_admin_id', $request->assigned_admin_id);
            }

            if ($request->has('unassigned') && $request->boolean('unassigned')) {
                $query->whereNull('assigned_admin_id');
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            $reports = $query->paginate($request->get('per_page', 20));

            return response()->json([
                'status' => 'success',
                'data' => [
                    'reports' => $reports->items(),
                    'pagination' => [
                        'current_page' => $reports->currentPage(),
                        'total_pages' => $reports->lastPage(),
                        'per_page' => $reports->perPage(),
                        'total' => $reports->total(),
                        'from' => $reports->firstItem(),
                        'to' => $reports->lastItem(),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengambil data laporan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Dispute Management - Assign report to admin
     */
    public function assignReport(Request $request, $reportId)
    {
        $roleCheck = $this->checkAdminRole();
        if ($roleCheck) return $roleCheck;
        
        try {
            $admin = Auth::user();
            
            // Validate that assigned_to user is an admin
            if ($request->has('assigned_to')) {
                $assignedUser = User::find($request->assigned_to);
                if (!$assignedUser || !$assignedUser->hasRole('admin')) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Report hanya dapat di-assign ke admin.'
                    ], 422);
                }
            }
            $report = UserReport::findOrFail($reportId);

            if ($report->status !== 'pending') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Laporan ini sudah tidak dalam status pending'
                ], 400);
            }

            $report->assign($admin->id);
            
            // Update priority if provided
            if ($request->has('priority')) {
                $report->update(['priority' => $request->priority]);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Report berhasil di-assign.',
                'data' => $report->fresh(['reporter', 'reportedUser', 'assignedAdmin'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal assign laporan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Dispute Management - Resolve report
     */
    public function resolveReport(Request $request, $reportId)
    {
        try {
            $admin = Auth::user();
            $report = UserReport::findOrFail($reportId);

            $validator = Validator::make($request->all(), [
                'action' => 'required|in:resolve,dismiss,escalate',
                'resolution' => 'required_if:action,resolve|string|max:1000',
                'reason' => 'required_if:action,dismiss|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Data tidak valid',
                    'errors' => $validator->errors()
                ], 422);
            }

            switch ($request->action) {
                case 'resolve':
                    $report->resolve($request->resolution, $admin->id);
                    $message = 'Report berhasil diselesaikan.';
                    break;
                case 'dismiss':
                    $report->dismiss($request->reason, $admin->id);
                    $message = 'Laporan berhasil ditolak';
                    break;
                case 'escalate':
                    $report->escalate($admin->id);
                    $message = 'Laporan berhasil dieskalasi';
                    break;
            }

            return response()->json([
                'status' => 'success',
                'message' => $message,
                'data' => $report->fresh(['reporter', 'reportedUser', 'assignedAdmin'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menyelesaikan laporan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Platform Analytics - Complete match history
     */
    public function getPlatformMatchHistory(Request $request)
    {
        try {
            $query = MatchHistory::with(['event', 'player1', 'player2', 'sport']);

            // Filters
            if ($request->has('event_id')) {
                $query->where('event_id', $request->event_id);
            }
            
            if ($request->has('sport_id')) {
                $query->where('sport_id', $request->sport_id);
            }

            if ($request->has('date_from')) {
                $query->whereDate('match_date', '>=', $request->date_from);
            }

            if ($request->has('date_to')) {
                $query->whereDate('match_date', '<=', $request->date_to);
            }

            if ($request->has('player_id')) {
                $query->where(function($q) use ($request) {
                    $q->where('player1_id', $request->player_id)
                      ->orWhere('player2_id', $request->player_id);
                });
            }

            if ($request->has('community_id')) {
                $query->whereHas('event', function($q) use ($request) {
                    $q->where('community_id', $request->community_id);
                });
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'match_date');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            $matches = $query->paginate($request->get('per_page', 50));

            // Additional statistics
            $stats = [
                'total_matches' => MatchHistory::count(),
                'matches_today' => MatchHistory::whereDate('match_date', today())->count(),
                'matches_this_week' => MatchHistory::whereBetween('match_date', [now()->startOfWeek(), now()->endOfWeek()])->count(),
                'matches_this_month' => MatchHistory::whereMonth('match_date', now()->month)->count(),
                'average_mmr_change' => MatchHistory::selectRaw('AVG(ABS(player1_mmr_after - player1_mmr_before) + ABS(player2_mmr_after - player2_mmr_before)) / 2 as avg_change')->first()->avg_change ?? 0,
            ];

            return response()->json([
                'status' => 'success',
                'data' => [
                    'matches' => $matches->items(),
                    'pagination' => [
                        'current_page' => $matches->currentPage(),
                        'total_pages' => $matches->lastPage(),
                        'per_page' => $matches->perPage(),
                        'total' => $matches->total(),
                        'from' => $matches->firstItem(),
                        'to' => $matches->lastItem(),
                    ],
                    'statistics' => $stats
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengambil data match history: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Platform Analytics - System statistics
     */
    public function getSystemAnalytics(Request $request)
    {
        $roleCheck = $this->checkAdminRole();
        if ($roleCheck) return $roleCheck;
        
        try {
            $period = $request->get('period', '30'); // days

            $dateFrom = now()->subDays($period);

            $analytics = [
                'user_analytics' => [
                    'new_registrations' => User::where('created_at', '>=', $dateFrom)->count(),
                    'active_users' => User::where('is_active', true)->count(),
                    'premium_users' => User::where('subscription_tier', 'premium')->count(),
                    'average_credit_score' => round(User::avg('credit_score'), 2),
                    'users_by_credit_range' => [
                        'excellent' => User::where('credit_score', '>=', 80)->count(),
                        'good' => User::whereBetween('credit_score', [60, 79])->count(),
                        'warning' => User::whereBetween('credit_score', [40, 59])->count(),
                        'restricted' => User::where('credit_score', '<', 40)->count(),
                    ]
                ],
                'event_analytics' => [
                    'total_events' => Event::count(),
                    'events_created' => Event::where('created_at', '>=', $dateFrom)->count(),
                    'active_events' => Event::where('status', 'active')->where('event_date', '>=', now())->count(),
                    'completed_events' => Event::where('status', 'completed')->count(),
                    'cancelled_events' => Event::where('status', 'cancelled')->count(),
                    'events_by_type' => Event::selectRaw('event_type, COUNT(*) as count')->groupBy('event_type')->get(),
                ],
                'community_analytics' => [
                    'total_communities' => Community::count(),
                    'active_communities' => Community::where('is_active', true)->count(),
                    'communities_by_sport' => Community::with('sport')->selectRaw('sport_id, COUNT(*) as count')->groupBy('sport_id')->get(),
                ],
                'match_analytics' => [
                    'total_matches' => MatchHistory::count(),
                    'matches_period' => MatchHistory::where('match_date', '>=', $dateFrom)->count(),
                    'matches_by_sport' => MatchHistory::with('sport')->selectRaw('sport_id, COUNT(*) as count')->groupBy('sport_id')->get(),
                ],
                'report_analytics' => [
                    'total_reports' => UserReport::count(),
                    'pending_reports' => UserReport::pending()->count(),
                    'resolved_reports' => UserReport::resolved()->count(),
                    'reports_by_type' => UserReport::selectRaw('report_type, COUNT(*) as count')->groupBy('report_type')->get(),
                ]
            ];

            return response()->json([
                'status' => 'success',
                'data' => [
                    'growth_metrics' => [
                        'new_users' => $analytics['user_analytics']['new_registrations'],
                        'new_events' => $analytics['event_analytics']['events_created'],
                        'new_communities' => Community::where('created_at', '>=', $dateFrom)->count(),
                    ],
                    'engagement_metrics' => [
                        'active_users' => $analytics['user_analytics']['active_users'],
                        'active_events' => $analytics['event_analytics']['active_events'],
                        'total_matches' => $analytics['match_analytics']['matches_period'],
                    ],
                    'platform_performance' => [
                        'completion_rate' => $this->calculateEventCompletionRate(),
                        'average_credit_score' => $analytics['user_analytics']['average_credit_score'],
                        'pending_reports' => $analytics['report_analytics']['pending_reports'],
                    ],
                    'period_days' => $period,
                    'date_range' => [
                        'from' => $dateFrom->toDateString(),
                        'to' => now()->toDateString()
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengambil analytics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Admin Activity Logs
     */
    public function getAdminActivities(Request $request)
    {
        try {
            $query = AdminActivity::with('admin');

            // Filters
            if ($request->has('admin_id')) {
                $query->where('admin_id', $request->admin_id);
            }

            if ($request->has('action_type')) {
                $query->where('action_type', $request->action_type);
            }

            if ($request->has('target_type')) {
                $query->where('target_type', $request->target_type);
            }

            if ($request->has('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }

            if ($request->has('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            $activities = $query->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 20));

            return response()->json([
                'status' => 'success',
                'data' => [
                    'activities' => $activities->items(),
                    'pagination' => [
                        'current_page' => $activities->currentPage(),
                        'total_pages' => $activities->lastPage(),
                        'per_page' => $activities->perPage(),
                        'total' => $activities->total(),
                        'from' => $activities->firstItem(),
                        'to' => $activities->lastItem(),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengambil log aktivitas admin: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper method to calculate event completion rate
     */
    private function calculateEventCompletionRate()
    {
        $totalEvents = Event::where('event_date', '<', now())->count();
        $completedEvents = Event::where('status', 'completed')->count();

        return $totalEvents > 0 ? round(($completedEvents / $totalEvents) * 100, 2) : 0;
    }
}
