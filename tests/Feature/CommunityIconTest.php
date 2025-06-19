<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use App\Models\User;
use App\Models\Community;
use App\Models\Sport;

class CommunityIconTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;
    protected $community;
    protected $sport;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test user
        $this->user = User::factory()->create();
        
        // Create sport
        $this->sport = Sport::factory()->create();
        
        // Create community with user as host
        $this->community = Community::factory()->create([
            'host_user_id' => $this->user->id,
            'sport_id' => $this->sport->id,
        ]);

        // Fake storage for testing
        Storage::fake('public');
    }

    public function test_host_can_upload_community_icon()
    {
        $this->actingAs($this->user, 'sanctum');
        
        $file = UploadedFile::fake()->image('community_icon.png', 512, 512);
        
        $response = $this->postJson("/api/communities/{$this->community->id}/upload-icon", [
            'icon' => $file
        ]);
        
        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success',
                    'message' => 'Icon komunitas berhasil diupload!',
                    'data' => [
                        'has_icon' => true
                    ]
                ]);
        
        // Check if file was stored
        Storage::disk('public')->assertExists('community-icons/community_' . $this->community->id . '_' . now()->timestamp . '.png');
        
        // Check database
        $this->community->refresh();
        $this->assertNotNull($this->community->getRawOriginal('icon_url'));
        $this->assertTrue($this->community->has_icon);
    }

    public function test_non_host_cannot_upload_community_icon()
    {
        $otherUser = User::factory()->create();
        $this->actingAs($otherUser, 'sanctum');
        
        $file = UploadedFile::fake()->image('community_icon.png');
        
        $response = $this->postJson("/api/communities/{$this->community->id}/upload-icon", [
            'icon' => $file
        ]);
        
        $response->assertStatus(403)
                ->assertJson([
                    'status' => 'error',
                    'message' => 'Hanya host yang dapat mengupload icon komunitas.'
                ]);
    }

    public function test_upload_icon_validates_file_type()
    {
        $this->actingAs($this->user, 'sanctum');
        
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');
        
        $response = $this->postJson("/api/communities/{$this->community->id}/upload-icon", [
            'icon' => $file
        ]);
        
        $response->assertStatus(422)
                ->assertJsonValidationErrors('icon');
    }

    public function test_upload_icon_validates_file_size()
    {
        $this->actingAs($this->user, 'sanctum');
        
        $file = UploadedFile::fake()->image('large_icon.png')->size(3000); // 3MB
        
        $response = $this->postJson("/api/communities/{$this->community->id}/upload-icon", [
            'icon' => $file
        ]);
        
        $response->assertStatus(422)
                ->assertJsonValidationErrors('icon');
    }

    public function test_upload_icon_replaces_existing_icon()
    {
        $this->actingAs($this->user, 'sanctum');
        
        // Upload first icon
        $firstFile = UploadedFile::fake()->image('first_icon.png');
        
        $response = $this->postJson("/api/communities/{$this->community->id}/upload-icon", [
            'icon' => $firstFile
        ]);
        
        $response->assertStatus(200);
        $firstIconPath = $this->community->fresh()->getRawOriginal('icon_url');
        
        // Sleep to ensure different timestamp
        sleep(1);
        
        // Upload second icon
        $secondFile = UploadedFile::fake()->image('second_icon.png');
        
        $response = $this->postJson("/api/communities/{$this->community->id}/upload-icon", [
            'icon' => $secondFile
        ]);
        
        $response->assertStatus(200);
        
        // Check that new icon path is different
        $this->community->refresh();
        $newIconPath = $this->community->getRawOriginal('icon_url');
        $this->assertNotEquals($firstIconPath, $newIconPath);
    }

    public function test_host_can_delete_community_icon()
    {
        $this->actingAs($this->user, 'sanctum');
        
        // First upload an icon
        $file = UploadedFile::fake()->image('community_icon.png');
        $this->postJson("/api/communities/{$this->community->id}/upload-icon", [
            'icon' => $file
        ]);
        
        // Then delete it
        $response = $this->deleteJson("/api/communities/{$this->community->id}/delete-icon");
        
        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success',
                    'message' => 'Icon komunitas berhasil dihapus!',
                    'data' => [
                        'icon_url' => null,
                        'has_icon' => false
                    ]
                ]);
        
        // Check database
        $this->community->refresh();
        $this->assertNull($this->community->getRawOriginal('icon_url'));
        $this->assertFalse($this->community->has_icon);
    }

    public function test_non_host_cannot_delete_community_icon()
    {
        $otherUser = User::factory()->create();
        $this->actingAs($otherUser, 'sanctum');
        
        $response = $this->deleteJson("/api/communities/{$this->community->id}/delete-icon");
        
        $response->assertStatus(403)
                ->assertJson([
                    'status' => 'error',
                    'message' => 'Hanya host yang dapat menghapus icon komunitas.'
                ]);
    }

    public function test_delete_icon_returns_404_when_no_icon_exists()
    {
        $this->actingAs($this->user, 'sanctum');
        
        $response = $this->deleteJson("/api/communities/{$this->community->id}/delete-icon");
        
        $response->assertStatus(404)
                ->assertJson([
                    'status' => 'error',
                    'message' => 'Komunitas tidak memiliki icon.'
                ]);
    }

    public function test_get_community_icon_returns_correct_data()
    {
        // Test without icon
        $response = $this->getJson("/api/communities/{$this->community->id}/icon");
        
        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success',
                    'data' => [
                        'icon_url' => null,
                        'has_icon' => false
                    ]
                ]);
        
        // Upload icon and test with icon
        $this->actingAs($this->user, 'sanctum');
        $file = UploadedFile::fake()->image('community_icon.png');
        $this->postJson("/api/communities/{$this->community->id}/upload-icon", [
            'icon' => $file
        ]);
        
        $response = $this->getJson("/api/communities/{$this->community->id}/icon");
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'icon_url',
                        'has_icon'
                    ]
                ]);
        
        $this->assertTrue($response->json('data.has_icon'));
        $this->assertNotNull($response->json('data.icon_url'));
    }

    public function test_icon_url_accessor_returns_full_url()
    {
        $this->actingAs($this->user, 'sanctum');
        
        $file = UploadedFile::fake()->image('community_icon.png');
        $this->postJson("/api/communities/{$this->community->id}/upload-icon", [
            'icon' => $file
        ]);
        
        $this->community->refresh();
        
        // Check that accessor returns full URL
        $iconUrl = $this->community->icon_url;
        $this->assertStringContainsString(asset('storage/'), $iconUrl);
    }

    public function test_supports_multiple_image_formats()
    {
        $this->actingAs($this->user, 'sanctum');
        
        $formats = ['png', 'jpg', 'jpeg', 'webp'];
        
        foreach ($formats as $format) {
            $file = UploadedFile::fake()->image("test_icon.{$format}");
            
            $response = $this->postJson("/api/communities/{$this->community->id}/upload-icon", [
                'icon' => $file
            ]);
            
            $response->assertStatus(200);
        }
    }
} 