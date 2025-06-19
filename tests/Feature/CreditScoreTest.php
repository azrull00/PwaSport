<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Sport;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\CreditScoreLog;
use Carbon\Carbon;
use Laravel\Sanctum\Sanctum;

class CreditScoreTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $sport;
    protected $event;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create(['credit_score' => 85]);
        $this->sport = Sport::factory()->create();
        $this->event = Event::factory()->create([
            'sport_id' => $this->sport->id,
            'host_id' => $this->user->id,
            'event_date' => now()->addDays(2)->format('Y-m-d'),
        ]);
    }

    /**
     * @test
     */
    public function authenticated_user_can_get_credit_score_summary()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/credit-score/summary');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'status',
                     'data' => [
                         'summary' => [
                             'current_score',
                             'total_earned',
                             'total_deducted',
                             'total_entries',
                             'restrictions',
                             'recent_activity'
                         ]
                     ]
                 ]);
    }

    /**
     * @test
     */
    public function authenticated_user_can_get_credit_score_restrictions()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/credit-score/restrictions');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'status',
                     'data' => [
                         'restrictions'
                     ]
                 ]);
    }

    /**
     * @test
     */
    public function authenticated_user_can_get_credit_score_history()
    {
        Sanctum::actingAs($this->user);

        // Create some credit score logs
        CreditScoreLog::factory()->count(3)->create(['user_id' => $this->user->id]);

        $response = $this->getJson('/api/credit-score');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'status',
                     'data' => [
                         'logs',
                         'summary'
                     ]
                 ]);
    }

    /**
     * @test
     */
    public function can_filter_credit_score_history_by_type()
    {
        Sanctum::actingAs($this->user);

        // Create logs with different types
        CreditScoreLog::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'penalty'
        ]);
        CreditScoreLog::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'bonus'
        ]);

        $response = $this->getJson('/api/credit-score?type=penalty');

        $response->assertStatus(200);
        // Additional assertions can be added to verify filtering
    }

    /**
     * @test
     */
    public function user_cannot_cancel_event_they_are_not_participant_of()
    {
        $otherUser = User::factory()->create();
        Sanctum::actingAs($otherUser);

        $response = $this->postJson('/api/credit-score/cancel-event', [
            'event_id' => $this->event->id,
            'reason' => 'Cannot attend'
        ]);

        $response->assertStatus(422)
                 ->assertJson([
                     'status' => 'error',
                     'message' => 'Anda tidak terdaftar dalam event ini.'
                 ]);
    }

    /**
     * @test
     */
    public function participant_can_cancel_event_with_penalty()
    {
        // Create another user as participant
        $participant = User::factory()->create(['credit_score' => 75]);
        
        // Add participant to event
        EventParticipant::create([
            'event_id' => $this->event->id,
            'user_id' => $participant->id,
            'status' => 'confirmed',
            'registered_at' => now()
        ]);

        Sanctum::actingAs($participant);

        $response = $this->postJson('/api/credit-score/cancel-event', [
            'event_id' => $this->event->id,
            'reason' => 'Emergency came up'
        ]);

        $response->assertStatus(200);
        
        // Verify penalty was applied
        $participant->refresh();
        $this->assertLessThan(75, $participant->credit_score);
        
        // Verify participant was removed from event
        $this->assertDatabaseMissing('event_participants', [
            'event_id' => $this->event->id,
            'user_id' => $participant->id
        ]);
    }

    /**
     * @test
     */
    public function only_host_can_report_no_show()
    {
        $participant = User::factory()->create();
        $otherUser = User::factory()->create();
        
        EventParticipant::create([
            'event_id' => $this->event->id,
            'user_id' => $participant->id,
            'status' => 'confirmed',
            'registered_at' => now()
        ]);

        Sanctum::actingAs($otherUser);

        $response = $this->postJson('/api/credit-score/no-show', [
            'event_id' => $this->event->id,
            'user_id' => $participant->id,
            'reason' => 'Did not show up'
        ]);

        $response->assertStatus(403)
                 ->assertJson([
                     'status' => 'error',
                     'message' => 'Hanya host yang dapat melaporkan no-show.'
                 ]);
    }

    /**
     * @test
     */
    public function host_can_report_no_show_penalty()
    {
        $participant = User::factory()->create(['credit_score' => 80]);
        
        EventParticipant::create([
            'event_id' => $this->event->id,
            'user_id' => $participant->id,
            'status' => 'confirmed',
            'registered_at' => now()
        ]);

        Sanctum::actingAs($this->user); // Host

        $response = $this->postJson('/api/credit-score/no-show', [
            'event_id' => $this->event->id,
            'user_id' => $participant->id,
            'reason' => 'Did not show up without notice'
        ]);

        $response->assertStatus(200);
        
        // Verify penalty was applied (30 points for no-show)
        $participant->refresh();
        $this->assertEquals(50, $participant->credit_score);
        
        // Verify participation status was updated
        $this->assertDatabaseHas('event_participants', [
            'event_id' => $this->event->id,
            'user_id' => $participant->id,
            'status' => 'no_show'
        ]);
    }

    /**
     * @test
     */
    public function can_process_event_completion_bonus()
    {
        $participant = User::factory()->create(['credit_score' => 70]);
        
        EventParticipant::create([
            'event_id' => $this->event->id,
            'user_id' => $participant->id,
            'status' => 'checked_in',
            'registered_at' => now()
        ]);

        Sanctum::actingAs($this->user); // Any authenticated user

        $response = $this->postJson('/api/credit-score/completion-bonus', [
            'event_id' => $this->event->id,
            'user_id' => $participant->id
        ]);

        $response->assertStatus(200);
        
        // Verify bonus was applied (2 points for completion)
        $participant->refresh();
        $this->assertEquals(72, $participant->credit_score);
    }

    /**
     * @test
     */
    public function cannot_give_completion_bonus_twice()
    {
        $participant = User::factory()->create(['credit_score' => 70]);
        
        EventParticipant::create([
            'event_id' => $this->event->id,
            'user_id' => $participant->id,
            'status' => 'checked_in',
            'registered_at' => now()
        ]);

        Sanctum::actingAs($this->user);

        // First bonus
        $this->postJson('/api/credit-score/completion-bonus', [
            'event_id' => $this->event->id,
            'user_id' => $participant->id
        ]);

        // Try to give bonus again
        $response = $this->postJson('/api/credit-score/completion-bonus', [
            'event_id' => $this->event->id,
            'user_id' => $participant->id
        ]);

        $response->assertStatus(422)
                 ->assertJson([
                     'status' => 'error',
                     'message' => 'Bonus completion sudah diberikan.'
                 ]);
    }

    /**
     * @test
     */
    public function unauthenticated_user_cannot_access_credit_score_routes()
    {
        $routes = [
            '/api/credit-score',
            '/api/credit-score/summary',
            '/api/credit-score/restrictions'
        ];

        foreach ($routes as $route) {
            $response = $this->getJson($route);
            $response->assertStatus(401);
        }
    }

    /**
     * @test
     */
    public function credit_score_penalty_calculation_is_correct()
    {
        $participant = User::factory()->create(['credit_score' => 75]);
        
        // Create event that's 1 hour away (should get maximum penalty)
        $nearEvent = Event::factory()->create([
            'sport_id' => $this->sport->id,
            'host_id' => $this->user->id,
            'event_date' => now()->addHour(),
        ]);
        
        EventParticipant::create([
            'event_id' => $nearEvent->id,
            'user_id' => $participant->id,
            'status' => 'confirmed',
            'registered_at' => now()
        ]);

        Sanctum::actingAs($participant);

        $response = $this->postJson('/api/credit-score/cancel-event', [
            'event_id' => $nearEvent->id,
            'reason' => 'Last minute cancellation'
        ]);

        $response->assertStatus(200);
        
        // Should get 25 point penalty for cancelling < 2 hours before
        $participant->refresh();
        $this->assertEquals(50, $participant->credit_score);
    }
}
