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
        Schema::create('user_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blocking_user_id')->constrained('users')->onDelete('cascade'); // User who is blocking
            $table->foreignId('blocked_user_id')->constrained('users')->onDelete('cascade'); // User being blocked
            $table->text('reason')->nullable();
            $table->timestamps();
            
            $table->unique(['blocking_user_id', 'blocked_user_id']); // One block per user pair
            $table->index(['blocking_user_id']);
            $table->index(['blocked_user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_blocks');
    }
};
