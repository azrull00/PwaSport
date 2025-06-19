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
        Schema::table('communities', function (Blueprint $table) {
            // Add sport_id foreign key
            $table->foreignId('sport_id')->constrained('sports')->onDelete('cascade');
            
            // Rename primary_host_id to host_user_id to match model
            $table->renameColumn('primary_host_id', 'host_user_id');
            
            // Add community type
            $table->enum('community_type', ['public', 'private', 'invite_only'])->default('public');
            
            // Add location fields
            $table->string('location_name')->nullable();
            $table->string('city')->nullable();
            $table->string('district')->nullable();
            $table->string('province')->nullable();
            $table->string('country')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            
            // Add venue fields expected by factory
            $table->string('venue_name')->nullable();
            $table->text('venue_address')->nullable();
            
            // Add member management fields
            $table->integer('max_members')->default(100);
            $table->integer('member_count')->default(0);
            
            // Update rating fields to match factory expectations
            $table->decimal('total_ratings', 8, 2)->default(0);
            
            // Add fields expected by factory
            $table->boolean('is_public')->default(true);
            
            // Update existing contact fields to match model expectations
            $table->json('contact_info')->nullable();
            $table->json('rules')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('communities', function (Blueprint $table) {
            // Remove added fields
            $table->dropForeign(['sport_id']);
            $table->dropColumn([
                'sport_id',
                'community_type',
                'location_name',
                'city',
                'district',
                'province',
                'country',
                'latitude',
                'longitude',
                'venue_name',
                'venue_address',
                'max_members',
                'member_count',
                'total_ratings',
                'is_public',
                'contact_info',
                'rules'
            ]);
            
            // Rename back
            $table->renameColumn('host_user_id', 'primary_host_id');
        });
    }
};
