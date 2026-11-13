<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Tests\Feature\Media;

use Atannex\DeskEditor\Events\DeskFileUploaded;
use Atannex\DeskEditor\Media\Jobs\ProcessMedia;
use Atannex\DeskEditor\Media\Models\DeskMedia;
use Atannex\DeskEditor\Media\Processing\Ffmpeg;
use Atannex\DeskEditor\Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class UploadMediaTest extends TestCase
{
    use MediaTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Ffmpeg::flush();
    }

    public function test_image_upload_is_recorded_with_responsive_variants(): void
    {
        Event::fake([DeskFileUploaded::class]);
        $user = $this->user();

        $response = $this->actingAs($user)
            ->postJson('/desk-editor/upload', ['file' => UploadedFile::fake()->image('Sunset Beach.jpg', 2000, 1000), 'kind' => 'image'])
            ->assertCreated()
            ->assertJson(['name' => 'Sunset Beach.jpg', 'kind' => 'image', 'mime' => 'image/jpeg', 'width' => 2000, 'height' => 1000, 'status' => 'ready'])
            ->assertJsonStructure(['url', 'path', 'disk', 'name', 'size', 'mime', 'kind', 'width', 'height', 'id', 'status', 'thumb_url', 'srcset', 'poster_url']);

        $media = DeskMedia::query()->findOrFail($response->json('id'));
        $this->assertTrue($media->isOwnedBy($user));
        $this->assertSame('image', $media->kind);
        $this->assertSame([480, 960, 1600], array_column($media->variants(), 'width'));

        $base = substr($media->path, 0, -4);

        foreach ([480, 960, 1600] as $width) {
            Storage::disk('public')->assertExists("{$base}-{$width}.webp");
            $size = getimagesizefromstring((string) Storage::disk('public')->get("{$base}-{$width}.webp"));
            $this->assertSame($width, $size[0] ?? null);
            $this->assertSame('image/webp', $size['mime'] ?? null);
        }

        Storage::disk('public')->assertMissing("{$base}-2400.webp");
        $this->assertStringEndsWith('-480.webp', (string) $response->json('thumb_url'));
        $this->assertStringContainsString('-960.webp 960w', (string) $response->json('srcset'));
        $this->assertStringEndsWith(' 2000w', (string) $response->json('srcset'));

        Event::assertDispatched(DeskFileUploaded::class, fn (DeskFileUploaded $e) => $e->media?->is($media) && $e->file['id'] === $media->id);
    }

    public function test_gd_driver_and_disabled_processing(): void
    {
        config(['desk-editor.media.image_driver' => 'gd']);
        $user = $this->user();

        $id = $this->actingAs($user)
            ->postJson('/desk-editor/upload', ['file' => UploadedFile::fake()->image('a.png', 1000, 500), 'kind' => 'image'])
            ->assertCreated()->json('id');
        $this->assertSame([480, 960], array_column(DeskMedia::query()->findOrFail($id)->variants(), 'width'));

        config(['desk-editor.media.image_driver' => 'none']);
        $response = $this->actingAs($user)
            ->postJson('/desk-editor/upload', ['file' => UploadedFile::fake()->image('b.png', 1000, 500), 'kind' => 'image'])
            ->assertCreated()
            ->assertJsonPath('srcset', null);
        $this->assertSame($response->json('url'), $response->json('thumb_url'));
    }

    public function test_small_images_get_no_variants(): void
    {
        $response = $this->actingAs($this->user())
            ->postJson('/desk-editor/upload', ['file' => UploadedFile::fake()->image('icon.png', 300, 300), 'kind' => 'image'])
            ->assertCreated()
            ->assertJsonPath('srcset', null);

        $this->assertSame($response->json('url'), $response->json('thumb_url'));
    }

    public function test_attachments_are_recorded_as_files(): void
    {
        $response = $this->actingAs($this->user())
            ->postJson('/desk-editor/upload', ['file' => UploadedFile::fake()->create('report.pdf', 100, 'application/pdf')])
            ->assertCreated()
            ->assertJsonPath('kind', 'file')
            ->assertJsonPath('thumb_url', null);

        $this->assertDatabaseHas('desk_media', ['id' => $response->json('id'), 'kind' => 'file', 'mime' => 'application/pdf', 'status' => 'ready']);
    }

    public function test_poster_upload_links_to_its_video(): void
    {
        $user = $this->user();
        $video = $this->media($user, ['kind' => 'video', 'mime' => 'video/mp4', 'path' => 'desk-editor/v/clip-abc.mp4']);

        $response = $this->actingAs($user)
            ->postJson('/desk-editor/upload', ['file' => UploadedFile::fake()->image('poster.jpg', 640, 360), 'kind' => 'image', 'parent_id' => $video->id, 'purpose' => 'poster'])
            ->assertCreated();

        $poster = DeskMedia::query()->findOrFail($response->json('id'));
        $video->refresh();

        $this->assertSame($video->id, $poster->parent_id);
        $this->assertSame($poster->url, $video->posterUrl());
        $this->assertSame($poster->url, $video->toEditorArray()['thumb_url']);

        // Posters stay out of the library listing; deleting the poster reverts the video.
        $this->actingAs($user)->getJson('/desk-editor/media')->assertJsonCount(1, 'data');
        $poster->delete();
        $this->assertNull($video->refresh()->posterUrl());
    }

    public function test_poster_upload_is_validated_and_authorised(): void
    {
        $owner = $this->user();
        $video = $this->media($owner, ['kind' => 'video', 'mime' => 'video/mp4']);
        $image = $this->media($owner);
        $file = fn () => UploadedFile::fake()->image('poster.jpg', 64, 64);

        $this->actingAs($owner)->postJson('/desk-editor/upload', ['file' => $file(), 'kind' => 'image', 'parent_id' => 99999, 'purpose' => 'poster'])
            ->assertUnprocessable()->assertJsonValidationErrors('parent_id');
        $this->actingAs($owner)->postJson('/desk-editor/upload', ['file' => $file(), 'kind' => 'image', 'parent_id' => $image->id, 'purpose' => 'poster'])
            ->assertUnprocessable()->assertJsonValidationErrors('purpose');
        $this->actingAs($owner)->postJson('/desk-editor/upload', ['file' => $file(), 'kind' => 'image', 'purpose' => 'poster'])
            ->assertUnprocessable()->assertJsonValidationErrors('parent_id');

        Gate::define('manage-media', fn () => false);
        $this->actingAs($this->user())->postJson('/desk-editor/upload', ['file' => $file(), 'kind' => 'image', 'parent_id' => $video->id, 'purpose' => 'poster'])
            ->assertForbidden();
    }

    public function test_spoofed_mime_types_are_rejected(): void
    {
        $fake = UploadedFile::fake()->createWithContent('photo.jpg', '<?php system($_GET["c"]);');

        $this->actingAs($this->user())
            ->postJson('/desk-editor/upload', ['file' => new UploadedFile($fake->getRealPath(), 'photo.jpg', 'image/jpeg', null, true), 'kind' => 'image'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $this->assertDatabaseCount('desk_media', 0);
    }

    public function test_video_without_ffmpeg_is_stored_as_ready(): void
    {
        config(['desk-editor.media.ffmpeg.enabled' => false]);

        $this->actingAs($this->user())
            ->postJson('/desk-editor/upload', ['file' => UploadedFile::fake()->create('clip.mp4', 64, 'video/mp4'), 'kind' => 'video'])
            ->assertCreated()
            ->assertJsonPath('status', 'ready')
            ->assertJsonPath('poster_url', null);
    }

    public function test_unreadable_video_is_marked_failed(): void
    {
        if (! app(Ffmpeg::class)->available()) {
            $this->markTestSkipped('ffmpeg/ffprobe are not installed.');
        }

        $response = $this->actingAs($this->user())
            ->postJson('/desk-editor/upload', ['file' => UploadedFile::fake()->create('broken.mp4', 16, 'video/mp4'), 'kind' => 'video'])
            ->assertCreated()
            ->assertJsonPath('status', 'failed');

        $this->assertNotEmpty(DeskMedia::query()->findOrFail($response->json('id'))->metaValue('errors'));
    }

    public function test_video_is_probed_given_a_poster_and_transcoded(): void
    {
        $ffmpeg = app(Ffmpeg::class);

        if (! $ffmpeg->available()) {
            $this->markTestSkipped('ffmpeg/ffprobe are not installed.');
        }

        $source = sys_get_temp_dir().'/desk-media-test-'.uniqid().'.webm';
        $process = new Process([
            (string) $ffmpeg->binary('ffmpeg'), '-nostdin', '-y', '-v', 'error',
            '-f', 'lavfi', '-i', 'testsrc=duration=2:size=320x240:rate=15',
            '-f', 'lavfi', '-i', 'sine=frequency=440:duration=2',
            '-c:v', 'libvpx', '-b:v', '200k', '-c:a', 'libvorbis', '-shortest', $source,
        ]);
        $process->run();

        if (! $process->isSuccessful() || ! is_file($source)) {
            $this->markTestSkipped('ffmpeg cannot encode a WebM test clip here: '.$process->getErrorOutput());
        }

        try {
            $response = $this->actingAs($this->user())
                ->postJson('/desk-editor/upload', ['file' => new UploadedFile($source, 'Demo Clip.webm', null, null, true), 'kind' => 'video'])
                ->assertCreated()
                ->assertJson(['kind' => 'video', 'mime' => 'video/webm', 'status' => 'ready', 'width' => 320, 'height' => 240]);
        } finally {
            @unlink($source);
        }

        $media = DeskMedia::query()->findOrFail($response->json('id'));
        $this->assertEqualsWithDelta(2.0, (float) $media->duration, 0.2);
        $this->assertNotNull($media->posterUrl());
        $this->assertNotNull($media->transcodedUrl());

        $disk = Storage::disk('public');
        $disk->assertExists((string) $media->metaValue('poster_path'));
        $disk->assertExists((string) $media->metaValue('transcoded_path'));
        $this->assertSame('image/jpeg', getimagesizefromstring((string) $disk->get((string) $media->metaValue('poster_path')))['mime'] ?? null);
        $this->assertSame($media->posterUrl(), $response->json('poster_url'));

        $probe = $ffmpeg->probe($disk->path((string) $media->metaValue('transcoded_path')));
        $this->assertSame('h264', $probe['streams'][0]['codec_name'] ?? null);

        // Deleting removes the original and every derivative.
        $paths = $media->storedPaths();
        $this->assertCount(3, $paths);
        $media->delete();

        foreach ($paths as $path) {
            $disk->assertMissing($path);
        }
    }

    public function test_video_jobs_respect_the_configured_queue(): void
    {
        if (! app(Ffmpeg::class)->available()) {
            $this->markTestSkipped('ffmpeg/ffprobe are not installed.');
        }

        Queue::fake();
        config(['desk-editor.media.ffmpeg.queue' => 'media']);

        $this->actingAs($this->user())
            ->postJson('/desk-editor/upload', ['file' => UploadedFile::fake()->create('clip.mp4', 16, 'video/mp4'), 'kind' => 'video'])
            ->assertCreated()
            ->assertJsonPath('status', 'processing');

        Queue::assertPushedOn('media', ProcessMedia::class);
    }
}
