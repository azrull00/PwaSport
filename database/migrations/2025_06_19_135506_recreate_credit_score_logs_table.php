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
        // Drop existing table
        Schema::dropIfExists('credit_score_logs');
        
        // Recreate with correct structure
        Schema::create('credit_score_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('event_id')->nullable()->constrained('events')->onDelete('set null');
            $table->string('type', 50); // penalty, bonus, manual_adjustment
            $table->integer('change_amount');
            $table->integer('old_score');
            $table->integer('new_score');
            $table->text('description');
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['user_id', 'created_at']);
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_score_logs');
        
        // Restore original table structure
        Schema::create('credit_score_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('event_id')->nullable()->constrained('events')->onDelete('set null');
            $table->foreignId('match_id')->nullable()->constrained('match_history')->onDelete('set null');
            $table->foreignId('admin_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('action_type', ['no_show', 'late_cancel', 'poor_rating', 'good_behavior', 'bonus', 'manual_adjustment']);
            $table->integer('points_change');
            $table->integer('previous_score');
            $table->integer('new_score');
            $table->text('reason');
            $table->timestamps();
        });
    }
};
