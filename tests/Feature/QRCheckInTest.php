<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\Sport;
use App\Models\Community;
use Laravel\Sanctum\Sanctum;
use Carbon\Carbon;

class QRCheckInTest extends TestCase
{
    use RefreshDatabase;

    protected $host;
    protected $player;
    protected $event;
    protected $sport;
    protected $community;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test data
        $this->sport = Sport::factory()->create(['name' => 'Badminton']);
        $this->community = Community::factory()->create();

        // Create host user
        $this->host = User::factory()->create([
            'user_type' => 'host',
            'credit_score' => 100,
        ]);
        $this->host->profile()->create([
            'first_name' => 'Host',
            'last_name' => 'User',
            'qr_code' => 'rackethub_' . $this->host->id . '_host123',
        ]);

        // Create player user
        $this->player = User::factory()->create([
            'user_type' => 'player',
            'credit_score' => 100,
        ]);
        $this->player->profile()->create([
            'first_name' => 'Player',
            'last_name' => 'User',
            'qr_code' => 'rackethub_' . $this->player->id . '_player123',
        ]);

        // Create event
        $this->event = Event::factory()->create([
            'host_id' => $this->host->id,
            'sport_id' => $this->sport->id,
            'community_id' => $this->community->id,
            'event_date' => Carbon::tomorrow(),
            'max_participants' => 10,
            'status' => 'active',
        ]);
    }

    /** @test */
    public function player_can_get_their_qr_code()
    {
        Sanctum::actingAs($this->player);

        $response = $this->getJson('/api/users/my-qr-code');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'qr_data' => [
                        'qr_code',
                        'user_id',
                        'user_name',
                        'generated_at',
                        'expires_at',
                    ],
                    'qr_string',
                    'user_info' => [
                        'name',
                        'photo',
                        'credit_score',
                    ]
                ]
            ]);

        $this->assertEquals('rackethub_' . $this->player->id . '_player123', $response->json('data.qr_string'));
        $this->assertEquals($this->player->id, $response->json('data.qr_data.user_id'));
        $this->assertEquals('Player User', $response->json('data.qr_data.user_name'));
    }

    /** @test */
    public function player_can_regenerate_qr_code()
    {
        Sanctum::actingAs($this->player);
        $oldQRCode = $this->player->profile->qr_code;

        $response = $this->postJson('/api/users/regenerate-qr');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'qr_code',
                    'generated_at',
                ]
            ]);

        $this->player->profile->refresh();
        $this->assertNotEquals($oldQRCode, $this->player->profile->qr_code);
        $this->assertStringStartsWith('rackethub_' . $this->player->id, $this->player->profile->qr_code);
    }

    /** @test */
    public function host_can_check_in_participant_via_qr_scan()
    {
        // Register player for event
        EventParticipant::create([
            'event_id' => $this->event->id,
            'user_id' => $this->player->id,
            'status' => 'confirmed',
            'registered_at' => now(),
        ]);

        Sanctum::actingAs($this->host);

        $response = $this->postJson("/api/events/{$this->event->id}/check-in-qr", [
            'qr_code' => $this->player->profile->qr_code,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'participant',
                    'check_in_method',
                    'checked_in_at',
                ]
            ]);

        $this->assertEquals('qr_scan', $response->json('data.check_in_method'));

        // Verify participant status updated
        $participant = EventParticipant::where([
            'event_id' => $this->event->id,
            'user_id' => $this->player->id,
        ])->first();

        $this->assertEquals('checked_in', $participant->status);
        $this->assertNotNull($participant->checked_in_at);
    }

    /** @test */
    public function host_cannot_check_in_unregistered_player_via_qr()
    {
        Sanctum::actingAs($this->host);

        $response = $this->postJson("/api/events/{$this->event->id}/check-in-qr", [
            'qr_code' => $this->player->profile->qr_code,
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'status' => 'error',
                'message' => 'Player tidak terdaftar untuk event ini.',
            ]);

        $this->assertArrayHasKey('data', $response->json());
        $this->assertEquals('Player User', $response->json('data.player_name'));
        $this->assertEquals($this->player->id, $response->json('data.player_id'));
    }

    /** @test */
    public function host_cannot_check_in_with_invalid_qr_code()
    {
        Sanctum::actingAs($this->host);

        $response = $this->postJson("/api/events/{$this->event->id}/check-in-qr", [
            'qr_code' => 'invalid_qr_code_123',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'status' => 'error',
                'message' => 'QR code tidak valid atau tidak ditemukan.',
            ]);
    }

    /** @test */
    public function system_warns_if_player_already_checked_in()
    {
        // Register and check-in player
        EventParticipant::create([
            'event_id' => $this->event->id,
            'user_id' => $this->player->id,
            'status' => 'checked_in',
            'registered_at' => now(),
            'checked_in_at' => now(),
        ]);

        Sanctum::actingAs($this->host);

        $response = $this->postJson("/api/events/{$this->event->id}/check-in-qr", [
            'qr_code' => $this->player->profile->qr_code,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'warning',
                'message' => 'Player sudah di-check-in sebelumnya.',
            ]);

        $this->assertArrayHasKey('data', $response->json());
        $this->assertArrayHasKey('checked_in_at', $response->json('data'));
    }

    /** @test */
    public function only_host_can_check_in_participants_via_qr()
    {
        // Register player
        EventParticipant::create([
            'event_id' => $this->event->id,
            'user_id' => $this->player->id,
            'status' => 'confirmed',
            'registered_at' => now(),
        ]);

        // Try to check-in as non-host user
        Sanctum::actingAs($this->player);

        $response = $this->postJson("/api/events/{$this->event->id}/check-in-qr", [
            'qr_code' => $this->player->profile->qr_code,
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'status' => 'error',
                'message' => 'Hanya host yang dapat melakukan check-in.',
            ]);
    }

    /** @test */
    public function host_can_bulk_check_in_multiple_participants()
    {
        // Create additional players
        $player2 = User::factory()->create(['user_type' => 'player']);
        $player2->profile()->create([
            'first_name' => 'Player2',
            'last_name' => 'User',
            'qr_code' => 'rackethub_' . $player2->id . '_player2',
        ]);

        $player3 = User::factory()->create(['user_type' => 'player']);
        $player3->profile()->create([
            'first_name' => 'Player3',
            'last_name' => 'User',
            'qr_code' => 'rackethub_' . $player3->id . '_player3',
        ]);

        // Register all players
        $participant1 = EventParticipant::create([
            'event_id' => $this->event->id,
            'user_id' => $this->player->id,
            'status' => 'confirmed',
            'registered_at' => now(),
        ]);

        $participant2 = EventParticipant::create([
            'event_id' => $this->event->id,
            'user_id' => $player2->id,
            'status' => 'confirmed',
            'registered_at' => now(),
        ]);

        $participant3 = EventParticipant::create([
            'event_id' => $this->event->id,
            'user_id' => $player3->id,
            'status' => 'cancelled', // This one should fail
            'registered_at' => now(),
        ]);

        Sanctum::actingAs($this->host);

        $response = $this->postJson("/api/events/{$this->event->id}/bulk-check-in", [
            'participant_ids' => [$participant1->id, $participant2->id, $participant3->id],
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'checked_in',
                    'errors',
                    'total_checked_in',
                    'total_errors',
                ]
            ]);

        $this->assertEquals(2, $response->json('data.total_checked_in'));
        $this->assertEquals(1, $response->json('data.total_errors'));

        // Verify participants status
        $participant1->refresh();
        $participant2->refresh();
        $participant3->refresh();

        $this->assertEquals('checked_in', $participant1->status);
        $this->assertEquals('checked_in', $participant2->status);
        $this->assertEquals('cancelled', $participant3->status); // Should remain unchanged
    }

    /** @test */
    public function host_can_get_check_in_statistics()
    {
        // Create participants with different statuses
        EventParticipant::create([
            'event_id' => $this->event->id,
            'user_id' => $this->player->id,
            'status' => 'checked_in',
            'registered_at' => now(),
            'checked_in_at' => now(),
        ]);

        $player2 = User::factory()->create(['user_type' => 'player']);
        $player2->profile()->create([
            'first_name' => 'Player2',
            'last_name' => 'User',
            'qr_code' => 'rackethub_' . $player2->id . '_player2',
        ]);

        EventParticipant::create([
            'event_id' => $this->event->id,
            'user_id' => $player2->id,
            'status' => 'confirmed',
            'registered_at' => now(),
        ]);

        Sanctum::actingAs($this->host);

        $response = $this->getJson("/api/events/{$this->event->id}/check-in-stats");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'statistics' => [
                        'total_registered',
                        'checked_in',
                        'confirmed_not_checked',
                        'waiting_list',
                        'no_show',
                        'cancelled',
                        'check_in_rate',
                    ],
                    'recent_check_ins',
                    'event_info' => [
                        'id',
                        'title',
                        'event_date',
                        'max_participants',
                    ]
                ]
            ]);

        $stats = $response->json('data.statistics');
        $this->assertEquals(2, $stats['total_registered']);
        $this->assertEquals(1, $stats['checked_in']);
        $this->assertEquals(1, $stats['confirmed_not_checked']);
        $this->assertEquals(50.0, $stats['check_in_rate']);
    }

    /** @test */
    public function only_host_can_view_check_in_statistics()
    {
        Sanctum::actingAs($this->player);

        $response = $this->getJson("/api/events/{$this->event->id}/check-in-stats");

        $response->assertStatus(403)
            ->assertJson([
                'status' => 'error',
                'message' => 'Hanya host yang dapat melihat statistik check-in.',
            ]);
    }

    /** @test */
    public function qr_check_in_validates_participant_status()
    {
        // Register player with 'waiting' status
        EventParticipant::create([
            'event_id' => $this->event->id,
            'user_id' => $this->player->id,
            'status' => 'waiting',
            'registered_at' => now(),
        ]);

        Sanctum::actingAs($this->host);

        $response = $this->postJson("/api/events/{$this->event->id}/check-in-qr", [
            'qr_code' => $this->player->profile->qr_code,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'message' => 'Player tidak dapat di-check-in. Status saat ini: waiting',
            ]);

        $this->assertArrayHasKey('data', $response->json());
        $this->assertEquals('waiting', $response->json('data.current_status'));
    }

    /** @test */
    public function qr_check_in_requires_valid_qr_code()
    {
        Sanctum::actingAs($this->host);

        $response = $this->postJson("/api/events/{$this->event->id}/check-in-qr", [
            'qr_code' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['qr_code']);
    }

    /** @test */
    public function player_without_profile_cannot_get_qr_code()
    {
        $userWithoutProfile = User::factory()->create(['user_type' => 'player']);
        
        Sanctum::actingAs($userWithoutProfile);

        $response = $this->getJson('/api/users/my-qr-code');

        $response->assertStatus(404)
            ->assertJson([
                'status' => 'error',
                'message' => 'QR code belum tersedia. Silakan lengkapi profil Anda.',
            ]);
    }
} 