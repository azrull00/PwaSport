<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\NotificationSchedulerService;

class CleanupNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:cleanup {--days=30 : Number of days to keep notifications}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old read notifications older than specified days';

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
        $days = $this->option('days');
        
        $this->info("Cleaning up notifications older than {$days} days...");

        try {
            $deletedCount = $this->notificationService->cleanupOldNotifications($days);
            
            $this->info("✅ Successfully cleaned up {$deletedCount} old notifications");
            
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Error cleaning up notifications: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
} 