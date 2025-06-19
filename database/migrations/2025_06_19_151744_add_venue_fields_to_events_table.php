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
        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('venue_id')->nullable()->constrained('venues')->onDelete('set null')->after('host_id');
            $table->integer('courts_used')->default(1)->after('max_participants'); // How many courts this event uses
            $table->integer('max_courts')->nullable()->after('courts_used'); // Maximum courts available for matchmaking
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign(['venue_id']);
            $table->dropColumn(['venue_id', 'courts_used', 'max_courts']);
        });
    }
};
