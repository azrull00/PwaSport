<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Sport;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\UserSportRating;
use App\Models\MatchHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Spatie\Permission\Models\Role;

class MatchmakingTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $host;
    protected $sport;
    protected $event;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create roles
        Role::create(['name' => 'host']);
        Role::create(['name' => 'player']);
        Role::create(['name' => 'admin']);

        // Create test data
        $this->host = User::factory()->create();
        $this->host->assignRole('host');
        
        $this->sport = Sport::factory()->create();
        
        $this->event = Event::factory()->create([
            'host_id' => $this->host->id,
            'sport_id' => $this->sport->id,
            'status' => 'published',
            'max_courts' => 4,
            'event_date' => now()->addDays(1)
        ]);
    }

    /** @test */
    public function host_can_get_matchmaking_status()
    {
        Sanctum::actingAs($this->host);

        $response = $this->getJson("/api/matchmaking/{$this->event->id}");

        $response->assertOk()
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'event',
                        'matches',
                        'unmatched_participants',
                        'statistics'
                    ]
                ]);
    }

    /** @test */
    public function only_host_can_access_matchmaking()
    {
        $otherUser = User::factory()->create();
        Sanctum::actingAs($otherUser);

        $response = $this->getJson("/api/matchmaking/{$this->event->id}");

        $response->assertStatus(403)
                ->assertJson([
                    'status' => 'error',
                    'message' => 'Anda tidak memiliki akses untuk melihat matchmaking ini.'
                ]);
    }

    /** @test */
    public function can_generate_singles_matchmaking()
    {
        Sanctum::actingAs($this->host);

        // Create 4 participants with different skill levels
        $participants = User::factory()->count(4)->create();
        foreach ($participants as $index => $participant) {
            // Create confirmed participation
            EventParticipant::create([
                'event_id' => $this->event->id,
                'user_id' => $participant->id,
                'status' => 'confirmed',
                'registered_at' => now()
            ]);

            // Create skill ratings
            UserSportRating::create([
                'user_id' => $participant->id,
                'sport_id' => $this->sport->id,
                'skill_rating' => 1000 + ($index * 100), // 1000, 1100, 1200, 1300
                'matches_played' => $index + 1
            ]);
        }

        $response = $this->postJson("/api/matchmaking/{$this->event->id}/generate", [
            'match_type' => 'singles',
            'max_courts' => 2,
            'skill_tolerance' => 200
        ]);

        $response->assertOk()
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'event_id',
                        'match_type',
                        'total_participants',
                        'total_matches',
                        'courts_needed',
                        'matches',
                        'waiting_list'
                    ]
                ])
                ->assertJson([
                    'status' => 'success',
                    'data' => [
                        'match_type' => 'singles',
                        'total_participants' => 4,
                        'total_matches' => 2
                    ]
                ]);
    }

    /** @test */
    public function can_generate_doubles_matchmaking()
    {
        Sanctum::actingAs($this->host);

        // Create 8 participants for doubles
        $participants = User::factory()->count(8)->create();
        foreach ($participants as $index => $participant) {
            EventParticipant::create([
                'event_id' => $this->event->id,
                'user_id' => $participant->id,
                'status' => 'confirmed',
                'registered_at' => now()
            ]);

            UserSportRating::create([
                'user_id' => $participant->id,
                'sport_id' => $this->sport->id,
                'skill_rating' => 1000 + ($index * 50),
                'matches_played' => $index + 1
            ]);
        }

        $response = $this->postJson("/api/matchmaking/{$this->event->id}/generate", [
            'match_type' => 'doubles',
            'max_courts' => 2,
            'skill_tolerance' => 200
        ]);

        $response->assertOk()
                ->assertJson([
                    'status' => 'success',
                    'data' => [
                        'match_type' => 'doubles',
                        'total_participants' => 8
                    ]
                ]);
    }

    /** @test */
    public function cannot_generate_matchmaking_with_insufficient_participants()
    {
        Sanctum::actingAs($this->host);

        // Only create 1 participant
        $participant = User::factory()->create();
        EventParticipant::create([
            'event_id' => $this->event->id,
            'user_id' => $participant->id,
            'status' => 'confirmed',
            'registered_at' => now()
        ]);

        $response = $this->postJson("/api/matchmaking/{$this->event->id}/generate", [
            'match_type' => 'singles'
        ]);

        $response->assertStatus(422)
                ->assertJson([
                    'status' => 'error',
                    'message' => 'Minimal 2 peserta diperlukan untuk matchmaking.'
                ]);
    }

    /** @test */
    public function can_save_matchmaking_results()
    {
        Sanctum::actingAs($this->host);

        // Create 2 participants
        $participants = User::factory()->count(2)->create();
        foreach ($participants as $participant) {
            EventParticipant::create([
                'event_id' => $this->event->id,
                'user_id' => $participant->id,
                'status' => 'confirmed',
                'registered_at' => now()
            ]);
        }

        $matchData = [
            'matches' => [
                [
                    'court_number' => 1,
                    'player1_id' => $participants[0]->id,
                    'player2_id' => $participants[1]->id,
                    'estimated_duration' => 60
                ]
            ]
        ];

        $response = $this->postJson("/api/matchmaking/{$this->event->id}/save", $matchData);

        $response->assertOk()
                ->assertJson([
                    'status' => 'success',
                    'message' => 'Matchmaking berhasil disimpan dan pertandingan dimulai!'
                ]);

        // Verify match was created in database
        $this->assertDatabaseHas('match_history', [
            'event_id' => $this->event->id,
            'player1_id' => $participants[0]->id,
            'player2_id' => $participants[1]->id,
            'court_number' => 1
        ]);

        // Verify event status changed to ongoing
        $this->event->refresh();
        $this->assertEquals('ongoing', $this->event->status);
    }

    /** @test */
    public function matchmaking_validation_works()
    {
        Sanctum::actingAs($this->host);

        // Test invalid match data
        $response = $this->postJson("/api/matchmaking/{$this->event->id}/generate", [
            'match_type' => 'invalid_type',
            'max_courts' => 0,
            'skill_tolerance' => -100
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['match_type', 'max_courts', 'skill_tolerance']);
    }

    /** @test */
    public function non_host_cannot_generate_matchmaking()
    {
        $otherUser = User::factory()->create();
        Sanctum::actingAs($otherUser);

        $response = $this->postJson("/api/matchmaking/{$this->event->id}/generate", [
            'match_type' => 'singles'
        ]);

        $response->assertStatus(403)
                ->assertJson([
                    'status' => 'error',
                    'message' => 'Hanya host yang dapat mengatur matchmaking.'
                ]);
    }

    /** @test */
    public function admin_can_access_matchmaking()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/matchmaking/{$this->event->id}");

        $response->assertOk();
    }

    /** @test */
    public function matchmaking_considers_skill_tolerance()
    {
        Sanctum::actingAs($this->host);

        // Create participants with very different skill levels
        $participants = User::factory()->count(4)->create();
        foreach ($participants as $index => $participant) {
            EventParticipant::create([
                'event_id' => $this->event->id,
                'user_id' => $participant->id,
                'status' => 'confirmed',
                'registered_at' => now()
            ]);

            UserSportRating::create([
                'user_id' => $participant->id,
                'sport_id' => $this->sport->id,
                'skill_rating' => 1000 + ($index * 500), // 1000, 1500, 2000, 2500 (large gaps)
                'matches_played' => 5
            ]);
        }

        // Test with strict tolerance
        $response = $this->postJson("/api/matchmaking/{$this->event->id}/generate", [
            'match_type' => 'singles',
            'skill_tolerance' => 100 // Very strict
        ]);

        $response->assertOk();
        $data = $response->json('data');
        
        // Should create fewer matches due to strict tolerance
        $this->assertLessThanOrEqual(2, $data['total_matches']);
    }

    /** @test */
    public function unauthenticated_user_cannot_access_matchmaking()
    {
        $response = $this->getJson("/api/matchmaking/{$this->event->id}");

        $response->assertStatus(401);
    }
}
