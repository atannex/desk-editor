<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Blog\Http\Controllers;

use Atannex\DeskEditor\Blog\Enums\CommentStatus;
use Atannex\DeskEditor\Blog\Models\DeskComment;
use Atannex\DeskEditor\Blog\Support\Blog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AnalyticsController extends Controller
{
    public const GATE = 'desk-articles.view';

    public function __invoke(Request $request): View
    {
        abort_unless(Gate::has(self::GATE) ? Gate::allows(self::GATE) : $request->user() !== null, 403);

        $days = 30;
        $today = now()->startOfDay();
        $from = $today->copy()->subDays($days - 1);
        $previousFrom = $from->copy()->subDays($days);

        $daily = DB::table('desk_article_views')
            ->where('date', '>=', $from->toDateString())
            ->groupBy('date')
            ->selectRaw('date, sum(views) as views, sum(visitors) as visitors')
            ->get()
            ->keyBy(fn (object $row) => substr((string) $row->date, 0, 10));

        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $from->copy()->addDays($i);
            $row = $daily->get($date->toDateString());
            $series[] = [
                'date' => $date->toDateString(),
                'label' => $date->isoFormat('MMM D'),
                'views' => (int) ($row->views ?? 0),
                'visitors' => (int) ($row->visitors ?? 0),
            ];
        }

        $views = array_sum(array_column($series, 'views'));
        $visitors = array_sum(array_column($series, 'visitors'));
        $previous = DB::table('desk_article_views')->whereBetween('date', [$previousFrom->toDateString(), $from->copy()->subDay()->toDateString()])
            ->selectRaw('coalesce(sum(views), 0) as views, coalesce(sum(visitors), 0) as visitors')->first();
        $previousViews = (int) ($previous->views ?? 0);
        $previousVisitors = (int) ($previous->visitors ?? 0);

        $model = Blog::model();
        $articlesTable = (new $model)->getTable();

        $topArticles = DB::table('desk_article_views as v')
            ->join($articlesTable.' as a', 'a.id', '=', 'v.article_id')
            ->leftJoin('desk_categories as c', 'c.id', '=', 'a.category_id')
            ->whereNull('a.deleted_at')
            ->where('v.date', '>=', $from->toDateString())
            ->groupBy('a.id', 'a.title', 'a.slug', 'a.locale', 'a.comments_count', 'c.name')
            ->selectRaw('a.id, a.title, a.slug, a.locale, a.comments_count, c.name as category, sum(v.views) as views, sum(v.visitors) as visitors')
            ->orderByDesc('views')
            ->limit(8)
            ->get();

        $topCategories = DB::table('desk_article_views as v')
            ->join($articlesTable.' as a', 'a.id', '=', 'v.article_id')
            ->join('desk_categories as c', 'c.id', '=', 'a.category_id')
            ->whereNull('a.deleted_at')
            ->where('v.date', '>=', $from->toDateString())
            ->groupBy('c.id', 'c.name', 'c.color')
            ->selectRaw('c.id, c.name, c.color, sum(v.views) as views')
            ->orderByDesc('views')
            ->limit(6)
            ->get();

        return view('desk-editor::analytics.index', [
            'series' => $series,
            'kpis' => [
                'views' => [$views, $this->change($views, $previousViews)],
                'visitors' => [$visitors, $this->change($visitors, $previousVisitors)],
                'published' => [$model::query()->published()->count(), null],
                'comments' => [DeskComment::query()->approved()->count(), null],
            ],
            'pending' => DeskComment::query()->where('status', CommentStatus::Pending->value)->count(),
            'allTimeViews' => (int) $model::query()->sum('views_count'),
            'topArticles' => $topArticles,
            'topCategories' => $topCategories,
            'recentComments' => DeskComment::query()->with(['article', 'user'])->whereIn('status', [CommentStatus::Approved->value, CommentStatus::Pending->value])->latest('id')->limit(6)->get(),
        ]);
    }

    private function change(int $current, int $previous): ?float
    {
        if ($previous === 0) {
            return $current > 0 ? null : 0.0;
        }

        return round(($current - $previous) / $previous * 100, 1);
    }
}
