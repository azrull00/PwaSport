<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\NotificationSchedulerService;

class ProcessNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:process 
                            {--events : Schedule event notifications only}
                            {--matches : Schedule match notifications only}
                            {--send : Send pending notifications only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process and send scheduled notifications for events and matches';

    protected $notificationService;

    /**
     * Create a new command instance.
     */
    public function __construct(NotificationSchedulerService $notificationService)
    {
        parent::__construct();
        $this->notificationService = $notificationService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting notification processing...');

        $eventsOnly = $this->option('events');
        $matchesOnly = $this->option('matches');
        $sendOnly = $this->option('send');

        $totalProcessed = 0;

        try {
            // Schedule event notifications (H-24 hours)
            if (!$matchesOnly && !$sendOnly) {
                $this->info('Scheduling event notifications...');
                $eventNotifications = $this->notificationService->scheduleEventNotifications();
                $this->info("✅ Scheduled {$eventNotifications} event notifications");
                $totalProcessed += $eventNotifications;
            }

            // Schedule match notifications (H-1 hour)
            if (!$eventsOnly && !$sendOnly) {
                $this->info('Scheduling match notifications...');
                $matchNotifications = $this->notificationService->scheduleMatchNotifications();
                $this->info("✅ Scheduled {$matchNotifications} match notifications");
                $totalProcessed += $matchNotifications;
            }

            // Process and send pending notifications
            if (!$eventsOnly && !$matchesOnly) {
                $this->info('Sending pending notifications...');
                $sentNotifications = $this->notificationService->processPendingNotifications();
                $this->info("✅ Sent {$sentNotifications} pending notifications");
                $totalProcessed += $sentNotifications;
            }

            $this->info("🎉 Notification processing completed! Total processed: {$totalProcessed}");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Error processing notifications: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
} 