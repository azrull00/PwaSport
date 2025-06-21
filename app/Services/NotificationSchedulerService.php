<?php

namespace App\Services;

use App\Models\Event;
use App\Models\MatchHistory;
use App\Models\Notification;
use App\Models\EventParticipant;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotificationSchedulerService
{
    /**
     * Schedule notifications for events (H-24 hours)
     */
    public function scheduleEventNotifications()
    {
        $tomorrow = Carbon::tomorrow();
        
        // Get events happening tomorrow that don't have H-24 notifications yet
        $events = Event::whereDate('event_date', $tomorrow)
            ->where('status', '!=', 'cancelled')
            ->get();

        $notificationsCreated = 0;

        foreach ($events as $event) {
            // Check if H-24 notification already exists
            $existingNotification = Notification::where('event_id', $event->id)
                ->where('type', 'event_reminder_24h')
                ->exists();

            if (!$existingNotification) {
                $this->createEventReminderNotifications($event);
                $notificationsCreated++;
            }
        }

        Log::info("Event notifications scheduled for {$notificationsCreated} events");
        return $notificationsCreated;
    }

    /**
     * Schedule notifications for matches (H-1 hour)
     */
    public function scheduleMatchNotifications()
    {
        $oneHourFromNow = Carbon::now()->addHour();
        $twoHoursFromNow = Carbon::now()->addHours(2);

        // Get matches starting in the next 1-2 hours that don't have H-1 notifications yet
        $matches = MatchHistory::with(['event', 'player1', 'player2'])
            ->where('match_status', 'scheduled')
            ->whereHas('event', function($query) use ($oneHourFromNow, $twoHoursFromNow) {
                $query->whereDate('event_date', $oneHourFromNow->toDateString())
                      ->whereTime('start_time', '>=', $oneHourFromNow->toTimeString())
                      ->whereTime('start_time', '<=', $twoHoursFromNow->toTimeString());
            })
            ->get();

        $notificationsCreated = 0;

        foreach ($matches as $match) {
            // Check if H-1 notification already exists for this match
            $existingNotification = Notification::where('match_id', $match->id)
                ->where('type', 'match_reminder_1h')
                ->exists();

            if (!$existingNotification) {
                $this->createMatchReminderNotifications($match);
                $notificationsCreated++;
            }
        }

        Log::info("Match notifications scheduled for {$notificationsCreated} matches");
        return $notificationsCreated;
    }

    /**
     * Create event reminder notifications for all participants
     */
    private function createEventReminderNotifications(Event $event)
    {
        $participants = EventParticipant::where('event_id', $event->id)
            ->where('status', 'confirmed')
            ->get();

        foreach ($participants as $participant) {
            Notification::create([
                'user_id' => $participant->user_id,
                'event_id' => $event->id,
                'type' => 'event_reminder_24h',
                'title' => 'Reminder: Event Besok!',
                'message' => "Jangan lupa! Event '{$event->title}' akan dimulai besok pada {$this->formatDateTime($event->event_date, $event->start_time)}. Lokasi: {$event->location_name}",
                'data' => json_encode([
                    'event_id' => $event->id,
                    'event_title' => $event->title,
                    'event_date' => $event->event_date,
                    'start_time' => $event->start_time,
                    'location' => $event->location_name,
                    'reminder_type' => '24h'
                ]),
                'is_read' => false,
                'scheduled_for' => Carbon::parse($event->event_date)->subDay(),
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        Log::info("Created event reminder notifications for event: {$event->title} ({$participants->count()} participants)");
    }

    /**
     * Create match reminder notifications for both players
     */
    private function createMatchReminderNotifications(MatchHistory $match)
    {
        $matchDateTime = Carbon::parse($match->event->event_date . ' ' . $match->event->start_time);
        $reminderTime = $matchDateTime->subHour();

        $players = [$match->player1, $match->player2];
        
        foreach ($players as $player) {
            if (!$player) continue;

            $opponentName = $player->id === $match->player1_id 
                ? $match->player2->name 
                : $match->player1->name;

            Notification::create([
                'user_id' => $player->id,
                'event_id' => $match->event_id,
                'match_id' => $match->id,
                'type' => 'match_reminder_1h',
                'title' => 'Match Dimulai 1 Jam Lagi!',
                'message' => "Match Anda vs {$opponentName} akan dimulai dalam 1 jam di Court {$match->court_number}. Event: {$match->event->title}",
                'data' => json_encode([
                    'match_id' => $match->id,
                    'event_id' => $match->event_id,
                    'event_title' => $match->event->title,
                    'court_number' => $match->court_number,
                    'opponent_name' => $opponentName,
                    'match_time' => $matchDateTime->toISOString(),
                    'reminder_type' => '1h'
                ]),
                'is_read' => false,
                'scheduled_for' => $reminderTime,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        Log::info("Created match reminder notifications for match: {$match->id} on court {$match->court_number}");
    }

    /**
     * Process and send due notifications
     */
    public function processPendingNotifications()
    {
        $dueNotifications = Notification::where('scheduled_for', '<=', now())
            ->where('is_sent', false)
            ->orderBy('scheduled_for')
            ->get();

        $sentCount = 0;

        foreach ($dueNotifications as $notification) {
            try {
                // Mark as sent
                $notification->update([
                    'is_sent' => true,
                    'sent_at' => now()
                ]);

                // Here you can integrate with push notification service
                // For now, we'll just mark as sent
                $this->sendPushNotification($notification);
                
                $sentCount++;
                
            } catch (\Exception $e) {
                Log::error("Failed to send notification {$notification->id}: " . $e->getMessage());
            }
        }

        if ($sentCount > 0) {
            Log::info("Sent {$sentCount} pending notifications");
        }

        return $sentCount;
    }

    /**
     * Create instant notification (for immediate events)
     */
    public function createInstantNotification($userId, $type, $title, $message, $data = [])
    {
        return Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => json_encode($data),
            'is_read' => false,
            'is_sent' => true,
            'sent_at' => now(),
            'scheduled_for' => now(),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    /**
     * Create notification when user joins event
     */
    public function notifyEventJoined($userId, Event $event)
    {
        return $this->createInstantNotification(
            $userId,
            'event_joined',
            'Berhasil Bergabung!',
            "Anda telah bergabung dengan event '{$event->title}' pada {$this->formatDateTime($event->event_date, $event->start_time)}",
            [
                'event_id' => $event->id,
                'event_title' => $event->title,
                'event_date' => $event->event_date,
                'start_time' => $event->start_time
            ]
        );
    }

    /**
     * Create notification when user leaves event
     */
    public function notifyEventLeft($userId, Event $event)
    {
        return $this->createInstantNotification(
            $userId,
            'event_left',
            'Keluar dari Event',
            "Anda telah keluar dari event '{$event->title}'",
            [
                'event_id' => $event->id,
                'event_title' => $event->title
            ]
        );
    }

    /**
     * Create notification when match is assigned
     */
    public function notifyMatchAssigned($userId, MatchHistory $match)
    {
        $opponentName = $userId === $match->player1_id 
            ? $match->player2->name 
            : $match->player1->name;

        return $this->createInstantNotification(
            $userId,
            'match_assigned',
            'Match Baru Ditugaskan!',
            "Anda akan bermain melawan {$opponentName} di Court {$match->court_number}",
            [
                'match_id' => $match->id,
                'event_id' => $match->event_id,
                'court_number' => $match->court_number,
                'opponent_name' => $opponentName
            ]
        );
    }

    /**
     * Create notification when player is overridden by host
     */
    public function notifyPlayerOverridden($userId, Event $event, $reason = null)
    {
        $message = "Host telah mengganti Anda dari match di event '{$event->title}'";
        if ($reason) {
            $message .= ". Alasan: {$reason}";
        }

        return $this->createInstantNotification(
            $userId,
            'player_overridden',
            'Diganti dari Match',
            $message,
            [
                'event_id' => $event->id,
                'event_title' => $event->title,
                'reason' => $reason
            ]
        );
    }

    /**
     * Send push notification (placeholder for external service integration)
     */
    private function sendPushNotification(Notification $notification)
    {
        // Placeholder for push notification service integration
        // You can integrate with Firebase, Pusher, or other services here
        
        Log::info("Push notification sent to user {$notification->user_id}: {$notification->title}");
        
        // For now, just mark as sent in database
        return true;
    }

    /**
     * Format date time for display
     */
    private function formatDateTime($date, $time)
    {
        $dateTime = Carbon::parse($date . ' ' . $time);
        return $dateTime->format('d M Y, H:i');
    }

    /**
     * Clean up old notifications (optional - can be called weekly)
     */
    public function cleanupOldNotifications($daysOld = 30)
    {
        $cutoffDate = Carbon::now()->subDays($daysOld);
        
        $deletedCount = Notification::where('created_at', '<', $cutoffDate)
            ->where('is_read', true)
            ->delete();

        Log::info("Cleaned up {$deletedCount} old notifications");
        return $deletedCount;
    }
} 