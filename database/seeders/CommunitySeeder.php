<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\CommunityMessage;
use App\Models\User;
use App\Models\Sport;

class CommunitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sports = Sport::all();
        $hosts = User::where('user_type', 'host')->get();
        $players = User::where('user_type', 'player')->get();

        if ($hosts->isEmpty() || $players->isEmpty() || $sports->isEmpty()) {
            $this->command->warn('Please run UserSeeder and SportsSeeder first!');
            return;
        }

        $communities = [
            [
                'name' => 'Jakarta Badminton Club',
                'description' => 'Komunitas badminton terbesar di Jakarta. Menyediakan latihan rutin setiap hari dan turnamen bulanan. Cocok untuk semua level pemain.',
                'sport' => 'Badminton',
                'city' => 'Jakarta',
                'province' => 'DKI Jakarta',
                'country' => 'Indonesia',
                'location_name' => 'GOR Badminton Senayan',
                'venue_name' => 'Senayan Sports Center',
                'venue_address' => 'Jl. Asia Afrika, Senayan, Jakarta Pusat',
                'latitude' => -6.2297465,
                'longitude' => 106.8066715,
                'community_type' => 'public',
                'skill_level_focus' => 'mixed',
                'max_members' => 100,
                'membership_fee' => 0, // FREE FOR DEVELOPMENT
                'regular_schedule' => 'Senin, Rabu, Jumat 19:00-21:00',
                'is_public' => true,
                'is_premium_required' => false,
                'contact_info' => json_encode([
                    'whatsapp' => '081234567890',
                    'instagram' => '@jakartabadmintonclub'
                ]),
                'rules' => json_encode([
                    'Datang tepat waktu',
                    'Bawa perlengkapan sendiri',
                    'Hormati sesama anggota',
                    'Bayar iuran tepat waktu'
                ])
            ],
            [
                'name' => 'Bandung Futsal Warriors',
                'description' => 'Komunitas futsal kompetitif di Bandung. Fokus pada peningkatan skill dan fair play. Rutin mengadakan sparring dengan klub lain.',
                'sport' => 'Futsal',
                'city' => 'Bandung',
                'province' => 'Jawa Barat',
                'country' => 'Indonesia',
                'location_name' => 'Lapangan Futsal Sarijadi',
                'venue_name' => 'Sarijadi Futsal Center',
                'venue_address' => 'Jl. Sarijadi No. 15, Bandung',
                'latitude' => -6.8915132,
                'longitude' => 107.6107456,
                'community_type' => 'public',
                'skill_level_focus' => 'mahir',
                'max_members' => 50,
                'membership_fee' => 0, // FREE FOR DEVELOPMENT
                'regular_schedule' => 'Selasa, Kamis 20:00-22:00, Minggu 16:00-18:00',
                'is_public' => true,
                'is_premium_required' => false,
                'contact_info' => json_encode([
                    'whatsapp' => '081234567891',
                    'email' => 'contact@bandungfutsalwarriors.com'
                ]),
                'rules' => json_encode([
                    'Komitmen latihan minimal 2x seminggu',
                    'Tidak boleh terlambat >15 menit',
                    'Wajib ikut turnamen klub'
                ])
            ],
            [
                'name' => 'Surabaya Basketball League',
                'description' => 'Liga basket informal untuk pemain dewasa di Surabaya. Kompetisi friendly dengan sistem poin bulanan. Terbuka untuk semua level.',
                'sport' => 'Basket',
                'city' => 'Surabaya',
                'province' => 'Jawa Timur',
                'country' => 'Indonesia',
                'location_name' => 'Lapangan Basket Taman Bungkul',
                'venue_name' => 'Taman Bungkul Basketball Court',
                'venue_address' => 'Taman Bungkul, Jl. Raya Darmo, Surabaya',
                'latitude' => -7.2909804,
                'longitude' => 112.7378019,
                'community_type' => 'public',
                'skill_level_focus' => 'mixed',
                'max_members' => 80,
                'membership_fee' => 0,
                'regular_schedule' => 'Sabtu 08:00-12:00, Minggu 08:00-12:00',
                'is_public' => true,
                'is_premium_required' => false,
                'contact_info' => json_encode([
                    'whatsapp' => '081234567892',
                    'telegram' => '@sbybasketleague'
                ]),
                'rules' => json_encode([
                    'Fair play adalah prioritas',
                    'Respect terhadap wasit',
                    'Tidak ada kekerasan fisik/verbal'
                ])
            ],
            [
                'name' => 'Medan Table Tennis Club',
                'description' => 'Klub tenis meja eksklusif dengan fasilitas premium. Coaching professional dan peralatan berkualitas tinggi tersedia.',
                'sport' => 'Tenis Meja',
                'city' => 'Medan',
                'province' => 'Sumatera Utara', 
                'country' => 'Indonesia',
                'location_name' => 'Medan Table Tennis Center',
                'venue_name' => 'Premium TT Center',
                'venue_address' => 'Jl. Gatot Subroto No. 88, Medan',
                'latitude' => 3.5951956,
                'longitude' => 98.6722227,
                'community_type' => 'private',
                'skill_level_focus' => 'ahli',
                'max_members' => 30,
                'membership_fee' => 0, // FREE FOR DEVELOPMENT
                'regular_schedule' => 'Senin-Jumat 18:00-21:00, Weekend 14:00-17:00',
                'is_public' => false,
                'is_premium_required' => true,
                'contact_info' => json_encode([
                    'phone' => '061-1234567',
                    'email' => 'info@medanttclub.com'
                ]),
                'rules' => json_encode([
                    'Member harus sudah berpengalaman',
                    'Wajib menggunakan sepatu khusus',
                    'Booking meja harus H-1'
                ])
            ]
        ];

        foreach ($communities as $communityData) {
            // Find sport and host
            $sport = $sports->where('name', $communityData['sport'])->first();
            $host = $hosts->random();

            if (!$sport) continue;

            $community = Community::create([
                'name' => $communityData['name'],
                'description' => $communityData['description'],
                'sport_id' => $sport->id,
                'host_user_id' => $host->id,
                'city' => $communityData['city'],
                'province' => $communityData['province'],
                'country' => $communityData['country'],
                'location_name' => $communityData['location_name'],
                'venue_name' => $communityData['venue_name'],
                'venue_address' => $communityData['venue_address'],
                'latitude' => $communityData['latitude'],
                'longitude' => $communityData['longitude'],
                'community_type' => $communityData['community_type'],
                'skill_level_focus' => $communityData['skill_level_focus'],
                'max_members' => $communityData['max_members'],
                'membership_fee' => $communityData['membership_fee'],
                'regular_schedule' => $communityData['regular_schedule'],
                'is_public' => $communityData['is_public'],
                'is_premium_required' => $communityData['is_premium_required'],
                'contact_info' => $communityData['contact_info'],
                'rules' => $communityData['rules'],
                'average_skill_rating' => rand(30, 50) / 10,
                'hospitality_rating' => rand(35, 50) / 10,
                'total_events' => rand(5, 25),
                'is_active' => true,
            ]);

            // Add host as admin member
            CommunityMember::create([
                'community_id' => $community->id,
                'user_id' => $host->id,
                'role' => 'admin',
                'status' => 'active',
                'joined_at' => now()->subDays(rand(30, 365)),
                'last_activity_at' => now()->subDays(rand(1, 7)),
            ]);

            // Add some random players as members
            $maxPlayers = min(rand(3, 8), $players->count());
            $randomPlayers = $players->random($maxPlayers);
            foreach ($randomPlayers as $player) {
                if ($player->id === $host->id) continue; // Skip if player is also host

                CommunityMember::create([
                    'community_id' => $community->id,
                    'user_id' => $player->id,
                    'role' => rand(1, 10) > 8 ? 'moderator' : 'member',
                    'status' => 'active',
                    'joined_at' => now()->subDays(rand(1, 180)),
                    'last_activity_at' => now()->subDays(rand(0, 14)),
                ]);

                // Add some sample messages
                if (rand(1, 3) === 1) {
                    $messages = [
                        'Halo semua! Senang bergabung di komunitas ini 👋',
                        'Kapan jadwal latihan berikutnya?',
                        'Ada yang mau sparring hari Sabtu?',
                        'Terima kasih untuk sesi latihan yang bagus kemarin!',
                        'Siapa yang ikut turnamen bulan depan?'
                    ];

                    CommunityMessage::create([
                        'community_id' => $community->id,
                        'user_id' => $player->id,
                        'message' => $messages[array_rand($messages)],
                        'message_type' => 'text',
                        'created_at' => now()->subDays(rand(0, 30)),
                    ]);
                }
            }

            // Update member count
            $community->update([
                'member_count' => CommunityMember::where('community_id', $community->id)
                    ->where('status', 'active')->count()
            ]);
        }

        $this->command->info('Communities, members, and messages seeded successfully!');
    }
}