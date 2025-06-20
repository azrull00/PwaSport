<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CreditScoreLog;
use App\Models\User;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Services\EmailNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CreditScoreController extends Controller
{
    protected $notificationService;

    public function __construct(EmailNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Get user's credit score history
     */
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            $query = CreditScoreLog::with(['user.profile', 'event.sport'])
                ->where('user_id', $user->id);

            // Filter by type
            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            // Filter by date range
            if ($request->has('date_from')) {
                $query->where('created_at', '>=', $request->date_from);
            }
            if ($request->has('date_to')) {
                $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
            }

            // Order by newest first
            $query->orderBy('created_at', 'desc');

            // Pagination
            $perPage = $request->get('per_page', 15);
            $logs = $query->paginate($perPage);

            // Get current score and summary
            $summary = $this->getCreditScoreSummary($user);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'logs' => $logs,
                    'summary' => $summary
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil riwayat credit score.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get credit score summary for user
     */
    public function getSummary(Request $request)
    {
        try {
            $user = Auth::user();
            $summary = $this->getCreditScoreSummary($user);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'summary' => $summary
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil ringkasan credit score.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process event cancellation penalty
     */
    public function processCancellationPenalty(Request $request)
    {
        try {
            $request->validate([
                'event_id' => 'required|exists:events,id',
                'reason' => 'nullable|string|max:500',
            ]);

            $user = Auth::user();
            $event = Event::findOrFail($request->event_id);

            // Check if user is participant
            $participation = EventParticipant::where([
                'event_id' => $request->event_id,
                'user_id' => $user->id,
            ])->first();

            if (!$participation) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Anda tidak terdaftar dalam event ini.'
                ], 422);
            }

            // Calculate penalty based on time remaining
            $penalty = $this->calculateCancellationPenalty($event);
            
            if ($penalty > 0) {
                DB::beginTransaction();

                // Apply penalty
                $this->applyCreditScoreChange($user, -$penalty, 'penalty', 
                    "Penalty pembatalan event: {$event->title}", $event->id, [
                        'cancellation_hours_before' => $this->getHoursBeforeEvent($event),
                        'original_penalty' => $penalty,
                        'reason' => $request->reason
                    ]);

                // Remove from event
                $participation->delete();

                // Send notification
                $this->notificationService->sendCreditScoreChange($user, [
                    'type' => 'penalty',
                    'amount' => $penalty,
                    'new_score' => $user->fresh()->credit_score,
                    'reason' => 'Event cancellation'
                ]);

                DB::commit();

                return response()->json([
                    'status' => 'success',
                    'message' => "Event berhasil dibatalkan. Penalty: -{$penalty} poin.",
                    'data' => [
                        'penalty_applied' => $penalty,
                        'new_credit_score' => $user->fresh()->credit_score
                    ]
                ]);
            } else {
                // Remove from event without penalty
                $participation->delete();

                return response()->json([
                    'status' => 'success',
                    'message' => 'Event berhasil dibatalkan tanpa penalty.',
                    'data' => [
                        'penalty_applied' => 0,
                        'new_credit_score' => $user->credit_score
                    ]
                ]);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat memproses pembatalan event.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process no-show penalty (called by host)
     */
    public function processNoShowPenalty(Request $request)
    {
        try {
            $request->validate([
                'event_id' => 'required|exists:events,id',
                'user_id' => 'required|exists:users,id',
                'reason' => 'nullable|string|max:500',
            ]);

            $hostUser = Auth::user();
            $event = Event::findOrFail($request->event_id);
            $noShowUser = User::findOrFail($request->user_id);

            // Only host can report no-show
            if ($event->host_id !== $hostUser->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Hanya host yang dapat melaporkan no-show.'
                ], 403);
            }

            // Check if user was registered
            $participation = EventParticipant::where([
                'event_id' => $request->event_id,
                'user_id' => $request->user_id,
                'status' => 'confirmed'
            ])->first();

            if (!$participation) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User tidak terdaftar atau sudah tidak konfirmasi.'
                ], 422);
            }

            // Check if already reported
            $existingPenalty = CreditScoreLog::where([
                'user_id' => $request->user_id,
                'event_id' => $request->event_id,
                'type' => 'no_show_penalty'
            ])->first();

            if ($existingPenalty) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No-show sudah pernah dilaporkan untuk user ini.'
                ], 422);
            }

            DB::beginTransaction();

            // Apply no-show penalty
            $penalty = 30; // Fixed penalty for no-show
            $this->applyCreditScoreChange($noShowUser, -$penalty, 'no_show_penalty', 
                "No-show penalty untuk event: {$event->title}", $event->id, [
                    'reported_by' => $hostUser->id,
                    'reason' => $request->reason
                ]);

            // Update participation status
            $participation->update(['status' => 'no_show']);

            // Send notification
            $this->notificationService->sendCreditScoreChange($noShowUser, [
                'type' => 'no_show_penalty',
                'amount' => $penalty,
                'new_score' => $noShowUser->fresh()->credit_score,
                'reason' => 'No-show reported by host'
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => "No-show penalty berhasil diterapkan. Penalty: -{$penalty} poin.",
                'data' => [
                    'penalty_applied' => $penalty,
                    'user_new_score' => $noShowUser->fresh()->credit_score
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat memproses no-show penalty.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process event completion bonus
     */
    public function processEventCompletionBonus(Request $request)
    {
        try {
            $request->validate([
                'event_id' => 'required|exists:events,id',
                'user_id' => 'required|exists:users,id',
            ]);

            $user = Auth::user();
            $event = Event::findOrFail($request->event_id);
            $participantUser = User::findOrFail($request->user_id);

            // Check if user completed the event
            $participation = EventParticipant::where([
                'event_id' => $request->event_id,
                'user_id' => $request->user_id,
                'status' => 'checked_in'
            ])->first();

            if (!$participation) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User tidak check-in untuk event ini.'
                ], 422);
            }

            // Check if bonus already given
            $existingBonus = CreditScoreLog::where([
                'user_id' => $request->user_id,
                'event_id' => $request->event_id,
                'type' => 'event_completion_bonus'
            ])->first();

            if ($existingBonus) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Bonus completion sudah diberikan.'
                ], 422);
            }

            DB::beginTransaction();

            // Give completion bonus
            $bonus = 2; // Base completion bonus
            $this->applyCreditScoreChange($participantUser, $bonus, 'event_completion_bonus', 
                "Bonus completion event: {$event->title}", $event->id, [
                    'completion_date' => now()
                ]);

            // Check for consecutive bonus
            $consecutiveBonus = $this->checkConsecutiveEventBonus($participantUser);
            if ($consecutiveBonus > 0) {
                $this->applyCreditScoreChange($participantUser, $consecutiveBonus, 'consecutive_events_bonus', 
                    "Bonus 5 event berturut-turut", null, [
                        'consecutive_count' => 5
                    ]);
            }

            // Send notification
            $totalBonus = $bonus + $consecutiveBonus;
            $this->notificationService->sendCreditScoreChange($participantUser, [
                'type' => 'bonus',
                'amount' => $totalBonus,
                'new_score' => $participantUser->fresh()->credit_score,
                'reason' => 'Event completion bonus'
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => "Bonus completion berhasil diberikan: +{$totalBonus} poin.",
                'data' => [
                    'bonus_applied' => $totalBonus,
                    'user_new_score' => $participantUser->fresh()->credit_score
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat memberikan bonus completion.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get credit score restrictions for user
     */
    public function getRestrictions(Request $request)
    {
        try {
            $user = Auth::user();
            $restrictions = $this->getCreditScoreRestrictions($user);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'restrictions' => $restrictions
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil informasi pembatasan.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate cancellation penalty based on time before event
     */
    private function calculateCancellationPenalty(Event $event)
    {
        $hoursBeforeEvent = $this->getHoursBeforeEvent($event);

        if ($hoursBeforeEvent >= 24) {
            return 5;  // -5 points for 24+ hours before
        } elseif ($hoursBeforeEvent >= 12) {
            return 10; // -10 points for 12-24 hours before
        } elseif ($hoursBeforeEvent >= 6) {
            return 15; // -15 points for 6-12 hours before
        } elseif ($hoursBeforeEvent >= 2) {
            return 20; // -20 points for 2-6 hours before
        } else {
            return 25; // -25 points for < 2 hours before
        }
    }

    /**
     * Get hours remaining before event
     */
    private function getHoursBeforeEvent(Event $event)
    {
        $eventDateTime = Carbon::parse($event->event_date . ' ' . $event->start_time);
        return now()->diffInHours($eventDateTime, false);
    }

    /**
     * Apply credit score change and log it
     */
    private function applyCreditScoreChange(User $user, $amount, $type, $description, $eventId = null, $additionalData = [])
    {
        $oldScore = $user->credit_score;
        $newScore = max(0, min(100, $oldScore + $amount)); // Keep between 0-100

        // Update user credit score
        $user->update(['credit_score' => $newScore]);

        // Log the change
        CreditScoreLog::create([
            'user_id' => $user->id,
            'event_id' => $eventId,
            'type' => $type,
            'old_score' => $oldScore,
            'new_score' => $newScore,
            'change_amount' => $amount,
            'description' => $description,
            'metadata' => $additionalData,
        ]);
    }

    /**
     * Check if user qualifies for consecutive events bonus
     */
    private function checkConsecutiveEventBonus(User $user)
    {
        // Get last 5 completed events
        $recentCompletions = EventParticipant::where('user_id', $user->id)
            ->where('status', 'checked_in')
            ->with('event')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        if ($recentCompletions->count() >= 5) {
            // Check if user already got consecutive bonus recently
            $recentConsecutiveBonus = CreditScoreLog::where([
                'user_id' => $user->id,
                'type' => 'consecutive_events_bonus'
            ])->where('created_at', '>=', now()->subDays(30))->first();

            if (!$recentConsecutiveBonus) {
                return 5; // Give 5 point bonus
            }
        }

        return 0;
    }

    /**
     * Get credit score summary for user
     */
    private function getCreditScoreSummary(User $user)
    {
        $logs = CreditScoreLog::where('user_id', $user->id);
        
        return [
            'current_score' => $user->credit_score,
            'total_earned' => $logs->where('change_amount', '>', 0)->sum('change_amount'),
            'total_deducted' => abs($logs->where('change_amount', '<', 0)->sum('change_amount')),
            'total_entries' => $logs->count(),
            'restrictions' => $this->getCreditScoreRestrictions($user),
            'recent_activity' => $logs->orderBy('created_at', 'desc')->take(3)->get()
        ];
    }

    /**
     * Get restrictions based on credit score
     */
    private function getCreditScoreRestrictions(User $user)
    {
        $score = $user->credit_score;

        if ($score >= 80) {
            return [
                'level' => 'excellent',
                'restrictions' => [],
                'message' => 'Tidak ada pembatasan. Credit score sangat baik!'
            ];
        } elseif ($score >= 60) {
            return [
                'level' => 'good',
                'restrictions' => ['limited_premium_events'],
                'message' => 'Akses terbatas ke beberapa premium event.'
            ];
        } elseif ($score >= 40) {
            return [
                'level' => 'warning',
                'restrictions' => ['no_premium_events', 'limited_event_joins'],
                'message' => 'Tidak dapat join premium event. Maksimal 3 event per minggu.'
            ];
        } else {
            return [
                'level' => 'restricted',
                'restrictions' => ['no_event_joins', 'account_review'],
                'message' => 'Akun dibatasi. Tidak dapat join event baru. Hubungi admin.'
            ];
        }
    }
}
