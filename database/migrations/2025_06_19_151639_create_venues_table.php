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
        Schema::create('venues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sport_id')->constrained('sports')->onDelete('cascade');
            $table->foreignId('owner_id')->constrained('users')->onDelete('cascade'); // Venue owner
            $table->string('name', 100);
            $table->text('address');
            $table->string('city', 50);
            $table->string('district', 50)->nullable();
            $table->string('province', 50);
            $table->string('country', 50)->default('Indonesia');
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->integer('total_courts')->default(1);
            $table->enum('court_type', ['indoor', 'outdoor', 'covered'])->default('indoor');
            $table->decimal('hourly_rate', 10, 2)->nullable(); // Cost per hour per court
            $table->json('facilities')->nullable(); // ["parking", "shower", "cafeteria", "ac", "lighting"]
            $table->json('operating_hours')->nullable(); // {"monday": {"open": "06:00", "close": "22:00"}}
            $table->string('contact_phone', 20)->nullable();
            $table->string('contact_email', 100)->nullable();
            $table->text('description')->nullable();
            $table->json('rules')->nullable(); // Venue specific rules
            $table->json('photos')->nullable(); // Array of photo URLs
            $table->decimal('average_rating', 3, 2)->default(0);
            $table->integer('total_reviews')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_verified')->default(false); // Admin verification
            $table->timestamps();

            // Indexes for performance
            $table->index(['sport_id']);
            $table->index(['owner_id']);
            $table->index(['city']);
            $table->index(['province']);
            $table->index(['latitude', 'longitude']);
            $table->index(['is_active']);
            $table->index(['is_verified']);
            $table->index(['average_rating']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('venues');
    }
};
