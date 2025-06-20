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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('type', ['event_reminder', 'match_result', 'rating_received', 'credit_score_change', 'event_cancelled', 'waitlist_promoted', 'new_event', 'community_invite']);
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable(); // Additional data (event_id, match_id, etc.)
            $table->boolean('is_read')->default(false);
            $table->datetime('read_at')->nullable();
            $table->timestamps();
            
            $table->index(['user_id']);
            $table->index(['type']);
            $table->index(['is_read']);
            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
