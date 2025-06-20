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
        Schema::table('match_history', function (Blueprint $table) {
            $table->integer('court_number')->nullable(); // Court assignment for matchmaking
            $table->integer('estimated_duration')->nullable(); // Estimated match duration in minutes
            $table->enum('match_status', ['scheduled', 'ongoing', 'completed', 'cancelled'])->default('scheduled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('match_history', function (Blueprint $table) {
            $table->dropColumn(['court_number', 'estimated_duration', 'match_status']);
        });
    }
};
