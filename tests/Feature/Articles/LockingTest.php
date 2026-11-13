<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Tests\Feature\Articles;

use Atannex\DeskEditor\Articles\Support\EditLock;
use Atannex\DeskEditor\Enums\ArticleStatus;
use Atannex\DeskEditor\Models\DeskArticle;
use Atannex\DeskEditor\Tests\Fixtures\User;
use Atannex\DeskEditor\Tests\TestCase;

class LockingTest extends TestCase
{
    public function test_opening_the_editor_takes_the_lock_and_others_see_a_banner(): void
    {
        $ada = $this->user();
        $grace = User::query()->create(['name' => 'Grace Hopper', 'email' => 'grace@example.com']);
        $article = DeskArticle::create(['title' => 'Shared', 'status' => ArticleStatus::Draft]);

        $this->actingAs($ada)->get(route('desk-editor.articles.edit', $article))->assertOk();
        $this->assertSame((string) $ada->id, app(EditLock::class)->holder($article)['user_id']);

        $this->actingAs($grace)->get(route('desk-editor.articles.edit', $article))->assertOk()
            ->assertSee('Ada Lovelace is editing this article')
            ->assertSee(__('desk-editor::articles.lock.take_over'));
    }

    public function test_heartbeat_reports_holder_and_take_over(): void
    {
        $ada = $this->user();
        $grace = User::query()->create(['name' => 'Grace Hopper', 'email' => 'grace@example.com']);
        $article = DeskArticle::create(['title' => 'Shared', 'status' => ArticleStatus::Draft]);

        $this->actingAs($ada)->postJson(route('desk-editor.articles.lock', $article))
            ->assertOk()->assertJsonPath('acquired', true)->assertJsonPath('holder', null);

        $this->actingAs($grace)->postJson(route('desk-editor.articles.lock', $article))
            ->assertOk()->assertJsonPath('acquired', false)->assertJsonPath('holder.name', 'Ada Lovelace');

        $this->actingAs($grace)->postJson(route('desk-editor.articles.lock', $article), ['take_over' => 1])
            ->assertOk()->assertJsonPath('acquired', true);
        $this->assertSame('lock_taken', $article->activities()->value('type'));

        // Ada's next heartbeat learns she lost the lock.
        $this->actingAs($ada)->postJson(route('desk-editor.articles.lock', $article))
            ->assertJsonPath('acquired', false)->assertJsonPath('holder.name', 'Grace Hopper');

        $this->actingAs($grace)->post(route('desk-editor.articles.lock.release', $article))->assertNoContent();
        $this->assertNull(app(EditLock::class)->holder($article));
    }

    public function test_lock_expires(): void
    {
        $ada = $this->user();
        $article = DeskArticle::create(['title' => 'Shared', 'status' => ArticleStatus::Draft]);
        app(EditLock::class)->acquire($article, $ada);

        $this->travel(config('desk-editor.articles.lock_seconds') + 5)->seconds();

        $this->assertNull(app(EditLock::class)->holder($article));
    }

    public function test_stale_json_save_returns_409(): void
    {
        $this->actingAs($this->user());
        $article = DeskArticle::create(['title' => 'Draft', 'status' => ArticleStatus::Draft]);
        $loaded = (string) $article->updated_at->getTimestamp();

        $this->travel(5)->seconds();
        $article->update(['title' => 'Someone else saved']);

        $this->putJson(route('desk-editor.articles.update', $article), ['title' => 'Mine', 'status' => 'draft', 'loaded_at' => $loaded])
            ->assertStatus(409)
            ->assertJsonPath('conflict', true)
            ->assertJsonPath('reload_url', route('desk-editor.articles.edit', $article));
        $this->assertSame('Someone else saved', $article->fresh()->title);

        $this->putJson(route('desk-editor.articles.update', $article), ['title' => 'Mine', 'status' => 'draft', 'loaded_at' => $loaded, 'overwrite' => 1])
            ->assertOk();
        $this->assertSame('Mine', $article->fresh()->title);
    }

    public function test_fresh_save_returns_the_new_version(): void
    {
        $this->actingAs($this->user());
        $article = DeskArticle::create(['title' => 'Draft', 'status' => ArticleStatus::Draft]);
        $loaded = (string) $article->updated_at->getTimestamp();
        $this->travel(5)->seconds();

        $version = $this->putJson(route('desk-editor.articles.update', $article), ['title' => 'One', 'status' => 'draft', 'loaded_at' => $loaded])
            ->assertOk()->json('article.version');

        $this->travel(5)->seconds();
        $this->putJson(route('desk-editor.articles.update', $article), ['title' => 'Two', 'status' => 'draft', 'loaded_at' => $version])->assertOk();
    }

    public function test_stale_html_save_redirects_back_with_conflict_and_keeps_input(): void
    {
        $this->actingAs($this->user());
        $article = DeskArticle::create(['title' => 'Draft', 'body' => '<p>Server</p>', 'status' => ArticleStatus::Draft]);
        $loaded = (string) $article->updated_at->getTimestamp();
        $this->travel(5)->seconds();
        $article->update(['title' => 'Newer']);

        $this->from(route('desk-editor.articles.edit', $article))
            ->put(route('desk-editor.articles.update', $article), ['title' => 'My title', 'body' => '<p>My words</p>', 'status' => 'draft', 'loaded_at' => $loaded])
            ->assertRedirect(route('desk-editor.articles.edit', $article))
            ->assertSessionHasErrors('conflict');

        $this->get(route('desk-editor.articles.edit', $article))->assertOk()
            ->assertSee(__('desk-editor::articles.conflict.title'))
            ->assertSee(__('desk-editor::articles.conflict.reload'))
            ->assertSee(__('desk-editor::articles.conflict.overwrite'))
            ->assertSee('My title')
            ->assertSee('My words');
    }

    public function test_form_carries_version_and_heartbeat_urls(): void
    {
        $this->actingAs($this->user());
        $article = DeskArticle::create(['title' => 'Draft', 'status' => ArticleStatus::Draft]);

        $this->get(route('desk-editor.articles.edit', $article))->assertOk()
            ->assertSee('name="loaded_at" value="'.$article->updated_at->getTimestamp().'"', false)
            ->assertSee('data-lock-url="'.route('desk-editor.articles.lock', $article).'"', false);
    }
}
