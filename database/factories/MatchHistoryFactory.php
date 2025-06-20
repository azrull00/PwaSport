<?php

namespace Database\Factories;

use App\Models\MatchHistory;
use App\Models\User;
use App\Models\Sport;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MatchHistory>
 */
class MatchHistoryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = MatchHistory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $player1 = User::factory()->create();
        $player2 = User::factory()->create();
        $sport = Sport::factory()->create();
        $event = Event::factory()->create(['sport_id' => $sport->id]);
        $host = User::factory()->create();

        $player1_mmr_before = $this->faker->numberBetween(1000, 2000);
        $player2_mmr_before = $this->faker->numberBetween(1000, 2000);
        
        $result = $this->faker->randomElement(['player1_win', 'player2_win', 'draw']);
        
        // MMR changes based on result
        $player1_mmr_after = $result === 'player1_win' 
            ? $player1_mmr_before + $this->faker->numberBetween(10, 30)
            : $player1_mmr_before - $this->faker->numberBetween(10, 30);
            
        $player2_mmr_after = $result === 'player2_win' 
            ? $player2_mmr_before + $this->faker->numberBetween(10, 30)
            : $player2_mmr_before - $this->faker->numberBetween(10, 30);

        return [
            'event_id' => $event->id,
            'player1_id' => $player1->id,
            'player2_id' => $player2->id,
            'sport_id' => $sport->id,
            'result' => $result,
            'match_score' => json_encode([
                'player1_score' => $this->faker->numberBetween(0, 21),
                'player2_score' => $this->faker->numberBetween(0, 21),
                'sets' => [
                    ['player1' => $this->faker->numberBetween(0, 21), 'player2' => $this->faker->numberBetween(0, 21)]
                ]
            ]),
            'player1_mmr_before' => $player1_mmr_before,
            'player1_mmr_after' => $player1_mmr_after,
            'player2_mmr_before' => $player2_mmr_before,
            'player2_mmr_after' => $player2_mmr_after,
            'recorded_by_host_id' => $host->id,
            'match_notes' => $this->faker->optional()->sentence(),
            'match_date' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ];
    }

    /**
     * Indicate that player1 won.
     */
    public function player1Wins(): static
    {
        return $this->state(fn (array $attributes) => [
            'result' => 'player1_win',
        ]);
    }

    /**
     * Indicate that player2 won.
     */
    public function player2Wins(): static
    {
        return $this->state(fn (array $attributes) => [
            'result' => 'player2_win',
        ]);
    }

    /**
     * Indicate that the match was a draw.
     */
    public function draw(): static
    {
        return $this->state(fn (array $attributes) => [
            'result' => 'draw',
        ]);
    }
} 