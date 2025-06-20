<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\UserSportRating;
use App\Models\Sport;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sports = Sport::all();
        
        // Create test players
        $players = [
            [
                'name' => 'Budi Santoso',
                'email' => 'budi.santoso@example.com',
                'password' => Hash::make('password123'),
                'phone_number' => '081234567890',
                'user_type' => 'player',
                'profile' => [
                    'first_name' => 'Budi',
                    'last_name' => 'Santoso',
                    'date_of_birth' => '1995-03-15',
                    'gender' => 'male',
                    'city' => 'Jakarta',
                    'province' => 'DKI Jakarta',
                    'country' => 'Indonesia',
                    'bio' => 'Pemain badminton antusias yang suka bermain di akhir pekan.',
                ],
                'sports' => ['Badminton' => ['level' => 'menengah', 'mmr' => 1200, 'matches' => 25, 'wins' => 18]]
            ],
            [
                'name' => 'Sari Dewi',
                'email' => 'sari.dewi@example.com',
                'password' => Hash::make('password123'),
                'phone_number' => '081234567891',
                'user_type' => 'player',
                'profile' => [
                    'first_name' => 'Sari',
                    'last_name' => 'Dewi',
                    'date_of_birth' => '1992-08-22',
                    'gender' => 'female',
                    'city' => 'Bandung',
                    'province' => 'Jawa Barat',
                    'country' => 'Indonesia',
                    'bio' => 'Pecinta olahraga futsal dan volley. Aktif di komunitas olahraga kampus.',
                ],
                'sports' => [
                    'Futsal' => ['level' => 'mahir', 'mmr' => 1450, 'matches' => 32, 'wins' => 24],
                    'Voli' => ['level' => 'menengah', 'mmr' => 1100, 'matches' => 18, 'wins' => 12]
                ]
            ],
            [
                'name' => 'Andi Wijaya',
                'email' => 'andi.wijaya@example.com',
                'password' => Hash::make('password123'),
                'phone_number' => '081234567892',
                'user_type' => 'player',
                'profile' => [
                    'first_name' => 'Andi',
                    'last_name' => 'Wijaya',
                    'date_of_birth' => '1988-12-10',
                    'gender' => 'male',
                    'city' => 'Surabaya',
                    'province' => 'Jawa Timur',
                    'country' => 'Indonesia',
                    'bio' => 'Basketball enthusiast dan coach untuk anak-anak. Bermain sejak SMA.',
                ],
                'sports' => ['Basket' => ['level' => 'ahli', 'mmr' => 1650, 'matches' => 48, 'wins' => 35]]
            ],
            [
                'name' => 'Nina Permata',
                'email' => 'nina.permata@example.com',
                'password' => Hash::make('password123'),
                'phone_number' => '081234567893',
                'user_type' => 'player',
                'profile' => [
                    'first_name' => 'Nina',
                    'last_name' => 'Permata',
                    'date_of_birth' => '1996-07-05',
                    'gender' => 'female',
                    'city' => 'Medan',
                    'province' => 'Sumatera Utara',
                    'country' => 'Indonesia',
                    'bio' => 'Pemain tenis meja yang aktif di turnamen lokal. Suka tantangan baru.',
                ],
                'sports' => ['Tenis Meja' => ['level' => 'mahir', 'mmr' => 1380, 'matches' => 28, 'wins' => 20]]
            ]
        ];

        // Create test hosts
        $hosts = [
            [
                'name' => 'Rahman Sports Center',
                'email' => 'rahman.sports@example.com',
                'password' => Hash::make('password123'),
                'phone_number' => '081234567894',
                'user_type' => 'host',
                'profile' => [
                    'first_name' => 'Rahman',
                    'last_name' => 'Sports Center',
                    'date_of_birth' => '1985-04-18',
                    'gender' => 'male',
                    'city' => 'Jakarta',
                    'province' => 'DKI Jakarta',
                    'country' => 'Indonesia',
                    'bio' => 'Pengelola fasilitas olahraga dengan 10+ tahun pengalaman. Menyediakan lapangan berkualitas untuk berbagai olahraga.',
                ],
                'sports' => []
            ],
            [
                'name' => 'Bandung Sport Hub',
                'email' => 'bandung.hub@example.com',
                'password' => Hash::make('password123'),
                'phone_number' => '081234567895',
                'user_type' => 'host',
                'profile' => [
                    'first_name' => 'Bandung Sport',
                    'last_name' => 'Hub',
                    'date_of_birth' => '1980-11-25',
                    'gender' => 'male',
                    'city' => 'Bandung',
                    'province' => 'Jawa Barat',
                    'country' => 'Indonesia',
                    'bio' => 'Kompleks olahraga terlengkap di Bandung. Melayani pemain dari pemula hingga profesional.',
                ],
                'sports' => []
            ]
        ];

        foreach ($players as $playerData) {
            $user = User::create([
                'name' => $playerData['name'],
                'email' => $playerData['email'],
                'password' => $playerData['password'],
                'phone_number' => $playerData['phone_number'],
                'user_type' => $playerData['user_type'],
            ]);

            // Assign player role
            $user->assignRole('player');

            // Create profile
            UserProfile::create(array_merge([
                'user_id' => $user->id,
                'qr_code' => 'QR' . strtoupper(substr(md5($user->id . time()), 0, 8)),
            ], $playerData['profile']));

            // Create sport ratings
            foreach ($playerData['sports'] as $sportName => $sportData) {
                $sport = $sports->where('name', $sportName)->first();
                if ($sport) {
                    UserSportRating::create([
                        'user_id' => $user->id,
                        'sport_id' => $sport->id,
                        'mmr' => $sportData['mmr'],
                        'level' => rand(1, 8),
                        'matches_played' => $sportData['matches'],
                        'wins' => $sportData['wins'],
                        'losses' => $sportData['matches'] - $sportData['wins'],
                        'win_rate' => round(($sportData['wins'] / $sportData['matches']) * 100, 2),
                        'last_match_at' => now()->subDays(rand(1, 30)),
                    ]);
                }
            }
        }

        foreach ($hosts as $hostData) {
            $user = User::create([
                'name' => $hostData['name'],
                'email' => $hostData['email'],
                'password' => $hostData['password'],
                'phone_number' => $hostData['phone_number'],
                'user_type' => $hostData['user_type'],
            ]);

            // Assign host role
            $user->assignRole('host');

            // Create profile
            UserProfile::create(array_merge([
                'user_id' => $user->id,
                'qr_code' => 'QR' . strtoupper(substr(md5($user->id . time() . rand()), 0, 8)),
            ], $hostData['profile']));
        }

        $this->command->info('Users, profiles, and sport ratings seeded successfully!');
    }
}
