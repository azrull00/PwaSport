<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Sport;

class SportsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sports = [
            [
                'name' => 'Badminton',
                'code' => 'badminton',
                'description' => 'Olahraga raket yang dimainkan menggunakan raket untuk memukul shuttlecock melewati net.',
                'icon' => 'badminton-icon.svg',
                'is_active' => true,
            ],
            [
                'name' => 'Tennis',
                'code' => 'tennis',
                'description' => 'Olahraga raket yang dimainkan di lapangan berbentuk persegi panjang dengan net di tengah.',
                'icon' => 'tennis-icon.svg',
                'is_active' => true,
            ],
            [
                'name' => 'Paddle Tennis',
                'code' => 'paddle',
                'description' => 'Variasi tennis yang dimainkan di lapangan yang dikelilingi dinding dengan raket paddle.',
                'icon' => 'paddle-icon.svg',
                'is_active' => true,
            ],
            [
                'name' => 'Squash',
                'code' => 'squash',
                'description' => 'Olahraga raket yang dimainkan di ruangan tertutup dengan memantulkan bola ke dinding.',
                'icon' => 'squash-icon.svg',
                'is_active' => true,
            ],
            [
                'name' => 'Table Tennis',
                'code' => 'table_tennis',
                'description' => 'Ping pong - olahraga raket yang dimainkan di atas meja dengan net di tengah.',
                'icon' => 'table-tennis-icon.svg',
                'is_active' => true,
            ],
            [
                'name' => 'Pickleball',
                'code' => 'pickleball',
                'description' => 'Olahraga yang menggabungkan elemen badminton, tennis, dan ping pong.',
                'icon' => 'pickleball-icon.svg',
                'is_active' => true,
            ],
        ];

        foreach ($sports as $sport) {
            Sport::create($sport);
        }
    }
}
