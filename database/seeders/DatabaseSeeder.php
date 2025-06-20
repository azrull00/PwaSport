<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            SportsSeeder::class,
            UserSeeder::class,
            CommunitySeeder::class,
            EventSeeder::class,
        ]);
        
        $this->command->info('🎉 All seeders completed successfully!');
        $this->command->info('📊 Database now contains:');
        $this->command->info('   - Roles and permissions');
        $this->command->info('   - Sports data');
        $this->command->info('   - Users (players & hosts) with profiles');
        $this->command->info('   - Sport ratings for players');
        $this->command->info('   - Communities with members and messages');
        $this->command->info('   - Events with participants');
        $this->command->info('   - Venues with facilities');
    }
}
