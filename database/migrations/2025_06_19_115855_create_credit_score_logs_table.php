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
        Schema::create('credit_score_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('action_type', ['no_show', 'late_cancel', 'poor_rating', 'good_behavior', 'bonus', 'manual_adjustment']);
            $table->integer('points_change'); // Positive or negative value
            $table->integer('previous_score');
            $table->integer('new_score');
            $table->text('reason');
            $table->foreignId('event_id')->nullable()->constrained('events')->onDelete('set null'); // Related event if any
            $table->foreignId('match_id')->nullable()->constrained('match_history')->onDelete('set null'); // Related match if any
            $table->foreignId('admin_user_id')->nullable()->constrained('users')->onDelete('set null'); // Admin who made manual adjustment
            $table->timestamps();
            
            $table->index(['user_id']);
            $table->index(['action_type']);
            $table->index(['event_id']);
            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_score_logs');
    }
};
