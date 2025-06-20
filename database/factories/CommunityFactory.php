<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Community;
use App\Models\User;
use App\Models\Sport;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Community>
 */
class CommunityFactory extends Factory
{
    protected $model = Community::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company() . ' ' . $this->faker->randomElement(['Club', 'Community', 'Group']),
            'description' => $this->faker->paragraph(),
            'sport_id' => Sport::factory(),
            'host_user_id' => User::factory(),
            'community_type' => $this->faker->randomElement(['public', 'private', 'invite_only']),
            'venue_name' => $this->faker->company() . ' Sports Center',
            'venue_address' => $this->faker->address(),
            'city' => $this->faker->city(),
            'district' => $this->faker->citySuffix(),
            'province' => $this->faker->state(),
            'country' => 'Indonesia',
            'latitude' => $this->faker->latitude(-10, -5),
            'longitude' => $this->faker->longitude(95, 141),
            'max_members' => $this->faker->numberBetween(10, 100),
            'member_count' => $this->faker->numberBetween(1, 50),
            'membership_fee' => 0, // FREE FOR DEVELOPMENT
            'total_ratings' => $this->faker->numberBetween(0, 50),
            'average_skill_rating' => $this->faker->randomFloat(2, 3.0, 5.0),
            'hospitality_rating' => $this->faker->randomFloat(2, 3.0, 5.0),
            'total_events' => $this->faker->numberBetween(0, 20),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the community is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
} 