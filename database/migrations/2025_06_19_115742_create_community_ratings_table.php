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
        Schema::create('community_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_id')->constrained('communities')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('event_id')->constrained('events')->onDelete('cascade'); // Which event they participated in
            $table->integer('skill_rating')->unsigned(); // 1-5 scale - Community skill level
            $table->integer('hospitality_rating')->unsigned(); // 1-5 scale - Community hospitality
            $table->text('review')->nullable();
            $table->timestamps();
            
            $table->unique(['community_id', 'user_id', 'event_id']); // One rating per event participation
            $table->index(['community_id']);
            $table->index(['user_id']);
            $table->index(['event_id']);
            $table->index(['skill_rating']);
            $table->index(['hospitality_rating']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('community_ratings');
    }
};
