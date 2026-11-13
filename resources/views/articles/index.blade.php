@extends('desk-editor::articles.layout')

@php
    use Atannex\DeskEditor\Enums\ArticleStatus;
    $tabs = [null => [__('desk-editor::articles.all'), $total]];
    foreach (ArticleStatus::cases() as $case) {
        $tabs[$case->value] = [$case->label(), (int) ($counts[$case->value] ?? 0)];
    }
    $tabs['trashed'] = [__('desk-editor::articles.trash'), $trashed];
    $filtered = (bool) (request('q') || request('category') || request('featured') || request('author') || request('locale'));
    $canCreate = $deskPolicy->allows('create');
    $bulkActions = $status === 'trashed'
        ? ($deskPolicy->allows('delete') ? ['restore', 'delete'] : [])
        : array_values(array_filter(['publish', 'draft', 'archive', 'feature', 'trash'], fn ($a) => $deskPolicy->allows(match ($a) { 'publish', 'archive' => 'publish', 'trash' => 'delete', default => 'update' })));
    $select = 'h-10 cursor-pointer rounded-xl border border-zinc-200 bg-white px-3 text-sm text-zinc-900 shadow-xs outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/15 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-100';
    $emptyTitle = match (true) {
        $filtered => __('desk-editor::articles.empty_filtered'),
        $status === 'trashed' => __('desk-editor::articles.empty_trash'),
        $status === 'review' => __('desk-editor::articles.empty_review'),
        $status !== null && $total > 0 => __('desk-editor::articles.empty_status', ['status' => \Illuminate\Support\Str::lower(ArticleStatus::from($status)->label())]),
        default => __('desk-editor::articles.empty_title'),
    };
@endphp

@section('header-actions')
    @if ($canCreate)
    <a href="{{ route('desk-editor.articles.create') }}" aria-label="{{ __('desk-editor::articles.new') }}" class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-indigo-600 px-2.5 text-sm font-medium text-white no-underline shadow-sm shadow-indigo-600/25 transition sm:px-3.5 hover:bg-indigo-500 active:scale-[.98] dark:bg-indigo-500 dark:hover:bg-indigo-400">
        <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
        <span class="hidden sm:inline">{{ __('desk-editor::articles.new') }}</span>
    </a>
    @endif
@endsection

@section('content')
<main class="mx-auto max-w-7xl px-4 pt-8 pb-28 sm:px-6">
    <div class="animate-de-rise mb-8 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('desk-editor::articles.title') }}</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('desk-editor::articles.subtitle') }}</p>
        </div>
        <p class="text-xs text-zinc-500 tabular-nums dark:text-zinc-400">{{ number_format($stats['words']) }} {{ \Illuminate\Support\Str::lower(__('desk-editor::articles.stats.words')) }}</p>
    </div>

    {{-- Insight cards --}}
    <div class="mb-8 grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach ([
            ['total', $total, 'from-indigo-500/15 to-indigo-500/0 text-indigo-600 dark:text-indigo-400', 'M4 4h16v16H4zM8 8h8M8 12h8M8 16h5'],
            ['published', $stats['published'], 'from-emerald-500/15 to-emerald-500/0 text-emerald-600 dark:text-emerald-400', 'M20 6 9 17l-5-5'],
            ['scheduled', $stats['scheduled'], 'from-sky-500/15 to-sky-500/0 text-sky-600 dark:text-sky-400', 'M12 6v6l4 2M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Z'],
            ['review', $stats['review'], 'from-amber-500/15 to-amber-500/0 text-amber-600 dark:text-amber-400', 'M2.06 12.35a1 1 0 0 1 0-.7 10.75 10.75 0 0 1 19.88 0 1 1 0 0 1 0 .7 10.75 10.75 0 0 1-19.88 0M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z'],
        ] as $i => [$key, $value, $tone, $path])
            <div class="animate-de-rise relative overflow-hidden rounded-2xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-800 dark:bg-zinc-900" style="animation-delay: {{ $i * 50 }}ms">
                <div class="pointer-events-none absolute inset-0 bg-gradient-to-br {{ $tone }} opacity-60"></div>
                <div class="relative flex items-start justify-between">
                    <div>
                        <div class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('desk-editor::articles.stats.'.$key) }}</div>
                        <div class="mt-1.5 text-2xl font-semibold tabular-nums">{{ $value }}</div>
                    </div>
                    <svg viewBox="0 0 24 24" class="size-5 {{ $tone }}" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $path }}"/></svg>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Status tabs --}}
    <nav class="-mx-4 mb-4 flex gap-1 overflow-x-auto px-4 sm:mx-0 sm:px-0" aria-label="Status">
        @foreach ($tabs as $value => [$label, $count])
            @php($active = (string) $status === (string) $value)
            <a href="{{ route('desk-editor.articles.index', array_filter([...request()->except(['status', 'page']), 'status' => $value ?: null])) }}"
               @class([
                   'inline-flex shrink-0 items-center gap-2 rounded-lg px-3 py-1.5 text-sm font-medium transition',
                   'bg-zinc-900 text-white shadow-sm dark:bg-white dark:text-zinc-900' => $active,
                   'text-zinc-600 hover:bg-zinc-200/60 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white' => ! $active,
               ])>
                {{ $label }}
                <span @class(['rounded-md px-1.5 text-[11px] tabular-nums', 'bg-white/20 dark:bg-zinc-900/10' => $active, 'bg-zinc-200/70 dark:bg-zinc-800' => ! $active])>{{ $count }}</span>
            </a>
        @endforeach
    </nav>

    {{-- Filters --}}
    <form method="GET" class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center" data-filters>
        @if ($status)<input type="hidden" name="status" value="{{ $status }}">@endif
        <label class="relative flex-1">
            <svg viewBox="0 0 24 24" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-zinc-500 dark:text-zinc-400" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input type="search" name="q" value="{{ request('q') }}" aria-label="{{ __('desk-editor::articles.search') }}" placeholder="{{ __('desk-editor::articles.search') }}" class="h-10 w-full rounded-xl border border-zinc-200 bg-white pr-3 pl-9 text-sm shadow-xs transition outline-none placeholder:text-zinc-400 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/15 dark:border-zinc-800 dark:bg-zinc-900">
        </label>
        <div class="grid grid-cols-2 gap-2 sm:flex">
        <select name="category" onchange="this.form.submit()" class="{{ $select }}" aria-label="{{ __('desk-editor::articles.columns.category') }}">
            <option value="">{{ __('desk-editor::articles.any_category') }}</option>
            @foreach ($categories as $category)
                <option value="{{ $category->name }}" @selected(request('category') === $category->name)>{{ $category->parent_id ? '— ' : '' }}{{ $category->name }}</option>
            @endforeach
        </select>
        @if (count($authors) > 0)
            <select name="author" onchange="this.form.submit()" class="{{ $select }}" aria-label="{{ __('desk-editor::articles.columns.author') }}" data-author-filter>
                <option value="">{{ __('desk-editor::articles.all_authors') }}</option>
                @foreach ($authors as $person)
                    <option value="{{ $person['id'] }}" @selected((string) request('author') === $person['id'])>{{ $person['name'] }}</option>
                @endforeach
            </select>
        @endif
        @if ($multilingual)
            <select name="locale" onchange="this.form.submit()" class="{{ $select }}" aria-label="{{ __('desk-editor::articles.fields.locale') }}" data-locale-filter>
                <option value="">{{ __('desk-editor::articles.all_locales') }}</option>
                @foreach ($locales as $code => $name)
                    <option value="{{ $code }}" @selected(request('locale') === $code)>{{ $name }}</option>
                @endforeach
            </select>
        @endif
        <select name="sort" onchange="this.form.submit()" class="{{ $select }}" aria-label="Sort">
            @foreach (['updated_at', 'created_at', 'published_at', 'title', 'word_count'] as $option)
                <option value="{{ $option }}" @selected($sort === $option)>{{ __('desk-editor::articles.sort.'.$option) }}</option>
            @endforeach
        </select>
        </div>
        <div class="flex gap-2">
        <button type="submit" name="direction" value="{{ $direction === 'asc' ? 'desc' : 'asc' }}" aria-label="Sort direction" class="grid h-10 w-10 shrink-0 cursor-pointer place-items-center rounded-xl border border-zinc-200 bg-white text-zinc-500 shadow-xs transition hover:text-zinc-900 dark:border-zinc-800 dark:bg-zinc-900 dark:hover:text-white" title="{{ $direction }}">
            <svg viewBox="0 0 24 24" @class(['size-4 transition-transform', 'rotate-180' => $direction === 'asc']) fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 16 4 4 4-4M7 20V4M21 8l-4-4-4 4M17 4v16"/></svg>
        </button>
        <label class="inline-flex h-10 cursor-pointer items-center gap-2 rounded-xl border border-zinc-200 bg-white px-3 text-sm shadow-xs select-none dark:border-zinc-800 dark:bg-zinc-900">
            <input type="checkbox" name="featured" value="1" onchange="this.form.submit()" @checked(request()->boolean('featured')) class="size-4 accent-indigo-600">
            {{ __('desk-editor::articles.featured_only') }}
        </label>
        </div>
    </form>

    {{-- List --}}
    <form method="POST" action="{{ route('desk-editor.articles.bulk') }}" data-bulk-form>
        @csrf
        <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
            @if ($articles->isEmpty())
                <div class="animate-de-rise flex flex-col items-center px-6 py-20 text-center">
                    <div class="relative mb-5">
                        <div class="absolute inset-0 animate-pulse rounded-full bg-indigo-500/20 blur-2xl"></div>
                        <div class="relative grid size-16 place-items-center rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                            <svg viewBox="0 0 24 24" class="size-7 text-indigo-500" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4M10 13h4M12 11v4"/></svg>
                        </div>
                    </div>
                    <h2 class="text-base font-semibold">{{ $emptyTitle }}</h2>
                    @unless ($filtered || $status === 'trashed' || $status === 'review')
                        <p class="mt-1 max-w-sm text-sm text-zinc-500 dark:text-zinc-400">{{ __('desk-editor::articles.empty_body') }}</p>
                    @endunless
                    <div class="mt-6 flex gap-2">
                        @if ($filtered)
                            <a href="{{ route('desk-editor.articles.index') }}" class="inline-flex h-9 items-center rounded-lg border border-zinc-200 px-3.5 text-sm font-medium transition hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800">{{ __('desk-editor::articles.clear_filters') }}</a>
                        @endif
                        @if ($canCreate && $status !== 'trashed')
                            <a href="{{ route('desk-editor.articles.create') }}" class="inline-flex h-9 items-center rounded-lg bg-indigo-600 px-3.5 text-sm font-medium text-white no-underline transition hover:bg-indigo-500">{{ __('desk-editor::articles.new') }}</a>
                        @endif
                    </div>
                </div>
            @else
                <div class="hidden grid-cols-[2.5rem_minmax(0,1fr)_8rem_8rem_7rem] items-center lg:grid-cols-[2.5rem_minmax(0,1fr)_8rem_9rem_8rem_5rem_7rem] gap-4 border-b border-zinc-200 bg-zinc-50/80 px-4 py-2.5 text-[11px] font-semibold tracking-wider text-zinc-600 uppercase md:grid dark:border-zinc-800 dark:bg-zinc-900/60 dark:text-zinc-400">
                    <input type="checkbox" data-select-all class="size-4 cursor-pointer accent-indigo-600" aria-label="Select all">
                    <span>{{ __('desk-editor::articles.columns.article') }}</span>
                    <span>{{ __('desk-editor::articles.columns.status') }}</span>
                    <span>{{ __('desk-editor::articles.columns.author') }}</span>
                    <span class="hidden lg:block">{{ __('desk-editor::articles.columns.category') }}</span>
                    <span class="hidden text-right lg:block">{{ __('desk-editor::articles.columns.words') }}</span>
                    <span class="text-right">{{ __('desk-editor::articles.columns.updated') }}</span>
                </div>
                <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($articles as $i => $article)
                        @php($authorName = app(\Atannex\DeskEditor\Articles\Support\Authors::class)->nameOf($article->author))
                        <li class="animate-de-rise group relative grid grid-cols-[2.5rem_minmax(0,1fr)] items-center gap-x-4 gap-y-2 px-4 py-3.5 transition-colors hover:bg-zinc-50 md:grid-cols-[2.5rem_minmax(0,1fr)_8rem_8rem_7rem] lg:grid-cols-[2.5rem_minmax(0,1fr)_8rem_9rem_8rem_5rem_7rem] dark:hover:bg-zinc-800/40" style="animation-delay: {{ min($i, 10) * 25 }}ms">
                            <input type="checkbox" name="ids[]" value="{{ $article->id }}" @disabled($bulkActions === []) data-row-check class="relative z-10 size-4 cursor-pointer accent-indigo-600" aria-label="Select {{ $article->title }}">
                            <div class="flex min-w-0 items-center gap-3.5">
                                <div class="relative hidden h-12 w-[4.5rem] shrink-0 overflow-hidden rounded-lg bg-gradient-to-br from-zinc-100 to-zinc-200 ring-1 ring-black/5 sm:block dark:from-zinc-800 dark:to-zinc-700 dark:ring-white/10">
                                    @if ($article->cover_image)
                                        <img src="{{ $article->cover_image }}" alt="" loading="lazy" class="size-full object-cover transition-transform duration-500 group-hover:scale-105">
                                    @else
                                        <span class="grid size-full place-items-center text-lg font-semibold text-zinc-500 dark:text-zinc-500">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($article->title, 0, 1)) }}</span>
                                    @endif
                                </div>
                                <div class="min-w-0">
                                    <a href="{{ $article->trashed() ? '#' : route('desk-editor.articles.edit', $article) }}" class="flex items-center gap-1.5 truncate font-medium text-zinc-900 after:absolute after:inset-0 hover:text-indigo-600 dark:text-white dark:hover:text-indigo-400">
                                        @if ($article->featured)
                                            <svg viewBox="0 0 24 24" class="size-3.5 shrink-0 fill-amber-400 text-amber-400" stroke="currentColor" stroke-width="2"><path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/></svg>
                                        @endif
                                        <span class="truncate">{{ $article->title }}</span>
                                        @if ($multilingual)
                                            <span class="shrink-0 rounded bg-zinc-100 px-1 font-mono text-[10px] font-semibold text-zinc-500 uppercase dark:bg-zinc-800 dark:text-zinc-400" title="{{ $locales[$article->locale] ?? $article->locale }}">{{ $article->locale }}</span>
                                        @endif
                                    </a>
                                    <p class="mt-0.5 truncate text-[13px] text-zinc-500 dark:text-zinc-400">{{ $article->excerpt ?: '—' }}</p>
                                    <div class="mt-1 flex flex-wrap items-center gap-1 md:hidden">
                                        <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-medium ring-1 ring-inset {{ $article->status->badge() }}"><span class="size-1.5 rounded-full {{ $article->status->dot() }}"></span>{{ $article->status->label() }}</span>
                                        @if ($authorName)<span class="text-[11px] text-zinc-500 dark:text-zinc-400">{{ $authorName }} ·</span>@endif
                                        <span class="text-[11px] text-zinc-500">{{ $article->updated_at?->diffForHumans() }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="hidden md:block">
                                <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset {{ $article->status->badge() }}">
                                    <span class="size-1.5 rounded-full {{ $article->status->dot() }} {{ $article->status === ArticleStatus::Scheduled ? 'animate-pulse' : '' }}"></span>
                                    {{ $article->status->label() }}
                                </span>
                                @if ($article->status === ArticleStatus::Scheduled && $article->published_at)
                                    <div class="mt-1 text-[11px] text-zinc-500 dark:text-zinc-400">{{ $article->published_at->format('M j, H:i') }}</div>
                                @endif
                            </div>
                            <div class="hidden min-w-0 md:block">
                                @if ($authorName)
                                    <span class="flex min-w-0 items-center gap-2 text-sm text-zinc-600 dark:text-zinc-300" title="{{ $authorName }}">
                                        <span class="grid size-6 shrink-0 place-items-center rounded-full text-[10px] font-semibold {{ \Atannex\DeskEditor\Articles\Support\Authors::tone($authorName) }}">{{ \Atannex\DeskEditor\Articles\Support\Authors::initials($authorName) }}</span>
                                        <span class="truncate">{{ $authorName }}</span>
                                    </span>
                                @else
                                    <span class="text-sm text-zinc-500">—</span>
                                @endif
                            </div>
                            <div class="hidden min-w-0 lg:block">
                                @if ($article->category)
                                    <span class="flex min-w-0 items-center gap-1.5 text-sm text-zinc-600 dark:text-zinc-300"><span class="size-2 shrink-0 rounded-full" style="background: {{ $article->category->cssColor() }}"></span><span class="truncate">{{ $article->category->name }}</span></span>
                                @else
                                    <span class="text-sm text-zinc-500">—</span>
                                @endif
                            </div>
                            <div class="hidden text-right text-sm text-zinc-600 tabular-nums lg:block dark:text-zinc-300">{{ number_format($article->word_count) }}</div>
                            <div class="hidden text-right md:block">
                                <div class="text-sm text-zinc-600 dark:text-zinc-300" title="{{ $article->updated_at }}">{{ $article->updated_at?->diffForHumans(short: true) }}</div>
                                <div class="relative z-10 mt-0.5 flex justify-end gap-2 text-xs opacity-0 transition-opacity group-hover:opacity-100 focus-within:opacity-100">
                                    @unless ($article->trashed())
                                        <a href="{{ route('desk-editor.articles.preview', $article) }}" class="text-zinc-500 hover:text-indigo-600 dark:hover:text-indigo-400">{{ __('desk-editor::articles.preview') }}</a>
                                        <a href="{{ route('desk-editor.articles.edit', $article) }}" class="text-zinc-500 hover:text-indigo-600 dark:hover:text-indigo-400">{{ __('desk-editor::articles.edit') }}</a>
                                    @endunless
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- Bulk action bar --}}
        <div data-bulk-bar class="pointer-events-none fixed inset-x-0 bottom-6 z-40 flex translate-y-24 justify-center px-4 opacity-0 transition duration-300 ease-out">
            <div class="pointer-events-auto flex flex-wrap items-center gap-1 rounded-2xl border border-zinc-200 bg-white/95 p-1.5 pl-4 shadow-2xl shadow-zinc-900/20 backdrop-blur dark:border-zinc-700 dark:bg-zinc-900/95">
                <span class="mr-2 text-sm font-medium" data-bulk-count></span>
                @foreach ($bulkActions as $action)
                    <button type="submit" name="action" value="{{ $action }}" @if ($action === 'delete') data-confirm="{{ __('desk-editor::articles.confirm_force_delete') }}" @endif
                        @class([
                            'h-8 cursor-pointer rounded-lg px-3 text-sm font-medium transition active:scale-95',
                            'text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10' => in_array($action, ['trash', 'delete']),
                            'text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-800' => ! in_array($action, ['trash', 'delete']),
                        ])>{{ __('desk-editor::articles.bulk.'.$action) }}</button>
                @endforeach
            </div>
        </div>
    </form>

    {{-- Pagination --}}
    @if ($articles->hasPages())
        <nav class="mt-6 flex items-center justify-between gap-4 text-sm" aria-label="Pagination">
            <p class="text-zinc-500 dark:text-zinc-400">{{ $articles->firstItem() }}–{{ $articles->lastItem() }} / {{ $articles->total() }}</p>
            <div class="flex items-center gap-1">
                @if ($articles->onFirstPage())
                    <span class="inline-flex h-9 items-center rounded-lg px-3 text-zinc-300 dark:text-zinc-600">‹</span>
                @else
                    <a href="{{ $articles->previousPageUrl() }}" class="inline-flex h-9 items-center rounded-lg px-3 hover:bg-zinc-200/60 dark:hover:bg-zinc-800">‹</a>
                @endif
                @foreach ($articles->getUrlRange(max(1, $articles->currentPage() - 2), min($articles->lastPage(), $articles->currentPage() + 2)) as $page => $url)
                    <a href="{{ $url }}" @class(['inline-flex h-9 min-w-9 items-center justify-center rounded-lg px-2 tabular-nums transition', 'bg-zinc-900 font-semibold text-white dark:bg-white dark:text-zinc-900' => $page === $articles->currentPage(), 'hover:bg-zinc-200/60 dark:hover:bg-zinc-800' => $page !== $articles->currentPage()])>{{ $page }}</a>
                @endforeach
                @if ($articles->hasMorePages())
                    <a href="{{ $articles->nextPageUrl() }}" class="inline-flex h-9 items-center rounded-lg px-3 hover:bg-zinc-200/60 dark:hover:bg-zinc-800">›</a>
                @else
                    <span class="inline-flex h-9 items-center rounded-lg px-3 text-zinc-300 dark:text-zinc-600">›</span>
                @endif
            </div>
        </nav>
    @endif
</main>
@endsection

@push('desk-scripts')
<script>
    (() => {
        const form = document.querySelector('[data-bulk-form]');
        const bar = document.querySelector('[data-bulk-bar]');
        const count = document.querySelector('[data-bulk-count]');
        const all = document.querySelector('[data-select-all]');
        const rows = () => [...form.querySelectorAll('[data-row-check]')];
        const label = @json(__('desk-editor::articles.selected'));
        const refresh = () => {
            const n = rows().filter((c) => c.checked).length;
            bar.classList.toggle('translate-y-24', !n);
            bar.classList.toggle('opacity-0', !n);
            count.textContent = label.replace(':count', n);
            if (all) all.checked = n && n === rows().length;
        };
        form.addEventListener('change', (e) => {
            if (e.target === all) rows().forEach((c) => (c.checked = all.checked));
            refresh();
        });
        form.addEventListener('submit', (e) => {
            const message = e.submitter?.dataset.confirm;
            if (message && !confirm(message)) e.preventDefault();
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === '/' && !/input|textarea|select/i.test(document.activeElement.tagName)) {
                e.preventDefault();
                document.querySelector('[data-filters] input[name=q]').focus();
            }
        });
    })();
</script>
@endpush
