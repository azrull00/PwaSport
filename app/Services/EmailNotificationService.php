<?php

namespace App\Services;

use App\Models\User;
use App\Models\Event;
use App\Models\Notification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class EmailNotificationService
{
    /**
     * Send email notification for event reminder
     */
    public function sendEventReminder(User $user, Event $event)
    {
        try {
            $subject = "Pengingat Event: {$event->title}";
            $message = "Halo {$user->name}, event '{$event->title}' akan dimulai besok pada {$event->start_time}.";
            
            $this->sendEmail($user->email, $subject, $message);
            $this->createNotification($user->id, 'event_reminder', $subject, $message, ['event_id' => $event->id]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send event reminder: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send email notification for match result
     */
    public function sendMatchResult(User $user, $matchData)
    {
        try {
            $subject = "Hasil Match Telah Diinput";
            $message = "Hasil match Anda telah diinput. Silakan beri rating untuk lawan main Anda.";
            
            $this->sendEmail($user->email, $subject, $message);
            $this->createNotification($user->id, 'match_result', $subject, $message, $matchData);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send match result: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send email notification for rating received
     */
    public function sendRatingReceived(User $user, $ratingData)
    {
        try {
            $subject = "Anda Mendapat Rating Baru";
            $message = "Anda telah menerima rating baru dari partner main Anda.";
            
            $this->sendEmail($user->email, $subject, $message);
            $this->createNotification($user->id, 'rating_received', $subject, $message, $ratingData);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send rating notification: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send email notification for player rating
     */
    public function sendPlayerRating(User $user, $rating)
    {
        try {
            $subject = "Anda Mendapat Rating Baru";
            $ratingUser = $rating->ratingUser;
            $overallRating = $rating->getOverallRating();
            
            $message = "Anda telah menerima rating {$overallRating}/5 dari {$ratingUser->name} untuk pertandingan di '{$rating->event->title}'.";
            
            $this->sendEmail($user->email, $subject, $message);
            $this->createNotification($user->id, 'player_rating', $subject, $message, [
                'rating_id' => $rating->id,
                'event_id' => $rating->event_id,
                'overall_rating' => $overallRating
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send player rating notification: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send email notification for credit score change
     */
    public function sendCreditScoreChange(User $user, $scoreData)
    {
        try {
            $subject = "Perubahan Credit Score";
            $message = "Credit score Anda telah berubah. Score saat ini: {$scoreData['new_score']}.";
            
            $this->sendEmail($user->email, $subject, $message);
            $this->createNotification($user->id, 'credit_score_change', $subject, $message, $scoreData);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send credit score notification: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send email notification for event cancellation
     */
    public function sendEventCancellation(User $user, Event $event, $reason = '')
    {
        try {
            $subject = "Event Dibatalkan: {$event->title}";
            $message = "Event '{$event->title}' telah dibatalkan. " . ($reason ? "Alasan: {$reason}" : '');
            
            $this->sendEmail($user->email, $subject, $message);
            $this->createNotification($user->id, 'event_cancelled', $subject, $message, ['event_id' => $event->id]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send event cancellation: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send email notification for waitlist promotion
     */
    public function sendWaitlistPromotion(User $user, Event $event)
    {
        try {
            $subject = "Anda Telah Dikonfirmasi untuk Event: {$event->title}";
            $message = "Selamat! Anda telah dipromosikan dari waiting list dan dikonfirmasi untuk mengikuti event '{$event->title}'.";
            
            $this->sendEmail($user->email, $subject, $message);
            $this->createNotification($user->id, 'waitlist_promoted', $subject, $message, ['event_id' => $event->id]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send waitlist promotion: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send email notification for new event
     */
    public function sendNewEventNotification(User $user, Event $event)
    {
        try {
            $subject = "Event Baru Tersedia: {$event->title}";
            $message = "Ada event baru yang mungkin menarik untuk Anda: '{$event->title}' pada {$event->start_time}.";
            
            $this->sendEmail($user->email, $subject, $message);
            $this->createNotification($user->id, 'new_event', $subject, $message, ['event_id' => $event->id]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send new event notification: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send email notification for community invite
     */
    public function sendCommunityInvite(User $user, $communityData)
    {
        try {
            $subject = "Undangan Bergabung Komunitas";
            $message = "Anda diundang untuk bergabung dengan komunitas '{$communityData['name']}'.";
            
            $this->sendEmail($user->email, $subject, $message);
            $this->createNotification($user->id, 'community_invite', $subject, $message, $communityData);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send community invite: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send email notification for user report
     */
    public function sendReportNotification(User $user, $report)
    {
        try {
            $subject = "Laporan Baru Mengenai Akun Anda";
            $message = "Ada laporan baru mengenai akun Anda. Tim kami akan meninjau dan memberikan update dalam 24-48 jam.";
            
            $this->sendEmail($user->email, $subject, $message);
            $this->createNotification($user->id, 'user_reported', $subject, $message, [
                'report_id' => $report->id,
                'report_type' => $report->report_type
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send report notification: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send email notification for report resolution
     */
    public function sendReportResolution(User $user, $report, $resolution)
    {
        try {
            $subject = "Update Laporan: " . ucfirst($report->status);
            $message = "Laporan Anda dengan ID #{$report->id} telah {$report->status}. ";
            
            if ($report->status === 'resolved') {
                $message .= "Resolusi: {$resolution}";
            } elseif ($report->status === 'dismissed') {
                $message .= "Laporan ditolak karena tidak cukup bukti atau tidak melanggar aturan.";
            }
            
            $this->sendEmail($user->email, $subject, $message);
            $this->createNotification($user->id, 'report_resolved', $subject, $message, [
                'report_id' => $report->id,
                'status' => $report->status,
                'resolution' => $resolution
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send report resolution: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send basic email
     */
    private function sendEmail($to, $subject, $message)
    {
        Mail::raw($message, function ($mail) use ($to, $subject) {
            $mail->to($to)
                 ->subject($subject)
                 ->from(config('mail.from.address'), config('mail.from.name'));
        });
    }

    /**
     * Create in-app notification
     */
    private function createNotification($userId, $type, $title, $message, $data = null)
    {
        Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
        ]);
    }

    /**
     * Send bulk notifications to multiple users
     */
    public function sendBulkNotification(array $userIds, $type, $title, $message, $data = null)
    {
        foreach ($userIds as $userId) {
            $user = User::find($userId);
            if ($user) {
                $this->sendEmail($user->email, $title, $message);
                $this->createNotification($userId, $type, $title, $message, $data);
            }
        }
    }
} 