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
        Schema::create('community_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_id')->constrained('communities')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('role', ['member', 'admin', 'moderator'])->default('member');
            $table->enum('status', ['active', 'pending', 'suspended', 'banned'])->default('active');
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamp('last_activity_at')->nullable();
            $table->text('notes')->nullable(); // Admin notes about member
            $table->timestamps();
            
            $table->unique(['community_id', 'user_id']);
            $table->index(['community_id']);
            $table->index(['user_id']);
            $table->index(['status']);
            $table->index(['role']);
            $table->index(['joined_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('community_members');
    }
}; 