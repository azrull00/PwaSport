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
            // Add location fields
            $table->string('location_name')->nullable();
            $table->text('location_address')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('district', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('country', 100)->default('Indonesia');
            
            // Add skill level field
            $table->enum('skill_level_required', ['pemula', 'menengah', 'mahir', 'ahli', 'profesional', 'mixed'])->default('mixed');
            
            // Add cancellation reason
            $table->text('cancellation_reason')->nullable();
            
            // Add premium and auto-confirm flags
            $table->boolean('is_premium_only')->default(false);
            $table->boolean('auto_confirm_participants')->default(false);

            // Add indexes for location-based queries
            $table->index(['latitude', 'longitude']);
            $table->index(['city']);
            $table->index(['skill_level_required']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'location_name', 'location_address', 'latitude', 'longitude',
                'city', 'district', 'province', 'country',
                'skill_level_required', 'cancellation_reason',
                'is_premium_only', 'auto_confirm_participants'
            ]);
        });
    }
};
