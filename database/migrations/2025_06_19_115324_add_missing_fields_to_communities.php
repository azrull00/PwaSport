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
            // Fix field naming for consistency - add fields without ->after() for safety
            if (!Schema::hasColumn('communities', 'skill_level_focus')) {
                $table->enum('skill_level_focus', ['pemula', 'menengah', 'mahir', 'ahli', 'profesional', 'mixed'])->default('mixed');
            }
            
            if (!Schema::hasColumn('communities', 'membership_fee')) {
                $table->decimal('membership_fee', 10, 2)->default(0.00);
            }
            
            if (!Schema::hasColumn('communities', 'regular_schedule')) {
                $table->text('regular_schedule')->nullable();
            }
            
            if (!Schema::hasColumn('communities', 'is_premium_required')) {
                $table->boolean('is_premium_required')->default(false);
            }
            
            // Add has_icon field only (icon_url is added by another migration)
            if (!Schema::hasColumn('communities', 'has_icon')) {
                $table->boolean('has_icon')->default(false);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('communities', function (Blueprint $table) {
            if (Schema::hasColumn('communities', 'skill_level_focus')) {
                $table->dropColumn('skill_level_focus');
            }
            
            if (Schema::hasColumn('communities', 'membership_fee')) {
                $table->dropColumn('membership_fee');
            }
            
            if (Schema::hasColumn('communities', 'regular_schedule')) {
                $table->dropColumn('regular_schedule');
            }
            
            if (Schema::hasColumn('communities', 'is_premium_required')) {
                $table->dropColumn('is_premium_required');
            }
            
            if (Schema::hasColumn('communities', 'has_icon')) {
                $table->dropColumn('has_icon');
            }
        });
    }
}; 