<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Tests\Feature\Media;

use Atannex\DeskEditor\Media\Models\DeskMedia;
use Atannex\DeskEditor\Tests\TestCase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class MediaLibraryTest extends TestCase
{
    use MediaTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_library_requires_authentication(): void
    {
        $this->getJson('/desk-editor/media')->assertUnauthorized();
    }

    public function test_listing_is_paginated_newest_first_and_filterable(): void
    {
        $user = $this->user();
        $first = $this->media($user, ['name' => 'beach.jpg']);
        $this->media($user, ['name' => 'intro.mp4', 'kind' => 'video', 'mime' => 'video/mp4', 'duration' => 12.5]);
        $last = $this->media($user, ['name' => 'team.png', 'alt' => 'The whole team on a sunny beach']);

        $this->actingAs($user)->getJson('/desk-editor/media')
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 1)
            ->assertJsonPath('meta.per_page', 40)
            ->assertJsonPath('data.0.id', $last->id)
            ->assertJsonPath('data.2.id', $first->id)
            ->assertJsonStructure(['data' => [['id', 'url', 'name', 'mime', 'kind', 'size', 'size_human', 'width', 'height', 'duration', 'alt', 'status', 'thumb_url', 'srcset', 'poster_url', 'transcoded_url', 'created_at', 'created_at_human']]]);

        $this->actingAs($user)->getJson('/desk-editor/media?kind=video')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.duration', 12.5)
            ->assertJsonPath('data.0.size_human', '1 KB');

        // Search covers name and alt text.
        $this->actingAs($user)->getJson('/desk-editor/media?q=beach')->assertJsonCount(2, 'data');
        $this->actingAs($user)->getJson('/desk-editor/media?q=sunny')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $last->id);
        $this->actingAs($user)->getJson('/desk-editor/media?q=%25')->assertJsonCount(0, 'data');
        $this->actingAs($user)->getJson('/desk-editor/media?kind=bogus')->assertUnprocessable();
        $this->actingAs($user)->getJson('/desk-editor/media?page=2&per_page=2')->assertJsonPath('meta.current_page', 2)->assertJsonCount(1, 'data');
    }

    public function test_non_managers_only_see_their_own_media(): void
    {
        $alice = $this->user();
        $bob = $this->user();
        $this->media($alice);
        $this->media($bob);

        $this->actingAs($alice)->getJson('/desk-editor/media')->assertJsonPath('meta.total', 2);

        Gate::define('manage-media', fn () => false);
        $this->actingAs($alice)->getJson('/desk-editor/media')->assertJsonPath('meta.total', 1);
    }

    public function test_show_lists_where_media_is_used(): void
    {
        $user = $this->user();
        $media = $this->media($user, ['path' => 'desk-editor/2026/01/hero-abc12345.jpg']);
        $other = $this->media($user, ['path' => 'desk-editor/2026/01/other_x%-1.jpg']);
        $inBody = $this->article('<p><img src="/storage/desk-editor/2026/01/hero-abc12345-960.webp"></p>');
        $asCover = $this->article('', 'https://cdn.example.com/desk-editor/2026/01/hero-abc12345.jpg');
        $this->article('<p>unrelated</p>');

        $usage = $this->actingAs($user)->getJson("/desk-editor/media/{$media->id}")
            ->assertOk()
            ->assertJsonPath('id', $media->id)
            ->json('usage');

        $this->assertEqualsCanonicalizing([$inBody, $asCover], array_column($usage, 'id'));
        $this->assertSame(['article', 'article'], array_column($usage, 'type'));
        $this->assertSame('Hello world', $usage[0]['title']);

        $this->actingAs($user)->getJson("/desk-editor/media/{$other->id}")->assertJsonPath('usage', []);
        $this->actingAs($user)->getJson('/desk-editor/media/999999')->assertNotFound();
    }

    public function test_alt_text_and_name_can_be_updated(): void
    {
        $user = $this->user();
        $media = $this->media($user);

        $this->actingAs($user)->patchJson("/desk-editor/media/{$media->id}", ['alt' => '  A red bicycle  ', 'name' => 'bike.png'])
            ->assertOk()
            ->assertJsonPath('alt', 'A red bicycle')
            ->assertJsonPath('name', 'bike.png');

        $this->actingAs($user)->patchJson("/desk-editor/media/{$media->id}", ['alt' => null])->assertOk()->assertJsonPath('alt', null);
        $this->actingAs($user)->patchJson("/desk-editor/media/{$media->id}", ['name' => ''])->assertUnprocessable();
        $this->assertSame('bike.png', $media->refresh()->name);
    }

    public function test_delete_removes_the_record_and_all_files(): void
    {
        $user = $this->user();
        $disk = Storage::disk('public');
        $media = $this->media($user, ['path' => 'desk-editor/a/pic.png']);
        $disk->put('desk-editor/a/pic-480.webp', 'v');
        $disk->put('desk-editor/a/unrelated.png', 'keep');
        $media->setMeta(['variants' => [['width' => 480, 'height' => 320, 'path' => 'desk-editor/a/pic-480.webp', 'url' => $disk->url('desk-editor/a/pic-480.webp')]]])->save();
        $poster = $this->media($user, ['parent_id' => $media->id, 'path' => 'desk-editor/a/poster.jpg']);

        $this->actingAs($user)->deleteJson("/desk-editor/media/{$media->id}")->assertNoContent();

        $this->assertDatabaseMissing('desk_media', ['id' => $media->id]);
        $this->assertDatabaseMissing('desk_media', ['id' => $poster->id]);
        $disk->assertMissing(['desk-editor/a/pic.png', 'desk-editor/a/pic-480.webp', 'desk-editor/a/poster.jpg']);
        $disk->assertExists('desk-editor/a/unrelated.png');
    }

    public function test_other_users_cannot_change_media_without_the_gate(): void
    {
        $owner = $this->user();
        $intruder = $this->user();
        $media = $this->media($owner);

        Gate::define('manage-media', fn () => false);

        $this->actingAs($intruder)->deleteJson("/desk-editor/media/{$media->id}")->assertForbidden();
        $this->actingAs($intruder)->patchJson("/desk-editor/media/{$media->id}", ['alt' => 'x'])->assertForbidden();
        $this->actingAs($intruder)->getJson("/desk-editor/media/{$media->id}")->assertForbidden();
        $this->assertDatabaseHas('desk_media', ['id' => $media->id, 'alt' => null]);

        // The owner keeps full control; managers can act on anyone's media.
        $this->actingAs($owner)->patchJson("/desk-editor/media/{$media->id}", ['alt' => 'mine'])->assertOk();
        Gate::define('manage-media', fn ($user) => $user->is($intruder));
        $this->actingAs($intruder)->deleteJson("/desk-editor/media/{$media->id}")->assertNoContent();
    }

    public function test_library_can_be_disabled(): void
    {
        config(['desk-editor.media.library' => false]);

        $this->actingAs($this->user())->getJson('/desk-editor/media')->assertNotFound();
    }

    public function test_admin_page_renders(): void
    {
        $user = $this->user();
        $this->media($user, ['name' => 'landscape.png']);
        $this->media($user, ['name' => 'talk.mp4', 'kind' => 'video', 'mime' => 'video/mp4', 'duration' => 75.0, 'status' => DeskMedia::STATUS_PROCESSING]);
        $this->media($user, ['name' => 'notes.pdf', 'kind' => 'file', 'mime' => 'application/pdf', 'path' => 'desk-editor/n/notes.pdf']);

        $this->actingAs($user)->get('/desk/media')
            ->assertOk()
            ->assertSee('landscape.png')
            ->assertSee('1:15')
            ->assertSee('data-dropzone', false)
            ->assertSee('data-upload-input', false);

        $this->actingAs($user)->get('/desk/media?kind=video')->assertOk()->assertSee('talk.mp4')->assertDontSee('landscape.png');
        $this->actingAs($user)->get('/desk/media?q=nothing-matches')->assertOk()->assertSee(__('desk-editor::media.empty_filtered'));
    }
}
