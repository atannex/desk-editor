<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Tests\Feature;

use Atannex\DeskEditor\Enums\ArticleStatus;
use Atannex\DeskEditor\Models\DeskArticle;
use Atannex\DeskEditor\Models\DeskCategory;
use Atannex\DeskEditor\Tests\TestCase;
use Illuminate\Support\Facades\Gate;

class ArticlesTest extends TestCase
{
    public function test_guests_are_redirected_or_rejected(): void
    {
        $this->getJson('/desk/articles')->assertUnauthorized();
    }

    public function test_index_lists_filters_and_searches(): void
    {
        DeskArticle::create(['title' => 'Launching Desk', 'body' => '<p>Big news</p>', 'status' => ArticleStatus::Published, 'category_id' => DeskCategory::findOrCreateByName('News')->id]);
        DeskArticle::create(['title' => 'Draft ideas', 'body' => '<p>Rough</p>', 'status' => ArticleStatus::Draft]);

        $this->actingAs($this->user());

        $this->get('/desk/articles')->assertOk()->assertSee('Launching Desk')->assertSee('Draft ideas');
        $this->get('/desk/articles?status=draft')->assertOk()->assertDontSee('Launching Desk')->assertSee('Draft ideas');
        $this->get('/desk/articles?q=launch')->assertOk()->assertSee('Launching Desk')->assertDontSee('Draft ideas');
        $this->get('/desk/articles?category=News')->assertOk()->assertSee('Launching Desk')->assertDontSee('Draft ideas');
    }

    public function test_create_page_renders_the_editor(): void
    {
        $this->actingAs($this->user())
            ->get('/desk/articles/create')
            ->assertOk()
            ->assertSee('data-desk-editor', false)
            ->assertSee('name="title"', false);
    }

    public function test_storing_sanitises_computes_stats_and_records_a_revision(): void
    {
        $user = $this->user();

        $response = $this->actingAs($user)->post('/desk/articles', [
            'title' => 'Hello World',
            'body' => '<h2>Intro</h2><p>One two three four.</p><script>alert(1)</script>',
            'status' => 'published',
            'tags' => 'laravel, editor, laravel',
            'featured' => '1',
        ]);

        $article = DeskArticle::firstOrFail();
        $response->assertRedirect(route('desk-editor.articles.edit', $article));

        $this->assertSame('hello-world', $article->slug);
        $this->assertStringNotContainsString('script', $article->body->html());
        $this->assertSame(5, $article->word_count);
        $this->assertSame(['editor', 'laravel'], $article->tagNames());
        $this->assertTrue($article->featured);
        $this->assertNotNull($article->published_at);
        $this->assertSame('Intro One two three four.', $article->excerpt);
        $this->assertTrue($article->author->is($user));
        $this->assertSame(1, $article->revisions()->count());
    }

    public function test_slugs_are_unique(): void
    {
        $a = DeskArticle::create(['title' => 'Same title', 'status' => ArticleStatus::Draft]);
        $b = DeskArticle::create(['title' => 'Same title', 'status' => ArticleStatus::Draft]);

        $this->assertSame('same-title', $a->slug);
        $this->assertSame('same-title-2', $b->slug);
    }

    public function test_scheduled_articles_require_a_date_and_stay_unpublished_until_then(): void
    {
        $this->actingAs($this->user())
            ->post('/desk/articles', ['title' => 'Later', 'status' => 'scheduled'])
            ->assertSessionHasErrors('published_at');

        DeskArticle::create(['title' => 'Later', 'status' => ArticleStatus::Scheduled, 'published_at' => now()->addDay()]);
        DeskArticle::create(['title' => 'Now', 'status' => ArticleStatus::Published]);

        $this->assertSame(['Now'], DeskArticle::published()->pluck('title')->all());
    }

    public function test_updating_and_restoring_a_revision(): void
    {
        $this->actingAs($this->user());
        $article = DeskArticle::create(['title' => 'Original', 'body' => '<p>First version</p>', 'status' => ArticleStatus::Draft]);
        $first = $article->revisions()->firstOrFail();

        $this->put(route('desk-editor.articles.update', $article), [
            'title' => 'Changed',
            'body' => '<p>Second version</p>',
            'status' => 'review',
            'revision_note' => 'Tightened copy',
        ])->assertRedirect();

        $article->refresh();
        $this->assertSame('Changed', $article->title);
        $this->assertSame(ArticleStatus::Review, $article->status);
        $this->assertSame('Tightened copy', $article->revisions()->first()->note);

        $this->post(route('desk-editor.articles.revisions.restore', [$article, $first->id]))->assertRedirect();

        $article->refresh();
        $this->assertSame('Original', $article->title);
        $this->assertSame('<p>First version</p>', $article->body->html());
        $this->assertSame(3, $article->revisions()->count());
    }

    public function test_json_save_for_keyboard_shortcut(): void
    {
        $this->actingAs($this->user());
        $article = DeskArticle::create(['title' => 'Quick', 'status' => ArticleStatus::Draft]);

        $this->putJson(route('desk-editor.articles.update', $article), ['title' => 'Quicker', 'status' => 'draft', 'body' => '<p>Saved with Ctrl+S</p>'])
            ->assertOk()
            ->assertJsonPath('article.word_count', 3);
    }

    public function test_duplicate_bulk_trash_and_restore(): void
    {
        $this->actingAs($this->user());
        $article = DeskArticle::create(['title' => 'Source', 'body' => '<p>Body</p>', 'status' => ArticleStatus::Published, 'featured' => true]);

        $this->post(route('desk-editor.articles.duplicate', $article))->assertRedirect();
        $copy = DeskArticle::where('title', 'Source (copy)')->firstOrFail();
        $this->assertSame(ArticleStatus::Draft, $copy->status);
        $this->assertFalse($copy->featured);

        $this->post(route('desk-editor.articles.bulk'), ['action' => 'archive', 'ids' => [$article->id, $copy->id]])->assertRedirect();
        $this->assertSame(2, DeskArticle::status(ArticleStatus::Archived)->count());

        $this->delete(route('desk-editor.articles.destroy', $article))->assertRedirect();
        $this->assertSoftDeleted($article);
        $this->get('/desk/articles?status=trashed')->assertSee('Source');

        $this->post(route('desk-editor.articles.restore', $article))->assertRedirect();
        $this->assertNotSoftDeleted($article);

        $this->delete(route('desk-editor.articles.force-delete', $copy))->assertRedirect();
        $this->assertDatabaseMissing('desk_articles', ['id' => $copy->id]);
    }

    public function test_preview_renders_sanitised_content(): void
    {
        $this->actingAs($this->user());
        $article = DeskArticle::create(['title' => 'Readable', 'body' => '<p>Reader view</p>', 'status' => ArticleStatus::Published]);
        $article->syncTagNames(['news']);

        $this->get(route('desk-editor.articles.preview', $article))
            ->assertOk()
            ->assertSee('Reader view')
            ->assertSee('#news');
    }

    public function test_access_can_be_restricted_with_the_gate(): void
    {
        Gate::define('manage-desk-articles', fn ($user) => $user->email === 'boss@example.com');

        $this->actingAs($this->user())->get('/desk/articles')->assertForbidden();
    }

    public function test_search_ranks_title_matches_first(): void
    {
        DeskArticle::create(['title' => 'Notes', 'excerpt' => 'All about laravel queues', 'status' => ArticleStatus::Draft]);
        DeskArticle::create(['title' => 'Laravel tips', 'status' => ArticleStatus::Draft]);

        $this->actingAs($this->user())
            ->get('/desk/articles?q=laravel')
            ->assertOk()
            ->assertSeeInOrder(['Laravel tips', 'Notes']);
    }

    public function test_editor_is_wired_for_server_autosave_comments_and_collaboration(): void
    {
        $this->actingAs($this->user());
        $article = DeskArticle::create(['title' => 'Wired', 'status' => ArticleStatus::Draft]);

        $html = $this->get(route('desk-editor.articles.edit', $article))->assertOk()->getContent();

        $this->assertStringContainsString('article-'.$article->id, $html);
        $this->assertStringContainsString('article:'.$article->id, $html);
        $this->assertStringContainsString('commentsEnabled', $html);
    }

    public function test_view_live_uses_the_public_url_pattern(): void
    {
        $this->app['config']->set('desk-editor.articles.public_url', 'https://example.com/news/{slug}');
        $article = DeskArticle::create(['title' => 'Live one', 'status' => ArticleStatus::Published]);

        $this->assertSame('https://example.com/news/live-one', $article->publicUrl());
    }
}
