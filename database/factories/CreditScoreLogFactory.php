<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;
use App\Models\Event;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CreditScoreLog>
 */
class CreditScoreLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $types = ['penalty', 'bonus', 'cancellation_penalty', 'no_show_penalty', 'event_completion_bonus', 'good_rating_bonus', 'consecutive_events_bonus'];
        $type = $this->faker->randomElement($types);
        
        // Determine change amount based on type
        $changeAmount = match($type) {
            'penalty', 'cancellation_penalty' => $this->faker->numberBetween(-25, -5),
            'no_show_penalty' => -30,
            'bonus', 'event_completion_bonus' => $this->faker->numberBetween(1, 5),
            'good_rating_bonus' => 1,
            'consecutive_events_bonus' => 5,
            default => $this->faker->numberBetween(-10, 10)
        };

        $oldScore = $this->faker->numberBetween(40, 90);
        $newScore = max(0, min(100, $oldScore + $changeAmount));

        return [
            'user_id' => User::factory(),
            'event_id' => $this->faker->boolean(70) ? Event::factory() : null,
            'type' => $type,
            'old_score' => $oldScore,
            'new_score' => $newScore,
            'change_amount' => $changeAmount,
            'description' => $this->getDescriptionForType($type),
            'metadata' => $this->getMetadataForType($type),
        ];
    }

    /**
     * Get description based on type
     */
    private function getDescriptionForType($type)
    {
        return match($type) {
            'penalty' => 'Penalty untuk pelanggaran',
            'cancellation_penalty' => 'Penalty pembatalan event',
            'no_show_penalty' => 'Penalty tidak hadir ke event',
            'bonus' => 'Bonus umum',
            'event_completion_bonus' => 'Bonus completion event',
            'good_rating_bonus' => 'Bonus rating baik',
            'consecutive_events_bonus' => 'Bonus 5 event berturut-turut',
            default => 'Perubahan credit score'
        };
    }

    /**
     * Get metadata based on type
     */
    private function getMetadataForType($type)
    {
        return match($type) {
            'cancellation_penalty' => [
                'cancellation_hours_before' => $this->faker->numberBetween(1, 48),
                'reason' => $this->faker->sentence()
            ],
            'no_show_penalty' => [
                'reported_by' => $this->faker->numberBetween(1, 100),
                'reason' => 'Tidak hadir tanpa pemberitahuan'
            ],
            'event_completion_bonus' => [
                'completion_date' => $this->faker->dateTimeThisMonth()
            ],
            'good_rating_bonus' => [
                'overall_rating' => $this->faker->randomFloat(1, 4.0, 5.0),
                'rating_from' => $this->faker->name()
            ],
            'consecutive_events_bonus' => [
                'consecutive_count' => 5
            ],
            default => []
        };
    }

    /**
     * Create penalty log state
     */
    public function penalty()
    {
        return $this->state(function (array $attributes) {
            $oldScore = $this->faker->numberBetween(50, 90);
            $changeAmount = $this->faker->numberBetween(-25, -5);
            
            return [
                'type' => 'penalty',
                'old_score' => $oldScore,
                'new_score' => max(0, $oldScore + $changeAmount),
                'change_amount' => $changeAmount,
                'description' => 'Penalty untuk pelanggaran',
            ];
        });
    }

    /**
     * Create bonus log state
     */
    public function bonus()
    {
        return $this->state(function (array $attributes) {
            $oldScore = $this->faker->numberBetween(40, 80);
            $changeAmount = $this->faker->numberBetween(1, 5);
            
            return [
                'type' => 'bonus',
                'old_score' => $oldScore,
                'new_score' => min(100, $oldScore + $changeAmount),
                'change_amount' => $changeAmount,
                'description' => 'Bonus untuk partisipasi',
            ];
        });
    }

    /**
     * Create cancellation penalty state
     */
    public function cancellationPenalty()
    {
        return $this->state(function (array $attributes) {
            $oldScore = $this->faker->numberBetween(50, 90);
            $changeAmount = $this->faker->numberBetween(-25, -5);
            
            return [
                'type' => 'cancellation_penalty',
                'old_score' => $oldScore,
                'new_score' => max(0, $oldScore + $changeAmount),
                'change_amount' => $changeAmount,
                'description' => 'Penalty pembatalan event',
                'metadata' => [
                    'cancellation_hours_before' => $this->faker->numberBetween(1, 48),
                    'reason' => $this->faker->sentence()
                ]
            ];
        });
    }

    /**
     * Create no-show penalty state
     */
    public function noShowPenalty()
    {
        return $this->state(function (array $attributes) {
            $oldScore = $this->faker->numberBetween(60, 90);
            
            return [
                'type' => 'no_show_penalty',
                'old_score' => $oldScore,
                'new_score' => max(0, $oldScore - 30),
                'change_amount' => -30,
                'description' => 'Penalty tidak hadir ke event',
                'metadata' => [
                    'reported_by' => $this->faker->numberBetween(1, 100),
                    'reason' => 'Tidak hadir tanpa pemberitahuan'
                ]
            ];
        });
    }
}
