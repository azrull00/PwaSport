<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Event;
use App\Models\Community;
use App\Models\Sport;
use App\Models\EventParticipant;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\Event as EventFacade;
use App\Events\ChatMessageSent;
use App\Events\EventUpdated;
use App\Events\RealTimeNotification;

class RealTimeTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;
    protected $event;
    protected $community;
    protected $sport;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sport = Sport::factory()->create();
        $this->user = User::factory()->create();
        $this->event = Event::factory()->create([
            'sport_id' => $this->sport->id,
            'host_id' => $this->user->id,
        ]);
        // Remove community creation from setUp - create only when needed
    }

    /**
     * @test
     */
    public function authenticated_user_can_send_chat_message_to_event()
    {
        $participant = User::factory()->create();
        
        EventParticipant::create([
            'event_id' => $this->event->id,
            'user_id' => $participant->id,
            'status' => 'confirmed',
            'registered_at' => now()
        ]);

        EventFacade::fake();
        Sanctum::actingAs($participant);

        $response = $this->postJson('/api/realtime/chat/send', [
            'message' => 'Hello everyone in the event!',
            'event_id' => $this->event->id,
            'message_type' => 'text'
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'status' => 'success',
                     'message' => 'Pesan berhasil dikirim.',
                 ]);

        EventFacade::assertDispatched(ChatMessageSent::class);
    }

    /**
     * @test
     */
    public function authenticated_user_can_send_chat_message_to_community()
    {
        $community = Community::factory()->create([
            'host_user_id' => $this->user->id,
            'sport_id' => $this->sport->id
        ]);

        // Create event in community and add user as participant
        $event = Event::factory()->create([
            'community_id' => $community->id,
            'host_id' => $this->user->id,
            'sport_id' => $this->sport->id
        ]);

        EventParticipant::create([
            'event_id' => $event->id,
            'user_id' => $this->user->id,
            'status' => 'confirmed',
            'registered_at' => now()
        ]);

        EventFacade::fake();
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/realtime/chat/send', [
            'message' => 'Hello community!',
            'community_id' => $community->id,
            'message_type' => 'text'
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'status' => 'success',
                     'message' => 'Pesan berhasil dikirim.',
                 ]);

        EventFacade::assertDispatched(ChatMessageSent::class);
    }

    /**
     * @test
     */
    public function user_cannot_send_chat_to_event_they_are_not_part_of()
    {
        $outsider = User::factory()->create();
        
        Sanctum::actingAs($outsider);

        $response = $this->postJson('/api/realtime/chat/send', [
            'message' => 'Hello!',
            'event_id' => $this->event->id,
        ]);

        $response->assertStatus(403)
                 ->assertJson([
                     'status' => 'error',
                     'message' => 'Anda tidak memiliki akses ke chat event ini.'
                 ]);
    }

    /**
     * @test
     */
    public function host_can_broadcast_event_update()
    {
        Sanctum::actingAs($this->user); // Host

        EventFacade::fake();

        $response = $this->postJson('/api/realtime/event/broadcast', [
            'event_id' => $this->event->id,
            'update_type' => 'participant_joined',
            'data' => [
                'participant_name' => 'John Doe',
                'total_participants' => 5
            ]
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'status' => 'success',
                     'message' => 'Event update berhasil dikirim.',
                 ]);

        EventFacade::assertDispatched(EventUpdated::class);
    }

    /**
     * @test
     */
    public function non_host_cannot_broadcast_event_update()
    {
        $participant = User::factory()->create();
        
        EventParticipant::create([
            'event_id' => $this->event->id,
            'user_id' => $participant->id,
            'status' => 'confirmed',
            'registered_at' => now()
        ]);

        Sanctum::actingAs($participant);

        $response = $this->postJson('/api/realtime/event/broadcast', [
            'event_id' => $this->event->id,
            'update_type' => 'participant_joined',
            'data' => []
        ]);

        $response->assertStatus(403)
                 ->assertJson([
                     'status' => 'error',
                     'message' => 'Anda tidak memiliki izin untuk mengirim update event.'
                 ]);
    }

    /**
     * @test
     */
    public function authenticated_user_can_send_real_time_notification()
    {
        $recipient = User::factory()->create();
        
        // Create an event relationship so sender can send notification
        EventParticipant::create([
            'event_id' => $this->event->id,
            'user_id' => $recipient->id,
            'status' => 'confirmed',
            'registered_at' => now()
        ]);

        EventFacade::fake();
        Sanctum::actingAs($this->user); // Host

        $response = $this->postJson('/api/realtime/notification/send', [
            'user_id' => $recipient->id,
            'type' => 'event_invitation',
            'title' => 'Event Invitation',
            'message' => 'You are invited to join our badminton event!',
            'data' => [
                'event_id' => $this->event->id,
                'event_title' => $this->event->title
            ],
            'priority' => 'normal'
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'status' => 'success',
                     'message' => 'Notifikasi berhasil dikirim.',
                 ]);

        EventFacade::assertDispatched(RealTimeNotification::class);
    }

    /**
     * @test
     */
    public function can_get_online_users_for_channel()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/realtime/online-users?channel=event.' . $this->event->id);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'status',
                     'data' => [
                         'channel',
                         'online_users' => [
                             '*' => [
                                 'id',
                                 'name',
                                 'status',
                                 'last_seen'
                             ]
                         ],
                         'total_online'
                     ]
                 ]);
    }

    /**
     * @test
     */
    public function chat_message_validation_works()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/realtime/chat/send', [
            'message' => '', // Empty message
            'event_id' => $this->event->id,
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['message']);
    }

    /**
     * @test
     */
    public function event_update_validation_works()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/realtime/event/broadcast', [
            'event_id' => 999, // Non-existent event
            'update_type' => 'invalid_type', // Invalid update type
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['event_id', 'update_type']);
    }

    /**
     * @test
     */
    public function notification_validation_works()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/realtime/notification/send', [
            'user_id' => 999, // Non-existent user
            'type' => 'invalid_type', // Invalid type
            'title' => '', // Empty title
            'message' => '', // Empty message
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['user_id', 'type', 'title', 'message']);
    }

    /**
     * @test
     */
    public function unauthenticated_user_cannot_access_real_time_routes()
    {
        $routes = [
            'POST /api/realtime/chat/send',
            'POST /api/realtime/event/broadcast',
            'POST /api/realtime/notification/send',
            'GET /api/realtime/online-users'
        ];

        foreach ($routes as $route) {
            [$method, $path] = explode(' ', $route);
            
            if ($method === 'POST') {
                $response = $this->postJson($path, []);
            } else {
                $response = $this->getJson($path);
            }
            
            $response->assertStatus(401);
        }
    }

    /**
     * @test
     */
    public function chat_message_includes_correct_user_data()
    {
        EventParticipant::create([
            'event_id' => $this->event->id,
            'user_id' => $this->user->id,
            'status' => 'confirmed',
            'registered_at' => now()
        ]);

        EventFacade::fake();
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/realtime/chat/send', [
            'message' => 'Test message',
            'event_id' => $this->event->id,
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'status',
                     'message',
                     'data' => [
                         'user' => [
                             'id',
                             'name',
                             'avatar'
                         ],
                         'message',
                         'message_type',
                         'timestamp'
                     ]
                 ]);
    }
}
