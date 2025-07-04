<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('guest_players', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('temporary_id')->unique();
            $table->foreignId('event_id')->constrained('events')->onDelete('cascade');
            $table->integer('skill_level')->default(0);
            $table->integer('estimated_mmr')->default(1000);
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('expires_at');
            $table->softDeletes();
            $table->timestamps();
        });

        // Add guest player support to match history
        Schema::table('match_history', function (Blueprint $table) {
            $table->foreignId('player1_guest_id')->nullable()->constrained('guest_players');
            $table->foreignId('player2_guest_id')->nullable()->constrained('guest_players');
        });

        // Add guest player support to event participants
        Schema::table('event_participants', function (Blueprint $table) {
            $table->foreignId('guest_player_id')->nullable()->constrained('guest_players');
        });
    }

    public function down()
    {
        Schema::table('event_participants', function (Blueprint $table) {
            $table->dropForeign(['guest_player_id']);
            $table->dropColumn('guest_player_id');
        });

        Schema::table('match_history', function (Blueprint $table) {
            $table->dropForeign(['player1_guest_id']);
            $table->dropForeign(['player2_guest_id']);
            $table->dropColumn(['player1_guest_id', 'player2_guest_id']);
        });

        Schema::dropIfExists('guest_players');
    }
}; 