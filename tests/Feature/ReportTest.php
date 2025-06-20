<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Sport;
use App\Models\Event;
use App\Models\UserReport;
use App\Models\Community;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Spatie\Permission\Models\Role;

class ReportTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;
    protected $reportedUser;
    protected $sport;
    protected $event;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create roles
        Role::create(['name' => 'host']);
        Role::create(['name' => 'player']);
        Role::create(['name' => 'admin']);

        // Create test users
        $this->user = User::factory()->create();
        $this->user->assignRole('player');
        
        $this->reportedUser = User::factory()->create();
        $this->reportedUser->assignRole('player');
        
        $this->sport = Sport::factory()->create();
        
        $this->event = Event::factory()->create([
            'sport_id' => $this->sport->id,
            'host_id' => $this->user->id
        ]);
    }

    /** @test */
    public function authenticated_user_can_submit_report()
    {
        Sanctum::actingAs($this->user);

        $reportData = [
            'reported_user_id' => $this->reportedUser->id,
            'report_type' => 'misconduct',
            'description' => 'User was behaving inappropriately during the match.',
            'evidence' => ['https://example.com/photo1.jpg'],
            'related_type' => 'event',
            'related_id' => $this->event->id
        ];

        $response = $this->postJson('/api/reports', $reportData);

        $response->assertStatus(201)
                ->assertJson([
                    'status' => 'success',
                    'message' => 'Laporan berhasil diajukan. Tim kami akan meninjau dalam 24-48 jam.'
                ]);

        $this->assertDatabaseHas('user_reports', [
            'reporter_id' => $this->user->id,
            'reported_user_id' => $this->reportedUser->id,
            'report_type' => 'misconduct',
            'status' => 'pending'
        ]);
    }

    /** @test */
    public function can_submit_different_report_types()
    {
        Sanctum::actingAs($this->user);

        $reportTypes = [
            'misconduct',
            'cheating', 
            'harassment',
            'no_show',
            'rating_dispute',
            'inappropriate_behavior',
            'spam',
            'fake_profile'
        ];

        foreach ($reportTypes as $type) {
            $response = $this->postJson('/api/reports', [
                'reported_user_id' => $this->reportedUser->id,
                'report_type' => $type,
                'description' => "Test report for {$type}",
                'related_type' => 'event',
                'related_id' => $this->event->id
            ]);

            $response->assertStatus(201);
        }

        $this->assertDatabaseCount('user_reports', count($reportTypes));
    }

    /** @test */
    public function user_can_get_their_submitted_reports()
    {
        Sanctum::actingAs($this->user);

        // Create some reports by this user
        UserReport::factory()->count(3)->create([
            'reporter_id' => $this->user->id,
            'reported_user_id' => $this->reportedUser->id
        ]);

        // Create report by another user (should not be included)
        UserReport::factory()->create([
            'reporter_id' => $this->reportedUser->id,
            'reported_user_id' => $this->user->id
        ]);

        $response = $this->getJson('/api/reports/my-reports');

        $response->assertOk()
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'reports',
                        'pagination'
                    ]
                ]);

        $reports = $response->json('data.reports');
        $this->assertCount(3, $reports);
        
        // Verify all reports are by the authenticated user
        foreach ($reports as $report) {
            $this->assertEquals($this->user->id, $report['reporter_id']);
        }
    }

    /** @test */
    public function user_can_get_reports_against_them()
    {
        Sanctum::actingAs($this->reportedUser);

        // Create reports against this user (with pending status so they appear in results)
        UserReport::factory()->count(2)->create([
            'reporter_id' => $this->user->id,
            'reported_user_id' => $this->reportedUser->id,
            'status' => 'pending'
        ]);

        $response = $this->getJson('/api/reports/against-me');

        $response->assertOk();
        $reports = $response->json('data.reports');
        $this->assertCount(2, $reports);

        // Verify all reports are against the authenticated user
        foreach ($reports as $report) {
            $this->assertEquals($this->reportedUser->id, $report['reported_user_id']);
        }
    }

    /** @test */
    public function can_get_report_statistics()
    {
        Sanctum::actingAs($this->user);

        // Create various reports
        UserReport::factory()->create([
            'reporter_id' => $this->user->id,
            'report_type' => 'misconduct',
            'status' => 'pending'
        ]);

        UserReport::factory()->create([
            'reporter_id' => $this->user->id,
            'report_type' => 'cheating',
            'status' => 'resolved'
        ]);

        $response = $this->getJson('/api/reports/stats');

        $response->assertOk()
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'submitted_reports',
                        'received_reports',
                        'reports_by_type',
                        'reports_by_status'
                    ]
                ]);
    }

    /** @test */
    public function can_get_report_details()
    {
        Sanctum::actingAs($this->user);

        $report = UserReport::factory()->create([
            'reporter_id' => $this->user->id,
            'reported_user_id' => $this->reportedUser->id
        ]);

        $response = $this->getJson("/api/reports/{$report->id}");

        $response->assertOk()
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'report' => [
                            'id',
                            'report_type',
                            'description',
                            'status',
                            'reporter',
                            'reported_user'
                        ]
                    ]
                ]);
    }

    /** @test */
    public function user_can_update_report_within_time_limit()
    {
        Sanctum::actingAs($this->user);

        $report = UserReport::factory()->create([
            'reporter_id' => $this->user->id,
            'status' => 'pending',
            'created_at' => now()->subHours(12) // 12 hours ago (within 24h limit)
        ]);

        $updateData = [
            'description' => 'Updated description with more details.',
            'evidence' => ['https://example.com/updated-photo.jpg']
        ];

        $response = $this->putJson("/api/reports/{$report->id}", $updateData);

        $response->assertOk()
                ->assertJson([
                    'status' => 'success',
                    'message' => 'Report berhasil diperbarui.'
                ]);

        $report->refresh();
        $this->assertEquals('Updated description with more details.', $report->description);
    }

    /** @test */
    public function cannot_update_report_after_time_limit()
    {
        Sanctum::actingAs($this->user);

        $report = UserReport::factory()->create([
            'reporter_id' => $this->user->id,
            'status' => 'pending',
            'created_at' => now()->subHours(25) // 25 hours ago (beyond 24h limit)
        ]);

        $response = $this->putJson("/api/reports/{$report->id}", [
            'description' => 'This should fail'
        ]);

        $response->assertStatus(422)
                ->assertJson([
                    'status' => 'error',
                    'message' => 'Report hanya dapat diubah dalam 24 jam setelah dibuat.'
                ]);
    }

    /** @test */
    public function cannot_update_resolved_report()
    {
        Sanctum::actingAs($this->user);

        $report = UserReport::factory()->create([
            'reporter_id' => $this->user->id,
            'status' => 'resolved'
        ]);

        $response = $this->putJson("/api/reports/{$report->id}", [
            'description' => 'This should fail'
        ]);

        $response->assertStatus(422)
                ->assertJson([
                    'status' => 'error',
                    'message' => 'Report yang sudah diselesaikan tidak dapat diubah.'
                ]);
    }

    /** @test */
    public function user_can_cancel_pending_report()
    {
        Sanctum::actingAs($this->user);

        $report = UserReport::factory()->create([
            'reporter_id' => $this->user->id,
            'status' => 'pending'
        ]);

        $response = $this->deleteJson("/api/reports/{$report->id}");

        $response->assertOk()
                ->assertJson([
                    'status' => 'success',
                    'message' => 'Report berhasil dibatalkan.'
                ]);

        $report->refresh();
        $this->assertEquals('dismissed', $report->status);
    }

    /** @test */
    public function cannot_cancel_resolved_report()
    {
        Sanctum::actingAs($this->user);

        $report = UserReport::factory()->create([
            'reporter_id' => $this->user->id,
            'status' => 'resolved'
        ]);

        $response = $this->deleteJson("/api/reports/{$report->id}");

        $response->assertStatus(422)
                ->assertJson([
                    'status' => 'error',
                    'message' => 'Report yang sudah diselesaikan tidak dapat dibatalkan.'
                ]);
    }

    /** @test */
    public function user_cannot_access_other_users_reports()
    {
        $otherUser = User::factory()->create();
        Sanctum::actingAs($otherUser);

        $report = UserReport::factory()->create([
            'reporter_id' => $this->user->id
        ]);

        $response = $this->getJson("/api/reports/{$report->id}");

        $response->assertStatus(403)
                ->assertJson([
                    'status' => 'error',
                    'message' => 'Anda tidak memiliki akses ke report ini.'
                ]);
    }

    /** @test */
    public function cannot_report_yourself()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/reports', [
            'reported_user_id' => $this->user->id, // Same user
            'report_type' => 'misconduct',
            'description' => 'Self report',
            'related_type' => 'event',
            'related_id' => $this->event->id
        ]);

        $response->assertStatus(400)
                ->assertJson([
                    'status' => 'error',
                    'message' => 'Anda tidak dapat melaporkan diri sendiri'
                ]);
    }

    /** @test */
    public function report_validation_works()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/reports', [
            'reported_user_id' => 999, // Non-existent user
            'report_type' => 'invalid_type',
            'description' => '', // Empty description
            'related_type' => 'invalid_type',
            'related_id' => 999
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors([
                    'reported_user_id',
                    'report_type',
                    'description',
                    'related_type'
                ]);
    }

    /** @test */
    public function can_report_with_community_context()
    {
        Sanctum::actingAs($this->user);

        $community = Community::factory()->create([
            'sport_id' => $this->sport->id,
            'host_user_id' => $this->user->id
        ]);

        $response = $this->postJson('/api/reports', [
            'reported_user_id' => $this->reportedUser->id,
            'report_type' => 'harassment',
            'description' => 'Inappropriate behavior in community chat.',
            'related_type' => 'community',
            'related_id' => $community->id
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('user_reports', [
            'related_type' => 'community',
            'related_id' => $community->id
        ]);
    }

    /** @test */
    public function high_priority_reports_are_flagged()
    {
        Sanctum::actingAs($this->user);

        $highPriorityTypes = ['harassment', 'cheating', 'inappropriate_behavior'];

        foreach ($highPriorityTypes as $type) {
            $response = $this->postJson('/api/reports', [
                'reported_user_id' => $this->reportedUser->id,
                'report_type' => $type,
                'description' => "High priority report: {$type}",
                'related_type' => 'event',
                'related_id' => $this->event->id
            ]);

            $response->assertStatus(201);
        }

        // Check that high priority reports have correct priority
        $reports = UserReport::whereIn('report_type', $highPriorityTypes)->get();
        foreach ($reports as $report) {
            $this->assertEquals('high', $report->priority);
        }
    }

    /** @test */
    public function unauthenticated_user_cannot_access_report_routes()
    {
        $this->postJson('/api/reports', [])->assertStatus(401);
        $this->getJson('/api/reports/my-reports')->assertStatus(401);
        $this->getJson('/api/reports/against-me')->assertStatus(401);
        $this->getJson('/api/reports/stats')->assertStatus(401);
    }
}
