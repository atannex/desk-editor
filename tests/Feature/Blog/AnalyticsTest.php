<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Tests\Feature\Blog;

use Atannex\DeskEditor\Blog\Models\DeskArticleView;
use Illuminate\Support\Facades\Gate;

class AnalyticsTest extends BlogTestCase
{
    private const BROWSER = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15';

    public function test_views_are_recorded_and_visitors_deduplicated(): void
    {
        $article = $this->article();

        $this->withHeader('User-Agent', self::BROWSER)->get('/blog/'.$article->slug)->assertOk();
        $this->withHeader('User-Agent', self::BROWSER)->get('/blog/'.$article->slug)->assertOk();

        $row = DeskArticleView::firstOrFail();
        $this->assertSame(now()->toDateString(), $row->date->toDateString());
        $this->assertSame(2, $row->views);
        $this->assertSame(1, $row->visitors);
        $this->assertSame(2, $article->fresh()->views_count);

        // After the dedupe window a returning reader counts as a visitor again.
        $this->travel(31)->minutes();
        $this->withHeader('User-Agent', self::BROWSER)->get('/blog/'.$article->slug);
        $this->assertSame(2, DeskArticleView::firstOrFail()->visitors);
    }

    public function test_bots_and_prefetches_are_ignored(): void
    {
        $article = $this->article();

        $this->withHeader('User-Agent', 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)')->get('/blog/'.$article->slug)->assertOk();
        $this->withHeaders(['User-Agent' => self::BROWSER, 'Sec-Purpose' => 'prefetch'])->get('/blog/'.$article->slug)->assertOk();

        $this->assertSame(0, DeskArticleView::count());
        $this->assertSame(0, $article->fresh()->views_count);
    }

    public function test_bots_counted_when_ignore_bots_disabled_and_nothing_when_analytics_off(): void
    {
        $article = $this->article();

        config(['desk-editor.blog.analytics.ignore_bots' => false]);
        $this->withHeader('User-Agent', 'Googlebot')->get('/blog/'.$article->slug);
        $this->assertSame(1, $article->fresh()->views_count);

        config(['desk-editor.blog.analytics.enabled' => false]);
        $this->withHeader('User-Agent', self::BROWSER)->get('/blog/'.$article->slug);
        $this->assertSame(1, $article->fresh()->views_count);
    }

    public function test_analytics_page_renders_kpis_chart_and_tables(): void
    {
        $article = $this->article(['title' => 'Popular Story', 'category_id' => $this->category('Guides')->id]);
        DeskArticleView::create(['article_id' => $article->id, 'date' => now()->subDays(2)->toDateString(), 'views' => 40, 'visitors' => 30]);
        DeskArticleView::create(['article_id' => $article->id, 'date' => now()->subDays(40)->toDateString(), 'views' => 20, 'visitors' => 10]);

        $this->actingAs($this->user())
            ->get('/desk/analytics')
            ->assertOk()
            ->assertSee(__('desk-editor::blog.analytics.title'))
            ->assertSee('data-kpi="views"', false)
            ->assertSee('+100%')
            ->assertSee('<svg viewBox="0 0 720 220"', false)
            ->assertSee('desk-views-fill', false)
            ->assertSee('Popular Story')
            ->assertSee('Guides');
    }

    public function test_analytics_page_empty_state(): void
    {
        $this->actingAs($this->user())->get('/desk/analytics')->assertOk()->assertSee(__('desk-editor::blog.analytics.no_data'));
    }

    public function test_analytics_respects_view_gate(): void
    {
        Gate::define('desk-articles.view', fn () => false);

        $this->actingAs($this->user())->get('/desk/analytics')->assertForbidden();
    }
}
