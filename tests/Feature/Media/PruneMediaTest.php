<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Tests\Feature\Media;

use Atannex\DeskEditor\Media\Models\DeskMedia;
use Atannex\DeskEditor\Tests\Fixtures\User;
use Atannex\DeskEditor\Tests\TestCase;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PruneMediaTest extends TestCase
{
    use MediaTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function old(DeskMedia $media, int $hours = 48): DeskMedia
    {
        DB::table('desk_media')->where('id', $media->id)->update(['created_at' => now()->subHours($hours)]);

        return $media->refresh();
    }

    public function test_dry_run_lists_without_deleting_and_real_run_deletes_unreferenced_media(): void
    {
        $orphan = $this->old($this->media(null, ['path' => 'desk-editor/p/orphan.png', 'name' => 'orphan.png']));
        $inBody = $this->old($this->media(null, ['path' => 'desk-editor/p/body.png']));
        $asCover = $this->old($this->media(null, ['path' => 'desk-editor/p/cover.png']));
        $inDraft = $this->old($this->media(null, ['path' => 'desk-editor/p/draft.png']));
        $inRevision = $this->old($this->media(null, ['path' => 'desk-editor/p/rev.png']));
        $fresh = $this->media(null, ['path' => 'desk-editor/p/fresh.png']);
        $video = $this->old($this->media(null, ['path' => 'desk-editor/p/video.mp4', 'kind' => 'video']));
        $poster = $this->old($this->media(null, ['path' => 'desk-editor/p/video-poster.jpg', 'parent_id' => $video->id]));

        $this->article('<img src="/storage/desk-editor/p/body-960.webp"><video src="'.$video->url.'"></video>', $asCover->url);
        $articleId = $this->article();
        DB::table('desk_drafts')->insert(['key' => 'k', 'content' => '<img src="'.$inDraft->url.'">', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('desk_article_revisions')->insert(['desk_article_id' => $articleId, 'title' => 'Old', 'body' => '<img src="'.$inRevision->url.'">', 'status' => 'draft', 'created_at' => now()]);

        $this->artisan('desk-editor:prune-media', ['--dry-run' => true])
            ->expectsOutputToContain('orphan.png')
            ->expectsOutputToContain('Would delete 1')
            ->assertSuccessful();

        $this->assertDatabaseHas('desk_media', ['id' => $orphan->id]);
        Storage::disk('public')->assertExists('desk-editor/p/orphan.png');

        $this->artisan('desk-editor:prune-media')->expectsOutputToContain('Deleted 1')->assertSuccessful();

        $this->assertDatabaseMissing('desk_media', ['id' => $orphan->id]);
        Storage::disk('public')->assertMissing('desk-editor/p/orphan.png');

        foreach ([$inBody, $asCover, $inDraft, $inRevision, $fresh, $video, $poster] as $kept) {
            $this->assertDatabaseHas('desk_media', ['id' => $kept->id]);
        }
    }

    public function test_children_follow_their_parent_and_hours_option_applies(): void
    {
        $video = $this->old($this->media(null, ['path' => 'desk-editor/p/clip.mp4', 'kind' => 'video']), 5);
        $poster = $this->old($this->media(null, ['path' => 'desk-editor/p/clip-cover.jpg', 'parent_id' => $video->id]), 5);

        $this->artisan('desk-editor:prune-media')->expectsOutputToContain('Nothing to prune')->assertSuccessful();

        // Referencing only the poster keeps the whole family.
        $id = $this->article('<img src="'.$poster->url.'">');
        $this->artisan('desk-editor:prune-media', ['--hours' => 1])->expectsOutputToContain('Nothing to prune')->assertSuccessful();

        DB::table('desk_articles')->where('id', $id)->delete();
        $this->artisan('desk-editor:prune-media', ['--hours' => 1])->expectsOutputToContain('Deleted 1')->assertSuccessful();
        $this->assertDatabaseCount('desk_media', 0);
        Storage::disk('public')->assertMissing(['desk-editor/p/clip.mp4', 'desk-editor/p/clip-cover.jpg']);
    }

    public function test_configured_reference_columns_are_checked(): void
    {
        $media = $this->old($this->media(null, ['path' => 'desk-editor/p/avatar.png']));
        User::query()->create(['name' => $media->url, 'email' => 'avatar@example.com']);
        config(['desk-editor.media.prune.references' => [User::class => ['name']]]);

        $this->artisan('desk-editor:prune-media')->expectsOutputToContain('Nothing to prune')->assertSuccessful();
        $this->assertDatabaseHas('desk_media', ['id' => $media->id]);
    }

    public function test_command_is_scheduled_daily(): void
    {
        $events = collect(app(Schedule::class)->events())->filter(fn ($e) => str_contains((string) $e->command, 'desk-editor:prune-media'));

        $this->assertCount(1, $events);
        $this->assertSame('0 0 * * *', $events->first()->expression);
    }
}
