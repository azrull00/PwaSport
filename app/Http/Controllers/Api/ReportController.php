<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserReport;
use App\Models\User;
use App\Models\Event;
use App\Models\Community;
use App\Models\MatchHistory;
use App\Models\PlayerRating;
use App\Services\EmailNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ReportController extends Controller
{
    protected $notificationService;

    public function __construct(EmailNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Submit a user report
     */
    public function submitReport(Request $request)
    {
        try {
            $reporter = Auth::user();

            $validator = Validator::make($request->all(), [
                'reported_user_id' => 'required|exists:users,id',
                'report_type' => 'required|in:misconduct,cheating,harassment,no_show,rating_dispute,inappropriate_behavior,spam,fake_profile',
                'related_type' => 'nullable|in:event,match,community,rating',
                'related_id' => 'nullable|integer',
                'description' => 'required|string|min:10|max:1000',
                'evidence' => 'nullable|array',
                'evidence.*' => 'string|url', // URLs to evidence files
                'priority' => 'nullable|in:low,medium,high',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Data tidak valid',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check if user is trying to report themselves
            if ($reporter->id == $request->reported_user_id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Anda tidak dapat melaporkan diri sendiri'
                ], 400);
            }

            // Check if related entity exists
            if ($request->related_type && $request->related_id) {
                $this->validateRelatedEntity($request->related_type, $request->related_id);
            }

            // Determine priority based on report type
            $priority = $request->priority ?? $this->determinePriority($request->report_type);

            $report = UserReport::create([
                'reporter_id' => $reporter->id,
                'reported_user_id' => $request->reported_user_id,
                'report_type' => $request->report_type,
                'related_type' => $request->related_type,
                'related_id' => $request->related_id,
                'description' => $request->description,
                'evidence' => $request->evidence,
                'priority' => $priority,
            ]);

            // Send notification to reported user (optional)
            $reportedUser = User::find($request->reported_user_id);
            $this->notificationService->sendReportNotification($reportedUser, $report);

            return response()->json([
                'status' => 'success',
                'message' => 'Laporan berhasil diajukan. Tim kami akan meninjau dalam 24-48 jam.',
                'data' => [
                    'report_id' => $report->id,
                    'status' => $report->status,
                    'priority' => $report->priority,
                    'created_at' => $report->created_at
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengajukan laporan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's submitted reports
     */
    public function getMyReports()
    {
        try {
            $user = Auth::user();
            
            $reports = UserReport::with(['reportedUser.profile', 'assignedAdmin'])
                ->where('reporter_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'reports' => $reports->items(),
                    'pagination' => [
                        'current_page' => $reports->currentPage(),
                        'total_pages' => $reports->lastPage(),
                        'total_count' => $reports->total(),
                        'per_page' => $reports->perPage()
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
     * Get reports against current user
     */
    public function getReportsAgainstMe()
    {
        try {
            $user = Auth::user();
            
            $reports = UserReport::with(['reporter.profile', 'assignedAdmin'])
                ->where('reported_user_id', $user->id)
                ->where('status', '!=', 'dismissed') // Don't show dismissed reports
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'reports' => $reports->items(),
                    'pagination' => [
                        'current_page' => $reports->currentPage(),
                        'total_pages' => $reports->lastPage(),
                        'total_count' => $reports->total(),
                        'per_page' => $reports->perPage()
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
     * Get specific report details
     */
    public function getReportDetails($reportId)
    {
        try {
            $user = Auth::user();
            
            $report = UserReport::with([
                'reporter.profile', 
                'reportedUser.profile', 
                'assignedAdmin.profile'
            ])->findOrFail($reportId);

            // Check if user has access to this report
            if ($report->reporter_id !== $user->id && 
                $report->reported_user_id !== $user->id && 
                !$user->hasRole('admin')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Anda tidak memiliki akses ke report ini.'
                ], 403);
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'report' => $report
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengambil detail laporan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update report (only by reporter, within 24 hours)
     */
    public function updateReport(Request $request, $reportId)
    {
        try {
            $user = Auth::user();
            $report = UserReport::findOrFail($reportId);

            // Check ownership
            if ($report->reporter_id !== $user->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Anda tidak dapat mengubah laporan ini'
                ], 403);
            }

            // Check if report is still editable (24 hours after creation, and still pending)
            if ($report->status !== 'pending') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Report yang sudah diselesaikan tidak dapat diubah.'
                ], 422);
            }

            if ($report->created_at->diffInHours(now()) > 24) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Report hanya dapat diubah dalam 24 jam setelah dibuat.'
                ], 422);
            }

            $validator = Validator::make($request->all(), [
                'description' => 'sometimes|string|min:10|max:1000',
                'evidence' => 'sometimes|array',
                'evidence.*' => 'string|url',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Data tidak valid',
                    'errors' => $validator->errors()
                ], 422);
            }

            $report->update($request->only(['description', 'evidence']));

            return response()->json([
                'status' => 'success',
                'message' => 'Report berhasil diperbarui.',
                'data' => $report->fresh()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memperbarui laporan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel report (only by reporter, within 24 hours, and still pending)
     */
    public function cancelReport($reportId)
    {
        try {
            $user = Auth::user();
            $report = UserReport::findOrFail($reportId);

            // Check ownership
            if ($report->reporter_id !== $user->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Anda tidak dapat membatalkan laporan ini'
                ], 403);
            }

            // Check if report is still cancellable
            if ($report->status !== 'pending') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Report yang sudah diselesaikan tidak dapat dibatalkan.'
                ], 422);
            }

            if ($report->created_at->diffInHours(now()) > 24) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Report hanya dapat dibatalkan dalam 24 jam setelah dibuat.'
                ], 422);
            }

            $report->update([
                'status' => 'dismissed',
                'admin_notes' => 'Cancelled by reporter',
                'resolved_at' => now()
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Report berhasil dibatalkan.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal membatalkan laporan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get report statistics for user
     */
    public function getReportStats()
    {
        try {
            $user = Auth::user();

            $submittedReports = UserReport::where('reporter_id', $user->id);
            $receivedReports = UserReport::where('reported_user_id', $user->id);

            // Get reports by type
            $reportsByType = UserReport::where('reporter_id', $user->id)
                ->selectRaw('report_type, COUNT(*) as count')
                ->groupBy('report_type')
                ->pluck('count', 'report_type')
                ->toArray();

            // Get reports by status
            $reportsByStatus = UserReport::where('reporter_id', $user->id)
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();

            $stats = [
                'submitted_reports' => [
                    'total' => $submittedReports->count(),
                    'pending' => $submittedReports->where('status', 'pending')->count(),
                    'resolved' => $submittedReports->where('status', 'resolved')->count(),
                ],
                'received_reports' => [
                    'total' => $receivedReports->count(),
                    'pending' => $receivedReports->where('status', 'pending')->count(),
                    'resolved' => $receivedReports->where('status', 'resolved')->count(),
                ],
                'reports_by_type' => $reportsByType,
                'reports_by_status' => $reportsByStatus,
                'recent_activity' => UserReport::where(function($q) use ($user) {
                    $q->where('reporter_id', $user->id)
                      ->orWhere('reported_user_id', $user->id);
                })->where('created_at', '>=', now()->subDays(30))->count()
            ];

            return response()->json([
                'status' => 'success',
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengambil statistik laporan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper method to validate related entity exists
     */
    private function validateRelatedEntity($type, $id)
    {
        switch ($type) {
            case 'event':
                if (!Event::find($id)) {
                    throw new \Exception('Event tidak ditemukan');
                }
                break;
            case 'match':
                if (!MatchHistory::find($id)) {
                    throw new \Exception('Match tidak ditemukan');
                }
                break;
            case 'community':
                if (!Community::find($id)) {
                    throw new \Exception('Community tidak ditemukan');
                }
                break;
            case 'rating':
                if (!PlayerRating::find($id)) {
                    throw new \Exception('Rating tidak ditemukan');
                }
                break;
        }
    }

    /**
     * Helper method to determine priority based on report type
     */
    private function determinePriority($reportType)
    {
        $highPriorityTypes = ['harassment', 'cheating', 'inappropriate_behavior'];
        $mediumPriorityTypes = ['misconduct', 'no_show'];
        
        if (in_array($reportType, $highPriorityTypes)) {
            return 'high';
        } elseif (in_array($reportType, $mediumPriorityTypes)) {
            return 'medium';
        } else {
            return 'low';
        }
    }
}
