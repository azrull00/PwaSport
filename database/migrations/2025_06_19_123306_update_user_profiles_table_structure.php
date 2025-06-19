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
        Schema::table('user_profiles', function (Blueprint $table) {
            // Rename qr_code_hash to qr_code for consistency with controller
            $table->renameColumn('qr_code_hash', 'qr_code');
            
            // Add missing address fields
            $table->string('city', 100)->nullable();
            $table->string('district', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('country', 100)->default('Indonesia');
            $table->string('postal_code', 10)->nullable();
            
            // Add location privacy setting
            $table->boolean('is_location_public')->default(false);
            
            // Add emergency contact fields
            $table->string('emergency_contact_name', 100)->nullable();
            $table->string('emergency_contact_phone', 20)->nullable();
            
            // Add preferred language
            $table->string('preferred_language', 5)->default('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            // Remove added columns
            $table->dropColumn([
                'city', 'district', 'province', 'country', 'postal_code',
                'is_location_public', 'emergency_contact_name', 
                'emergency_contact_phone', 'preferred_language'
            ]);
            
            // Rename back to original
            $table->renameColumn('qr_code', 'qr_code_hash');
        });
    }
};
