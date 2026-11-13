<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Blog\Support;

use Atannex\DeskEditor\Models\DeskArticle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Records article page views into daily counters (desk_article_views) and the
 * article's views_count. Every human page view counts as a view; a visitor is
 * counted once per article within `blog.analytics.dedupe_minutes`, keyed by
 * session (falling back to a hash of IP + user agent).
 */
class ViewRecorder
{
    private const BOTS = '/bot|crawl|spider|slurp|facebookexternalhit|embedly|preview|monitor|curl|wget|python-requests|httpclient|headless|lighthouse|pingdom|uptime|feed|scrapy|axios|go-http|java\//i';

    public function enabled(): bool
    {
        return (bool) config('desk-editor.blog.analytics.enabled', true);
    }

    public function isBot(Request $request): bool
    {
        $agent = (string) $request->userAgent();

        return $agent === '' || preg_match(self::BOTS, $agent) === 1;
    }

    /**
     * @return bool whether a view was recorded
     */
    public function record(DeskArticle $article, Request $request): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        if (config('desk-editor.blog.analytics.ignore_bots', true) && $this->isBot($request)) {
            return false;
        }

        // Speculative prefetches are not views.
        if (in_array(strtolower((string) ($request->header('Sec-Purpose') ?? $request->header('Purpose'))), ['prefetch', 'prerender'], true)) {
            return false;
        }

        $minutes = max(1, (int) config('desk-editor.blog.analytics.dedupe_minutes', 30));
        $isNewVisitor = $this->isNewVisitor($article, $request, $minutes);

        $date = now()->toDateString();
        $table = DB::table('desk_article_views');

        DB::transaction(function () use ($table, $article, $date, $isNewVisitor): void {
            $table->insertOrIgnore(['article_id' => $article->getKey(), 'date' => $date, 'views' => 0, 'visitors' => 0]);
            DB::table('desk_article_views')
                ->where('article_id', $article->getKey())
                ->where('date', $date)
                ->update([
                    'views' => DB::raw('views + 1'),
                    'visitors' => DB::raw('visitors + '.($isNewVisitor ? 1 : 0)),
                ]);
            DB::table('desk_articles')->where('id', $article->getKey())->increment('views_count');
        });

        return true;
    }

    private function isNewVisitor(DeskArticle $article, Request $request, int $minutes): bool
    {
        $sessionKey = 'desk-blog.visits.'.$article->getKey();
        $session = $request->hasSession() ? $request->session() : null;
        $seen = $session?->get($sessionKey);
        $recent = is_int($seen) && $seen > now()->subMinutes($minutes)->getTimestamp();

        // Anonymous fingerprint (never stored in the database).
        $key = 'desk-blog:visit:'.$article->getKey().':'.hash('sha256', $request->ip().'|'.$request->userAgent());
        $fresh = Cache::add($key, 1, now()->addMinutes($minutes));

        $session?->put($sessionKey, now()->getTimestamp());

        return ! $recent && $fresh;
    }
}
