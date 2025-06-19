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
        Schema::create('player_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('match_history')->onDelete('cascade');
            $table->foreignId('rated_user_id')->constrained('users')->onDelete('cascade'); // Player being rated
            $table->foreignId('rating_user_id')->constrained('users')->onDelete('cascade'); // Player giving rating
            $table->integer('skill_rating')->unsigned(); // 1-5 scale
            $table->integer('sportsmanship_rating')->unsigned(); // 1-5 scale
            $table->integer('punctuality_rating')->unsigned(); // 1-5 scale
            $table->decimal('overall_rating', 3, 2); // Calculated average
            $table->text('review')->nullable();
            $table->boolean('is_disputed')->default(false);
            $table->timestamps();
            
            $table->unique(['match_id', 'rated_user_id', 'rating_user_id']); // One rating per match per player pair
            $table->index(['rated_user_id']);
            $table->index(['rating_user_id']);
            $table->index(['overall_rating']);
            $table->index(['is_disputed']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_ratings');
    }
};
