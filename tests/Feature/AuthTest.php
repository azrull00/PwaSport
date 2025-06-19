<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\Sport;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;

class AuthTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed sports data for tests
        Sport::factory()->create([
            'name' => 'Badminton',
            'code' => 'badminton',
            'description' => 'Test sport',
            'is_active' => true
        ]);
    }

    #[Test]
    public function user_can_register_with_valid_data()
    {
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone_number' => '+628123456789',
            'user_type' => 'player',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'date_of_birth' => '1990-01-01',
            'gender' => 'male',
            'city' => 'Jakarta',
            'district' => 'Central Jakarta',
            'province' => 'DKI Jakarta',
            'country' => 'Indonesia'
        ];

        $response = $this->postJson('/api/auth/register', $userData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'status',
                    'message',
                    'data' => [
                        'user' => [
                            'id',
                            'name',
                            'email',
                            'phone_number',
                            'user_type',
                            'subscription_tier',
                            'credit_score',
                            'profile'
                        ],
                        'token',
                        'token_type'
                    ]
                ]);

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'name' => 'John Doe',
            'credit_score' => 100
        ]);

        $this->assertDatabaseHas('user_profiles', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'city' => 'Jakarta'
        ]);
    }

    #[Test]
    public function registration_fails_with_invalid_email()
    {
        $userData = [
            'name' => 'John Doe',
            'email' => 'invalid-email',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone_number' => '+628123456789',
            'user_type' => 'player',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'date_of_birth' => '1990-01-01',
            'gender' => 'male'
        ];

        $response = $this->postJson('/api/auth/register', $userData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function registration_fails_with_duplicate_email()
    {
        User::factory()->create(['email' => 'john@example.com']);

        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone_number' => '+628123456789',
            'user_type' => 'player',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'date_of_birth' => '1990-01-01',
            'gender' => 'male'
        ];

        $response = $this->postJson('/api/auth/register', $userData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function user_can_login_with_email()
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => Hash::make('password123')
        ]);

        UserProfile::factory()->create(['user_id' => $user->id]);

        $loginData = [
            'login' => 'john@example.com',
            'password' => 'password123'
        ];

        $response = $this->postJson('/api/auth/login', $loginData);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'message',
                    'data' => [
                        'user',
                        'token',
                        'token_type'
                    ]
                ]);
    }

    #[Test]
    public function user_can_login_with_phone_number()
    {
        $user = User::factory()->create([
            'phone_number' => '+628123456789',
            'password' => Hash::make('password123')
        ]);

        UserProfile::factory()->create(['user_id' => $user->id]);

        $loginData = [
            'login' => '+628123456789',
            'password' => 'password123'
        ];

        $response = $this->postJson('/api/auth/login', $loginData);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'message',
                    'data' => [
                        'user',
                        'token',
                        'token_type'
                    ]
                ]);
    }

    #[Test]
    public function login_fails_with_invalid_credentials()
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => Hash::make('password123')
        ]);

        $loginData = [
            'login' => 'john@example.com',
            'password' => 'wrongpassword'
        ];

        $response = $this->postJson('/api/auth/login', $loginData);

        $response->assertStatus(422)
                ->assertJsonStructure([
                    'status',
                    'message',
                    'errors'
                ]);
    }

    #[Test]
    public function authenticated_user_can_logout()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/auth/logout');

        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success',
                    'message' => 'Logout berhasil!'
                ]);
    }

    #[Test]
    public function authenticated_user_can_get_profile()
    {
        $user = User::factory()->create();
        $profile = UserProfile::factory()->create(['user_id' => $user->id]);
        
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/auth/profile');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'user' => [
                            'id',
                            'name',
                            'email',
                            'profile'
                        ]
                    ]
                ]);
    }

    #[Test]
    public function authenticated_user_can_update_profile()
    {
        $user = User::factory()->create();
        $profile = UserProfile::factory()->create(['user_id' => $user->id]);
        
        Sanctum::actingAs($user);

        $updateData = [
            'name' => 'Updated Name',
            'first_name' => 'Updated',
            'last_name' => 'Name',
            'bio' => 'Updated bio',
            'city' => 'Updated City'
        ];

        $response = $this->putJson('/api/auth/profile', $updateData);

        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success',
                    'message' => 'Profil berhasil diperbarui!'
                ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name'
        ]);

        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $user->id,
            'first_name' => 'Updated',
            'last_name' => 'Name',
            'bio' => 'Updated bio',
            'city' => 'Updated City'
        ]);
    }

    #[Test]
    public function unauthenticated_user_cannot_access_protected_routes()
    {
        $response = $this->getJson('/api/auth/profile');
        $response->assertStatus(401);

        $response = $this->putJson('/api/auth/profile', ['name' => 'Test']);
        $response->assertStatus(401);

        $response = $this->postJson('/api/auth/logout');
        $response->assertStatus(401);
    }
} 