<?php

namespace Database\Factories;

use App\Models\UserSportRating;
use App\Models\User;
use App\Models\Sport;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserSportRatingFactory extends Factory
{
    protected $model = UserSportRating::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'sport_id' => Sport::factory(),
            'mmr' => $this->faker->numberBetween(800, 1800),
            'level' => $this->faker->numberBetween(1, 10),
            'matches_played' => $this->faker->numberBetween(0, 100),
            'wins' => function (array $attributes) {
                return $this->faker->numberBetween(0, $attributes['matches_played']);
            },
            'losses' => function (array $attributes) {
                return $attributes['matches_played'] - $attributes['wins'];
            },
            'win_rate' => function (array $attributes) {
                if ($attributes['matches_played'] == 0) {
                    return 0;
                }
                return round(($attributes['wins'] / $attributes['matches_played']) * 100, 2);
            },
            'last_match_at' => $this->faker->optional()->dateTimeBetween('-1 year', 'now'),
        ];
    }
} 