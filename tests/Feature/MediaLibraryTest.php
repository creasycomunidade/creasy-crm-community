<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaLibraryTest extends TestCase
{
    use RefreshDatabase;

    private function clientUser(): User
    {
        return User::factory()->create([
            'role'              => 'client',
            'email_verified_at' => now(),
        ]);
    }

    public function test_user_can_view_media_library(): void
    {
        $user = $this->clientUser();
        $this->actingAs($user)
            ->get(route('client.media.index'))
            ->assertOk();
    }

    public function test_user_can_upload_file(): void
    {
        Storage::fake('public');
        $user = $this->clientUser();

        $file = UploadedFile::fake()->image('avatar.jpg', 100, 100);

        $this->actingAs($user)
            ->post(route('client.media.store'), ['file' => $file])
            ->assertRedirect();

        $this->assertDatabaseHas('media', [
            'mediable_type' => User::class,
            'mediable_id'   => $user->id,
        ]);
    }

    public function test_user_can_list_only_their_images_and_videos_for_picker(): void
    {
        Storage::fake('public');
        $user = $this->clientUser();
        $otherUser = $this->clientUser();

        Media::factory()->create([
            'mediable_type' => User::class,
            'mediable_id' => $user->id,
            'disk' => 'public',
            'path' => 'media/photo.jpg',
            'filename' => 'campanha.jpg',
            'mime_type' => 'image/jpeg',
        ]);
        Media::factory()->create([
            'mediable_type' => User::class,
            'mediable_id' => $user->id,
            'disk' => 'public',
            'path' => 'media/document.pdf',
            'filename' => 'documento.pdf',
            'mime_type' => 'application/pdf',
        ]);
        Media::factory()->create([
            'mediable_type' => User::class,
            'mediable_id' => $otherUser->id,
            'disk' => 'public',
            'path' => 'media/private.mp4',
            'filename' => 'privado.mp4',
            'mime_type' => 'video/mp4',
        ]);

        $this->actingAs($user)
            ->getJson(route('client.media.library'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.filename', 'campanha.jpg');
    }

    public function test_media_picker_can_filter_videos(): void
    {
        Storage::fake('public');
        $user = $this->clientUser();

        foreach ([['foto.jpg', 'image/jpeg'], ['reel.mp4', 'video/mp4']] as [$filename, $mime]) {
            Media::factory()->create([
                'mediable_type' => User::class,
                'mediable_id' => $user->id,
                'disk' => 'public',
                'path' => 'media/'.$filename,
                'filename' => $filename,
                'mime_type' => $mime,
            ]);
        }

        $this->actingAs($user)
            ->getJson(route('client.media.library', ['type' => 'video']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.filename', 'reel.mp4');
    }

    public function test_user_can_delete_own_media(): void
    {
        Storage::fake('public');
        $user  = $this->clientUser();
        $media = Media::factory()->create([
            'mediable_type' => User::class,
            'mediable_id'   => $user->id,
            'disk'          => 'public',
            'path'          => 'media/test.jpg',
        ]);

        $this->actingAs($user)
            ->deleteJson(route('client.media.destroy', $media))
            ->assertOk();

        $this->assertDatabaseMissing('media', ['id' => $media->id]);
    }

    public function test_guest_cannot_access_media_library(): void
    {
        $this->get(route('client.media.index'))
            ->assertRedirect(route('login'));
    }
}
