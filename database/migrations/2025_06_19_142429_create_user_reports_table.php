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
        Schema::create('user_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('reported_user_id')->constrained('users')->onDelete('cascade');
            $table->string('report_type'); // 'misconduct', 'cheating', 'harassment', 'no_show', 'rating_dispute', etc.
            $table->string('related_type')->nullable(); // 'event', 'match', 'community', 'chat'
            $table->unsignedBigInteger('related_id')->nullable(); // ID of related entity
            $table->text('description');
            $table->json('evidence')->nullable(); // Store photos, screenshots, etc.
            $table->enum('status', ['pending', 'under_review', 'resolved', 'dismissed', 'escalated'])->default('pending');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->foreignId('assigned_admin_id')->nullable()->constrained('users')->onDelete('set null');
            $table->text('admin_notes')->nullable();
            $table->text('resolution')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['reporter_id']);
            $table->index(['reported_user_id']);
            $table->index(['status', 'priority']);
            $table->index(['assigned_admin_id']);
            $table->index(['related_type', 'related_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_reports');
    }
};
