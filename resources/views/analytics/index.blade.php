@extends('desk-editor::articles.layout')

@php
    use Atannex\DeskEditor\Blog\Support\Blog;
    $w = 720; $h = 220; $padTop = 16; $padBottom = 28; $padX = 8;
    $max = max(1, max(array_column($series, 'views')));
    // Round the scale up to a "nice" number for gridlines.
    $magnitude = 10 ** max(0, (int) floor(log10($max)));
    $niceMax = (int) (ceil($max / $magnitude) * $magnitude);
    $niceMax = max($niceMax, 4);
    $n = count($series);
    $step = ($w - $padX * 2) / max(1, $n - 1);
    $y = fn (int $v) => round($padTop + ($h - $padTop - $padBottom) * (1 - $v / $niceMax), 2);
    $points = [];
    foreach ($series as $i => $point) {
        $points[] = [round($padX + $i * $step, 2), $y($point['views'])];
    }
    $line = 'M'.implode(' L', array_map(fn ($p) => $p[0].' '.$p[1], $points));
    $area = $line.' L'.end($points)[0].' '.($h - $padBottom).' L'.$points[0][0].' '.($h - $padBottom).' Z';
    $grid = [0, (int) round($niceMax / 2), $niceMax];
    $hasData = array_sum(array_column($series, 'views')) > 0;
    $maxCategory = max(1, (int) ($topCategories->max('views') ?? 1));
@endphp

@section('title', __('desk-editor::blog.analytics.title'))

@section('header-actions')
    @if (Route::has('desk-editor.comments.index'))
        <a href="{{ route('desk-editor.comments.index') }}" class="hidden h-9 items-center gap-1.5 rounded-lg px-3 text-sm font-medium text-zinc-600 transition hover:bg-zinc-100 hover:text-zinc-900 sm:inline-flex dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-white">
            {{ __('desk-editor::blog.moderation.title') }}
            @if ($pending)<span class="rounded-md bg-amber-100 px-1.5 text-[11px] font-semibold text-amber-800 tabular-nums dark:bg-amber-400/15 dark:text-amber-300">{{ $pending }}</span>@endif
        </a>
    @endif
    @if (Route::has('desk-editor.blog.home'))
        <a href="{{ route('desk-editor.blog.home') }}" target="_blank" rel="noopener" class="hidden h-9 items-center gap-1.5 rounded-lg px-3 text-sm font-medium text-zinc-600 transition hover:bg-zinc-100 hover:text-zinc-900 sm:inline-flex dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-white">
            {{ __('desk-editor::blog.analytics.open_blog') }}
            <svg viewBox="0 0 24 24" class="size-3.5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7M8 7h9v9"/></svg>
        </a>
    @endif
@endsection

@section('content')
<main class="mx-auto max-w-7xl px-4 pt-8 pb-28 sm:px-6">
    <div class="animate-de-rise mb-8 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('desk-editor::blog.analytics.title') }}</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('desk-editor::blog.analytics.subtitle') }}</p>
        </div>
        <span class="inline-flex w-fit items-center gap-1.5 rounded-lg border border-zinc-200 bg-white px-3 py-1.5 text-xs font-medium text-zinc-600 shadow-xs dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300">
            <svg viewBox="0 0 24 24" class="size-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
            {{ __('desk-editor::blog.analytics.range') }}
        </span>
    </div>

    {{-- KPI cards --}}
    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach ([
            ['views', 'from-indigo-500/15 to-indigo-500/0 text-indigo-600 dark:text-indigo-400', 'M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z', __('desk-editor::blog.analytics.all_time', ['count' => number_format($allTimeViews)])],
            ['visitors', 'from-violet-500/15 to-violet-500/0 text-violet-600 dark:text-violet-400', 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM22 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8', null],
            ['published', 'from-emerald-500/15 to-emerald-500/0 text-emerald-600 dark:text-emerald-400', 'M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7ZM14 2v4a2 2 0 0 0 2 2h4M9 15l2 2 4-4', null],
            ['comments', 'from-sky-500/15 to-sky-500/0 text-sky-600 dark:text-sky-400', 'M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z', $pending ? __('desk-editor::blog.analytics.pending', ['count' => $pending]) : null],
        ] as $i => [$key, $tone, $path, $note])
            @php [$value, $change] = $kpis[$key]; @endphp
            <div class="animate-de-rise relative overflow-hidden rounded-2xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-800 dark:bg-zinc-900" style="animation-delay: {{ $i * 50 }}ms" data-kpi="{{ $key }}">
                <div class="pointer-events-none absolute inset-0 bg-gradient-to-br {{ $tone }} opacity-60"></div>
                <div class="relative flex items-start justify-between">
                    <div class="min-w-0">
                        <div class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('desk-editor::blog.analytics.'.$key) }}</div>
                        <div class="mt-1.5 text-2xl font-semibold tabular-nums">{{ number_format($value) }}</div>
                    </div>
                    <svg viewBox="0 0 24 24" class="size-5 shrink-0 {{ $tone }}" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $path }}"/></svg>
                </div>
                <div class="relative mt-2 flex flex-wrap items-center gap-1.5 text-[11px] text-zinc-500 dark:text-zinc-400">
                    @if (in_array($key, ['views', 'visitors'], true))
                        @if ($change === null)
                            <span class="rounded-md bg-indigo-50 px-1.5 py-0.5 font-semibold text-indigo-700 dark:bg-indigo-400/10 dark:text-indigo-300">{{ __('desk-editor::blog.analytics.new') }}</span>
                        @else
                            <span @class([
                                'inline-flex items-center gap-0.5 rounded-md px-1.5 py-0.5 font-semibold tabular-nums',
                                'bg-emerald-50 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300' => $change > 0,
                                'bg-rose-50 text-rose-700 dark:bg-rose-400/10 dark:text-rose-300' => $change < 0,
                                'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300' => $change == 0,
                            ])>
                                <span aria-hidden="true">{{ $change > 0 ? '↑' : ($change < 0 ? '↓' : '→') }}</span>{{ $change > 0 ? '+' : '' }}{{ $change }}%
                            </span>
                        @endif
                        <span>{{ __('desk-editor::blog.analytics.vs_previous') }}</span>
                    @endif
                    @if ($note)<span>{{ $note }}</span>@endif
                </div>
            </div>
        @endforeach
    </div>

    {{-- Chart --}}
    <section class="animate-de-rise mb-6 rounded-2xl border border-zinc-200 bg-white p-4 shadow-xs sm:p-6 dark:border-zinc-800 dark:bg-zinc-900" style="animation-delay: 200ms" aria-labelledby="chart-title">
        <div class="mb-4 flex items-baseline justify-between gap-4">
            <h2 id="chart-title" class="text-sm font-semibold">{{ __('desk-editor::blog.analytics.chart') }}</h2>
            <span class="text-xs text-zinc-500 tabular-nums dark:text-zinc-400">{{ $series[0]['label'] }} – {{ end($series)['label'] }}</span>
        </div>
        <div class="relative text-indigo-600 dark:text-indigo-400" data-chart>
            <svg viewBox="0 0 {{ $w }} {{ $h }}" class="h-auto w-full overflow-visible" role="img" aria-label="{{ __('desk-editor::blog.analytics.chart_label') }}">
                <defs>
                    <linearGradient id="desk-views-fill" x1="0" x2="0" y1="0" y2="1">
                        <stop offset="0%" stop-color="currentColor" stop-opacity="0.28"/>
                        <stop offset="100%" stop-color="currentColor" stop-opacity="0"/>
                    </linearGradient>
                </defs>
                @foreach ($grid as $tick)
                    <line x1="{{ $padX }}" x2="{{ $w - $padX }}" y1="{{ $y($tick) }}" y2="{{ $y($tick) }}" class="stroke-zinc-200 dark:stroke-zinc-800" stroke-width="1" @if ($tick > 0) stroke-dasharray="3 4" @endif vector-effect="non-scaling-stroke"/>
                    <text x="{{ $padX }}" y="{{ $y($tick) - 5 }}" class="fill-zinc-500 text-[10px] tabular-nums">{{ number_format($tick) }}</text>
                @endforeach
                @foreach ($series as $i => $point)
                    @if ($i % 7 === 0 || $i === $n - 1)
                        <text x="{{ $points[$i][0] }}" y="{{ $h - 8 }}" text-anchor="{{ $i === 0 ? 'start' : ($i === $n - 1 ? 'end' : 'middle') }}" class="fill-zinc-400 text-[10px]">{{ $point['label'] }}</text>
                    @endif
                @endforeach
                <path d="{{ $area }}" fill="url(#desk-views-fill)"/>
                <path d="{{ $line }}" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke"/>
                <line data-chart-guide x1="0" x2="0" y1="{{ $padTop }}" y2="{{ $h - $padBottom }}" class="stroke-zinc-300 opacity-0 dark:stroke-zinc-600" stroke-width="1" vector-effect="non-scaling-stroke"/>
            </svg>
            <span data-chart-dot class="pointer-events-none absolute size-2.5 -translate-x-1/2 -translate-y-1/2 rounded-full bg-current opacity-0 ring-2 ring-white transition-opacity dark:ring-zinc-900" style="left: 0; top: 0"></span>
            <div data-chart-tip class="pointer-events-none absolute top-0 z-10 -translate-x-1/2 rounded-lg bg-zinc-900 px-2.5 py-1.5 text-xs whitespace-nowrap text-white opacity-0 shadow-lg transition-opacity dark:bg-white dark:text-zinc-900" role="status" aria-live="polite"></div>
            <div class="absolute inset-0 flex" data-chart-hits>
                @foreach ($series as $i => $point)
                    <span class="h-full flex-1" role="img" tabindex="0" data-i="{{ $i }}" data-x="{{ $points[$i][0] / $w * 100 }}" data-y="{{ $points[$i][1] / $h * 100 }}" data-label="{{ $point['label'] }}" data-views="{{ number_format($point['views']) }}" data-visitors="{{ number_format($point['visitors']) }}" aria-label="{{ $point['label'] }}: {{ $point['views'] }} {{ __('desk-editor::blog.analytics.views') }}"></span>
                @endforeach
            </div>
            @unless ($hasData)
                <div class="absolute inset-0 grid place-items-center">
                    <p class="max-w-xs rounded-xl bg-white/90 px-4 py-3 text-center text-sm text-zinc-500 shadow-sm backdrop-blur dark:bg-zinc-900/90 dark:text-zinc-400">{{ __('desk-editor::blog.analytics.no_data') }}</p>
                </div>
            @endunless
        </div>
    </section>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Top articles --}}
        <section class="animate-de-rise overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-xs lg:col-span-2 dark:border-zinc-800 dark:bg-zinc-900" style="animation-delay: 250ms" aria-labelledby="top-articles">
            <h2 id="top-articles" class="border-b border-zinc-200 px-5 py-4 text-sm font-semibold dark:border-zinc-800">{{ __('desk-editor::blog.analytics.top_articles') }}</h2>
            @if ($topArticles->isEmpty())
                <p class="px-5 py-10 text-center text-sm text-zinc-500 dark:text-zinc-400">{{ __('desk-editor::blog.analytics.no_data') }}</p>
            @else
                <table class="w-full text-sm">
                    <thead class="bg-zinc-50/80 text-[11px] font-semibold tracking-wider text-zinc-600 uppercase dark:bg-zinc-900/60 dark:text-zinc-400">
                        <tr>
                            <th scope="col" class="px-5 py-2.5 text-left">{{ __('desk-editor::blog.analytics.article') }}</th>
                            <th scope="col" class="px-3 py-2.5 text-right">{{ __('desk-editor::blog.analytics.views') }}</th>
                            <th scope="col" class="hidden px-3 py-2.5 text-right sm:table-cell">{{ __('desk-editor::blog.analytics.visitors') }}</th>
                            <th scope="col" class="hidden px-5 py-2.5 text-right sm:table-cell">{{ __('desk-editor::blog.analytics.comments') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($topArticles as $row)
                            <tr class="transition hover:bg-zinc-50/70 dark:hover:bg-zinc-800/30">
                                <td class="max-w-0 px-5 py-3">
                                    <div class="flex items-center gap-3">
                                        <span class="w-4 shrink-0 text-xs text-zinc-500 dark:text-zinc-400 tabular-nums">{{ $loop->iteration }}</span>
                                        <div class="min-w-0">
                                            @if (Route::has('desk-editor.articles.edit'))
                                                <a href="{{ route('desk-editor.articles.edit', $row->id) }}" class="block truncate font-medium hover:text-indigo-600 dark:hover:text-indigo-400">{{ $row->title }}</a>
                                            @else
                                                <span class="block truncate font-medium">{{ $row->title }}</span>
                                            @endif
                                            @if ($row->category)<span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $row->category }}</span>@endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-3 text-right font-semibold tabular-nums">{{ number_format((int) $row->views) }}</td>
                                <td class="hidden px-3 py-3 text-right text-zinc-600 tabular-nums sm:table-cell dark:text-zinc-400">{{ number_format((int) $row->visitors) }}</td>
                                <td class="hidden px-5 py-3 text-right text-zinc-600 tabular-nums sm:table-cell dark:text-zinc-400">{{ number_format((int) $row->comments_count) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>

        <div class="space-y-6">
            {{-- Top categories --}}
            <section class="animate-de-rise rounded-2xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-800 dark:bg-zinc-900" style="animation-delay: 300ms" aria-labelledby="top-categories">
                <h2 id="top-categories" class="mb-4 text-sm font-semibold">{{ __('desk-editor::blog.analytics.top_categories') }}</h2>
                @forelse ($topCategories as $category)
                    <div class="mb-3 last:mb-0">
                        <div class="mb-1 flex items-center justify-between text-sm">
                            <span class="flex min-w-0 items-center gap-2 truncate">
                                <span class="size-2 shrink-0 rounded-full bg-indigo-500" @if (Blog::color($category->color)) style="background: {{ Blog::color($category->color) }}" @endif></span>
                                {{ $category->name }}
                            </span>
                            <span class="text-zinc-600 tabular-nums dark:text-zinc-300">{{ number_format((int) $category->views) }}</span>
                        </div>
                        <div class="h-1.5 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                            <div class="h-full rounded-full bg-indigo-500 transition-[width] duration-700 dark:bg-indigo-400" style="width: {{ max(2, round((int) $category->views / $maxCategory * 100)) }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('desk-editor::blog.analytics.no_data') }}</p>
                @endforelse
            </section>

            {{-- Recent comments --}}
            <section class="animate-de-rise rounded-2xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-800 dark:bg-zinc-900" style="animation-delay: 350ms" aria-labelledby="recent-comments">
                <div class="mb-4 flex items-center justify-between">
                    <h2 id="recent-comments" class="text-sm font-semibold">{{ __('desk-editor::blog.analytics.recent_comments') }}</h2>
                    @if (Route::has('desk-editor.comments.index'))
                        <a href="{{ route('desk-editor.comments.index') }}" class="text-xs font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">{{ __('desk-editor::blog.analytics.moderate') }}</a>
                    @endif
                </div>
                <ul class="space-y-4">
                    @forelse ($recentComments as $comment)
                        <li class="flex gap-3">
                            <span class="grid size-8 shrink-0 place-items-center rounded-full text-[11px] font-semibold text-white" style="background: hsl({{ $comment->hue() }} 55% 27%)" aria-hidden="true">{{ $comment->initials() }}</span>
                            <div class="min-w-0 text-sm">
                                <div class="flex items-center gap-1.5">
                                    <span class="truncate font-medium">{{ $comment->authorName() }}</span>
                                    <span class="inline-flex items-center rounded-md px-1 text-[10px] font-medium ring-1 ring-inset {{ $comment->status->badge() }}">{{ $comment->status->label() }}</span>
                                </div>
                                <p class="line-clamp-2 text-zinc-600 dark:text-zinc-400">{{ \Illuminate\Support\Str::limit($comment->body, 140) }}</p>
                                <p class="mt-0.5 truncate text-xs text-zinc-500">{{ $comment->article?->title }} · {{ $comment->created_at?->diffForHumans() }}</p>
                            </div>
                        </li>
                    @empty
                        <li class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('desk-editor::blog.analytics.no_comments') }}</li>
                    @endforelse
                </ul>
            </section>
        </div>
    </div>
</main>
@endsection

@push('desk-scripts')
<script>
    (() => {
        const chart = document.querySelector('[data-chart]');
        if (!chart) return;
        const tip = chart.querySelector('[data-chart-tip]');
        const dot = chart.querySelector('[data-chart-dot]');
        const guide = chart.querySelector('[data-chart-guide]');
        const show = (el) => {
            const x = Number(el.dataset.x), y = Number(el.dataset.y);
            tip.innerHTML = '';
            const strong = document.createElement('div'); strong.className = 'font-semibold'; strong.textContent = el.dataset.label;
            const line = document.createElement('div'); line.className = 'opacity-80';
            line.textContent = `${el.dataset.views} {{ __('desk-editor::blog.analytics.views') }} · ${el.dataset.visitors} {{ __('desk-editor::blog.analytics.visitors') }}`;
            tip.append(strong, line);
            tip.style.left = `clamp(4rem, ${x}%, calc(100% - 4rem))`;
            tip.style.top = `calc(${y}% - 3.25rem)`;
            dot.style.left = `${x}%`; dot.style.top = `${y}%`;
            const vx = x / 100 * {{ $w }};
            guide.setAttribute('x1', vx); guide.setAttribute('x2', vx);
            [tip, dot, guide].forEach((n) => n.classList.replace('opacity-0', 'opacity-100'));
        };
        const hide = () => [tip, dot, guide].forEach((n) => n.classList.replace('opacity-100', 'opacity-0'));
        chart.querySelectorAll('[data-chart-hits] > span').forEach((el) => {
            el.addEventListener('mouseenter', () => show(el));
            el.addEventListener('focus', () => show(el));
            el.addEventListener('blur', hide);
        });
        chart.addEventListener('mouseleave', hide);
    })();
</script>
@endpush
