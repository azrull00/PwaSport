<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Event;
use App\Models\Sport;
use App\Models\EventParticipant;
use App\Models\MatchHistory;
use App\Models\GuestPlayer;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;
use Carbon\Carbon;

class UpdatedMatchmakingTest extends TestCase
{
    use RefreshDatabase;

    protected $host;
    protected $event;
    protected $sport;
    protected $venue;
    protected $players;

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
            'courts_count' => 4
        ]);

        $this->event = Event::factory()->create([
            'host_id' => $this->host->id,
            'sport_id' => $this->sport->id,
            'venue_id' => $this->venue->id,
            'event_date' => Carbon::today(),
            'status' => 'active'
        ]);

        // Create test players
        $this->players = User::factory()->count(6)->create();
        
        // Add participants to event
        foreach ($this->players as $player) {
            EventParticipant::factory()->create([
                'event_id' => $this->event->id,
                'user_id' => $player->id,
                'status' => 'confirmed'
            ]);
        }
    }

    public function test_host_can_get_matchmaking_status()
    {
        Sanctum::actingAs($this->host);

        $response = $this->getJson("/api/matchmaking/{$this->event->id}/status");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'matches',
                    'waiting_players'
                ]);
    }

    public function test_host_can_create_fair_matches()
    {
        Sanctum::actingAs($this->host);

        $response = $this->postJson("/api/matchmaking/{$this->event->id}/fair-matches");

        $response->assertStatus(200);
        
        // Check that matches were created
        $this->assertDatabaseHas('match_history', [
            'event_id' => $this->event->id,
            'match_status' => 'pending'
        ]);
    }

    public function test_host_can_override_player_with_regular_user()
    {
        Sanctum::actingAs($this->host);

        // Create a match first
        $match = MatchHistory::factory()->create([
            'event_id' => $this->event->id,
            'sport_id' => $this->sport->id,
            'player1_id' => $this->players[0]->id,
            'player2_id' => $this->players[1]->id,
            'match_status' => 'pending'
        ]);

        // Override player1 with player2
        $response = $this->postJson("/api/matchmaking/{$this->event->id}/override-player", [
            'match_id' => $match->id,
            'player_to_replace' => $this->players[0]->id,
            'replacement_player' => $this->players[2]->id
        ]);

        $response->assertStatus(200)
                ->assertJsonPath('message', 'Player override successful');

        // Check that the match was updated
        $this->assertDatabaseHas('match_history', [
            'id' => $match->id,
            'player1_id' => $this->players[2]->id,
            'player2_id' => $this->players[1]->id
        ]);
    }

    public function test_host_can_override_player_with_guest()
    {
        Sanctum::actingAs($this->host);

        // Create a guest player
        $guest = GuestPlayer::factory()->create([
            'event_id' => $this->event->id,
            'name' => 'Guest Player',
            'estimated_mmr' => 1200
        ]);

        // Create a match
        $match = MatchHistory::factory()->create([
            'event_id' => $this->event->id,
            'sport_id' => $this->sport->id,
            'player1_id' => $this->players[0]->id,
            'player2_id' => $this->players[1]->id,
            'match_status' => 'pending'
        ]);

        // Override player1 with guest
        $response = $this->postJson("/api/matchmaking/{$this->event->id}/override-player", [
            'match_id' => $match->id,
            'player_to_replace' => $this->players[0]->id,
            'replacement_player' => 'guest_' . $guest->id
        ]);

        $response->assertStatus(200)
                ->assertJsonPath('message', 'Player override successful');

        // Check that the match was updated
        $this->assertDatabaseHas('match_history', [
            'id' => $match->id,
            'player1_id' => null,
            'player1_guest_id' => $guest->id,
            'player2_id' => $this->players[1]->id
        ]);
    }

    public function test_host_can_assign_court_to_match()
    {
        Sanctum::actingAs($this->host);

        $match = MatchHistory::factory()->create([
            'event_id' => $this->event->id,
            'sport_id' => $this->sport->id,
            'player1_id' => $this->players[0]->id,
            'player2_id' => $this->players[1]->id,
            'match_status' => 'pending',
            'court_number' => null
        ]);

        $response = $this->postJson("/api/matchmaking/{$this->event->id}/assign-court", [
            'match_id' => $match->id,
            'court_number' => 1
        ]);

        $response->assertStatus(200)
                ->assertJsonPath('message', 'Court assigned successfully');

        $this->assertDatabaseHas('match_history', [
            'id' => $match->id,
            'court_number' => 1,
            'match_status' => 'scheduled'
        ]);
    }

    public function test_cannot_assign_court_already_in_use()
    {
        Sanctum::actingAs($this->host);

        // Create existing match on court 1
        MatchHistory::factory()->create([
            'event_id' => $this->event->id,
            'sport_id' => $this->sport->id,
            'player1_id' => $this->players[0]->id,
            'player2_id' => $this->players[1]->id,
            'court_number' => 1,
            'match_status' => 'ongoing'
        ]);

        // Try to assign another match to same court
        $newMatch = MatchHistory::factory()->create([
            'event_id' => $this->event->id,
            'sport_id' => $this->sport->id,
            'player1_id' => $this->players[2]->id,
            'player2_id' => $this->players[3]->id,
            'match_status' => 'pending'
        ]);

        $response = $this->postJson("/api/matchmaking/{$this->event->id}/assign-court", [
            'match_id' => $newMatch->id,
            'court_number' => 1
        ]);

        $response->assertStatus(400)
                ->assertJsonPath('message', 'Court is currently in use');
    }

    public function test_host_can_get_court_status()
    {
        Sanctum::actingAs($this->host);

        // Create some matches
        MatchHistory::factory()->create([
            'event_id' => $this->event->id,
            'sport_id' => $this->sport->id,
            'player1_id' => $this->players[0]->id,
            'player2_id' => $this->players[1]->id,
            'court_number' => 1,
            'match_status' => 'ongoing'
        ]);

        $response = $this->getJson("/api/matchmaking/{$this->event->id}/court-status");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'event_id',
                        'event_title',
                        'courts',
                        'matches'
                    ]
                ])
                ->assertJsonPath('status', 'success');
    }

    public function test_host_can_start_match()
    {
        Sanctum::actingAs($this->host);

        $match = MatchHistory::factory()->create([
            'event_id' => $this->event->id,
            'sport_id' => $this->sport->id,
            'player1_id' => $this->players[0]->id,
            'player2_id' => $this->players[1]->id,
            'match_status' => 'scheduled'
        ]);

        $response = $this->postJson("/api/matchmaking/{$this->event->id}/start-match/{$match->id}");

        $response->assertStatus(200);
    }

    public function test_host_can_end_match()
    {
        Sanctum::actingAs($this->host);

        $match = MatchHistory::factory()->create([
            'event_id' => $this->event->id,
            'sport_id' => $this->sport->id,
            'player1_id' => $this->players[0]->id,
            'player2_id' => $this->players[1]->id,
            'match_status' => 'ongoing'
        ]);

        $response = $this->postJson("/api/matchmaking/{$this->event->id}/end-match/{$match->id}");

        $response->assertStatus(200)
                ->assertJsonPath('status', 'success')
                ->assertJsonPath('message', 'Match ended successfully');

        $this->assertDatabaseHas('match_history', [
            'id' => $match->id,
            'match_status' => 'completed'
        ]);
    }

    public function test_non_host_cannot_access_matchmaking_endpoints()
    {
        $regularUser = User::factory()->create();
        Sanctum::actingAs($regularUser);

        $response = $this->getJson("/api/matchmaking/{$this->event->id}/status");
        $response->assertStatus(403);

        $response = $this->postJson("/api/matchmaking/{$this->event->id}/fair-matches");
        $response->assertStatus(403);

        $response = $this->getJson("/api/matchmaking/{$this->event->id}/court-status");
        $response->assertStatus(403);
    }

    public function test_validation_errors_for_override_player()
    {
        Sanctum::actingAs($this->host);

        $match = MatchHistory::factory()->create([
            'event_id' => $this->event->id,
            'sport_id' => $this->sport->id,
            'player1_id' => $this->players[0]->id,
            'player2_id' => $this->players[1]->id
        ]);

        // Test missing required fields
        $response = $this->postJson("/api/matchmaking/{$this->event->id}/override-player", []);

        $response->assertStatus(422)
                ->assertJsonValidationErrors([
                    'match_id',
                    'player_to_replace',
                    'replacement_player'
                ]);
    }

    public function test_validation_errors_for_assign_court()
    {
        Sanctum::actingAs($this->host);

        // Test missing required fields
        $response = $this->postJson("/api/matchmaking/{$this->event->id}/assign-court", []);

        $response->assertStatus(422)
                ->assertJsonValidationErrors([
                    'match_id',
                    'court_number'
                ]);

        // Test invalid court number
        $response = $this->postJson("/api/matchmaking/{$this->event->id}/assign-court", [
            'match_id' => 999,
            'court_number' => 0 // Below minimum
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['court_number']);
    }

    public function test_event_model_binding_works()
    {
        Sanctum::actingAs($this->host);

        // Test with non-existent event ID
        $response = $this->getJson("/api/matchmaking/99999/status");
        $response->assertStatus(404);

        // Test with valid event ID
        $response = $this->getJson("/api/matchmaking/{$this->event->id}/status");
        $response->assertStatus(200);
    }
} 