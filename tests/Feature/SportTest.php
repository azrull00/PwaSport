<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Sport;
use App\Models\Event;
use App\Models\Community;
use App\Models\UserProfile;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;

class SportTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test sports
        Sport::factory()->create([
            'name' => 'Badminton',
            'code' => 'badminton',
            'description' => 'Badminton sport',
            'is_active' => true
        ]);
        
        Sport::factory()->create([
            'name' => 'Tennis',
            'code' => 'tennis',
            'description' => 'Tennis sport',
            'is_active' => true
        ]);
        
        Sport::factory()->create([
            'name' => 'Inactive Sport',
            'code' => 'inactive',
            'description' => 'Inactive sport',
            'is_active' => false
        ]);
    }

    /** @test */
    public function can_get_all_active_sports()
    {
        $response = $this->getJson('/api/sports');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'sports' => [
                            '*' => [
                                'id',
                                'name',
                                'description',
                                'is_active'
                            ]
                        ]
                    ]
                ]);

        $data = $response->json();
        $this->assertCount(2, $data['data']['sports']); // Only active sports
        $this->assertEquals('Badminton', $data['data']['sports'][0]['name']);
        $this->assertEquals('Tennis', $data['data']['sports'][1]['name']);
    }

    /** @test */
    public function authenticated_user_can_get_sport_details()
    {
        $user = User::factory()->create();
        $sport = Sport::where('name', 'Badminton')->first();
        
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/sports/{$sport->id}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'sport' => [
                            'id',
                            'name',
                            'description',
                            'is_active'
                        ]
                    ]
                ]);

        $data = $response->json();
        $this->assertEquals('Badminton', $data['data']['sport']['name']);
    }

    /** @test */
    public function authenticated_user_can_get_sport_events()
    {
        $user = User::factory()->create();
        $sport = Sport::where('name', 'Badminton')->first();
        
        // Create community and events
        $community = Community::factory()->create([
            'sport_id' => $sport->id,
            'host_user_id' => $user->id,
            'is_active' => true
        ]);
        
        Event::factory()->count(3)->create([
            'sport_id' => $sport->id,
            'community_id' => $community->id,
            'host_id' => $user->id,
            'event_date' => now()->addDays(1),
            'status' => 'published'
        ]);
        
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/sports/{$sport->id}/events");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'sport',
                        'events' => [
                            'data' => [
                                '*' => [
                                    'id',
                                    'title',
                                    'event_date',
                                    'status',
                                    'sport_id',
                                    'community_id'
                                ]
                            ]
                        ]
                    ]
                ]);
    }

    /** @test */
    public function can_filter_sport_events_by_type()
    {
        $user = User::factory()->create();
        $sport = Sport::where('name', 'Badminton')->first();
        
        $community = Community::factory()->create([
            'sport_id' => $sport->id,
            'host_user_id' => $user->id,
            'is_active' => true
        ]);
        
        Event::factory()->create([
            'sport_id' => $sport->id,
            'community_id' => $community->id,
            'host_id' => $user->id,
            'event_type' => 'mabar',
            'event_date' => now()->addDays(1),
            'status' => 'published'
        ]);
        
        Event::factory()->create([
            'sport_id' => $sport->id,
            'community_id' => $community->id,
            'host_id' => $user->id,
            'event_type' => 'tournament',
            'event_date' => now()->addDays(1),
            'status' => 'published'
        ]);
        
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/sports/{$sport->id}/events?type=mabar");

        $response->assertStatus(200);
        
        $data = $response->json();
        $this->assertCount(1, $data['data']['events']['data']);
        $this->assertEquals('mabar', $data['data']['events']['data'][0]['event_type']);
    }

    /** @test */
    public function can_filter_sport_events_by_city()
    {
        $user = User::factory()->create();
        $sport = Sport::where('name', 'Badminton')->first();
        
        $community = Community::factory()->create([
            'sport_id' => $sport->id,
            'host_user_id' => $user->id,
            'city' => 'Jakarta',
            'is_active' => true
        ]);
        
        Event::factory()->create([
            'sport_id' => $sport->id,
            'community_id' => $community->id,
            'host_id' => $user->id,
            'event_date' => now()->addDays(1),
            'status' => 'published'
        ]);
        
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/sports/{$sport->id}/events?city=Jakarta");

        $response->assertStatus(200);
        
        $data = $response->json();
        $this->assertGreaterThanOrEqual(1, count($data['data']['events']['data']));
    }

    /** @test */
    public function authenticated_user_can_get_sport_communities()
    {
        $user = User::factory()->create();
        $sport = Sport::where('name', 'Badminton')->first();
        
        Community::factory()->count(2)->create([
            'sport_id' => $sport->id,
            'host_user_id' => $user->id,
            'is_active' => true
        ]);
        
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/sports/{$sport->id}/communities");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'sport',
                        'communities' => [
                            'data' => [
                                '*' => [
                                    'id',
                                    'name',
                                    'description',
                                    'sport_id',
                                    'host_user_id',
                                    'is_active'
                                ]
                            ]
                        ]
                    ]
                ]);
    }

    /** @test */
    public function can_filter_sport_communities_by_city()
    {
        $user = User::factory()->create();
        $sport = Sport::where('name', 'Badminton')->first();
        
        Community::factory()->create([
            'sport_id' => $sport->id,
            'host_user_id' => $user->id,
            'city' => 'Jakarta',
            'is_active' => true
        ]);
        
        Community::factory()->create([
            'sport_id' => $sport->id,
            'host_user_id' => $user->id,
            'city' => 'Bandung',
            'is_active' => true
        ]);
        
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/sports/{$sport->id}/communities?city=Jakarta");

        $response->assertStatus(200);
        
        $data = $response->json();
        $this->assertGreaterThanOrEqual(1, count($data['data']['communities']['data']));
    }

    /** @test */
    public function can_filter_sport_communities_by_type()
    {
        $user = User::factory()->create();
        $sport = Sport::where('name', 'Badminton')->first();
        
        Community::factory()->create([
            'sport_id' => $sport->id,
            'host_user_id' => $user->id,
            'community_type' => 'public',
            'is_active' => true
        ]);
        
        Community::factory()->create([
            'sport_id' => $sport->id,
            'host_user_id' => $user->id,
            'community_type' => 'private',
            'is_active' => true
        ]);
        
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/sports/{$sport->id}/communities?type=public");

        $response->assertStatus(200);
        
        $data = $response->json();
        $this->assertGreaterThanOrEqual(1, count($data['data']['communities']['data']));
    }

    /** @test */
    public function unauthenticated_user_cannot_access_protected_sport_routes()
    {
        $sport = Sport::where('name', 'Badminton')->first();

        $response = $this->getJson("/api/sports/{$sport->id}");
        $response->assertStatus(401);

        $response = $this->getJson("/api/sports/{$sport->id}/events");
        $response->assertStatus(401);

        $response = $this->getJson("/api/sports/{$sport->id}/communities");
        $response->assertStatus(401);
    }

    /** @test */
    public function returns_404_for_non_existent_sport()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/sports/999");
        $response->assertStatus(404);
    }
} 
