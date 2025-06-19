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
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_id')->constrained('communities')->onDelete('cascade');
            $table->foreignId('sport_id')->constrained('sports')->onDelete('cascade');
            $table->foreignId('host_id')->constrained('users')->onDelete('cascade');
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->enum('event_type', ['mabar', 'coaching', 'friendly_match', 'tournament'])->default('mabar');
            $table->datetime('event_date');
            $table->datetime('registration_deadline')->nullable();
            $table->integer('max_participants');
            $table->integer('current_participants')->default(0);
            $table->decimal('entry_fee', 10, 2)->default(0.00); // For future premium events
            $table->enum('status', ['draft', 'published', 'full', 'ongoing', 'completed', 'cancelled'])->default('draft');
            $table->boolean('auto_queue_enabled')->default(true);
            $table->json('event_settings')->nullable(); // For event-specific configurations
            $table->timestamps();
            
            $table->index(['community_id']);
            $table->index(['sport_id']);
            $table->index(['host_id']);
            $table->index(['event_type']);
            $table->index(['status']);
            $table->index(['event_date']);
            $table->index(['registration_deadline']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
