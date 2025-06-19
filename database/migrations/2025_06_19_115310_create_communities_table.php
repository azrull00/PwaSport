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
        Schema::create('communities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('primary_host_id')->constrained('users')->onDelete('cascade');
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->string('contact_email')->nullable();
            $table->decimal('average_skill_rating', 3, 2)->default(0.00); // 1-5 scale
            $table->decimal('hospitality_rating', 3, 2)->default(0.00); // 1-5 scale
            $table->integer('total_events')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['primary_host_id']);
            $table->index(['is_active']);
            $table->index(['average_skill_rating']);
            $table->index(['hospitality_rating']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('communities');
    }
};
