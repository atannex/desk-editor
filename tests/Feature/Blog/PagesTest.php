<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Tests\Feature\Blog;

use Atannex\DeskEditor\Enums\ArticleStatus;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class PagesTest extends BlogTestCase
{
    public function test_home_lists_published_articles_with_featured_hero(): void
    {
        $this->article(['title' => 'Featured Story', 'featured' => true, 'category_id' => $this->category('News')->id]);
        $this->article(['title' => 'Second Story', 'published_at' => now()->subDays(2)]);
        $this->article(['title' => 'Secret Draft', 'status' => ArticleStatus::Draft]);

        $this->get('/blog')
            ->assertOk()
            ->assertSee('Featured Story')
            ->assertSee('Second Story')
            ->assertDontSee('Secret Draft')
            ->assertSee('The Desk Journal')
            ->assertSee('application/rss+xml', false)
            ->assertSee('"@type":"WebSite"', false)
            ->assertSee('SearchAction', false);
    }

    public function test_home_empty_state_and_category_filter(): void
    {
        $this->get('/blog')->assertOk()->assertSee(__('desk-editor::blog.empty_title'));

        $news = $this->category('News');
        $this->article(['title' => 'News Item', 'category_id' => $news->id]);
        $this->article(['title' => 'Other Item']);

        $this->get('/blog?category=news')->assertOk()->assertSee('News Item')->assertDontSee('Other Item');
    }

    public function test_home_paginates(): void
    {
        config(['desk-editor.blog.per_page' => 2]);
        foreach (range(1, 6) as $i) {
            $this->article(['title' => "Story number {$i}", 'published_at' => now()->subDays($i)]);
        }

        $this->get('/blog')->assertOk()->assertSee('rel="next"', false)->assertSee('aria-label="Pagination"', false);
        $this->get('/blog?page=3')->assertOk()->assertSee('Story number 6');
    }

    public function test_article_page_renders_body_toc_tags_share_and_seo(): void
    {
        $author = $this->user();
        $article = $this->article([
            'title' => 'Deep Dive',
            'subtitle' => 'Everything about it',
            'excerpt' => 'A thorough exploration.',
            'cover_image' => '/storage/cover.jpg',
            'category_id' => $this->category('Engineering')->id,
        ], ['laravel', 'php'], $author);

        $this->get('/blog/'.$article->slug)
            ->assertOk()
            ->assertSee('<title>Deep Dive · The Desk Journal</title>', false)
            ->assertSee('<meta name="description" content="A thorough exploration.">', false)
            ->assertSee('<link rel="canonical" href="'.url('/blog/deep-dive').'">', false)
            ->assertSee('property="og:type" content="article"', false)
            ->assertSee('property="og:image" content="'.url('/storage/cover.jpg').'"', false)
            ->assertSee('name="twitter:card" content="summary_large_image"', false)
            ->assertSee('property="article:published_time"', false)
            ->assertSee('property="article:tag" content="laravel"', false)
            ->assertSee('"@type":"BlogPosting"', false)
            ->assertSee('"@type":"BreadcrumbList"', false)
            ->assertSee('data-toc-link="intro"', false)
            ->assertSee('data-toc-link="details"', false)
            ->assertSee('Welcome to the journal.')
            ->assertSee('#laravel')
            ->assertSee('linkedin.com/sharing', false)
            ->assertSee('data-share-copy', false)
            ->assertSee('Ada Lovelace')
            ->assertSee('data-reading-progress', false);
    }

    public function test_canonical_url_override_is_used(): void
    {
        $article = $this->article(['canonical_url' => 'https://example.org/original']);

        $this->get('/blog/'.$article->slug)->assertOk()->assertSee('<link rel="canonical" href="https://example.org/original">', false);
    }

    public function test_unpublished_scheduled_and_missing_articles_404(): void
    {
        $draft = $this->article(['title' => 'Draft', 'status' => ArticleStatus::Draft]);
        $scheduled = $this->article(['title' => 'Later', 'status' => ArticleStatus::Scheduled, 'published_at' => now()->addWeek()]);
        $trashed = $this->article(['title' => 'Gone']);
        $trashed->delete();

        $this->get('/blog/'.$draft->slug)->assertNotFound();
        $this->get('/blog/'.$scheduled->slug)->assertNotFound();
        $this->get('/blog/'.$trashed->slug)->assertNotFound();
        $this->get('/blog/does-not-exist')->assertNotFound();
    }

    public function test_previous_next_and_related_articles(): void
    {
        $older = $this->article(['title' => 'Older Post', 'published_at' => now()->subDays(5)], ['shared']);
        $current = $this->article(['title' => 'Current Post', 'published_at' => now()->subDays(3)], ['shared']);
        $this->article(['title' => 'Newer Post', 'published_at' => now()->subDay()]);

        $this->get('/blog/'.$current->slug)
            ->assertOk()
            ->assertSee('rel="prev"', false)
            ->assertSee('rel="next"', false)
            ->assertSee('Older Post')
            ->assertSee('Newer Post')
            ->assertSee(__('desk-editor::blog.related'));

        $this->assertNotNull($older);
    }

    public function test_translations_switcher_and_hreflang(): void
    {
        config(['desk-editor.articles.locales' => ['en' => 'English', 'fr' => 'Français']]);
        $group = (string) Str::uuid();
        $en = $this->article(['title' => 'Hello World', 'slug' => 'hello-world', 'locale' => 'en', 'translation_group' => $group]);
        $this->article(['title' => 'Bonjour le monde', 'slug' => 'hello-world', 'locale' => 'fr', 'translation_group' => $group]);

        $this->get('/blog/'.$en->slug)
            ->assertOk()
            ->assertSee('Hello World')
            ->assertSee('hreflang="fr"', false)
            ->assertSee('Français')
            ->assertSee('hreflang="x-default"', false);

        $this->get('/blog/hello-world?lang=fr')->assertOk()->assertSee('Bonjour le monde')->assertSee('<html lang="fr"', false);
        $this->get('/blog?lang=fr')->assertOk()->assertSee('Bonjour le monde')->assertDontSee('Hello World');
    }

    public function test_category_tag_and_author_pages(): void
    {
        $author = $this->user();
        $design = $this->category('Design');
        $this->article(['title' => 'Typography Matters', 'category_id' => $design->id], ['fonts'], $author);
        $this->article(['title' => 'Unrelated Post']);

        $this->get('/blog/category/design')->assertOk()->assertSee('Typography Matters')->assertDontSee('Unrelated Post')->assertSee('BreadcrumbList', false);
        $this->get('/blog/tag/fonts')->assertOk()->assertSee('Typography Matters')->assertDontSee('Unrelated Post');
        $this->get('/blog/author/'.$author->id.'/ada-lovelace')->assertOk()->assertSee('Ada Lovelace')->assertSee('Typography Matters')->assertDontSee('Unrelated Post');

        $this->get('/blog/category/nope')->assertNotFound();
        $this->get('/blog/tag/nope')->assertNotFound();
        $this->get('/blog/author/999')->assertNotFound();
    }

    public function test_search_highlights_terms(): void
    {
        $this->article(['title' => 'Laravel Tips', 'excerpt' => 'Tips for Laravel developers.']);
        $this->article(['title' => 'Cooking Pasta']);

        $this->get('/blog/search?q=laravel')
            ->assertOk()
            ->assertSee('<mark', false)
            ->assertSee('Laravel</mark>', false)
            ->assertDontSee('Cooking Pasta')
            ->assertSee('noindex', false);

        $this->get('/blog/search')->assertOk()->assertSee(__('desk-editor::blog.search_heading'));
        $this->get('/blog/search?q=zzzz')->assertOk()->assertSee(__('desk-editor::blog.empty_search'));
    }

    public function test_search_escapes_user_input(): void
    {
        $this->get('/blog/search?q=%3Cscript%3Ealert(1)%3C/script%3E')->assertOk()->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_routes_use_configured_names(): void
    {
        $this->assertTrue(Route::has('desk-editor.blog.home'));
        $this->assertSame(url('/blog/feed'), route('desk-editor.blog.feed'));
    }
}
