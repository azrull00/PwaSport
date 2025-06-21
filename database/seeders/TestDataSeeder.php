<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Notification;
use App\Models\Friendship;
use App\Models\FriendRequest;
use App\Models\PrivateMessage;
use App\Models\UserProfile;

class TestDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get or create test users
        $user1 = User::firstOrCreate(
            ['email' => 'player1@sportpwa.com'],
            [
                'name' => 'John Doe',
                'password' => bcrypt('password123'),
                'user_type' => 'player',
                'subscription_tier' => 'free',
                'credit_score' => 850,
                'is_active' => true,
            ]
        );

        $user2 = User::firstOrCreate(
            ['email' => 'player2@sportpwa.com'],
            [
                'name' => 'Jane Smith',
                'password' => bcrypt('password123'),
                'user_type' => 'player',
                'subscription_tier' => 'premium',
                'credit_score' => 920,
                'is_active' => true,
            ]
        );

        $user3 = User::firstOrCreate(
            ['email' => 'player3@sportpwa.com'],
            [
                'name' => 'Mike Johnson',
                'password' => bcrypt('password123'),
                'user_type' => 'player',
                'subscription_tier' => 'free',
                'credit_score' => 780,
                'is_active' => true,
            ]
        );

        // Create profiles for test users
        foreach ([$user1, $user2, $user3] as $user) {
            if (!$user->profile) {
                UserProfile::create([
                    'user_id' => $user->id,
                    'first_name' => explode(' ', $user->name)[0],
                    'last_name' => explode(' ', $user->name)[1] ?? '',
                    'date_of_birth' => now()->subYears(rand(20, 35)),
                    'gender' => rand(0, 1) ? 'male' : 'female',
                    'bio' => 'Suka bermain badminton dan olahraga lainnya.',
                    'qr_code' => 'QR_' . $user->id . '_' . time(),
                    'address' => 'Jakarta, Indonesia',
                ]);
            }
        }

        // Create friendships
        Friendship::firstOrCreate([
            'user_id' => $user1->id,
            'friend_id' => $user2->id,
        ], [
            'status' => 'accepted',
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ]);

        // Create friend request (pending)
        FriendRequest::firstOrCreate([
            'sender_id' => $user3->id,
            'receiver_id' => $user1->id,
        ], [
            'status' => 'pending',
            'created_at' => now()->subHours(2),
            'updated_at' => now()->subHours(2),
        ]);

        // Create sample notifications for user1
        $notificationData = [
            [
                'type' => 'community_invite',
                'title' => 'Undangan Komunitas Baru',
                'message' => 'Mike Johnson mengundang Anda bergabung di komunitas Badminton Jakarta.',
                'read_at' => null, // Unread
            ],
            [
                'type' => 'new_event',
                'title' => 'Event Baru Tersedia!',
                'message' => 'Ada event badminton seru di Jakarta Selatan. Yuk gabung!',
                'read_at' => null, // Unread
            ],
            [
                'type' => 'event_reminder',
                'title' => 'Reminder: Event Besok',
                'message' => 'Jangan lupa event badminton Anda besok jam 19:00 di GOR Senayan.',
                'read_at' => now()->subHours(1), // Read
            ],
            [
                'type' => 'match_result',
                'title' => 'Hasil Match Tersedia',
                'message' => 'Hasil match badminton Anda dengan Jane Smith sudah tersedia.',
                'read_at' => now()->subDays(1), // Read
            ],
            [
                'type' => 'rating_received',
                'title' => 'Rating Baru',
                'message' => 'Anda mendapat rating dari pemain lain. Cek profil Anda!',
                'read_at' => now()->subDays(3), // Read
            ]
        ];

        foreach ($notificationData as $data) {
            Notification::firstOrCreate([
                'user_id' => $user1->id,
                'type' => $data['type'],
                'title' => $data['title'],
            ], [
                'message' => $data['message'],
                'data' => json_encode([
                    'priority' => 'medium',
                    'action_url' => null,
                ]),
                'read_at' => $data['read_at'],
                'created_at' => now()->subHours(rand(1, 48)),
                'updated_at' => now()->subHours(rand(1, 48)),
            ]);
        }

        // Create some notifications for user2
        $user2Notifications = [
            [
                'type' => 'waitlist_promoted',
                'title' => 'Dipromosikan dari Waitlist',
                'message' => 'Anda dipromosikan dari waitlist event badminton Jakarta.',
                'read_at' => now()->subHours(6),
            ],
            [
                'type' => 'event_reminder',
                'title' => 'Reminder: Event Besok',
                'message' => 'Jangan lupa event badminton Anda besok jam 19:00.',
                'read_at' => null, // Unread
            ]
        ];

        foreach ($user2Notifications as $data) {
            Notification::firstOrCreate([
                'user_id' => $user2->id,
                'type' => $data['type'],
                'title' => $data['title'],
            ], [
                'message' => $data['message'],
                'data' => json_encode([
                    'priority' => 'medium',
                    'action_url' => null,
                ]),
                'read_at' => $data['read_at'],
                'created_at' => now()->subHours(rand(1, 24)),
                'updated_at' => now()->subHours(rand(1, 24)),
            ]);
        }

        // Create sample private messages between user1 and user2
        $messages = [
            [
                'sender_id' => $user2->id,
                'receiver_id' => $user1->id,
                'message' => 'Halo John! Gimana kabarnya?',
                'created_at' => now()->subHours(3),
            ],
            [
                'sender_id' => $user1->id,
                'receiver_id' => $user2->id,
                'message' => 'Halo Jane! Baik nih, lagi nyari lawan badminton.',
                'created_at' => now()->subHours(2),
            ],
            [
                'sender_id' => $user2->id,
                'receiver_id' => $user1->id,
                'message' => 'Wah kebetulan! Aku juga lagi mau main. Kapan bisa?',
                'created_at' => now()->subHours(2),
            ],
            [
                'sender_id' => $user1->id,
                'receiver_id' => $user2->id,
                'message' => 'Besok sore gimana? Sekitar jam 5.',
                'created_at' => now()->subHours(1),
            ],
            [
                'sender_id' => $user2->id,
                'receiver_id' => $user1->id,
                'message' => 'Oke deal! Tempat di GOR biasa ya.',
                'read_at' => null, // Unread message
                'created_at' => now()->subMinutes(30),
            ],
        ];

        foreach ($messages as $messageData) {
            PrivateMessage::firstOrCreate([
                'sender_id' => $messageData['sender_id'],
                'receiver_id' => $messageData['receiver_id'],
                'message' => $messageData['message'],
                'created_at' => $messageData['created_at'],
            ], [
                'message_type' => 'text',
                'read_at' => $messageData['read_at'] ?? now(),
                'is_deleted_by_sender' => false,
                'is_deleted_by_receiver' => false,
                'updated_at' => $messageData['created_at'],
            ]);
        }

        $this->command->info('Test data seeded successfully!');
        $this->command->info('Test users created:');
        $this->command->info('- player1@sportpwa.com (John Doe) - Password: password123');
        $this->command->info('- player2@sportpwa.com (Jane Smith) - Password: password123');
        $this->command->info('- player3@sportpwa.com (Mike Johnson) - Password: password123');
    }
}
