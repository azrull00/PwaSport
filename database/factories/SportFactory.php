<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Sport;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Sport>
 */
class SportFactory extends Factory
{
    protected $model = Sport::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sports = [
            ['name' => 'Badminton', 'code' => 'badminton'],
            ['name' => 'Tennis', 'code' => 'tennis'],
            ['name' => 'Table Tennis', 'code' => 'table_tennis'],
            ['name' => 'Squash', 'code' => 'squash'],
            ['name' => 'Pickleball', 'code' => 'pickleball'],
            ['name' => 'Paddle Tennis', 'code' => 'paddle'],
            ['name' => 'Basketball', 'code' => 'basketball'],
            ['name' => 'Football', 'code' => 'football'],
            ['name' => 'Soccer', 'code' => 'soccer'],
            ['name' => 'Volleyball', 'code' => 'volleyball'],
            ['name' => 'Baseball', 'code' => 'baseball'],
            ['name' => 'Golf', 'code' => 'golf'],
            ['name' => 'Hockey', 'code' => 'hockey'],
            ['name' => 'Rugby', 'code' => 'rugby'],
            ['name' => 'Cricket', 'code' => 'cricket'],
            ['name' => 'Swimming', 'code' => 'swimming'],
            ['name' => 'Boxing', 'code' => 'boxing'],
            ['name' => 'MMA', 'code' => 'mma'],
            ['name' => 'Track & Field', 'code' => 'track_field'],
            ['name' => 'Cycling', 'code' => 'cycling'],
        ];
        
        $sport = $this->faker->randomElement($sports);
        
        return [
            'name' => $sport['name'] . ' ' . $this->faker->randomNumber(3),
            'code' => $sport['code'] . '_' . $this->faker->randomNumber(3),
            'description' => $this->faker->paragraph(),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the sport is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
} 