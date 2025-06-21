<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            // Add new notification types
            $table->dropColumn('type');
        });
        
        Schema::table('notifications', function (Blueprint $table) {
            $table->enum('type', [
                'event_reminder', 
                'match_result', 
                'rating_received', 
                'credit_score_change', 
                'event_cancelled', 
                'waitlist_promoted', 
                'new_event', 
                'community_invite',
                'event_reminder_24h',
                'match_reminder_1h',
                'event_joined',
                'event_left',
                'match_assigned',
                'player_overridden'
            ])->after('user_id');
            
            // Add scheduling fields
            $table->foreignId('event_id')->nullable()->constrained('events')->onDelete('cascade')->after('user_id');
            $table->foreignId('match_id')->nullable()->constrained('match_history')->onDelete('cascade')->after('event_id');
            $table->timestamp('scheduled_for')->nullable()->after('data');
            $table->boolean('is_sent')->default(false)->after('is_read');
            $table->timestamp('sent_at')->nullable()->after('is_sent');
            
            // Add indexes for better performance
            $table->index(['scheduled_for']);
            $table->index(['is_sent']);
            $table->index(['event_id']);
            $table->index(['match_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropForeign(['event_id']);
            $table->dropForeign(['match_id']);
            $table->dropColumn([
                'event_id',
                'match_id', 
                'scheduled_for',
                'is_sent',
                'sent_at'
            ]);
            $table->dropIndex(['scheduled_for']);
            $table->dropIndex(['is_sent']);
            $table->dropIndex(['event_id']);
            $table->dropIndex(['match_id']);
        });
        
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn('type');
        });
        
        Schema::table('notifications', function (Blueprint $table) {
            $table->enum('type', ['event_reminder', 'match_result', 'rating_received', 'credit_score_change', 'event_cancelled', 'waitlist_promoted', 'new_event', 'community_invite'])->after('user_id');
        });
    }
}; 