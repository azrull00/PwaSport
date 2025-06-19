<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Event;
use App\Models\Community;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserReport>
 */
class UserReportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $reportTypes = [
            'misconduct',
            'cheating', 
            'harassment',
            'no_show',
            'rating_dispute',
            'inappropriate_behavior',
            'spam',
            'fake_profile'
        ];

        $statuses = ['pending', 'under_review', 'resolved', 'dismissed', 'escalated'];
        $priorities = ['low', 'medium', 'high', 'urgent'];
        $relatedTypes = ['event', 'community', 'match', 'chat'];

        return [
            'reporter_id' => User::factory(),
            'reported_user_id' => User::factory(),
            'report_type' => $this->faker->randomElement($reportTypes),
            'description' => $this->faker->paragraph(3),
            'evidence' => json_encode([
                $this->faker->imageUrl(640, 480, 'evidence'),
                $this->faker->url()
            ]),
            'status' => $this->faker->randomElement($statuses),
            'priority' => $this->faker->randomElement($priorities),
            'related_type' => $this->faker->randomElement($relatedTypes),
            'related_id' => function (array $attributes) {
                switch ($attributes['related_type']) {
                    case 'event':
                        return Event::factory()->create()->id;
                    case 'community':
                        return Community::factory()->create()->id;
                    default:
                        return $this->faker->numberBetween(1, 100);
                }
            },
            'admin_notes' => $this->faker->optional()->paragraph(),
            'resolution' => $this->faker->optional()->randomElement([
                'warning_issued',
                'temporary_suspension',
                'permanent_ban',
                'no_action_required',
                'resolved_externally'
            ]),
            'assigned_admin_id' => null,
            'resolved_at' => null,
        ];
    }

    /**
     * Indicate that the report is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'assigned_admin_id' => null,
            'admin_notes' => null,
            'resolution' => null,
            'resolved_at' => null,
        ]);
    }

    /**
     * Indicate that the report is resolved.
     */
    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'resolved',
            'admin_notes' => $this->faker->paragraph(),
            'resolution' => $this->faker->randomElement([
                'warning_issued',
                'temporary_suspension',
                'no_action_required'
            ]),
            'resolved_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
        ]);
    }

    /**
     * Indicate that the report is high priority.
     */
    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 'high',
            'report_type' => $this->faker->randomElement([
                'harassment',
                'cheating',
                'inappropriate_behavior'
            ]),
        ]);
    }
}
