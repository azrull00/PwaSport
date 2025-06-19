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
        Schema::create('user_sport_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('sport_id')->constrained('sports')->onDelete('cascade');
            $table->integer('mmr')->default(1000); // Match Making Rating
            $table->integer('level')->default(1); // Skill level (1-10)
            $table->integer('matches_played')->default(0);
            $table->integer('wins')->default(0);
            $table->integer('losses')->default(0);
            $table->decimal('win_rate', 5, 2)->default(0.00); // Percentage
            $table->timestamp('last_match_at')->nullable();
            $table->timestamps();
            
            $table->unique(['user_id', 'sport_id']);
            $table->index(['mmr']);
            $table->index(['level']);
            $table->index(['last_match_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_sport_ratings');
    }
};
