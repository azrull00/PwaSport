<?php

namespace Database\Factories;

use App\Models\Venue;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Venue>
 */
class VenueFactory extends Factory
{
    protected $model = Venue::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cities = [
            'Jakarta' => ['province' => 'DKI Jakarta', 'lat' => -6.2088, 'lng' => 106.8456],
            'Bandung' => ['province' => 'Jawa Barat', 'lat' => -6.9175, 'lng' => 107.6191],
            'Surabaya' => ['province' => 'Jawa Timur', 'lat' => -7.2575, 'lng' => 112.7521],
            'Medan' => ['province' => 'Sumatera Utara', 'lat' => 3.5952, 'lng' => 98.6722],
            'Yogyakarta' => ['province' => 'DI Yogyakarta', 'lat' => -7.7956, 'lng' => 110.3695],
        ];

        $cityData = $this->faker->randomElement($cities);
        $cityName = array_search($cityData, $cities);

        $facilities = [
            'parking', 'shower', 'cafeteria', 'ac', 'lighting', 'locker',
            'wifi', 'sound_system', 'first_aid', 'cctv', 'waiting_area'
        ];

        $operatingHours = [
            'monday' => ['open' => '06:00', 'close' => '22:00'],
            'tuesday' => ['open' => '06:00', 'close' => '22:00'],
            'wednesday' => ['open' => '06:00', 'close' => '22:00'],
            'thursday' => ['open' => '06:00', 'close' => '22:00'],
            'friday' => ['open' => '06:00', 'close' => '22:00'],
            'saturday' => ['open' => '06:00', 'close' => '23:00'],
            'sunday' => ['open' => '06:00', 'close' => '23:00'],
        ];

        return [
            'sport_id' => Sport::factory(),
            'owner_id' => User::factory(),
            'name' => $this->faker->company . ' Sports Center',
            'address' => $this->faker->streetAddress,
            'city' => $cityName,
            'district' => $this->faker->randomElement(['Central', 'North', 'South', 'East', 'West']) . ' ' . $cityName,
            'province' => $cityData['province'],
            'country' => 'Indonesia',
            'latitude' => $cityData['lat'] + $this->faker->randomFloat(6, -0.5, 0.5),
            'longitude' => $cityData['lng'] + $this->faker->randomFloat(6, -0.5, 0.5),
            'total_courts' => $this->faker->numberBetween(1, 8),
            'court_type' => $this->faker->randomElement(['indoor', 'outdoor', 'covered']),
            'hourly_rate' => $this->faker->numberBetween(50000, 300000),
            'facilities' => $this->faker->randomElements($facilities, $this->faker->numberBetween(3, 7)),
            'operating_hours' => $operatingHours,
            'contact_phone' => $this->faker->phoneNumber,
            'contact_email' => $this->faker->safeEmail,
            'description' => $this->faker->paragraph(3),
            'rules' => [
                'No smoking inside the venue',
                'Proper sports attire required',
                'Clean up after use',
                'Respect other players',
                'No outside food and drinks'
            ],
            'photos' => [
                'https://example.com/venue1.jpg',
                'https://example.com/venue2.jpg',
                'https://example.com/venue3.jpg'
            ],
            'average_rating' => $this->faker->randomFloat(2, 3.0, 5.0),
            'total_reviews' => $this->faker->numberBetween(0, 100),
            'is_active' => true,
            'is_verified' => $this->faker->boolean(80), // 80% chance of being verified
        ];
    }

    /**
     * Indicate that the venue is verified.
     */
    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_verified' => true,
        ]);
    }

    /**
     * Indicate that the venue is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create venue for specific sport.
     */
    public function forSport($sportId): static
    {
        return $this->state(fn (array $attributes) => [
            'sport_id' => $sportId,
        ]);
    }

    /**
     * Create venue in specific city.
     */
    public function inCity($city, $province, $lat, $lng): static
    {
        return $this->state(fn (array $attributes) => [
            'city' => $city,
            'province' => $province,
            'latitude' => $lat + $this->faker->randomFloat(6, -0.1, 0.1),
            'longitude' => $lng + $this->faker->randomFloat(6, -0.1, 0.1),
        ]);
    }
}
