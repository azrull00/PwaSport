<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\UserPreferredArea;
use App\Models\Event;
use App\Models\Community;
use App\Models\Sport;
use Laravel\Sanctum\Sanctum;

class LocationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /** @test */
    public function authenticated_user_can_get_preferred_areas()
    {
        $user = User::factory()->create(['subscription_tier' => 'free']);
        Sanctum::actingAs($user);

        UserPreferredArea::factory()->create([
            'user_id' => $user->id,
            'area_name' => 'Jakarta Selatan',
            'center_latitude' => -6.2297,
            'center_longitude' => 106.8176,
            'radius_km' => 15
        ]);

        $response = $this->getJson('/api/location/preferred-areas');

        $response->assertOk()
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'preferred_areas',
                        'total_areas',
                        'max_areas_allowed'
                    ]
                ]);

        $this->assertEquals(3, $response->json('data.max_areas_allowed')); // Free user limit
    }

    /** @test */
    public function free_user_can_add_up_to_3_preferred_areas()
    {
        $user = User::factory()->create(['subscription_tier' => 'free']);
        Sanctum::actingAs($user);

        // Add first area
        $response = $this->postJson('/api/location/preferred-areas', [
            'area_name' => 'Jakarta Selatan',
            'center_latitude' => -6.2297,
            'center_longitude' => 106.8176,
            'radius_km' => 15,
            'city' => 'Jakarta',
            'district' => 'Senayan'
        ]);

        $response->assertCreated();

        // Add second and third areas
        for ($i = 2; $i <= 3; $i++) {
            $response = $this->postJson('/api/location/preferred-areas', [
                'area_name' => "Area {$i}",
                'center_latitude' => -6.2297 + ($i * 0.01),
                'center_longitude' => 106.8176 + ($i * 0.01),
                'radius_km' => 10
            ]);
            $response->assertCreated();
        }

        // Try to add fourth area (should fail)
        $response = $this->postJson('/api/location/preferred-areas', [
            'area_name' => 'Area 4',
            'center_latitude' => -6.2297,
            'center_longitude' => 106.8176,
            'radius_km' => 10
        ]);

        $response->assertBadRequest()
                ->assertJsonPath('message', 'Anda hanya dapat memiliki maksimal 3 area favorit. Upgrade ke premium untuk unlimited areas.');
    }

    /** @test */
    public function premium_user_can_add_unlimited_preferred_areas()
    {
        $user = User::factory()->create(['subscription_tier' => 'premium']);
        Sanctum::actingAs($user);

        // Add multiple areas (more than 3)
        for ($i = 1; $i <= 5; $i++) {
            $response = $this->postJson('/api/location/preferred-areas', [
                'area_name' => "Premium Area {$i}",
                'center_latitude' => -6.2297 + ($i * 0.01),
                'center_longitude' => 106.8176 + ($i * 0.01),
                'radius_km' => 10
            ]);
            $response->assertCreated();
        }

        $this->assertEquals(5, UserPreferredArea::where('user_id', $user->id)->count());
    }

    /** @test */
    public function can_search_events_by_location()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $sport = Sport::factory()->create();
        
        // Create events at different locations
        Event::factory()->create([
            'sport_id' => $sport->id,
            'latitude' => -6.2297, // Jakarta center
            'longitude' => 106.8176,
            'status' => 'published',
            'event_date' => now()->addDays(1)
        ]);

        Event::factory()->create([
            'sport_id' => $sport->id,
            'latitude' => -6.9175, // Bandung (far away)
            'longitude' => 107.6191,
            'status' => 'published',
            'event_date' => now()->addDays(1)
        ]);

        $response = $this->postJson('/api/location/search/events', [
            'latitude' => -6.2297,
            'longitude' => 106.8176,
            'radius_km' => 50, // 50km radius
            'sport_id' => $sport->id
        ]);

        $response->assertOk()
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'events',
                        'pagination',
                        'search_parameters'
                    ]
                ]);

        // Should find events within radius
        $events = $response->json('data.events');
        $this->assertGreaterThan(0, count($events));
        
        // Each event should have distance_km field
        foreach ($events as $event) {
            $this->assertArrayHasKey('distance_km', $event);
        }
    }

    /** @test */
    public function can_calculate_distance_between_coordinates()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/location/calculate-distance', [
            'lat1' => -6.2297, // Jakarta
            'lon1' => 106.8176,
            'lat2' => -6.9175, // Bandung
            'lon2' => 107.6191
        ]);

        $response->assertOk()
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'distance_km',
                        'distance_m',
                        'coordinates'
                    ]
                ]);

        $distance = $response->json('data.distance_km');
        $this->assertGreaterThan(100, $distance); // Jakarta-Bandung ~150km
        $this->assertLessThan(200, $distance);
    }

    /** @test */
    public function can_get_events_in_preferred_areas()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $sport = Sport::factory()->create();

        // Create preferred area - explicitly set is_active
        $preferredArea = UserPreferredArea::factory()->create([
            'user_id' => $user->id,
            'center_latitude' => -6.2297,
            'center_longitude' => 106.8176,
            'radius_km' => 20,
            'is_active' => true
        ]);

        // Create event within preferred area
        $event = Event::factory()->create([
            'sport_id' => $sport->id,
            'latitude' => -6.2297,
            'longitude' => 106.8176,
            'status' => 'published',
            'event_date' => now()->addDays(1)
        ]);

        $response = $this->getJson('/api/location/preferred-areas/events');

        $response->assertOk()
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'events',
                        'preferred_areas_count',
                        'total_events_found'
                    ]
                ]);

        $this->assertGreaterThan(0, $response->json('data.total_events_found'));
    }

    /** @test */
    public function can_update_preferred_area()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $area = UserPreferredArea::factory()->create([
            'user_id' => $user->id,
            'area_name' => 'Original Area'
        ]);

        $response = $this->putJson("/api/location/preferred-areas/{$area->id}", [
            'area_name' => 'Updated Area',
            'radius_km' => 25
        ]);

        $response->assertOk();
        $this->assertEquals('Updated Area', $area->fresh()->area_name);
        $this->assertEquals(25, $area->fresh()->radius_km);
    }

    /** @test */
    public function can_delete_preferred_area()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $area = UserPreferredArea::factory()->create(['user_id' => $user->id]);

        $response = $this->deleteJson("/api/location/preferred-areas/{$area->id}");

        $response->assertOk();
        $this->assertSoftDeleted($area);
    }

    /** @test */
    public function user_cannot_access_other_users_preferred_areas()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        $area = UserPreferredArea::factory()->create(['user_id' => $user1->id]);

        Sanctum::actingAs($user2);

        $response = $this->putJson("/api/location/preferred-areas/{$area->id}", [
            'area_name' => 'Hacked Area'
        ]);

        $response->assertForbidden();
    }

    /** @test */
    public function location_search_validation_works()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Invalid latitude
        $response = $this->postJson('/api/location/search/events', [
            'latitude' => 95, // Invalid latitude
            'longitude' => 106.8176,
            'radius_km' => 50
        ]);

        $response->assertUnprocessable()
                ->assertJsonValidationErrors(['latitude']);

        // Missing required fields
        $response = $this->postJson('/api/location/search/events', [
            'radius_km' => 50
        ]);

        $response->assertUnprocessable()
                ->assertJsonValidationErrors(['latitude', 'longitude']);
    }

    /** @test */
    public function unauthenticated_user_cannot_access_location_routes()
    {
        $response = $this->getJson('/api/location/preferred-areas');
        $response->assertUnauthorized();

        $response = $this->postJson('/api/location/preferred-areas', []);
        $response->assertUnauthorized();

        $response = $this->postJson('/api/location/search/events', []);
        $response->assertUnauthorized();
    }
}
