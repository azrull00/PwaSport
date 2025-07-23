<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\UserSportRating;
use App\Models\Sport;
use App\Models\Venue;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\MatchHistory;
use App\Models\Notification;
use App\Models\PrivateMessage;
use App\Models\Friendship;
use App\Models\GuestPlayer;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class MatchmakingDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🎯 Creating comprehensive matchmaking demo data...');
        
        // Get all sports
        $sports = Sport::all();
        if ($sports->isEmpty()) {
            $this->command->error('⚠️ No sports found. Please run SportsSeeder first!');
            return;
        }

        // Create demo hosts with complete setups
        $this->createDemoHosts($sports);
        
        // Create demo players with various skill levels
        $this->createDemoPlayers($sports);
        
        // Create venues for hosts
        $this->createVenuesForHosts($sports);
        
        // Create communities
        $this->createCommunities($sports);
        
        // Create events with different statuses
        $this->createEventsWithMatchmaking($sports);
        
        // Create guest players for ongoing events
        // $this->createGuestPlayers(); // Commented out for now - model not found
        
        // Create active matchmaking scenarios
        $this->createActiveMatchmakingScenarios();
        
        // Create match history
        $this->createMatchHistoryData();
        
        // Create social connections
        $this->createSocialConnections();
        
        // Create notifications
        $this->createNotifications();
        
        $this->command->info('✅ Matchmaking demo data created successfully!');
        $this->displayDemoAccounts();
    }

    private function createDemoHosts($sports)
    {
        $hosts = [
            [
                'name' => 'SportHub Jakarta',
                'email' => 'host.jakarta@sportpwa.com',
                'password' => Hash::make('password123'),
                'phone_number' => '081234567001',
                'user_type' => 'host',
                'credit_score' => 1000,
                'is_active' => true,
                'subscription_tier' => 'premium',
                'profile' => [
                    'first_name' => 'SportHub',
                    'last_name' => 'Jakarta',
                    'date_of_birth' => '1985-01-01',
                    'gender' => 'male',
                    'city' => 'Jakarta',
                    'province' => 'DKI Jakarta',
                    'country' => 'Indonesia',
                    'bio' => 'Kompleks olahraga terbesar di Jakarta dengan 20+ lapangan dan fasilitas lengkap. Mengadakan turnamen rutin setiap bulan.',
                    'address' => 'Jl. Sudirman No. 123, Jakarta Pusat',
                ]
            ],
            [
                'name' => 'Bandung Sports Arena',
                'email' => 'host.bandung@sportpwa.com',
                'password' => Hash::make('password123'),
                'phone_number' => '081234567002',
                'user_type' => 'host',
                'credit_score' => 950,
                'is_active' => true,
                'subscription_tier' => 'premium',
                'profile' => [
                    'first_name' => 'Bandung Sports',
                    'last_name' => 'Arena',
                    'date_of_birth' => '1980-05-15',
                    'gender' => 'male',
                    'city' => 'Bandung',
                    'province' => 'Jawa Barat',
                    'country' => 'Indonesia',
                    'bio' => 'Arena olahraga modern di Bandung dengan teknologi court booking terbaru. Spesialisasi badminton dan futsal.',
                    'address' => 'Jl. Dago No. 456, Bandung',
                ]
            ],
            [
                'name' => 'Surabaya Elite Sports',
                'email' => 'host.surabaya@sportpwa.com',
                'password' => Hash::make('password123'),
                'phone_number' => '081234567003',
                'user_type' => 'host',
                'credit_score' => 980,
                'is_active' => true,
                'subscription_tier' => 'premium',
                'profile' => [
                    'first_name' => 'Surabaya Elite',
                    'last_name' => 'Sports',
                    'date_of_birth' => '1975-08-20',
                    'gender' => 'male',
                    'city' => 'Surabaya',
                    'province' => 'Jawa Timur',
                    'country' => 'Indonesia',
                    'bio' => 'Pusat olahraga elite di Surabaya dengan pelatih profesional dan program training khusus untuk atlet.',
                    'address' => 'Jl. Pemuda No. 789, Surabaya',
                ]
            ]
        ];

        foreach ($hosts as $hostData) {
            $host = User::firstOrCreate(
                ['email' => $hostData['email']],
                [
                    'name' => $hostData['name'],
                    'password' => $hostData['password'],
                    'phone_number' => $hostData['phone_number'],
                    'user_type' => $hostData['user_type'],
                    'credit_score' => $hostData['credit_score'],
                    'is_active' => $hostData['is_active'],
                    'subscription_tier' => $hostData['subscription_tier'],
                ]
            );

            if (!$host->hasRole('host')) {
                $host->assignRole('host');
            }

            $existingProfile = UserProfile::where('user_id', $host->id)->first();
            if (!$existingProfile) {
                UserProfile::create(array_merge([
                    'user_id' => $host->id,
                    'qr_code' => 'HOST_' . strtoupper(substr(md5($host->id . time()), 0, 10)),
                ], $hostData['profile']));
            }
        }
    }

    private function createDemoPlayers($sports)
    {
        $players = [
            [
                'name' => 'Ahmad Fadli',
                'email' => 'player.ahmad@sportpwa.com',
                'password' => Hash::make('password123'),
                'phone_number' => '081234567101',
                'user_type' => 'player',
                'credit_score' => 920,
                'subscription_tier' => 'premium',
                'profile' => [
                    'first_name' => 'Ahmad',
                    'last_name' => 'Fadli',
                    'date_of_birth' => '1995-03-15',
                    'gender' => 'male',
                    'city' => 'Jakarta',
                    'province' => 'DKI Jakarta',
                    'country' => 'Indonesia',
                    'bio' => 'Pemain badminton kompetitif dengan pengalaman turnamen nasional. Aktif mengikuti matchmaking.',
                    'address' => 'Jl. Kemang No. 12, Jakarta Selatan',
                ],
                'sports' => [
                    'Badminton' => ['mmr' => 1650, 'level' => 7, 'matches' => 85, 'wins' => 62],
                    'Tenis Meja' => ['mmr' => 1200, 'level' => 5, 'matches' => 25, 'wins' => 18],
                ]
            ],
            [
                'name' => 'Sari Indrawati',
                'email' => 'player.sari@sportpwa.com',
                'password' => Hash::make('password123'),
                'phone_number' => '081234567102',
                'user_type' => 'player',
                'credit_score' => 880,
                'subscription_tier' => 'premium',
                'profile' => [
                    'first_name' => 'Sari',
                    'last_name' => 'Indrawati',
                    'date_of_birth' => '1992-07-22',
                    'gender' => 'female',
                    'city' => 'Jakarta',
                    'province' => 'DKI Jakarta',
                    'country' => 'Indonesia',
                    'bio' => 'Pemain futsal wanita terbaik Jakarta. Kapten tim komunitas dan sering mengikuti turnamen.',
                    'address' => 'Jl. Menteng No. 45, Jakarta Pusat',
                ],
                'sports' => [
                    'Futsal' => ['mmr' => 1450, 'level' => 6, 'matches' => 68, 'wins' => 48],
                    'Basket' => ['mmr' => 1100, 'level' => 4, 'matches' => 32, 'wins' => 19],
                ]
            ],
            [
                'name' => 'Budi Santoso',
                'email' => 'player.budi@sportpwa.com',
                'password' => Hash::make('password123'),
                'phone_number' => '081234567103',
                'user_type' => 'player',
                'credit_score' => 850,
                'subscription_tier' => 'free',
                'profile' => [
                    'first_name' => 'Budi',
                    'last_name' => 'Santoso',
                    'date_of_birth' => '1990-11-10',
                    'gender' => 'male',
                    'city' => 'Bandung',
                    'province' => 'Jawa Barat',
                    'country' => 'Indonesia',
                    'bio' => 'Pemain basket hobi yang rajin ikut pickup game dan turnamen lokal.',
                    'address' => 'Jl. Braga No. 67, Bandung',
                ],
                'sports' => [
                    'Basket' => ['mmr' => 1320, 'level' => 5, 'matches' => 54, 'wins' => 35],
                    'Voli' => ['mmr' => 1150, 'level' => 4, 'matches' => 28, 'wins' => 18],
                ]
            ],
            [
                'name' => 'Nina Permata',
                'email' => 'player.nina@sportpwa.com',
                'password' => Hash::make('password123'),
                'phone_number' => '081234567104',
                'user_type' => 'player',
                'credit_score' => 890,
                'subscription_tier' => 'free',
                'profile' => [
                    'first_name' => 'Nina',
                    'last_name' => 'Permata',
                    'date_of_birth' => '1994-05-08',
                    'gender' => 'female',
                    'city' => 'Surabaya',
                    'province' => 'Jawa Timur',
                    'country' => 'Indonesia',
                    'bio' => 'Pemain tenis meja yang sering juara di turnamen regional. Suka tantangan baru.',
                    'address' => 'Jl. Tunjungan No. 89, Surabaya',
                ],
                'sports' => [
                    'Tenis Meja' => ['mmr' => 1580, 'level' => 7, 'matches' => 72, 'wins' => 54],
                    'Badminton' => ['mmr' => 1250, 'level' => 5, 'matches' => 38, 'wins' => 24],
                ]
            ],
            [
                'name' => 'Rudi Hartono',
                'email' => 'player.rudi@sportpwa.com',
                'password' => Hash::make('password123'),
                'phone_number' => '081234567105',
                'user_type' => 'player',
                'credit_score' => 780,
                'subscription_tier' => 'free',
                'profile' => [
                    'first_name' => 'Rudi',
                    'last_name' => 'Hartono',
                    'date_of_birth' => '1988-12-25',
                    'gender' => 'male',
                    'city' => 'Jakarta',
                    'province' => 'DKI Jakarta',
                    'country' => 'Indonesia',
                    'bio' => 'Pemain pemula yang antusias belajar berbagai olahraga. Aktif ikut komunitas.',
                    'address' => 'Jl. Cikini No. 23, Jakarta Pusat',
                ],
                'sports' => [
                    'Badminton' => ['mmr' => 950, 'level' => 3, 'matches' => 18, 'wins' => 8],
                    'Futsal' => ['mmr' => 900, 'level' => 3, 'matches' => 15, 'wins' => 6],
                ]
            ],
            [
                'name' => 'Lisa Anjani',
                'email' => 'player.lisa@sportpwa.com',
                'password' => Hash::make('password123'),
                'phone_number' => '081234567106',
                'user_type' => 'player',
                'credit_score' => 930,
                'subscription_tier' => 'premium',
                'profile' => [
                    'first_name' => 'Lisa',
                    'last_name' => 'Anjani',
                    'date_of_birth' => '1993-09-14',
                    'gender' => 'female',
                    'city' => 'Bandung',
                    'province' => 'Jawa Barat',
                    'country' => 'Indonesia',
                    'bio' => 'Atlet multi-sport dengan fokus pada voli dan basket. Mantan atlet sekolah.',
                    'address' => 'Jl. Pasteur No. 56, Bandung',
                ],
                'sports' => [
                    'Voli' => ['mmr' => 1420, 'level' => 6, 'matches' => 58, 'wins' => 41],
                    'Basket' => ['mmr' => 1380, 'level' => 6, 'matches' => 45, 'wins' => 32],
                ]
            ],
            [
                'name' => 'Eko Prasetyo',
                'email' => 'player.eko@sportpwa.com',
                'password' => Hash::make('password123'),
                'phone_number' => '081234567107',
                'user_type' => 'player',
                'credit_score' => 820,
                'subscription_tier' => 'free',
                'profile' => [
                    'first_name' => 'Eko',
                    'last_name' => 'Prasetyo',
                    'date_of_birth' => '1991-04-18',
                    'gender' => 'male',
                    'city' => 'Surabaya',
                    'province' => 'Jawa Timur',
                    'country' => 'Indonesia',
                    'bio' => 'Pemain futsal yang konsisten dan solid. Sering jadi anchor team.',
                    'address' => 'Jl. Diponegoro No. 78, Surabaya',
                ],
                'sports' => [
                    'Futsal' => ['mmr' => 1280, 'level' => 5, 'matches' => 62, 'wins' => 38],
                    'Sepak Bola' => ['mmr' => 1150, 'level' => 4, 'matches' => 22, 'wins' => 12],
                ]
            ],
            [
                'name' => 'Dewi Sartika',
                'email' => 'player.dewi@sportpwa.com',
                'password' => Hash::make('password123'),
                'phone_number' => '081234567108',
                'user_type' => 'player',
                'credit_score' => 900,
                'subscription_tier' => 'premium',
                'profile' => [
                    'first_name' => 'Dewi',
                    'last_name' => 'Sartika',
                    'date_of_birth' => '1996-01-30',
                    'gender' => 'female',
                    'city' => 'Jakarta',
                    'province' => 'DKI Jakarta',
                    'country' => 'Indonesia',
                    'bio' => 'Pemain badminton yang rajin latihan dan ikut turnamen weekend.',
                    'address' => 'Jl. Senopati No. 34, Jakarta Selatan',
                ],
                'sports' => [
                    'Badminton' => ['mmr' => 1380, 'level' => 6, 'matches' => 48, 'wins' => 32],
                    'Tenis' => ['mmr' => 1100, 'level' => 4, 'matches' => 20, 'wins' => 12],
                ]
            ]
        ];

        foreach ($players as $playerData) {
            $player = User::firstOrCreate(
                ['email' => $playerData['email']],
                [
                    'name' => $playerData['name'],
                    'password' => $playerData['password'],
                    'phone_number' => $playerData['phone_number'],
                    'user_type' => $playerData['user_type'],
                    'credit_score' => $playerData['credit_score'],
                    'subscription_tier' => $playerData['subscription_tier'],
                    'is_active' => true,
                ]
            );

            if (!$player->hasRole('player')) {
                $player->assignRole('player');
            }

            $existingProfile = UserProfile::where('user_id', $player->id)->first();
            if (!$existingProfile) {
                UserProfile::create(array_merge([
                    'user_id' => $player->id,
                    'qr_code' => 'PLAYER_' . strtoupper(substr(md5($player->id . time()), 0, 10)),
                ], $playerData['profile']));
            }

            // Create sport ratings
            foreach ($playerData['sports'] as $sportName => $sportData) {
                $sport = $sports->where('name', $sportName)->first();
                if ($sport) {
                    $existingRating = UserSportRating::where('user_id', $player->id)
                        ->where('sport_id', $sport->id)
                        ->first();
                    
                    if (!$existingRating) {
                        UserSportRating::create([
                            'user_id' => $player->id,
                            'sport_id' => $sport->id,
                            'mmr' => $sportData['mmr'],
                            'level' => $sportData['level'],
                            'matches_played' => $sportData['matches'],
                            'wins' => $sportData['wins'],
                            'losses' => $sportData['matches'] - $sportData['wins'],
                            'win_rate' => round(($sportData['wins'] / $sportData['matches']) * 100, 2),
                            'last_match_at' => now()->subDays(rand(1, 30)),
                        ]);
                    }
                }
            }
        }
    }

    private function createVenuesForHosts($sports)
    {
        $hosts = User::where('user_type', 'host')->get();
        
        $venueTemplates = [
            // Jakarta Host Venues
            [
                'host_index' => 0,
                'venues' => [
                    [
                        'name' => 'SportHub Badminton Center',
                        'sport' => 'Badminton',
                        'total_courts' => 12,
                        'court_type' => 'indoor',
                        'hourly_rate' => 120000,
                        'address' => 'Jl. Sudirman No. 123A, Jakarta Pusat',
                        'latitude' => -6.2088,
                        'longitude' => 106.8456,
                        'facilities' => ['AC', 'Locker Room', 'Shower', 'Canteen', 'Parking', 'Pro Shop'],
                        'operating_hours' => ['open' => '06:00', 'close' => '23:00'],
                    ],
                    [
                        'name' => 'SportHub Futsal Arena',
                        'sport' => 'Futsal',
                        'total_courts' => 8,
                        'court_type' => 'indoor',
                        'hourly_rate' => 250000,
                        'address' => 'Jl. Sudirman No. 123B, Jakarta Pusat',
                        'latitude' => -6.2090,
                        'longitude' => 106.8458,
                        'facilities' => ['Synthetic Grass', 'Floodlight', 'Locker Room', 'Cafeteria', 'Parking'],
                        'operating_hours' => ['open' => '07:00', 'close' => '24:00'],
                    ],
                    [
                        'name' => 'SportHub Basketball Courts',
                        'sport' => 'Basket',
                        'total_courts' => 6,
                        'court_type' => 'indoor',
                        'hourly_rate' => 200000,
                        'address' => 'Jl. Sudirman No. 123C, Jakarta Pusat',
                        'latitude' => -6.2092,
                        'longitude' => 106.8460,
                        'facilities' => ['Air Conditioning', 'Scoreboard', 'Locker Room', 'Parking'],
                        'operating_hours' => ['open' => '08:00', 'close' => '22:00'],
                    ]
                ]
            ],
            // Bandung Host Venues
            [
                'host_index' => 1,
                'venues' => [
                    [
                        'name' => 'Bandung Arena Badminton',
                        'sport' => 'Badminton',
                        'total_courts' => 10,
                        'court_type' => 'indoor',
                        'hourly_rate' => 100000,
                        'address' => 'Jl. Dago No. 456A, Bandung',
                        'latitude' => -6.8915,
                        'longitude' => 107.6107,
                        'facilities' => ['AC', 'Locker Room', 'Parking', 'Cafeteria'],
                        'operating_hours' => ['open' => '06:00', 'close' => '22:00'],
                    ],
                    [
                        'name' => 'Bandung Arena Futsal',
                        'sport' => 'Futsal',
                        'total_courts' => 6,
                        'court_type' => 'indoor',
                        'hourly_rate' => 220000,
                        'address' => 'Jl. Dago No. 456B, Bandung',
                        'latitude' => -6.8917,
                        'longitude' => 107.6109,
                        'facilities' => ['Synthetic Grass', 'Floodlight', 'Locker Room', 'Parking'],
                        'operating_hours' => ['open' => '08:00', 'close' => '23:00'],
                    ]
                ]
            ],
            // Surabaya Host Venues
            [
                'host_index' => 2,
                'venues' => [
                    [
                        'name' => 'Elite Sports Basketball Complex',
                        'sport' => 'Basket',
                        'total_courts' => 8,
                        'court_type' => 'indoor',
                        'hourly_rate' => 180000,
                        'address' => 'Jl. Pemuda No. 789A, Surabaya',
                        'latitude' => -7.2575,
                        'longitude' => 112.7521,
                        'facilities' => ['Professional Court', 'Scoreboard', 'Locker Room', 'Cafeteria'],
                        'operating_hours' => ['open' => '07:00', 'close' => '22:00'],
                    ],
                    [
                        'name' => 'Elite Sports Table Tennis Hall',
                        'sport' => 'Tenis Meja',
                        'total_courts' => 15,
                        'court_type' => 'indoor',
                        'hourly_rate' => 80000,
                        'address' => 'Jl. Pemuda No. 789B, Surabaya',
                        'latitude' => -7.2577,
                        'longitude' => 112.7523,
                        'facilities' => ['Professional Tables', 'AC', 'Locker Room', 'Parking'],
                        'operating_hours' => ['open' => '08:00', 'close' => '21:00'],
                    ]
                ]
            ]
        ];

        foreach ($venueTemplates as $hostVenues) {
            $host = $hosts->skip($hostVenues['host_index'])->first();
            
            foreach ($hostVenues['venues'] as $venueData) {
                $sport = $sports->where('name', $venueData['sport'])->first();
                if ($sport && $host) {
                    Venue::create([
                        'sport_id' => $sport->id,
                        'owner_id' => $host->id,
                        'name' => $venueData['name'],
                        'address' => $venueData['address'],
                        'city' => $host->profile->city,
                        'province' => $host->profile->province,
                        'country' => $host->profile->country,
                        'latitude' => $venueData['latitude'],
                        'longitude' => $venueData['longitude'],
                        'contact_phone' => $host->phone_number,
                        'facilities' => json_encode($venueData['facilities']),
                        'total_courts' => $venueData['total_courts'],
                        'court_type' => $venueData['court_type'],
                        'hourly_rate' => $venueData['hourly_rate'],
                        'operating_hours' => json_encode($venueData['operating_hours']),
                        'is_active' => true,
                        'is_verified' => true,
                    ]);
                }
            }
        }
    }

    private function createCommunities($sports)
    {
        $hosts = User::where('user_type', 'host')->get();
        $players = User::where('user_type', 'player')->get();
        
        $communityTemplates = [
            [
                'name' => 'Jakarta Badminton Masters',
                'sport' => 'Badminton',
                'host_index' => 0,
                'description' => 'Komunitas badminton terbesar di Jakarta untuk pemain intermediate hingga advanced. Mengadakan turnamen bulanan dan training session rutin.',
                'rules' => 'Wajib datang tepat waktu, menggunakan sepatu khusus badminton, dan menjaga sportivitas.',
                'member_limit' => 50,
                'city' => 'Jakarta',
            ],
            [
                'name' => 'Futsal Warriors Jakarta',
                'sport' => 'Futsal',
                'host_index' => 0,
                'description' => 'Tim futsal kompetitif Jakarta yang aktif di liga lokal dan turnamen regional.',
                'rules' => 'Latihan minimal 2x seminggu, komitmen tinggi untuk turnamen, dan fair play.',
                'member_limit' => 25,
                'city' => 'Jakarta',
            ],
            [
                'name' => 'Bandung Badminton Club',
                'sport' => 'Badminton',
                'host_index' => 1,
                'description' => 'Komunitas badminton ramah di Bandung untuk semua level. Fokus pada fun dan improvement.',
                'rules' => 'Respect semua anggota, sharing equipment, dan aktif dalam kegiatan komunitas.',
                'member_limit' => 40,
                'city' => 'Bandung',
            ],
            [
                'name' => 'Surabaya Basketball League',
                'sport' => 'Basket',
                'host_index' => 2,
                'description' => 'Liga basket lokal Surabaya dengan sistem kompetisi terstruktur dan rankingnya.',
                'rules' => 'Mengikuti aturan FIBA, komitmen untuk jadwal pertandingan, dan no violence.',
                'member_limit' => 60,
                'city' => 'Surabaya',
            ],
            [
                'name' => 'Elite Table Tennis Surabaya',
                'sport' => 'Tenis Meja',
                'host_index' => 2,
                'description' => 'Komunitas tenis meja elite dengan fokus pada teknik dan strategi bermain.',
                'rules' => 'Menggunakan equipment standar, latihan teknik dasar, dan saling membantu improve.',
                'member_limit' => 35,
                'city' => 'Surabaya',
            ]
        ];

        foreach ($communityTemplates as $communityData) {
            $sport = $sports->where('name', $communityData['sport'])->first();
            $host = $hosts->skip($communityData['host_index'])->first();
            
            if ($sport && $host) {
                $community = Community::create([
                    'sport_id' => $sport->id,
                    'host_user_id' => $host->id,
                    'name' => $communityData['name'],
                    'description' => $communityData['description'],
                    'rules' => $communityData['rules'],
                    'max_members' => $communityData['member_limit'],
                    'city' => $communityData['city'],
                    'province' => $host->profile->province,
                    'country' => $host->profile->country,
                    'is_active' => true,
                    'is_public' => true,
                    'created_at' => now()->subDays(rand(30, 90)),
                    'updated_at' => now()->subDays(rand(1, 30)),
                ]);

                // Add members to communities
                $suitablePlayers = $players->filter(function($player) use ($sport) {
                    return $player->sportRatings->where('sport_id', $sport->id)->isNotEmpty();
                });

                $memberCount = rand(15, $communityData['member_limit'] - 5);
                $selectedPlayers = $suitablePlayers->random(min($memberCount, $suitablePlayers->count()));

                foreach ($selectedPlayers as $player) {
                    CommunityMember::create([
                        'community_id' => $community->id,
                        'user_id' => $player->id,
                        'role' => rand(1, 10) == 1 ? 'admin' : 'member',
                        'joined_at' => now()->subDays(rand(1, 60)),
                    ]);
                }
            }
        }
    }

    private function createEventsWithMatchmaking($sports)
    {
        $hosts = User::where('user_type', 'host')->get();
        $venues = Venue::all();
        $communities = Community::all();
        
        $eventTemplates = [
            // Events dengan status ongoing (active matchmaking)
            [
                'title' => 'Tournament Badminton Jakarta Championship',
                'sport' => 'Badminton',
                'host_index' => 0,
                'venue_sport' => 'Badminton',
                'community_sport' => 'Badminton',
                'event_date' => now()->addHours(2),
                'status' => 'ongoing',
                'event_type' => 'tournament',
                'max_participants' => 24,
                'skill_level' => 'mahir',
                'entry_fee' => 50000,
                'description' => 'Turnamen badminton tingkat championship dengan hadiah jutaan rupiah. Peserta terbatas untuk pemain mahir.',
                'participant_count' => 20,
                'match_type' => 'singles',
            ],
            [
                'title' => 'Futsal League Match Day 5',
                'sport' => 'Futsal',
                'host_index' => 0,
                'venue_sport' => 'Futsal',
                'community_sport' => 'Futsal',
                'event_date' => now()->addHours(1),
                'status' => 'ongoing',
                'event_type' => 'friendly_match',
                'max_participants' => 20,
                'skill_level' => 'mahir',
                'entry_fee' => 0,
                'description' => 'Pertandingan liga futsal minggu ke-5. Tim sudah fix, pemain cadangan welcome.',
                'participant_count' => 18,
                'match_type' => 'team',
            ],
            // Events dengan status open_registration
            [
                'title' => 'Weekend Basketball Pickup',
                'sport' => 'Basket',
                'host_index' => 2,
                'venue_sport' => 'Basket',
                'community_sport' => 'Basket',
                'event_date' => now()->addDays(2),
                'status' => 'published',
                'event_type' => 'mabar',
                'max_participants' => 20,
                'skill_level' => 'mixed',
                'entry_fee' => 0,
                'description' => 'Pickup basketball game santai di weekend. Semua skill level welcome!',
                'participant_count' => 12,
                'match_type' => 'team',
            ],
            [
                'title' => 'Badminton Training Session',
                'sport' => 'Badminton',
                'host_index' => 1,
                'venue_sport' => 'Badminton',
                'community_sport' => 'Badminton',
                'event_date' => now()->addDays(3),
                'status' => 'published',
                'event_type' => 'coaching',
                'max_participants' => 16,
                'skill_level' => 'menengah',
                'entry_fee' => 25000,
                'description' => 'Sesi latihan badminton dengan coach berpengalaman. Fokus pada teknik dan strategi.',
                'participant_count' => 10,
                'match_type' => 'singles',
            ],
            [
                'title' => 'Table Tennis Tournament',
                'sport' => 'Tenis Meja',
                'host_index' => 2,
                'venue_sport' => 'Tenis Meja',
                'community_sport' => 'Tenis Meja',
                'event_date' => now()->addDays(5),
                'status' => 'published',
                'event_type' => 'tournament',
                'max_participants' => 32,
                'skill_level' => 'mixed',
                'entry_fee' => 35000,
                'description' => 'Turnamen tenis meja terbuka untuk semua level. Sistem gugur langsung.',
                'participant_count' => 25,
                'match_type' => 'singles',
            ],
            // Events yang sudah selesai (untuk history)
            [
                'title' => 'Jakarta Badminton Open 2025',
                'sport' => 'Badminton',
                'host_index' => 0,
                'venue_sport' => 'Badminton',
                'community_sport' => 'Badminton',
                'event_date' => now()->subDays(7),
                'status' => 'completed',
                'event_type' => 'tournament',
                'max_participants' => 32,
                'skill_level' => 'mahir',
                'entry_fee' => 75000,
                'description' => 'Turnamen badminton terbesar Jakarta tahun ini. Sudah selesai dengan antusias tinggi.',
                'participant_count' => 32,
                'match_type' => 'singles',
            ],
        ];

        foreach ($eventTemplates as $eventData) {
            $sport = $sports->where('name', $eventData['sport'])->first();
            $host = $hosts->skip($eventData['host_index'])->first();
            
            if (!$sport || !$host) {
                continue;
            }
            
            $venue = $venues->where('sport_id', $sport->id)->where('owner_id', $host->id)->first();
            $community = $communities->where('sport_id', $sport->id)->where('host_user_id', $host->id)->first();
            
            if ($sport && $host && $venue && $community) {
                $event = Event::create([
                    'community_id' => $community->id,
                    'sport_id' => $sport->id,
                    'host_id' => $host->id,
                    'venue_id' => $venue->id,
                    'title' => $eventData['title'],
                    'description' => $eventData['description'],
                    'event_date' => $eventData['event_date'],
                    'registration_deadline' => $eventData['event_date']->copy()->subHours(12),
                    'max_participants' => $eventData['max_participants'],
                    'courts_used' => min(4, $venue->total_courts),
                    'max_courts' => min(6, $venue->total_courts),
                    'skill_level_required' => $eventData['skill_level'],
                    'event_type' => $eventData['event_type'],
                    'status' => $eventData['status'],
                    'entry_fee' => $eventData['entry_fee'],
                    'latitude' => $venue->latitude,
                    'longitude' => $venue->longitude,
                    'location_name' => $venue->name,
                    'location_address' => $venue->address,
                    'auto_queue_enabled' => true,
                    'auto_confirm_participants' => true,
                    'is_premium_only' => false,
                    'created_at' => now()->subDays(rand(10, 30)),
                    'updated_at' => now()->subHours(rand(1, 24)),
                ]);

                // Add participants to events
                $this->addParticipantsToEvent($event, $eventData['participant_count'], $eventData['match_type']);
            }
        }
    }

    private function addParticipantsToEvent($event, $participantCount, $matchType)
    {
        $players = User::where('user_type', 'player')->get();
        
        // Filter players yang punya skill rating untuk sport ini
        $suitablePlayers = $players->filter(function($player) use ($event) {
            $sportRating = $player->sportRatings->where('sport_id', $event->sport_id)->first();
            return $sportRating && $sportRating->mmr > 0;
        });

        // Prioritaskan players yang sesuai dengan skill level event
        $suitablePlayers = $suitablePlayers->sortByDesc(function($player) use ($event) {
            $sportRating = $player->sportRatings->where('sport_id', $event->sport_id)->first();
            
            // Scoring berdasarkan kesesuaian skill level
            $score = 0;
            
            if ($event->skill_level == 'pemula' && $sportRating->mmr < 1100) $score += 100;
            if ($event->skill_level == 'menengah' && $sportRating->mmr >= 1100 && $sportRating->mmr < 1400) $score += 100;
            if ($event->skill_level == 'mahir' && $sportRating->mmr >= 1400) $score += 100;
            if ($event->skill_level == 'mixed') $score += 50;
            
            // Tambahan score untuk aktifitas
            $score += $sportRating->matches_played;
            
            return $score;
        });

        $selectedPlayers = $suitablePlayers->take($participantCount);

        foreach ($selectedPlayers as $index => $player) {
            $status = 'confirmed';
            
            // For ongoing events, some players should be checked_in
            if ($event->status == 'ongoing' && $index < $participantCount * 0.8) {
                $status = 'checked_in';
            }
            
            EventParticipant::create([
                'event_id' => $event->id,
                'user_id' => $player->id,
                'status' => $status,
                'queue_position' => $index + 1,
                'is_premium_protected' => $player->subscription_tier == 'premium',
                'registered_at' => now()->subDays(rand(1, 14)),
                'checked_in_at' => $status == 'checked_in' ? now()->subMinutes(rand(30, 120)) : null,
            ]);
        }
    }

    private function createActiveMatchmakingScenarios()
    {
        // Get ongoing events
        $ongoingEvents = Event::where('status', 'ongoing')->get();
        
        foreach ($ongoingEvents as $event) {
            $checkedInParticipants = $event->participants()
                ->where('status', 'checked_in')
                ->with('user')
                ->get();
            
            if ($checkedInParticipants->count() >= 2) {
                // Create ongoing matches
                $matchCount = min(3, floor($checkedInParticipants->count() / 2));
                
                for ($i = 0; $i < $matchCount; $i++) {
                    $player1 = $checkedInParticipants->get($i * 2);
                    $player2 = $checkedInParticipants->get($i * 2 + 1);
                    
                    if ($player1 && $player2) {
                        $player1Rating = $player1->user->sportRatings->where('sport_id', $event->sport_id)->first();
                        $player2Rating = $player2->user->sportRatings->where('sport_id', $event->sport_id)->first();
                        
                        MatchHistory::create([
                            'event_id' => $event->id,
                            'sport_id' => $event->sport_id,
                            'player1_id' => $player1->user_id,
                            'player2_id' => $player2->user_id,
                            'result' => 'draw', // Temporary result for ongoing match
                            'match_score' => json_encode(['player1_score' => 0, 'player2_score' => 0]),
                            'player1_mmr_before' => $player1Rating ? $player1Rating->mmr : 1000,
                            'player1_mmr_after' => $player1Rating ? $player1Rating->mmr : 1000,
                            'player2_mmr_before' => $player2Rating ? $player2Rating->mmr : 1000,
                            'player2_mmr_after' => $player2Rating ? $player2Rating->mmr : 1000,
                            'recorded_by_host_id' => $event->host_id,
                            'match_date' => now()->subMinutes(rand(10, 60)),
                            'court_number' => $i + 1,
                            'estimated_duration' => 60,
                            'match_status' => 'ongoing',
                            'match_notes' => 'Generated by matchmaking system',
                        ]);
                    }
                }
            }
        }
    }

    private function createMatchHistoryData()
    {
        $completedEvents = Event::where('status', 'completed')->get();
        
        foreach ($completedEvents as $event) {
            $participants = $event->participants()
                ->where('status', 'confirmed')
                ->with('user')
                ->get();
            
            if ($participants->count() >= 2) {
                // Create completed matches
                $matchCount = min(8, floor($participants->count() / 2));
                
                for ($i = 0; $i < $matchCount; $i++) {
                    $player1 = $participants->get($i * 2);
                    $player2 = $participants->get($i * 2 + 1);
                    
                    if ($player1 && $player2) {
                        $player1Rating = $player1->user->sportRatings->where('sport_id', $event->sport_id)->first();
                        $player2Rating = $player2->user->sportRatings->where('sport_id', $event->sport_id)->first();
                        
                        $results = ['player1_win', 'player2_win', 'draw'];
                        $result = $results[array_rand($results)];
                        
                        // Generate realistic scores based on sport
                        $scores = $this->generateScores($event->sport->name, $result);
                        
                        MatchHistory::create([
                            'event_id' => $event->id,
                            'sport_id' => $event->sport_id,
                            'player1_id' => $player1->user_id,
                            'player2_id' => $player2->user_id,
                            'result' => $result,
                            'match_score' => $scores,
                            'player1_mmr_before' => $player1Rating ? $player1Rating->mmr : 1000,
                            'player1_mmr_after' => $player1Rating ? $player1Rating->mmr + rand(-30, 30) : 1000,
                            'player2_mmr_before' => $player2Rating ? $player2Rating->mmr : 1000,
                            'player2_mmr_after' => $player2Rating ? $player2Rating->mmr + rand(-30, 30) : 1000,
                            'recorded_by_host_id' => $event->host_id,
                            'match_date' => $event->event_date->copy()->addMinutes(rand(30, 180)),
                            'court_number' => ($i % 4) + 1,
                            'estimated_duration' => rand(45, 90),
                            'match_status' => 'completed',
                            'match_notes' => 'Completed tournament match',
                        ]);
                    }
                }
            }
        }
    }

    private function generateScores($sportName, $result)
    {
        switch ($sportName) {
            case 'Badminton':
                $scores = [
                    'player1_score' => $result == 'player1_win' ? 21 : rand(15, 20),
                    'player2_score' => $result == 'player2_win' ? 21 : rand(15, 20),
                    'sets' => [
                        ['player1' => rand(18, 21), 'player2' => rand(15, 21)],
                        ['player1' => rand(15, 21), 'player2' => rand(18, 21)],
                    ]
                ];
                break;
            case 'Futsal':
                $scores = [
                    'player1_score' => $result == 'player1_win' ? rand(3, 8) : rand(0, 4),
                    'player2_score' => $result == 'player2_win' ? rand(3, 8) : rand(0, 4),
                    'half_time' => [
                        'player1' => rand(0, 3),
                        'player2' => rand(0, 3),
                    ]
                ];
                break;
            case 'Basket':
                $scores = [
                    'player1_score' => $result == 'player1_win' ? rand(75, 95) : rand(60, 80),
                    'player2_score' => $result == 'player2_win' ? rand(75, 95) : rand(60, 80),
                    'quarters' => [
                        ['player1' => rand(15, 25), 'player2' => rand(15, 25)],
                        ['player1' => rand(15, 25), 'player2' => rand(15, 25)],
                        ['player1' => rand(15, 25), 'player2' => rand(15, 25)],
                        ['player1' => rand(15, 25), 'player2' => rand(15, 25)],
                    ]
                ];
                break;
            case 'Tenis Meja':
                $scores = [
                    'player1_score' => $result == 'player1_win' ? 3 : rand(0, 2),
                    'player2_score' => $result == 'player2_win' ? 3 : rand(0, 2),
                    'sets' => [
                        ['player1' => rand(9, 11), 'player2' => rand(9, 11)],
                        ['player1' => rand(9, 11), 'player2' => rand(9, 11)],
                        ['player1' => rand(9, 11), 'player2' => rand(9, 11)],
                    ]
                ];
                break;
            default:
                $scores = [
                    'player1_score' => $result == 'player1_win' ? 1 : 0,
                    'player2_score' => $result == 'player2_win' ? 1 : 0,
                ];
        }
        
        if ($result == 'draw') {
            $scores['player1_score'] = $scores['player2_score'];
        }
        
        return $scores;
    }

    private function createSocialConnections()
    {
        $players = User::where('user_type', 'player')->get();
        
        // Create friendships
        for ($i = 0; $i < 15; $i++) {
            $player1 = $players->random();
            $player2 = $players->where('id', '!=', $player1->id)->random();
            
            // Check if friendship already exists
            $existingFriendship = Friendship::where(function($q) use ($player1, $player2) {
                $q->where('user_id', $player1->id)->where('friend_id', $player2->id);
            })->orWhere(function($q) use ($player1, $player2) {
                $q->where('user_id', $player2->id)->where('friend_id', $player1->id);
            })->first();
            
            if (!$existingFriendship) {
                Friendship::create([
                    'user_id' => $player1->id,
                    'friend_id' => $player2->id,
                    'status' => 'accepted',
                    'created_at' => now()->subDays(rand(1, 30)),
                    'updated_at' => now()->subDays(rand(1, 30)),
                ]);
            }
        }

        // Create private messages
        $friendships = Friendship::where('status', 'accepted')->get();
        
        foreach ($friendships->take(10) as $friendship) {
            $messageCount = rand(3, 15);
            
            for ($i = 0; $i < $messageCount; $i++) {
                $sender = rand(0, 1) ? $friendship->user_id : $friendship->friend_id;
                $receiver = $sender == $friendship->user_id ? $friendship->friend_id : $friendship->user_id;
                
                $messages = [
                    'Halo! Gimana kabar?',
                    'Mau main badminton nggak nanti sore?',
                    'Ada turnamen bagus nih minggu depan',
                    'Oke, jam berapa kita ketemu?',
                    'GG match tadi! Kamu improve banget',
                    'Kapan latihan lagi?',
                    'Thanks buat game tadi ya',
                    'Cek event baru di komunitas dong',
                    'Ikut tournament weekend ini nggak?',
                    'Skill kamu makin bagus nih',
                ];
                
                PrivateMessage::create([
                    'sender_id' => $sender,
                    'receiver_id' => $receiver,
                    'message' => $messages[array_rand($messages)],
                    'read_at' => rand(0, 1) ? now()->subHours(rand(1, 48)) : null,
                    'created_at' => now()->subDays(rand(0, 10))->subMinutes(rand(0, 1440)),
                    'updated_at' => now()->subDays(rand(0, 10))->subMinutes(rand(0, 1440)),
                ]);
            }
        }
    }

    private function createNotifications()
    {
        $players = User::where('user_type', 'player')->get();
        $hosts = User::where('user_type', 'host')->get();
        
        $playerNotifications = [
            'match_result' => 'Hasil pertandingan Anda telah diupdate.',
            'event_reminder' => 'Reminder: Event turnamen dimulai dalam 2 jam.',
            'new_event' => 'Event baru tersedia di komunitas Anda!',
            'community_invite' => 'Anda diundang untuk bergabung dalam turnamen eksklusif.',
            'rating_received' => 'Rating Anda naik menjadi 1450 MMR! Kerja bagus!',
            'credit_score_change' => 'Credit score Anda telah diupdate.',
            'waitlist_promoted' => 'Anda dipromosikan dari waitlist event badminton.',
            'event_cancelled' => 'Event yang Anda daftarkan telah dibatalkan.',
        ];
        
        $hostNotifications = [
            'new_event' => 'Event baru telah berhasil dibuat dan dipublikasikan.',
            'event_reminder' => 'Event Anda akan dimulai dalam 30 menit.',
            'match_result' => 'Match di Court 3 telah selesai.',
            'rating_received' => 'Rating venue Anda telah diupdate oleh peserta.',
            'credit_score_change' => 'Credit score venue Anda telah diupdate.',
            'event_cancelled' => 'Event telah dibatalkan karena kurang peserta.',
            'waitlist_promoted' => 'Peserta waitlist telah dipromosikan otomatis.',
            'community_invite' => 'Anda diundang untuk bergabung dengan komunitas lain.',
        ];

        // Create notifications for players
        foreach ($players as $player) {
            $notificationCount = rand(5, 12);
            
            for ($i = 0; $i < $notificationCount; $i++) {
                $type = array_rand($playerNotifications);
                $message = $playerNotifications[$type];
                
                Notification::create([
                    'user_id' => $player->id,
                    'type' => $type,
                    'title' => ucfirst(str_replace('_', ' ', $type)),
                    'message' => $message,
                    'data' => json_encode([
                        'priority' => rand(0, 1) ? 'high' : 'medium',
                        'action_url' => null,
                        'source' => 'matchmaking_system',
                    ]),
                    'read_at' => rand(0, 1) ? now()->subHours(rand(1, 48)) : null,
                    'created_at' => now()->subHours(rand(1, 168)),
                    'updated_at' => now()->subHours(rand(1, 168)),
                ]);
            }
        }

        // Create notifications for hosts
        foreach ($hosts as $host) {
            $notificationCount = rand(8, 15);
            
            for ($i = 0; $i < $notificationCount; $i++) {
                $type = array_rand($hostNotifications);
                $message = $hostNotifications[$type];
                
                Notification::create([
                    'user_id' => $host->id,
                    'type' => $type,
                    'title' => ucfirst(str_replace('_', ' ', $type)),
                    'message' => $message,
                    'data' => json_encode([
                        'priority' => rand(0, 1) ? 'high' : 'medium',
                        'action_url' => null,
                        'source' => 'host_management',
                    ]),
                    'read_at' => rand(0, 1) ? now()->subHours(rand(1, 48)) : null,
                    'created_at' => now()->subHours(rand(1, 168)),
                    'updated_at' => now()->subHours(rand(1, 168)),
                ]);
            }
        }
    }

    private function createGuestPlayers()
    {
        $ongoingEvents = Event::where('status', 'ongoing')->get();
        
        foreach ($ongoingEvents as $event) {
            $guestCount = rand(2, 5);
            
            for ($i = 0; $i < $guestCount; $i++) {
                $guestNames = [
                    'John Doe', 'Jane Smith', 'Mike Johnson', 'Sarah Wilson',
                    'David Chen', 'Lisa Wang', 'Kevin Brown', 'Amy Taylor',
                    'Robert Davis', 'Michelle Lee', 'Chris Martin', 'Jennifer Kim',
                    'Daniel Garcia', 'Emily Rodriguez', 'James Wilson', 'Ashley Martinez'
                ];
                
                $phones = [
                    '081234567200', '081234567201', '081234567202', '081234567203',
                    '081234567204', '081234567205', '081234567206', '081234567207',
                    '081234567208', '081234567209', '081234567210', '081234567211',
                    '081234567212', '081234567213', '081234567214', '081234567215'
                ];
                
                $name = $guestNames[array_rand($guestNames)];
                $phone = $phones[array_rand($phones)];
                
                // Check if guest already exists for this event
                $existingGuest = GuestPlayer::where('event_id', $event->id)
                    ->where('phone', $phone)
                    ->first();
                
                if (!$existingGuest) {
                    GuestPlayer::create([
                        'event_id' => $event->id,
                        'sport_id' => $event->sport_id,
                        'name' => $name,
                        'phone' => $phone,
                        'skill_level' => rand(1, 8),
                        'estimated_mmr' => rand(900, 1600),
                        'added_by' => $event->host_id,
                        'is_active' => true,
                        'valid_until' => $event->event_date->addHours(6),
                        'expires_at' => $event->event_date->addDays(1),
                        'created_at' => now()->subMinutes(rand(60, 300)),
                        'updated_at' => now()->subMinutes(rand(30, 180)),
                    ]);
                }
            }
        }
    }

    private function displayDemoAccounts()
    {
        $this->command->info('');
        $this->command->info('🎮 Demo Accounts Created:');
        $this->command->info('');
        
        $this->command->info('👑 HOST ACCOUNTS:');
        $hosts = User::where('user_type', 'host')->get();
        foreach ($hosts as $host) {
            $venueCount = Venue::where('owner_id', $host->id)->count();
            $eventCount = $host->hostedEvents()->count();
            $this->command->info("  📧 {$host->email} | 🏢 {$host->name} | 🏟️ {$venueCount} venues | 📅 {$eventCount} events");
        }
        
        $this->command->info('');
        $this->command->info('👤 PLAYER ACCOUNTS:');
        $players = User::where('user_type', 'player')->get();
        foreach ($players as $player) {
            $sportsCount = $player->sportRatings()->count();
            $eventsCount = $player->eventParticipations()->count();
            $this->command->info("  📧 {$player->email} | 👤 {$player->name} | 🏆 {$sportsCount} sports | 📅 {$eventsCount} events");
        }
        
        $this->command->info('');
        $this->command->info('📊 SUMMARY:');
        $this->command->info("  🏟️ Venues: " . Venue::count());
        $this->command->info("  🏘️ Communities: " . Community::count());
        $this->command->info("  📅 Events: " . Event::count());
        $this->command->info("  🎯 Active Events: " . Event::where('status', 'ongoing')->count());
        $this->command->info("  🏓 Match History: " . MatchHistory::count());
        $this->command->info("  👥 Event Participants: " . EventParticipant::count());
        // $this->command->info("  🎮 Guest Players: " . GuestPlayer::count()); // Commented out for now
        $this->command->info("  📧 Notifications: " . Notification::count());
        
        $this->command->info('');
        $this->command->info('🔑 Login dengan email dan password: password123');
        $this->command->info('');
        $this->command->info('🎯 Siap untuk preview matchmaking system!');
    }
} 