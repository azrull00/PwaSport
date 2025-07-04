<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Venue;
use App\Models\Event;
use App\Models\Sport;
use App\Models\EventParticipant;
use App\Models\MatchHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;
use Carbon\Carbon;

class HostControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $host;
    protected $venue;
    protected $sport;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->host = User::factory()->create([
            'name' => 'Test Host',
            'email' => 'host@example.com'
        ]);

        $this->sport = Sport::factory()->create([
            'name' => 'Badminton'
        ]);

        $this->venue = Venue::factory()->create([
            'owner_id' => $this->host->id,
            'name' => 'Test Venue',
            'courts_count' => 4,
            'capacity' => 100,
            'status' => 'active'
        ]);
    }

    public function test_host_can_get_venues()
    {
        Sanctum::actingAs($this->host);

        $response = $this->getJson('/api/host/venues');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'address',
                            'courts_count',
                            'capacity',
                            'status',
                            'upcoming_events',
                            'events'
                        ]
                    ]
                ])
                ->assertJsonPath('status', 'success')
                ->assertJsonPath('data.0.name', 'Test Venue');
    }

    public function test_host_can_create_venue()
    {
        Sanctum::actingAs($this->host);

        $venueData = [
            'name' => 'New Venue',
            'address' => '123 Test Street',
            'latitude' => -6.2088,
            'longitude' => 106.8456,
            'capacity' => 50,
            'courts_count' => 2,
            'status' => 'active',
            'operating_hours' => json_encode([
                'monday' => ['open' => '09:00', 'close' => '22:00'],
                'tuesday' => ['open' => '09:00', 'close' => '22:00']
            ]),
            'amenities' => json_encode(['parking', 'shower', 'cafe'])
        ];

        $response = $this->postJson('/api/host/venues', $venueData);

        $response->assertStatus(201)
                ->assertJsonPath('status', 'success')
                ->assertJsonPath('message', 'Venue created successfully');

        $this->assertDatabaseHas('venues', [
            'name' => 'New Venue',
            'owner_id' => $this->host->id,
            'courts_count' => 2
        ]);
    }

    public function test_host_can_update_venue()
    {
        Sanctum::actingAs($this->host);

        $updateData = [
            'name' => 'Updated Venue Name',
            'capacity' => 150,
            'status' => 'maintenance'
        ];

        $response = $this->putJson("/api/host/venues/{$this->venue->id}", $updateData);

        $response->assertStatus(200)
                ->assertJsonPath('status', 'success')
                ->assertJsonPath('message', 'Venue updated successfully');

        $this->assertDatabaseHas('venues', [
            'id' => $this->venue->id,
            'name' => 'Updated Venue Name',
            'capacity' => 150,
            'status' => 'maintenance'
        ]);
    }

    public function test_host_can_delete_venue_without_upcoming_events()
    {
        Sanctum::actingAs($this->host);

        $response = $this->deleteJson("/api/host/venues/{$this->venue->id}");

        $response->assertStatus(200)
                ->assertJsonPath('status', 'success')
                ->assertJsonPath('message', 'Venue deleted successfully');

        $this->assertDatabaseMissing('venues', [
            'id' => $this->venue->id
        ]);
    }

    public function test_host_cannot_delete_venue_with_upcoming_events()
    {
        Sanctum::actingAs($this->host);

        // Create upcoming event
        Event::factory()->create([
            'venue_id' => $this->venue->id,
            'host_id' => $this->host->id,
            'sport_id' => $this->sport->id,
            'event_date' => Carbon::tomorrow()
        ]);

        $response = $this->deleteJson("/api/host/venues/{$this->venue->id}");

        $response->assertStatus(400)
                ->assertJsonPath('status', 'error')
                ->assertJsonPath('message', 'Cannot delete venue with upcoming events');

        $this->assertDatabaseHas('venues', [
            'id' => $this->venue->id
        ]);
    }

    public function test_host_can_get_venue_stats()
    {
        Sanctum::actingAs($this->host);

        // Create test data
        $event = Event::factory()->create([
            'venue_id' => $this->venue->id,
            'host_id' => $this->host->id,
            'sport_id' => $this->sport->id,
            'event_date' => Carbon::today()
        ]);

        $participant = User::factory()->create();
        EventParticipant::factory()->create([
            'event_id' => $event->id,
            'user_id' => $participant->id,
            'status' => 'confirmed'
        ]);

        MatchHistory::factory()->create([
            'event_id' => $event->id,
            'sport_id' => $this->sport->id,
            'player1_id' => $this->host->id,
            'player2_id' => $participant->id,
            'match_status' => 'completed'
        ]);

        $response = $this->getJson("/api/host/venues/{$this->venue->id}/stats");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'venue',
                        'statistics' => [
                            'total_events',
                            'events_this_week',
                            'events_this_month',
                            'total_participants',
                            'total_matches',
                            'completed_matches',
                            'completion_rate'
                        ],
                        'upcoming_events',
                        'utilization'
                    ]
                ]);
    }

    public function test_host_can_get_venue_matchmaking_status()
    {
        Sanctum::actingAs($this->host);

        $event = Event::factory()->create([
            'venue_id' => $this->venue->id,
            'host_id' => $this->host->id,
            'sport_id' => $this->sport->id,
            'event_date' => Carbon::today(),
            'status' => 'active'
        ]);

        $participant = User::factory()->create();
        EventParticipant::factory()->create([
            'event_id' => $event->id,
            'user_id' => $participant->id,
            'status' => 'confirmed'
        ]);

        $response = $this->getJson("/api/host/venues/{$this->venue->id}/matchmaking-status");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'venue',
                        'events' => [
                            '*' => [
                                'event_id',
                                'event_title',
                                'sport',
                                'total_participants',
                                'active_matches',
                                'waiting_players',
                                'courts_available'
                            ]
                        ]
                    ]
                ]);
    }

    public function test_non_owner_cannot_access_venue()
    {
        $anotherUser = User::factory()->create();
        Sanctum::actingAs($anotherUser);

        $response = $this->getJson("/api/host/venues/{$this->venue->id}/stats");

        $response->assertStatus(404); // Should not find venue since it's not owned by this user
    }

    public function test_venue_validation_rules()
    {
        Sanctum::actingAs($this->host);

        // Test with invalid data
        $invalidData = [
            'name' => '', // Required
            'address' => '', // Required
            'latitude' => 100, // Out of range
            'longitude' => 200, // Out of range
            'capacity' => 0, // Below minimum
            'courts_count' => 0, // Below minimum
            'status' => 'invalid' // Not in enum
        ];

        $response = $this->postJson('/api/host/venues', $invalidData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors([
                    'name',
                    'address',
                    'latitude',
                    'longitude',
                    'capacity',
                    'courts_count',
                    'status'
                ]);
    }

    public function test_unauthenticated_user_cannot_access_host_endpoints()
    {
        $response = $this->getJson('/api/host/venues');
        $response->assertStatus(401);

        $response = $this->postJson('/api/host/venues', []);
        $response->assertStatus(401);

        $response = $this->putJson("/api/host/venues/{$this->venue->id}", []);
        $response->assertStatus(401);

        $response = $this->deleteJson("/api/host/venues/{$this->venue->id}");
        $response->assertStatus(401);
    }
} 