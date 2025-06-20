<?php

namespace App\Services;

use App\Models\User;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\CreditScoreLog;
use App\Models\PlayerRating;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CreditScoreService
{
    /**
     * Initialize user credit score (for new users)
     */
    public function initializeUserCreditScore(User $user)
    {
        if ($user->credit_score === null) {
            $user->update(['credit_score' => 100]);
            
            $this->logCreditScoreChange($user, 100, 'initialization', 
                'Credit score awal untuk user baru');
        }
    }

    /**
     * Process event cancellation with automatic penalty calculation
     */
    public function processEventCancellation(User $user, Event $event, $reason = null)
    {
        $penalty = $this->calculateCancellationPenalty($event);
        
        if ($penalty > 0) {
            $this->applyCreditScoreChange($user, -$penalty, 'cancellation_penalty', 
                "Penalty pembatalan event: {$event->title}", $event->id);
        }

        return $penalty;
    }

    /**
     * Process no-show penalty
     */
    public function processNoShowPenalty(User $user, Event $event, User $reportedBy, $reason = null)
    {
        $penalty = 30; // Fixed penalty for no-show
        
        $this->applyCreditScoreChange($user, -$penalty, 'no_show_penalty', 
            "No-show penalty untuk event: {$event->title}", $event->id, [
                'reported_by' => $reportedBy->id,
                'reporter_name' => $reportedBy->name,
                'penalty_amount' => $penalty,
                'reason' => $reason
            ]);

        return $penalty;
    }

    /**
     * Process event completion bonus
     */
    public function processEventCompletionBonus(User $user, Event $event)
    {
        // Check if bonus already given
        $existingBonus = CreditScoreLog::where([
            'user_id' => $user->id,
            'event_id' => $event->id,
            'type' => 'event_completion_bonus'
        ])->first();

        if ($existingBonus) {
            return 0;
        }

        $baseBonus = 2; // Base completion bonus
        $this->applyCreditScoreChange($user, $baseBonus, 'event_completion_bonus', 
            "Bonus completion event: {$event->title}", $event->id, [
                'completion_date' => now(),
                'base_bonus' => $baseBonus
            ]);

        // Check for consecutive bonus
        $consecutiveBonus = $this->checkAndApplyConsecutiveBonus($user);
        
        return $baseBonus + $consecutiveBonus;
    }

    /**
     * Process good rating bonus
     */
    public function processGoodRatingBonus(User $user, PlayerRating $rating)
    {
        $overallRating = $rating->getOverallRating();
        
        if ($overallRating >= 4.0) {
            $bonus = 1; // Bonus for good rating (4+ stars)
            
            $this->applyCreditScoreChange($user, $bonus, 'good_rating_bonus', 
                "Bonus rating baik: {$overallRating}/5", $rating->event_id, [
                    'rating_id' => $rating->id,
                    'overall_rating' => $overallRating,
                    'rating_from' => $rating->ratingUser->name,
                    'bonus_amount' => $bonus
                ]);

            return $bonus;
        }

        return 0;
    }

    /**
     * Check credit score restrictions for user
     */
    public function checkCreditScoreRestrictions(User $user)
    {
        $score = $user->credit_score;

        return [
            'can_join_events' => $score >= 40,
            'can_join_premium_events' => $score >= 60,
            'max_events_per_week' => $this->getMaxEventsPerWeek($score),
            'restrictions' => $this->getCreditScoreRestrictions($score),
            'warning_message' => $this->getWarningMessage($score)
        ];
    }

    /**
     * Get user's credit score statistics
     */
    public function getCreditScoreStatistics(User $user)
    {
        $logs = CreditScoreLog::where('user_id', $user->id);
        
        return [
            'current_score' => $user->credit_score,
            'total_earned' => $logs->where('change_amount', '>', 0)->sum('change_amount'),
            'total_lost' => abs($logs->where('change_amount', '<', 0)->sum('change_amount')),
            'total_transactions' => $logs->count(),
            'penalties_count' => $logs->whereIn('type', ['cancellation_penalty', 'no_show_penalty'])->count(),
            'bonuses_count' => $logs->whereIn('type', ['event_completion_bonus', 'good_rating_bonus', 'consecutive_events_bonus'])->count(),
            'last_30_days_change' => $logs->where('created_at', '>=', now()->subDays(30))->sum('change_amount'),
            'restrictions' => $this->checkCreditScoreRestrictions($user),
            'recent_activities' => $logs->with(['event.sport'])->orderBy('created_at', 'desc')->take(5)->get()
        ];
    }

    /**
     * Check if user can join specific event based on credit score
     */
    public function canJoinEvent(User $user, Event $event)
    {
        $restrictions = $this->checkCreditScoreRestrictions($user);
        
        // Basic join restriction
        if (!$restrictions['can_join_events']) {
            return [
                'can_join' => false,
                'reason' => 'Credit score terlalu rendah untuk join event.'
            ];
        }

        // Premium event restriction
        if ($event->is_premium && !$restrictions['can_join_premium_events']) {
            return [
                'can_join' => false,
                'reason' => 'Credit score tidak mencukupi untuk premium event.'
            ];
        }

        // Weekly limit check
        $weeklyJoins = $this->getUserWeeklyEventJoins($user);
        if ($weeklyJoins >= $restrictions['max_events_per_week']) {
            return [
                'can_join' => false,
                'reason' => 'Telah mencapai batas maksimal event per minggu.'
            ];
        }

        return [
            'can_join' => true,
            'reason' => null
        ];
    }

    /**
     * Auto-process credit score for completed events
     */
    public function autoProcessCompletedEvent(Event $event)
    {
        $checkedInParticipants = EventParticipant::where('event_id', $event->id)
            ->where('status', 'checked_in')
            ->with('user')
            ->get();

        $processed = 0;
        foreach ($checkedInParticipants as $participant) {
            $bonus = $this->processEventCompletionBonus($participant->user, $event);
            if ($bonus > 0) {
                $processed++;
            }
        }

        return $processed;
    }

    /**
     * Get penalty preview for cancellation
     */
    public function getCancellationPenaltyPreview(Event $event)
    {
        $penalty = $this->calculateCancellationPenalty($event);
        $hoursRemaining = $this->getHoursBeforeEvent($event);
        
        return [
            'penalty_amount' => $penalty,
            'hours_remaining' => $hoursRemaining,
            'penalty_level' => $this->getPenaltyLevel($penalty),
            'can_cancel_free' => $penalty === 0,
            'warning_message' => $this->getCancellationWarning($penalty, $hoursRemaining)
        ];
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
        return max(0, now()->diffInHours($eventDateTime, false));
    }

    /**
     * Apply credit score change and log it
     */
    private function applyCreditScoreChange(User $user, $amount, $type, $description, $eventId = null, $metadata = [])
    {
        $oldScore = $user->credit_score;
        $newScore = max(0, min(100, $oldScore + $amount)); // Keep between 0-100

        // Update user credit score
        $user->update(['credit_score' => $newScore]);

        // Log the change
        $this->logCreditScoreChange($user, $amount, $type, $description, $eventId, array_merge($metadata, [
            'old_score' => $oldScore,
            'new_score' => $newScore
        ]));
    }

    /**
     * Log credit score change
     */
    private function logCreditScoreChange(User $user, $amount, $type, $description, $eventId = null, $metadata = [])
    {
        CreditScoreLog::create([
            'user_id' => $user->id,
            'event_id' => $eventId,
            'type' => $type,
            'old_score' => $metadata['old_score'] ?? $user->credit_score - $amount,
            'new_score' => $metadata['new_score'] ?? $user->credit_score,
            'change_amount' => $amount,
            'description' => $description,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Check and apply consecutive events bonus
     */
    private function checkAndApplyConsecutiveBonus(User $user)
    {
        // Get last 5 completed events
        $recentCompletions = EventParticipant::where('user_id', $user->id)
            ->where('status', 'checked_in')
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
                $bonus = 5; // Give 5 point bonus
                $this->applyCreditScoreChange($user, $bonus, 'consecutive_events_bonus', 
                    'Bonus 5 event berturut-turut', null, [
                        'consecutive_count' => 5,
                        'bonus_amount' => $bonus
                    ]);
                
                return $bonus;
            }
        }

        return 0;
    }

    /**
     * Get maximum events per week based on credit score
     */
    private function getMaxEventsPerWeek($score)
    {
        if ($score >= 80) {
            return 999; // Unlimited
        } elseif ($score >= 60) {
            return 10;  // High limit
        } elseif ($score >= 40) {
            return 3;   // Limited
        } else {
            return 0;   // No events
        }
    }

    /**
     * Get user's weekly event joins count
     */
    private function getUserWeeklyEventJoins(User $user)
    {
        return EventParticipant::where('user_id', $user->id)
            ->where('created_at', '>=', now()->startOfWeek())
            ->count();
    }

    /**
     * Get credit score restrictions
     */
    private function getCreditScoreRestrictions($score)
    {
        if ($score >= 80) {
            return [
                'level' => 'excellent',
                'color' => 'green',
                'restrictions' => []
            ];
        } elseif ($score >= 60) {
            return [
                'level' => 'good',
                'color' => 'blue',
                'restrictions' => ['limited_premium_events']
            ];
        } elseif ($score >= 40) {
            return [
                'level' => 'warning',
                'color' => 'yellow',
                'restrictions' => ['no_premium_events', 'limited_event_joins']
            ];
        } else {
            return [
                'level' => 'restricted',
                'color' => 'red',
                'restrictions' => ['no_event_joins', 'account_review']
            ];
        }
    }

    /**
     * Get warning message based on score
     */
    private function getWarningMessage($score)
    {
        if ($score >= 80) {
            return 'Credit score excellent! Tidak ada pembatasan.';
        } elseif ($score >= 60) {
            return 'Credit score baik. Akses terbatas ke premium event.';
        } elseif ($score >= 40) {
            return 'Credit score rendah. Maksimal 3 event per minggu, tidak dapat join premium event.';
        } else {
            return 'Credit score sangat rendah. Tidak dapat join event baru. Hubungi admin.';
        }
    }

    /**
     * Get penalty level description
     */
    private function getPenaltyLevel($penalty)
    {
        if ($penalty === 0) return 'no_penalty';
        if ($penalty <= 10) return 'low';
        if ($penalty <= 20) return 'medium';
        return 'high';
    }

    /**
     * Get cancellation warning message
     */
    private function getCancellationWarning($penalty, $hoursRemaining)
    {
        if ($penalty === 0) {
            return 'Pembatalan gratis - lebih dari 24 jam sebelum event.';
        }
        
        return "Penalty pembatalan: -{$penalty} poin. Sisa waktu: {$hoursRemaining} jam.";
    }
} 