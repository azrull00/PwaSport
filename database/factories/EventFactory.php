<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Event;
use App\Models\User;
use App\Models\Sport;
use App\Models\Community;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Event>
 */
class EventFactory extends Factory
{
    protected $model = Event::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $eventDate = $this->faker->dateTimeBetween('+1 day', '+30 days');
        
        $cities = [
            ['name' => 'Jakarta Selatan', 'lat' => -6.2297, 'lon' => 106.8176, 'district' => 'Senayan', 'province' => 'DKI Jakarta'],
            ['name' => 'Jakarta Pusat', 'lat' => -6.1863, 'lon' => 106.8230, 'district' => 'Menteng', 'province' => 'DKI Jakarta'],
            ['name' => 'Jakarta Utara', 'lat' => -6.1389, 'lon' => 106.8639, 'district' => 'Kelapa Gading', 'province' => 'DKI Jakarta'],
            ['name' => 'Bandung', 'lat' => -6.9175, 'lon' => 107.6191, 'district' => 'Dago', 'province' => 'Jawa Barat'],
            ['name' => 'Surabaya', 'lat' => -7.2756, 'lon' => 112.7503, 'district' => 'Gubeng', 'province' => 'Jawa Timur'],
        ];
        
        $city = $this->faker->randomElement($cities);

        return [
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'sport_id' => Sport::factory(),
            'community_id' => Community::factory(),
            'host_id' => User::factory(),
            'event_type' => $this->faker->randomElement(['mabar', 'coaching', 'friendly_match', 'tournament']),
            'event_date' => $eventDate,
            'max_participants' => $this->faker->numberBetween(4, 20),
            'current_participants' => $this->faker->numberBetween(0, 10),
            'entry_fee' => $this->faker->randomElement([0, 25000, 50000, 75000, 100000]),
            'status' => $this->faker->randomElement(['draft', 'published', 'full', 'ongoing', 'completed', 'cancelled']),
            'registration_deadline' => $this->faker->dateTimeBetween('now', $eventDate),
            'auto_queue_enabled' => $this->faker->boolean(),
            'event_settings' => json_encode([
                'allow_waitlist' => $this->faker->boolean(),
                'max_waitlist' => $this->faker->numberBetween(5, 15),
                'auto_confirm' => $this->faker->boolean(),
            ]),
            // Location fields
            'location_name' => $this->faker->company() . ' Sports Center',
            'location_address' => $this->faker->address(),
            'latitude' => $city['lat'] + $this->faker->randomFloat(4, -0.05, 0.05), // Add variance
            'longitude' => $city['lon'] + $this->faker->randomFloat(4, -0.05, 0.05),
            'city' => $city['name'],
            'district' => $city['district'],
            'province' => $city['province'],
            'country' => 'Indonesia',
            'skill_level_required' => $this->faker->randomElement(['pemula', 'menengah', 'mahir', 'ahli', 'profesional', 'mixed']),
            'is_premium_only' => $this->faker->boolean(20), // 20% chance premium only
            'auto_confirm_participants' => $this->faker->boolean(),
        ];
    }

    /**
     * Indicate that the event is published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'published',
            'event_date' => $this->faker->dateTimeBetween('+1 day', '+30 days'),
        ]);
    }

    /**
     * Indicate that the event is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'event_date' => $this->faker->dateTimeBetween('-30 days', '-1 day'),
        ]);
    }

    /**
     * Indicate that the event is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
        ]);
    }
} 