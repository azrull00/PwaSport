<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\UserProfile;
use App\Models\User;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserProfile>
 */
class UserProfileFactory extends Factory
{
    protected $model = UserProfile::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'date_of_birth' => $this->faker->date('Y-m-d', '2000-01-01'),
            'gender' => $this->faker->randomElement(['male', 'female']),
            'bio' => $this->faker->sentence(),
            'qr_code' => $this->faker->uuid(),
            'city' => $this->faker->city(),
            'district' => $this->faker->citySuffix(),
            'province' => $this->faker->state(),
            'country' => 'Indonesia',
            'postal_code' => $this->faker->postcode(),
            'is_location_public' => $this->faker->boolean(),
            'emergency_contact_name' => $this->faker->name(),
            'emergency_contact_phone' => $this->faker->phoneNumber(),
            'preferred_language' => 'id',
        ];
    }
} 