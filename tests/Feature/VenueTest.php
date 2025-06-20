<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Sport;
use App\Models\Venue;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Spatie\Permission\Models\Role;

class VenueTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $host;
    protected $admin;
    protected $player;
    protected $sport;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create roles
        Role::create(['name' => 'host']);
        Role::create(['name' => 'player']);
        Role::create(['name' => 'admin']);

        // Create test users
        $this->host = User::factory()->create();
        $this->host->assignRole('host');
        
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        
        $this->player = User::factory()->create();
        $this->player->assignRole('player');
        
        $this->sport = Sport::factory()->create();
    }

    /** @test */
    public function authenticated_user_can_get_venues_list()
    {
        Sanctum::actingAs($this->player);
        
        Venue::factory()->count(3)->create([
            'sport_id' => $this->sport->id,
            'is_active' => true,
            'is_verified' => true
        ]);

        $response = $this->getJson('/api/venues');

        $response->assertOk()
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'venues',
                        'pagination'
                    ]
                ]);
    }

    /** @test */
    public function can_filter_venues_by_city()
    {
        Sanctum::actingAs($this->player);
        
        Venue::factory()->create([
            'sport_id' => $this->sport->id,
            'city' => 'Jakarta',
            'is_active' => true
        ]);
        
        Venue::factory()->create([
            'sport_id' => $this->sport->id,
            'city' => 'Bandung',
            'is_active' => true
        ]);

        $response = $this->getJson('/api/venues?city=Jakarta');

        $response->assertOk();
        $venues = $response->json('data.venues');
        $this->assertCount(1, $venues);
        $this->assertEquals('Jakarta', $venues[0]['city']);
    }

    /** @test */
    public function can_search_venues_by_location()
    {
        Sanctum::actingAs($this->player);
        
        Venue::factory()->create([
            'sport_id' => $this->sport->id,
            'latitude' => -6.2088,
            'longitude' => 106.8456,
            'is_active' => true
        ]);

        $response = $this->getJson('/api/venues?latitude=-6.2088&longitude=106.8456&radius_km=5');

        $response->assertOk();
        $venues = $response->json('data.venues');
        $this->assertGreaterThan(0, count($venues));
    }

    /** @test */
    public function can_get_venue_details()
    {
        Sanctum::actingAs($this->player);
        
        $venue = Venue::factory()->create([
            'sport_id' => $this->sport->id,
            'owner_id' => $this->host->id,
            'is_active' => true
        ]);

        $response = $this->getJson("/api/venues/{$venue->id}");

        $response->assertOk()
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'venue',
                        'availability',
                        'statistics'
                    ]
                ]);
    }

    /** @test */
    public function host_can_create_venue()
    {
        Sanctum::actingAs($this->host);

        $venueData = [
            'sport_id' => $this->sport->id,
            'name' => 'Test Sports Center',
            'address' => 'Jl. Test No. 123',
            'city' => 'Jakarta',
            'province' => 'DKI Jakarta',
            'latitude' => -6.2088,
            'longitude' => 106.8456,
            'total_courts' => 4,
            'court_type' => 'indoor',
            'hourly_rate' => 100000,
            'facilities' => ['parking', 'shower', 'cafeteria'],
            'contact_phone' => '081234567890',
            'contact_email' => 'test@venue.com',
            'description' => 'Modern sports facility'
        ];

        $response = $this->postJson('/api/venues', $venueData);

        $response->assertStatus(201)
                ->assertJson([
                    'status' => 'success',
                    'message' => 'Venue berhasil ditambahkan!'
                ]);

        $this->assertDatabaseHas('venues', [
            'name' => 'Test Sports Center',
            'owner_id' => $this->host->id,
            'sport_id' => $this->sport->id
        ]);
    }

    /** @test */
    public function admin_can_create_venue()
    {
        Sanctum::actingAs($this->admin);

        $venueData = [
            'sport_id' => $this->sport->id,
            'name' => 'Admin Venue',
            'address' => 'Jl. Admin No. 456',
            'city' => 'Bandung',
            'province' => 'Jawa Barat',
            'latitude' => -6.9175,
            'longitude' => 107.6191,
            'total_courts' => 2,
            'court_type' => 'outdoor'
        ];

        $response = $this->postJson('/api/venues', $venueData);

        $response->assertStatus(201);
        
        // Admin-created venues should be auto-verified
        $venue = Venue::where('name', 'Admin Venue')->first();
        $this->assertTrue($venue->is_verified);
    }

    /** @test */
    public function player_cannot_create_venue()
    {
        Sanctum::actingAs($this->player);

        $venueData = [
            'sport_id' => $this->sport->id,
            'name' => 'Player Venue',
            'address' => 'Jl. Player No. 789',
            'city' => 'Surabaya',
            'province' => 'Jawa Timur',
            'latitude' => -7.2575,
            'longitude' => 112.7521,
            'total_courts' => 1,
            'court_type' => 'indoor'
        ];

        $response = $this->postJson('/api/venues', $venueData);

        $response->assertStatus(403)
                ->assertJson([
                    'status' => 'error',
                    'message' => 'Hanya host dan admin yang dapat menambah venue.'
                ]);
    }

    /** @test */
    public function venue_owner_can_update_venue()
    {
        Sanctum::actingAs($this->host);
        
        $venue = Venue::factory()->create([
            'owner_id' => $this->host->id,
            'sport_id' => $this->sport->id,
            'name' => 'Original Name'
        ]);

        $updateData = [
            'name' => 'Updated Venue Name',
            'total_courts' => 6,
            'hourly_rate' => 150000
        ];

        $response = $this->putJson("/api/venues/{$venue->id}", $updateData);

        $response->assertOk()
                ->assertJson([
                    'status' => 'success',
                    'message' => 'Venue berhasil diperbarui!'
                ]);

        $venue->refresh();
        $this->assertEquals('Updated Venue Name', $venue->name);
        $this->assertEquals(6, $venue->total_courts);
        $this->assertEquals(150000, $venue->hourly_rate);
    }

    /** @test */
    public function non_owner_cannot_update_venue()
    {
        $otherHost = User::factory()->create();
        $otherHost->assignRole('host');
        
        $venue = Venue::factory()->create([
            'owner_id' => $this->host->id,
            'sport_id' => $this->sport->id
        ]);

        Sanctum::actingAs($otherHost);

        $response = $this->putJson("/api/venues/{$venue->id}", [
            'name' => 'Hacked Name'
        ]);

        $response->assertStatus(403)
                ->assertJson([
                    'status' => 'error',
                    'message' => 'Anda tidak memiliki izin untuk mengubah venue ini.'
                ]);
    }

    /** @test */
    public function can_check_venue_availability()
    {
        Sanctum::actingAs($this->player);
        
        $venue = Venue::factory()->create([
            'sport_id' => $this->sport->id,
            'total_courts' => 4
        ]);

        $response = $this->postJson("/api/venues/{$venue->id}/check-availability", [
            'event_date' => now()->addDays(5)->toDateString(),
            'duration_hours' => 2,
            'courts_needed' => 2
        ]);

        $response->assertOk()
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'venue_id',
                        'venue_name',
                        'requested_date',
                        'is_available',
                        'available_courts',
                        'total_courts'
                    ]
                ]);
    }

    /** @test */
    public function availability_check_considers_existing_events()
    {
        Sanctum::actingAs($this->player);
        
        $venue = Venue::factory()->create([
            'sport_id' => $this->sport->id,
            'total_courts' => 4
        ]);

        $eventDate = now()->addDays(3);

        // Create existing event that uses 2 courts
        Event::factory()->create([
            'venue_id' => $venue->id,
            'sport_id' => $this->sport->id,
            'event_date' => $eventDate,
            'courts_used' => 2,
            'status' => 'published'
        ]);

        $response = $this->postJson("/api/venues/{$venue->id}/check-availability", [
            'event_date' => $eventDate->toDateString(),
            'duration_hours' => 2,
            'courts_needed' => 3 // Requesting 3 courts when only 2 are available
        ]);

        $response->assertOk();
        $data = $response->json('data');
        $this->assertFalse($data['is_available']);
        $this->assertEquals(2, $data['available_courts']); // 4 total - 2 used = 2 available
    }

    /** @test */
    public function can_get_venue_schedule()
    {
        Sanctum::actingAs($this->player);
        
        $venue = Venue::factory()->create([
            'sport_id' => $this->sport->id
        ]);

        // Create some events for this venue
        Event::factory()->count(2)->create([
            'venue_id' => $venue->id,
            'sport_id' => $this->sport->id,
            'event_date' => now()->addDays(1),
            'status' => 'published'
        ]);

        $response = $this->getJson("/api/venues/{$venue->id}/schedule");

        $response->assertOk()
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'venue',
                        'schedule_period',
                        'schedule',
                        'total_events'
                    ]
                ]);
    }

    /** @test */
    public function venue_owner_can_delete_venue()
    {
        Sanctum::actingAs($this->host);
        
        $venue = Venue::factory()->create([
            'owner_id' => $this->host->id,
            'sport_id' => $this->sport->id
        ]);

        $response = $this->deleteJson("/api/venues/{$venue->id}");

        $response->assertOk()
                ->assertJson([
                    'status' => 'success',
                    'message' => 'Venue berhasil dihapus!'
                ]);

        $this->assertDatabaseMissing('venues', [
            'id' => $venue->id
        ]);
    }

    /** @test */
    public function cannot_delete_venue_with_upcoming_events()
    {
        Sanctum::actingAs($this->host);
        
        $venue = Venue::factory()->create([
            'owner_id' => $this->host->id,
            'sport_id' => $this->sport->id
        ]);

        // Create upcoming event
        Event::factory()->create([
            'venue_id' => $venue->id,
            'sport_id' => $this->sport->id,
            'event_date' => now()->addDays(3),
            'status' => 'published'
        ]);

        $response = $this->deleteJson("/api/venues/{$venue->id}");

        $response->assertStatus(422)
                ->assertJsonPath('status', 'error');
    }

    /** @test */
    public function venue_validation_works()
    {
        Sanctum::actingAs($this->host);

        $response = $this->postJson('/api/venues', [
            // Missing required fields
            'name' => '',
            'sport_id' => 999, // Non-existent sport
            'latitude' => 91, // Invalid latitude
            'total_courts' => 0 // Invalid court count
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors([
                    'sport_id',
                    'name', 
                    'address',
                    'city',
                    'province',
                    'latitude',
                    'total_courts'
                ]);
    }

    /** @test */
    public function unauthenticated_user_cannot_access_venue_routes()
    {
        $venue = Venue::factory()->create();

        $this->getJson('/api/venues')->assertStatus(401);
        $this->getJson("/api/venues/{$venue->id}")->assertStatus(401);
        $this->postJson('/api/venues', [])->assertStatus(401);
        $this->putJson("/api/venues/{$venue->id}", [])->assertStatus(401);
        $this->deleteJson("/api/venues/{$venue->id}")->assertStatus(401);
    }
}
