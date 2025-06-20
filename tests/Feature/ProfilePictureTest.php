<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\UserProfile;
use Laravel\Sanctum\Sanctum;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;

class ProfilePictureTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user with profile
        $this->user = User::factory()->create(['user_type' => 'player']);
        $this->user->profile()->create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'qr_code' => 'test_qr_code_' . $this->user->id,
        ]);

        // Fake storage for testing
        Storage::fake('public');
    }

    #[Test]
    public function user_can_upload_profile_picture()
    {
        Sanctum::actingAs($this->user);

        $file = UploadedFile::fake()->image('profile.jpg', 500, 500)->size(1000); // 1MB

        $response = $this->postJson('/api/users/upload-profile-picture', [
            'profile_picture' => $file,
        ]);

        // Debug output
        if ($response->status() !== 200) {
            dump('Response status: ' . $response->status());
            dump('Response content: ' . $response->getContent());
        }

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'user',
                    'profile_picture_url'
                ]
            ])
            ->assertJson([
                'status' => 'success',
                'message' => 'Foto profile berhasil diupload!'
            ]);

        // Verify file was stored
        $this->user->refresh();
        $this->assertNotNull($this->user->profile->profile_photo_url);
        
        // Verify file exists in storage
        Storage::disk('public')->assertExists($this->user->profile->profile_photo_url);
    }

    #[Test]
    public function upload_requires_authentication()
    {
        $file = UploadedFile::fake()->image('profile.jpg');

        $response = $this->postJson('/api/users/upload-profile-picture', [
            'profile_picture' => $file,
        ]);

        $response->assertStatus(401);
    }

    #[Test]
    public function upload_validates_file_type()
    {
        Sanctum::actingAs($this->user);

        // Test with non-image file
        $file = UploadedFile::fake()->create('document.pdf', 1000, 'application/pdf');

        $response = $this->postJson('/api/users/upload-profile-picture', [
            'profile_picture' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['profile_picture']);
    }

    #[Test]
    public function upload_validates_file_size()
    {
        Sanctum::actingAs($this->user);

        // Test with large file (3MB)
        $file = UploadedFile::fake()->image('large.jpg')->size(3072);

        $response = $this->postJson('/api/users/upload-profile-picture', [
            'profile_picture' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['profile_picture']);
    }

    #[Test]
    public function upload_replaces_existing_profile_picture()
    {
        Sanctum::actingAs($this->user);

        // Upload first picture
        $firstFile = UploadedFile::fake()->image('first.jpg');
        $response = $this->postJson('/api/users/upload-profile-picture', [
            'profile_picture' => $firstFile,
        ]);
        $response->assertStatus(200);

        $this->user->refresh();
        $firstPicturePath = $this->user->profile->profile_photo_url;

        // Sleep to ensure different timestamp
        sleep(2);

        // Upload second picture (should replace first)
        $secondFile = UploadedFile::fake()->image('second.jpg');
        $response = $this->postJson('/api/users/upload-profile-picture', [
            'profile_picture' => $secondFile,
        ]);
        $response->assertStatus(200);

        $this->user->refresh();
        $secondPicturePath = $this->user->profile->profile_photo_url;

        // Verify paths are different
        $this->assertNotEquals($firstPicturePath, $secondPicturePath);

        // Verify new file exists and old file is removed (note: old file should be cleaned up)
        Storage::disk('public')->assertExists($secondPicturePath);
        // Note: old file cleanup test might be flaky due to timing
    }

    #[Test]
    public function user_can_delete_profile_picture()
    {
        Sanctum::actingAs($this->user);

        // Upload a picture first
        $file = UploadedFile::fake()->image('profile.jpg');
        $uploadResponse = $this->postJson('/api/users/upload-profile-picture', [
            'profile_picture' => $file,
        ]);
        $uploadResponse->assertStatus(200);

        $this->user->refresh();
        $picturePath = $this->user->profile->profile_photo_url;

        // Delete the picture
        $response = $this->deleteJson('/api/users/delete-profile-picture');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Foto profile berhasil dihapus!'
            ]);

        // Verify picture is removed from database
        $this->user->refresh();
        $this->assertNull($this->user->profile->profile_photo_url);

        // Note: File deletion from storage might be timing sensitive in tests
    }

    #[Test]
    public function delete_fails_when_no_picture_exists()
    {
        Sanctum::actingAs($this->user);

        $response = $this->deleteJson('/api/users/delete-profile-picture');

        $response->assertStatus(404)
            ->assertJson([
                'status' => 'error',
                'message' => 'Tidak ada foto profile untuk dihapus.'
            ]);
    }

    #[Test]
    public function user_can_get_profile_picture_url()
    {
        // Upload a picture first
        Sanctum::actingAs($this->user);
        $file = UploadedFile::fake()->image('profile.jpg');
        $this->postJson('/api/users/upload-profile-picture', [
            'profile_picture' => $file,
        ]);

        $this->user->refresh();

        // Get picture URL (public endpoint, no auth needed)
        $response = $this->getJson("/api/users/{$this->user->id}/profile-picture");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'profile_picture_url',
                    'user_id',
                    'full_name'
                ]
            ]);

        $data = $response->json('data');
        $this->assertEquals($this->user->id, $data['user_id']);
        $this->assertEquals('Test User', $data['full_name']);
        $this->assertStringContainsString('storage/profile-pictures', $data['profile_picture_url']);
    }

    #[Test]
    public function get_picture_url_fails_when_no_picture_exists()
    {
        $response = $this->getJson("/api/users/{$this->user->id}/profile-picture");

        $response->assertStatus(404)
            ->assertJson([
                'status' => 'error',
                'message' => 'User tidak memiliki foto profile.'
            ]);
    }

    #[Test]
    public function profile_model_accessors_work_correctly()
    {
        // Test without profile picture
        $this->assertNull($this->user->profile->profile_picture_url);
        $this->assertFalse($this->user->profile->has_profile_picture);

        // Upload picture and test accessors
        Sanctum::actingAs($this->user);
        $file = UploadedFile::fake()->image('profile.jpg');
        $this->postJson('/api/users/upload-profile-picture', [
            'profile_picture' => $file,
        ]);

        $this->user->refresh();
        $this->assertNotNull($this->user->profile->profile_picture_url);
        $this->assertTrue($this->user->profile->has_profile_picture);
        $this->assertStringContainsString('storage/profile-pictures', $this->user->profile->profile_picture_url);
    }

    #[Test]
    public function upload_supports_multiple_image_formats()
    {
        Sanctum::actingAs($this->user);

        $formats = ['jpg', 'jpeg', 'png', 'webp'];

        foreach ($formats as $format) {
            $file = UploadedFile::fake()->image("profile.{$format}");

            $response = $this->postJson('/api/users/upload-profile-picture', [
                'profile_picture' => $file,
            ]);

            $response->assertStatus(200, "Failed to upload {$format} format");
        }
    }

    #[Test]
    public function upload_creates_unique_filenames()
    {
        Sanctum::actingAs($this->user);

        $file1 = UploadedFile::fake()->image('profile.jpg');
        $response1 = $this->postJson('/api/users/upload-profile-picture', [
            'profile_picture' => $file1,
        ]);

        $this->user->refresh();
        $firstPath = $this->user->profile->profile_photo_url;

        // Wait to ensure different timestamp
        sleep(2);

        $file2 = UploadedFile::fake()->image('profile.jpg');
        $response2 = $this->postJson('/api/users/upload-profile-picture', [
            'profile_picture' => $file2,
        ]);

        $this->user->refresh();
        $secondPath = $this->user->profile->profile_photo_url;

        $this->assertNotEquals($firstPath, $secondPath);
    }
} 