<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use App\Models\User;
use App\Models\Event;
use App\Models\Sport;
use App\Models\Community;

class EventThumbnailTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;
    protected $event;
    protected $sport;
    protected $community;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test user
        $this->user = User::factory()->create();
        
        // Create sport
        $this->sport = Sport::factory()->create();
        
        // Create community
        $this->community = Community::factory()->create([
            'host_user_id' => $this->user->id,
            'sport_id' => $this->sport->id,
        ]);
        
        // Create event with user as host
        $this->event = Event::factory()->create([
            'host_id' => $this->user->id,
            'sport_id' => $this->sport->id,
            'community_id' => $this->community->id,
        ]);

        // Fake storage for testing
        Storage::fake('public');
    }

    public function test_host_can_upload_event_thumbnail()
    {
        $this->actingAs($this->user, 'sanctum');
        
        $file = UploadedFile::fake()->image('event_thumbnail.jpg', 1280, 720);
        
        $response = $this->postJson("/api/events/{$this->event->id}/upload-thumbnail", [
            'thumbnail' => $file
        ]);
        
        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success',
                    'message' => 'Thumbnail event berhasil diupload!',
                    'data' => [
                        'has_thumbnail' => true
                    ]
                ]);
        
        // Check if file was stored
        Storage::disk('public')->assertExists('event-thumbnails/event_' . $this->event->id . '_' . now()->timestamp . '.jpg');
        
        // Check database
        $this->event->refresh();
        $this->assertNotNull($this->event->getRawOriginal('thumbnail_url'));
        $this->assertTrue($this->event->has_thumbnail);
    }

    public function test_non_host_cannot_upload_event_thumbnail()
    {
        $otherUser = User::factory()->create();
        $this->actingAs($otherUser, 'sanctum');
        
        $file = UploadedFile::fake()->image('event_thumbnail.jpg');
        
        $response = $this->postJson("/api/events/{$this->event->id}/upload-thumbnail", [
            'thumbnail' => $file
        ]);
        
        $response->assertStatus(403)
                ->assertJson([
                    'status' => 'error',
                    'message' => 'Hanya host yang dapat mengupload thumbnail event.'
                ]);
    }

    public function test_upload_thumbnail_validates_file_type()
    {
        $this->actingAs($this->user, 'sanctum');
        
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');
        
        $response = $this->postJson("/api/events/{$this->event->id}/upload-thumbnail", [
            'thumbnail' => $file
        ]);
        
        $response->assertStatus(422)
                ->assertJsonValidationErrors('thumbnail');
    }

    public function test_upload_thumbnail_validates_file_size()
    {
        $this->actingAs($this->user, 'sanctum');
        
        $file = UploadedFile::fake()->image('large_thumbnail.jpg')->size(6000); // 6MB
        
        $response = $this->postJson("/api/events/{$this->event->id}/upload-thumbnail", [
            'thumbnail' => $file
        ]);
        
        $response->assertStatus(422)
                ->assertJsonValidationErrors('thumbnail');
    }

    public function test_upload_thumbnail_replaces_existing_thumbnail()
    {
        $this->actingAs($this->user, 'sanctum');
        
        // Upload first thumbnail
        $firstFile = UploadedFile::fake()->image('first_thumbnail.jpg');
        
        $response = $this->postJson("/api/events/{$this->event->id}/upload-thumbnail", [
            'thumbnail' => $firstFile
        ]);
        
        $response->assertStatus(200);
        $firstThumbnailPath = $this->event->fresh()->getRawOriginal('thumbnail_url');
        
        // Sleep to ensure different timestamp
        sleep(1);
        
        // Upload second thumbnail
        $secondFile = UploadedFile::fake()->image('second_thumbnail.jpg');
        
        $response = $this->postJson("/api/events/{$this->event->id}/upload-thumbnail", [
            'thumbnail' => $secondFile
        ]);
        
        $response->assertStatus(200);
        
        // Check that new thumbnail path is different
        $this->event->refresh();
        $newThumbnailPath = $this->event->getRawOriginal('thumbnail_url');
        $this->assertNotEquals($firstThumbnailPath, $newThumbnailPath);
    }

    public function test_host_can_delete_event_thumbnail()
    {
        $this->actingAs($this->user, 'sanctum');
        
        // First upload a thumbnail
        $file = UploadedFile::fake()->image('event_thumbnail.jpg');
        $this->postJson("/api/events/{$this->event->id}/upload-thumbnail", [
            'thumbnail' => $file
        ]);
        
        // Then delete it
        $response = $this->deleteJson("/api/events/{$this->event->id}/delete-thumbnail");
        
        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success',
                    'message' => 'Thumbnail event berhasil dihapus!',
                    'data' => [
                        'thumbnail_url' => null,
                        'has_thumbnail' => false
                    ]
                ]);
        
        // Check database
        $this->event->refresh();
        $this->assertNull($this->event->getRawOriginal('thumbnail_url'));
        $this->assertFalse($this->event->has_thumbnail);
    }

    public function test_non_host_cannot_delete_event_thumbnail()
    {
        $otherUser = User::factory()->create();
        $this->actingAs($otherUser, 'sanctum');
        
        $response = $this->deleteJson("/api/events/{$this->event->id}/delete-thumbnail");
        
        $response->assertStatus(403)
                ->assertJson([
                    'status' => 'error',
                    'message' => 'Hanya host yang dapat menghapus thumbnail event.'
                ]);
    }

    public function test_delete_thumbnail_returns_404_when_no_thumbnail_exists()
    {
        $this->actingAs($this->user, 'sanctum');
        
        $response = $this->deleteJson("/api/events/{$this->event->id}/delete-thumbnail");
        
        $response->assertStatus(404)
                ->assertJson([
                    'status' => 'error',
                    'message' => 'Event tidak memiliki thumbnail.'
                ]);
    }

    public function test_get_event_thumbnail_returns_correct_data()
    {
        // Test without thumbnail
        $response = $this->getJson("/api/events/{$this->event->id}/thumbnail");
        
        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success',
                    'data' => [
                        'thumbnail_url' => null,
                        'has_thumbnail' => false
                    ]
                ]);
        
        // Upload thumbnail and test with thumbnail
        $this->actingAs($this->user, 'sanctum');
        $file = UploadedFile::fake()->image('event_thumbnail.jpg');
        $this->postJson("/api/events/{$this->event->id}/upload-thumbnail", [
            'thumbnail' => $file
        ]);
        
        $response = $this->getJson("/api/events/{$this->event->id}/thumbnail");
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'thumbnail_url',
                        'has_thumbnail'
                    ]
                ]);
        
        $this->assertTrue($response->json('data.has_thumbnail'));
        $this->assertNotNull($response->json('data.thumbnail_url'));
    }

    public function test_thumbnail_url_accessor_returns_full_url()
    {
        $this->actingAs($this->user, 'sanctum');
        
        $file = UploadedFile::fake()->image('event_thumbnail.jpg');
        $this->postJson("/api/events/{$this->event->id}/upload-thumbnail", [
            'thumbnail' => $file
        ]);
        
        $this->event->refresh();
        
        // Check that accessor returns full URL
        $thumbnailUrl = $this->event->thumbnail_url;
        $this->assertStringContainsString(asset('storage/'), $thumbnailUrl);
    }

    public function test_supports_multiple_image_formats()
    {
        $this->actingAs($this->user, 'sanctum');
        
        $formats = ['png', 'jpg', 'jpeg', 'webp'];
        
        foreach ($formats as $format) {
            $file = UploadedFile::fake()->image("test_thumbnail.{$format}");
            
            $response = $this->postJson("/api/events/{$this->event->id}/upload-thumbnail", [
                'thumbnail' => $file
            ]);
            
            $response->assertStatus(200);
        }
    }

    public function test_thumbnail_upload_handles_large_valid_files()
    {
        $this->actingAs($this->user, 'sanctum');
        
        // Create a file that's exactly at the limit (5MB)
        $file = UploadedFile::fake()->image('large_thumbnail.jpg')->size(5120);
        
        $response = $this->postJson("/api/events/{$this->event->id}/upload-thumbnail", [
            'thumbnail' => $file
        ]);
        
        $response->assertStatus(200);
    }

    public function test_unique_filename_generation()
    {
        $this->actingAs($this->user, 'sanctum');
        
        $file1 = UploadedFile::fake()->image('thumbnail.jpg');
        $file2 = UploadedFile::fake()->image('thumbnail.jpg');
        
        // Upload first thumbnail
        $response1 = $this->postJson("/api/events/{$this->event->id}/upload-thumbnail", [
            'thumbnail' => $file1
        ]);
        $response1->assertStatus(200);
        $firstPath = $this->event->fresh()->getRawOriginal('thumbnail_url');
        
        // Sleep to ensure different timestamp
        sleep(1);
        
        // Upload second thumbnail (should replace)
        $response2 = $this->postJson("/api/events/{$this->event->id}/upload-thumbnail", [
            'thumbnail' => $file2
        ]);
        $response2->assertStatus(200);
        $secondPath = $this->event->fresh()->getRawOriginal('thumbnail_url');
        
        // Paths should be different due to timestamp
        $this->assertNotEquals($firstPath, $secondPath);
    }
}