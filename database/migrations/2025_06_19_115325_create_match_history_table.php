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
        Schema::create('match_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->onDelete('cascade');
            $table->foreignId('player1_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('player2_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('sport_id')->constrained('sports')->onDelete('cascade');
            $table->enum('result', ['player1_win', 'player2_win', 'draw']);
            $table->json('match_score')->nullable(); // Store detailed score (e.g., sets, games)
            $table->integer('player1_mmr_before');
            $table->integer('player1_mmr_after');
            $table->integer('player2_mmr_before');
            $table->integer('player2_mmr_after');
            $table->foreignId('recorded_by_host_id')->constrained('users')->onDelete('cascade'); // Host who input the score
            $table->text('match_notes')->nullable(); // Additional notes from host
            $table->timestamp('match_date');
            $table->timestamps();
            
            $table->index(['event_id']);
            $table->index(['player1_id']);
            $table->index(['player2_id']);
            $table->index(['sport_id']);
            $table->index(['recorded_by_host_id']);
            $table->index(['match_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('match_history');
    }
};
