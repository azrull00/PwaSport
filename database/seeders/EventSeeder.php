<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\User;
use App\Models\Sport;
use App\Models\Community;
use App\Models\Venue;
use Carbon\Carbon;

class EventSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sports = Sport::all();
        $hosts = User::where('user_type', 'host')->get();
        $players = User::where('user_type', 'player')->get();
        $communities = Community::all();

        if ($hosts->isEmpty() || $players->isEmpty() || $sports->isEmpty()) {
            $this->command->warn('Please run UserSeeder, SportsSeeder, and CommunitySeeder first!');
            return;
        }

        // Create some venues first
        $venues = [
            [
                'name' => 'GOR Badminton Senayan',
                'address' => 'Jl. Asia Afrika, Senayan, Jakarta Pusat',
                'city' => 'Jakarta',
                'province' => 'DKI Jakarta',
                'country' => 'Indonesia',
                'latitude' => -6.2297465,
                'longitude' => 106.8066715,
                'contact_phone' => '021-5551234',
                'facilities' => json_encode(['AC', 'Locker Room', 'Parking', 'Canteen']),
                'total_courts' => 8,
                'court_type' => 'indoor',
                'hourly_rate' => 100000,
                'operating_hours' => json_encode(['open' => '06:00', 'close' => '22:00']),
                'sport' => 'Badminton',
            ],
            [
                'name' => 'Sarijadi Futsal Center',
                'address' => 'Jl. Sarijadi No. 15, Bandung',
                'city' => 'Bandung',
                'province' => 'Jawa Barat',
                'country' => 'Indonesia',
                'latitude' => -6.8915132,
                'longitude' => 107.6107456,
                'contact_phone' => '022-5551234',
                'facilities' => json_encode(['Synthetic Grass', 'Floodlight', 'Parking', 'Cafeteria']),
                'total_courts' => 4,
                'court_type' => 'indoor',
                'hourly_rate' => 200000,
                'operating_hours' => json_encode(['open' => '08:00', 'close' => '24:00']),
                'sport' => 'Futsal',
            ],
            [
                'name' => 'Taman Bungkul Basketball Court',
                'address' => 'Taman Bungkul, Jl. Raya Darmo, Surabaya',
                'city' => 'Surabaya',
                'province' => 'Jawa Timur',
                'country' => 'Indonesia',
                'latitude' => -7.2909804,
                'longitude' => 112.7378019,
                'contact_phone' => '031-5551234',
                'facilities' => json_encode(['Outdoor Court', 'Free Access', 'Public Restroom']),
                'total_courts' => 2,
                'court_type' => 'outdoor',
                'hourly_rate' => 0,
                'operating_hours' => json_encode(['open' => '06:00', 'close' => '18:00']),
                'sport' => 'Basket',
            ]
        ];

        foreach ($venues as $venueData) {
            $sport = $sports->where('name', $venueData['sport'])->first();
            $owner = $hosts->random();
            
            if ($sport && $owner) {
                Venue::create([
                    'sport_id' => $sport->id,
                    'owner_id' => $owner->id,
                    'name' => $venueData['name'],
                    'address' => $venueData['address'],
                    'city' => $venueData['city'],
                    'province' => $venueData['province'],
                    'country' => $venueData['country'],
                    'latitude' => $venueData['latitude'],
                    'longitude' => $venueData['longitude'],
                    'contact_phone' => $venueData['contact_phone'],
                    'facilities' => $venueData['facilities'],
                    'total_courts' => $venueData['total_courts'],
                    'court_type' => $venueData['court_type'],
                    'hourly_rate' => $venueData['hourly_rate'],
                    'operating_hours' => $venueData['operating_hours'],
                    'is_active' => true,
                    'is_verified' => true,
                ]);
            }
        }

        $createdVenues = Venue::all();

        // Create events with different types and times
        $events = [
            // Upcoming events
            [
                'title' => 'Tournament Badminton Jakarta Open',
                'description' => 'Turnamen badminton terbuka untuk semua level. Hadiah menarik untuk juara! Daftar sekarang dan tunjukkan kemampuan terbaikmu.',
                'sport' => 'Badminton',
                'venue' => 'GOR Badminton Senayan',
                'community' => 'Jakarta Badminton Club',
                'event_date' => now()->addDays(7)->format('Y-m-d'),
                'start_time' => '08:00:00',
                'end_time' => '17:00:00',
                'max_participants' => 32,
                'entry_fee' => 0,
                'skill_level' => 'mixed',
                'registration_deadline' => now()->addDays(5),
                'status' => 'open_registration',
                'event_type' => 'tournament',
                'is_community_event' => true,
            ],
            [
                'title' => 'Sparring Futsal Bandung Warriors',
                'description' => 'Sesi sparring rutin untuk mengasah kemampuan dan chemistry tim. Fokus pada passing dan tactical play.',
                'sport' => 'Futsal',
                'venue' => 'Sarijadi Futsal Center',
                'community' => 'Bandung Futsal Warriors',
                'event_date' => now()->addDays(3)->format('Y-m-d'),
                'start_time' => '20:00:00',
                'end_time' => '22:00:00',
                'max_participants' => 20,
                'entry_fee' => 0,
                'skill_level' => 'mahir',
                'registration_deadline' => now()->addDays(1),
                'status' => 'open_registration',
                'event_type' => 'training',
                'is_community_event' => true,
            ],
            [
                'title' => 'Pickup Basketball Game',
                'description' => 'Game santai basketball di Taman Bungkul. Terbuka untuk semua yang ingin bermain dan bersenang-senang. Free entry!',
                'sport' => 'Basket',
                'venue' => 'Taman Bungkul Basketball Court',
                'community' => 'Surabaya Basketball League',
                'event_date' => now()->addDays(2)->format('Y-m-d'),
                'start_time' => '16:00:00',
                'end_time' => '18:00:00',
                'max_participants' => 16,
                'entry_fee' => 0,
                'skill_level' => 'mixed',
                'registration_deadline' => now()->addDays(1),
                'status' => 'open_registration',
                'event_type' => 'casual',
                'is_community_event' => true,
            ],
            [
                'title' => 'Latihan Reguler Jakarta Badminton',
                'description' => 'Latihan rutin Jakarta Badminton Club. Sesi ini akan fokus pada teknik dasar dan strategy game.',
                'sport' => 'Badminton',
                'venue' => 'GOR Badminton Senayan',
                'community' => 'Jakarta Badminton Club',
                'event_date' => now()->addDays(1)->format('Y-m-d'),
                'start_time' => '19:00:00',
                'end_time' => '21:00:00',
                'max_participants' => 16,
                'entry_fee' => 0,
                'skill_level' => 'mixed',
                'registration_deadline' => now()->addHours(12),
                'status' => 'open_registration',
                'event_type' => 'training',
                'is_community_event' => true,
            ],
            // Past events
            [
                'title' => 'Turnamen Futsal Weekend Warrior',
                'description' => 'Turnamen futsal yang sudah berlangsung minggu lalu. Kompetisi sengit dengan 16 tim terbaik.',
                'sport' => 'Futsal',
                'venue' => 'Sarijadi Futsal Center',
                'community' => 'Bandung Futsal Warriors',
                'event_date' => now()->subDays(7)->format('Y-m-d'),
                'start_time' => '08:00:00',
                'end_time' => '18:00:00',
                'max_participants' => 32,
                'entry_fee' => 0,
                'skill_level' => 'mahir',
                'registration_deadline' => now()->subDays(14),
                'status' => 'completed',
                'event_type' => 'tournament',
                'is_community_event' => true,
            ],
            [
                'title' => 'Basketball Coaching Clinic',
                'description' => 'Clinic coaching yang sudah selesai. Banyak ilmu baru yang didapat tentang fundamental basketball.',
                'sport' => 'Basket',
                'venue' => 'Taman Bungkul Basketball Court',
                'community' => 'Surabaya Basketball League',
                'event_date' => now()->subDays(14)->format('Y-m-d'),
                'start_time' => '09:00:00',
                'end_time' => '12:00:00',
                'max_participants' => 24,
                'entry_fee' => 0,
                'skill_level' => 'mixed',
                'registration_deadline' => now()->subDays(21),
                'status' => 'completed',
                'event_type' => 'clinic',
                'is_community_event' => true,
            ]
        ];

        foreach ($events as $eventData) {
            // Find related models
            $sport = $sports->where('name', $eventData['sport'])->first();
            $venue = $createdVenues->where('name', $eventData['venue'])->first();
            $community = $communities->where('name', $eventData['community'])->first();
            $host = $community ? User::find($community->host_user_id) : $hosts->random();

            if (!$sport || !$venue || !$host) continue;

            $event = Event::create([
                'title' => $eventData['title'],
                'description' => $eventData['description'],
                'sport_id' => $sport->id,
                'host_id' => $host->id,
                'community_id' => $community->id ?? null,
                'venue_id' => $venue->id,
                'event_date' => $eventData['event_date'] . ' ' . $eventData['start_time'],
                'registration_deadline' => $eventData['registration_deadline'],
                'max_participants' => $eventData['max_participants'],
                'entry_fee' => $eventData['entry_fee'],
                'skill_level_required' => $eventData['skill_level'],
                'status' => $eventData['status'] === 'open_registration' ? 'published' : 
                           ($eventData['status'] === 'completed' ? 'completed' : 'draft'),
                'event_type' => $eventData['event_type'] === 'tournament' ? 'tournament' : 
                               ($eventData['event_type'] === 'training' ? 'coaching' : 
                               ($eventData['event_type'] === 'casual' ? 'mabar' : 'friendly_match')),
                'location_name' => $venue->name,
                'location_address' => $venue->address,
                'city' => $venue->city,
                'province' => $venue->province,
                'country' => $venue->country,
                'latitude' => $venue->latitude,
                'longitude' => $venue->longitude,
                'courts_used' => 1,
                'auto_confirm_participants' => true,
            ]);

            // Add participants
            $maxPossibleParticipants = min(
                $eventData['max_participants'], 
                $players->count()
            );
            
            $participantCount = rand(
                max(1, intval($maxPossibleParticipants * 0.3)), 
                $maxPossibleParticipants
            );
            
            $randomPlayers = $players->random($participantCount);
            
            foreach ($randomPlayers as $player) {
                $status = 'confirmed';
                $registeredAt = now()->subDays(rand(1, 30));
                
                // For past events, all should be confirmed and checked in
                if ($eventData['status'] === 'completed') {
                    $status = 'checked_in';
                    $checkedInAt = Carbon::parse($eventData['event_date'] . ' ' . $eventData['start_time'])->addMinutes(rand(-30, 60));
                } else {
                    // For upcoming events, random statuses
                    $status = rand(1, 10) > 8 ? 'registered' : 'confirmed';
                    $checkedInAt = null;
                }

                EventParticipant::create([
                    'event_id' => $event->id,
                    'user_id' => $player->id,
                    'status' => $status,
                    'registered_at' => $registeredAt,
                    'checked_in_at' => $checkedInAt,
                ]);
            }

            // Update participant count
            $event->update([
                'current_participants' => EventParticipant::where('event_id', $event->id)
                    ->whereIn('status', ['confirmed', 'pending'])->count()
            ]);
        }

        $this->command->info('Events, venues, and participants seeded successfully!');
    }
} 