<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Notification;
use App\Models\User;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Notification>
 */
class NotificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $types = ['event', 'message', 'friend_request', 'match', 'system'];
        $type = fake()->randomElement($types);
        
        $titles = [
            'event' => [
                'Event Baru Tersedia!',
                'Reminder: Event Besok',
                'Event Dibatalkan',
                'Event Dimulai Sebentar Lagi',
                'Terima kasih sudah berpartisipasi!'
            ],
            'message' => [
                'Pesan Baru',
                'Pesan dari Teman',
                'Chat Komunitas',
                'Balasan Pesan Anda',
                'Mention di Grup'
            ],
            'friend_request' => [
                'Permintaan Pertemanan Baru',
                'Permintaan Pertemanan Diterima',
                'Teman Baru Bergabung',
                'Seseorang ingin berteman',
                'Permintaan Pertemanan'
            ],
            'match' => [
                'Match Ditemukan!',
                'Lawan Siap Bermain',
                'Hasil Match Tersedia',
                'Rating Anda Naik!',
                'Turnamen Dimulai'
            ],
            'system' => [
                'Update Sistem',
                'Maintenance Terjadwal',
                'Fitur Baru Tersedia',
                'Peningkatan Performa',
                'Pemberitahuan Penting'
            ]
        ];

        $messages = [
            'event' => [
                'Ada event badminton seru di Jakarta Selatan. Yuk gabung!',
                'Jangan lupa event badminton Anda besok jam 19:00 di GOR Senayan.',
                'Maaf event badminton hari ini dibatalkan karena hujan.',
                'Event badminton Anda dimulai dalam 30 menit. Bersiaplah!',
                'Terima kasih sudah berpartisipasi di event badminton hari ini.'
            ],
            'message' => [
                'Anda mendapat pesan baru dari John Doe.',
                'Ada chat baru di komunitas Badminton Jakarta.',
                'Seseorang membalas pesan Anda di grup.',
                'Anda di-mention dalam percakapan grup.',
                'Pesan penting dari admin komunitas.'
            ],
            'friend_request' => [
                'Jane Smith ingin berteman dengan Anda.',
                'Permintaan pertemanan Anda telah diterima oleh Mike Johnson.',
                'Anda sekarang berteman dengan Sarah Wilson.',
                'Ada yang ingin menambahkan Anda sebagai teman.',
                'Permintaan pertemanan baru menunggu persetujuan.'
            ],
            'match' => [
                'Lawan ditemukan! Siap bermain badminton?',
                'Alex Chen siap bermain dengan Anda jam 20:00.',
                'Hasil match: Anda menang 21-19, 21-17. Selamat!',
                'Rating badminton Anda naik menjadi 1250 MMR.',
                'Turnamen Badminton Jakarta dimulai besok.'
            ],
            'system' => [
                'Sistem akan maintenance hari Minggu jam 02:00-04:00.',
                'Fitur chat pribadi sudah tersedia. Coba sekarang!',
                'Performa aplikasi telah ditingkatkan.',
                'Update keamanan penting telah dipasang.',
                'Selamat datang di SportPWA versi 2.0!'
            ]
        ];

        $title = fake()->randomElement($titles[$type]);
        $message = fake()->randomElement($messages[$type]);

        return [
            'user_id' => User::inRandomOrder()->first()?->id ?? 1,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => json_encode([
                'extra_info' => fake()->sentence(),
                'action_url' => fake()->optional()->url(),
                'priority' => fake()->randomElement(['low', 'medium', 'high'])
            ]),
            'read_at' => fake()->optional(0.7)->dateTimeBetween('-1 week', 'now'),
            'created_at' => fake()->dateTimeBetween('-1 month', 'now'),
            'updated_at' => fake()->dateTimeBetween('-1 month', 'now'),
        ];
    }

    /**
     * Indicate that the notification is unread.
     */
    public function unread(): static
    {
        return $this->state(fn (array $attributes) => [
            'read_at' => null,
        ]);
    }

    /**
     * Indicate that the notification is read.
     */
    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'read_at' => fake()->dateTimeBetween('-1 week', 'now'),
        ]);
    }

    /**
     * Create an event notification.
     */
    public function event(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'event',
            'title' => 'Event Baru Tersedia!',
            'message' => 'Ada event badminton seru di Jakarta Selatan. Yuk gabung!',
        ]);
    }

    /**
     * Create a message notification.
     */
    public function message(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'message',
            'title' => 'Pesan Baru',
            'message' => 'Anda mendapat pesan baru dari teman.',
        ]);
    }

    /**
     * Create a friend request notification.
     */
    public function friendRequest(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'friend_request',
            'title' => 'Permintaan Pertemanan Baru',
            'message' => 'Seseorang ingin berteman dengan Anda.',
        ]);
    }

    /**
     * Create a match notification.
     */
    public function match(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'match',
            'title' => 'Match Ditemukan!',
            'message' => 'Lawan siap bermain badminton dengan Anda.',
        ]);
    }
}
