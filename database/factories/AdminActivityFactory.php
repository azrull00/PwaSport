<?php

namespace Database\Factories;

use App\Models\AdminActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AdminActivity>
 */
class AdminActivityFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = AdminActivity::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $admin = User::factory()->create();
        $targetUser = User::factory()->create();

        return [
            'admin_id' => $admin->id,
            'action_type' => $this->faker->randomElement([
                'user_suspended',
                'user_activated', 
                'user_deleted',
                'report_resolved',
                'report_assigned',
                'credit_score_adjusted',
                'event_cancelled',
                'community_moderated'
            ]),
            'target_type' => $this->faker->randomElement(['user', 'event', 'community', 'report']),
            'target_id' => $targetUser->id,
            'description' => $this->faker->sentence(),
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
        ];
    }

    /**
     * Indicate that the activity is for user suspension.
     */
    public function userSuspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'action_type' => 'user_suspended',
            'target_type' => 'user',
        ]);
    }

    /**
     * Indicate that the activity is for report resolution.
     */
    public function reportResolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'action_type' => 'report_resolved',
            'target_type' => 'report',
        ]);
    }

    /**
     * Indicate that the activity is for credit score adjustment.
     */
    public function creditScoreAdjusted(): static
    {
        return $this->state(fn (array $attributes) => [
            'action_type' => 'credit_score_adjusted',
            'target_type' => 'user',
        ]);
    }
} 