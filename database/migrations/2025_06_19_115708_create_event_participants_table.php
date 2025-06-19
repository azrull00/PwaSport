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
        Schema::create('event_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('status', ['registered', 'waiting', 'confirmed', 'checked_in', 'no_show', 'cancelled'])->default('registered');
            $table->integer('queue_position')->nullable();
            $table->boolean('is_premium_protected')->default(false); // Premium players protection
            $table->datetime('registered_at');
            $table->datetime('checked_in_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->integer('credit_score_penalty')->default(0); // Credit score penalty applied
            $table->timestamps();
            
            $table->unique(['event_id', 'user_id']); // One registration per event per user
            $table->index(['event_id']);
            $table->index(['user_id']);
            $table->index(['status']);
            $table->index(['queue_position']);
            $table->index(['registered_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_participants');
    }
};
