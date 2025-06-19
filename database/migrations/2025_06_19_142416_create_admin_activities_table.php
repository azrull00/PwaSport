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
        Schema::create('admin_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('users')->onDelete('cascade');
            $table->string('action_type'); // 'user_suspend', 'dispute_resolve', 'credit_adjust', etc.
            $table->string('target_type'); // 'user', 'event', 'community', 'match', etc.
            $table->unsignedBigInteger('target_id');
            $table->text('description');
            $table->json('old_data')->nullable(); // Store old data before changes
            $table->json('new_data')->nullable(); // Store new data after changes
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['admin_id', 'created_at']);
            $table->index(['action_type', 'created_at']);
            $table->index(['target_type', 'target_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_activities');
    }
};
