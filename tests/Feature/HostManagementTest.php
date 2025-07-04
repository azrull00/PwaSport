<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Community;
use App\Models\Event;
use App\Models\Venue;
use App\Models\GuestPlayer;
use App\Models\CommunityMember;
use App\Models\EventParticipant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;

class HostManagementTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $host;
    protected $community;
    protected $venue;
    protected $event;

    public function setUp(): void
    {
        parent::setUp();

        // Create host user
        $this->host = User::factory()->create();
        $this->host->assignRole('host');

        // Create test data
        $this->community = Community::factory()->create([
            'host_id' => $this->host->id
        ]);

        $this->venue = Venue::factory()->create([
            'owner_id' => $this->host->id
        ]);

        $this->event = Event::factory()->create([
            'host_id' => $this->host->id,
            'venue_id' => $this->venue->id,
            'community_id' => $this->community->id
        ]);

        Sanctum::actingAs($this->host);
    }

    /** @test */
    public function host_can_view_dashboard_stats()
    {
        $response = $this->getJson('/api/host/dashboard/stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'stats' => [
                        'totalMembers',
                        'activeEvents',
                        'totalCommunities',
                        'pendingRequests'
                    ]
                ]
            ]);
    }

    /** @test */
    public function host_can_manage_venues()
    {
        // Create venue
        $venueData = [
            'name' => $this->faker->company,
            'address' => $this->faker->address,
            'capacity' => $this->faker->numberBetween(10, 100),
            'courts_count' => $this->faker->numberBetween(1, 10)
        ];

        $response = $this->postJson('/api/host/venues', $venueData);
        $response->assertStatus(201);
        $venueId = $response->json('data.venue.id');

        // Update venue
        $updateData = ['name' => 'Updated Venue Name'];
        $response = $this->putJson("/api/host/venues/{$venueId}", $updateData);
        $response->assertStatus(200);

        // Get venue stats
        $response = $this->getJson("/api/host/venues/{$venueId}/stats");
        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'venue',
                    'statistics',
                    'upcoming_events'
                ]
            ]);

        // Delete venue
        $response = $this->deleteJson("/api/host/venues/{$venueId}");
        $response->assertStatus(200);
    }

    /** @test */
    public function host_can_manage_community_settings()
    {
        $updateData = [
            'name' => 'Updated Community Name',
            'description' => 'Updated description',
            'rules' => 'Updated rules',
            'privacy' => 'private',
            'join_approval_required' => true,
            'max_members' => 100
        ];

        $response = $this->putJson("/api/host/communities/{$this->community->id}/settings", $updateData);
        
        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Community settings updated successfully'
            ]);
    }

    /** @test */
    public function host_can_manage_guest_players()
    {
        // Add guest player
        $guestData = [
            'name' => $this->faker->name,
            'phone' => $this->faker->phoneNumber,
            'skill_level' => $this->faker->numberBetween(1, 5)
        ];

        $response = $this->postJson("/api/host/events/{$this->event->id}/guest-players", $guestData);
        $response->assertStatus(201);
        $guestPlayerId = $response->json('data.guest_player.id');

        // Update guest player
        $updateData = ['name' => 'Updated Guest Name'];
        $response = $this->putJson("/api/host/events/{$this->event->id}/guest-players/{$guestPlayerId}", $updateData);
        $response->assertStatus(200);

        // List guest players
        $response = $this->getJson("/api/host/events/{$this->event->id}/guest-players");
        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'guest_players' => [
                        '*' => [
                            'id',
                            'name',
                            'phone',
                            'skill_level',
                            'checked_in'
                        ]
                    ]
                ]
            ]);

        // Remove guest player
        $response = $this->deleteJson("/api/host/events/{$this->event->id}/guest-players/{$guestPlayerId}");
        $response->assertStatus(200);
    }

    /** @test */
    public function host_can_manage_member_requests()
    {
        // Create a pending member
        $member = CommunityMember::factory()->create([
            'community_id' => $this->community->id,
            'status' => 'pending'
        ]);

        // Approve member request
        $response = $this->postJson("/api/host/communities/{$this->community->id}/members/{$member->id}/manage", [
            'action' => 'approve'
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Member request approved'
            ]);

        // Verify member status
        $this->assertEquals('active', $member->fresh()->status);
    }

    /** @test */
    public function host_can_process_qr_code_check_in()
    {
        // Create a participant
        $participant = EventParticipant::factory()->create([
            'event_id' => $this->event->id,
            'checked_in_at' => null
        ]);

        // Generate QR code data
        $timestamp = now()->timestamp;
        $type = 'participant';
        $id = $participant->user_id;
        $eventId = $this->event->id;
        $hash = hash('sha256', $type . $id . $eventId . $timestamp . config('app.key'));
        
        $qrCode = implode(':', [$type, $id, $eventId, $timestamp, $hash]);

        // Process QR code check-in
        $response = $this->postJson("/api/host/events/{$this->event->id}/check-in/qr", [
            'qr_code' => $qrCode,
            'check_in_type' => 'participant'
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Participant checked in successfully'
            ]);

        // Verify participant is checked in
        $this->assertNotNull($participant->fresh()->checked_in_at);
    }

    /** @test */
    public function host_can_generate_check_in_qr_code()
    {
        $participant = EventParticipant::factory()->create([
            'event_id' => $this->event->id
        ]);

        $response = $this->postJson("/api/host/events/{$this->event->id}/generate-qr", [
            'type' => 'participant',
            'id' => $participant->user_id
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'qr_code',
                    'expires_at'
                ]
            ]);

        // Verify QR code format
        $qrCode = $response->json('data.qr_code');
        $parts = explode(':', $qrCode);
        $this->assertCount(5, $parts); // type:id:event_id:timestamp:hash
    }

    /** @test */
    public function qr_code_check_in_validates_integrity()
    {
        // Try with invalid hash
        $invalidQrCode = implode(':', ['participant', '1', $this->event->id, now()->timestamp, 'invalid_hash']);

        $response = $this->postJson("/api/host/events/{$this->event->id}/check-in/qr", [
            'qr_code' => $invalidQrCode,
            'check_in_type' => 'participant'
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'status' => 'error',
                'message' => 'Invalid QR code signature'
            ]);
    }
} 