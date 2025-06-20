<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Sport;
use App\Models\Event;
use App\Models\UserReport;
use App\Models\AdminActivity;
use App\Models\CreditScoreLog;
use App\Models\MatchHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Spatie\Permission\Models\Role;

class AdminTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $admin;
    protected $user;
    protected $sport;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create roles
        Role::create(['name' => 'host']);
        Role::create(['name' => 'player']);
        Role::create(['name' => 'admin']);

        // Create test users
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        
        $this->user = User::factory()->create();
        $this->user->assignRole('player');
        
        $this->sport = Sport::factory()->create();
    }

    /** @test */
    public function admin_can_access_dashboard()
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/admin/dashboard');

        $response->assertOk()
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'user_metrics',
                        'event_metrics', 
                        'match_metrics',
                        'system_health',
                        'recent_activities'
                    ]
                ]);
    }

    /** @test */
    public function non_admin_cannot_access_dashboard()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/admin/dashboard');

        $response->assertStatus(403);
    }

    /** @test */
    public function admin_can_get_system_analytics()
    {
        Sanctum::actingAs($this->admin);

        // Create some test data
        User::factory()->count(5)->create();
        Event::factory()->count(3)->create(['sport_id' => $this->sport->id]);

        $response = $this->getJson('/api/admin/analytics');

        $response->assertOk()
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'growth_metrics',
                        'engagement_metrics',
                        'platform_performance'
                    ]
                ]);
    }

    /** @test */
    public function admin_can_get_users_list()
    {
        Sanctum::actingAs($this->admin);

        // Create test users
        User::factory()->count(10)->create();

        $response = $this->getJson('/api/admin/users');

        $response->assertOk()
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'users',
                        'pagination'
                    ]
                ]);
    }

    /** @test */
    public function admin_can_filter_users()
    {
        Sanctum::actingAs($this->admin);

        // Create users with different statuses
        User::factory()->create(['is_active' => false]);
        User::factory()->count(2)->create(['is_active' => true]);

        $response = $this->getJson('/api/admin/users?status=active');

        $response->assertOk();
        $users = $response->json('data.users');
        
        foreach ($users as $user) {
            $this->assertTrue($user['is_active']);
        }
    }

    /** @test */
    public function admin_can_get_user_details()
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson("/api/admin/users/{$this->user->id}");

        $response->assertOk()
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'user' => [
                            'id',
                            'name',
                            'email',
                            'profile',
                            'credit_score',
                            'sport_ratings'
                        ],
                        'statistics',
                        'recent_activities'
                    ]
                ]);
    }

    /** @test */
    public function admin_can_toggle_user_status()
    {
        Sanctum::actingAs($this->admin);

        $activeUser = User::factory()->create(['is_active' => true]);

        $response = $this->postJson("/api/admin/users/{$activeUser->id}/toggle-status", [
            'action' => 'suspend',
            'reason' => 'Violating community guidelines'
        ]);

        $response->assertOk()
                ->assertJson([
                    'status' => 'success'
                ]);

        $activeUser->refresh();
        $this->assertFalse($activeUser->is_active);

        // Check admin activity was logged
        $this->assertDatabaseHas('admin_activities', [
            'admin_id' => $this->admin->id,
            'action_type' => 'user_suspended',
            'target_type' => 'user',
            'target_id' => $activeUser->id
        ]);
    }

    /** @test */
    public function admin_can_adjust_user_credit_score()
    {
        Sanctum::actingAs($this->admin);

        // Set user score to 70 so we can add 50 without hitting the cap
        $this->user->update(['credit_score' => 70]);
        $originalScore = $this->user->credit_score;

        $response = $this->postJson("/api/admin/users/{$this->user->id}/adjust-credit", [
            'adjustment_type' => 'add',
            'amount' => 20,
            'reason' => 'Good behavior reward'
        ]);

        $response->assertOk()
                ->assertJson([
                    'status' => 'success'
                ]);

        $this->user->refresh();
        $this->assertEquals($originalScore + 20, $this->user->credit_score);

        // Check credit score log was created
        $this->assertDatabaseHas('credit_score_logs', [
            'user_id' => $this->user->id,
            'change_amount' => 20,
            'type' => 'admin_adjustment'
        ]);
    }

    /** @test */
    public function admin_can_get_reports_list()
    {
        Sanctum::actingAs($this->admin);

        // Create test reports
        UserReport::factory()->count(5)->create();

        $response = $this->getJson('/api/admin/reports');

        $response->assertOk()
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'reports',
                        'pagination'
                    ]
                ]);
    }

    /** @test */
    public function admin_can_filter_reports_by_status()
    {
        Sanctum::actingAs($this->admin);

        // Create reports with different statuses
        UserReport::factory()->create(['status' => 'pending']);
        UserReport::factory()->create(['status' => 'resolved']);

        $response = $this->getJson('/api/admin/reports?status=pending');

        $response->assertOk();
        $reports = $response->json('data.reports');

        foreach ($reports as $report) {
            $this->assertEquals('pending', $report['status']);
        }
    }

    /** @test */
    public function admin_can_assign_report()
    {
        Sanctum::actingAs($this->admin);

        $report = UserReport::factory()->create(['status' => 'pending']);

        $response = $this->postJson("/api/admin/reports/{$report->id}/assign", [
            'assigned_to' => $this->admin->id,
            'priority' => 'high'
        ]);

        $response->assertOk()
                ->assertJson([
                    'status' => 'success',
                    'message' => 'Report berhasil di-assign.'
                ]);

        $report->refresh();
        $this->assertEquals($this->admin->id, $report->assigned_to);
        $this->assertEquals('high', $report->priority);
        $this->assertEquals('under_review', $report->status);
    }

    /** @test */
    public function admin_can_resolve_report()
    {
        Sanctum::actingAs($this->admin);

        $report = UserReport::factory()->create([
            'status' => 'under_review',
            'assigned_to' => $this->admin->id
        ]);

        $response = $this->postJson("/api/admin/reports/{$report->id}/resolve", [
            'action' => 'resolve',
            'resolution' => 'warning_issued'
        ]);

        $response->assertOk()
                ->assertJson([
                    'status' => 'success',
                    'message' => 'Report berhasil diselesaikan.'
                ]);

        $report->refresh();
        $this->assertEquals('resolved', $report->status);
        $this->assertEquals('warning_issued', $report->resolution);
    }

    /** @test */
    public function admin_can_get_platform_match_history()
    {
        Sanctum::actingAs($this->admin);

        // Create test match history
        MatchHistory::factory()->count(5)->create();

        $response = $this->getJson('/api/admin/matches/history');

        $response->assertOk()
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'matches',
                        'pagination',
                        'statistics'
                    ]
                ]);
    }

    /** @test */
    public function admin_can_filter_match_history()
    {
        Sanctum::actingAs($this->admin);

        $event = Event::factory()->create(['sport_id' => $this->sport->id]);
        
        MatchHistory::factory()->create(['event_id' => $event->id]);
        MatchHistory::factory()->create(); // Different event

        $response = $this->getJson("/api/admin/matches/history?event_id={$event->id}");

        $response->assertOk();
        $matches = $response->json('data.matches');

        foreach ($matches as $match) {
            $this->assertEquals($event->id, $match['event_id']);
        }
    }

    /** @test */
    public function admin_can_get_admin_activities()
    {
        Sanctum::actingAs($this->admin);

        // Create test admin activities
        AdminActivity::factory()->count(3)->create([
            'admin_id' => $this->admin->id
        ]);

        $response = $this->getJson('/api/admin/activities');

        $response->assertOk()
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'activities',
                        'pagination'
                    ]
                ]);
    }

    /** @test */
    public function admin_activities_are_logged()
    {
        Sanctum::actingAs($this->admin);

        // Perform an admin action
        $this->postJson("/api/admin/users/{$this->user->id}/toggle-status", [
            'action' => 'suspend',
            'reason' => 'Test suspension'
        ]);

        // Check that activity was logged
        $this->assertDatabaseHas('admin_activities', [
            'admin_id' => $this->admin->id,
            'action_type' => 'user_suspended',
            'target_id' => $this->user->id
        ]);
    }

    /** @test */
    public function admin_can_search_users()
    {
        Sanctum::actingAs($this->admin);

        $searchUser = User::factory()->create([
            'name' => 'John Doe Test User',
            'email' => 'john.doe@test.com'
        ]);

        $response = $this->getJson('/api/admin/users?search=John Doe');

        $response->assertOk();
        $users = $response->json('data.users');
        
        $found = false;
        foreach ($users as $user) {
            if ($user['id'] === $searchUser->id) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Search user not found in results');
    }

    /** @test */
    public function admin_dashboard_shows_correct_metrics()
    {
        Sanctum::actingAs($this->admin);

        // Create test data
        $activeUsers = User::factory()->count(10)->create(['is_active' => true]);
        $inactiveUsers = User::factory()->count(2)->create(['is_active' => false]);
        $events = Event::factory()->count(5)->create(['sport_id' => $this->sport->id]);

        // Debug: Check actual user count
        $actualUserCount = User::count();
        $actualActiveCount = User::where('is_active', true)->count();
        
        $response = $this->getJson('/api/admin/dashboard');

        $response->assertOk();
        $data = $response->json('data');

        // Check user metrics
        $this->assertEquals($actualUserCount, $data['user_metrics']['total_users']); // Use actual count instead of hardcoded 12
        $this->assertEquals($actualActiveCount, $data['user_metrics']['active_users']); // Use actual active count

        // Check event metrics
        $this->assertEquals(5, $data['event_metrics']['total_events']);
    }

    /** @test */
    public function credit_score_adjustment_validation_works()
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson("/api/admin/users/{$this->user->id}/adjust-credit", [
            'adjustment_type' => 'invalid_type', // Should be add, subtract, or set
            'amount' => 'invalid', // Should be numeric
            'reason' => '' // Should not be empty
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['adjustment_type', 'amount', 'reason']);
    }

    /** @test */
    public function admin_cannot_assign_report_to_non_admin()
    {
        Sanctum::actingAs($this->admin);

        $report = UserReport::factory()->create();
        $regularUser = User::factory()->create();

        $response = $this->postJson("/api/admin/reports/{$report->id}/assign", [
            'assigned_to' => $regularUser->id
        ]);

        $response->assertStatus(422)
                ->assertJson([
            'status' => 'error',
            'message' => 'Report hanya dapat di-assign ke admin.'
        ]);
    }

    /** @test */
    public function unauthenticated_user_cannot_access_admin_routes()
    {
        $this->getJson('/api/admin/dashboard')->assertStatus(401);
        $this->getJson('/api/admin/users')->assertStatus(401);
        $this->getJson('/api/admin/reports')->assertStatus(401);
        $this->getJson('/api/admin/activities')->assertStatus(401);
    }

    /** @test */
    public function regular_user_cannot_access_admin_routes()
    {
        Sanctum::actingAs($this->user);

        $this->getJson('/api/admin/dashboard')->assertStatus(403);
        $this->getJson('/api/admin/users')->assertStatus(403);
        $this->getJson('/api/admin/reports')->assertStatus(403);
        $this->postJson("/api/admin/users/{$this->user->id}/toggle-status", [
            'action' => 'suspend',
            'reason' => 'Test reason'
        ])->assertStatus(403);
    }
}
