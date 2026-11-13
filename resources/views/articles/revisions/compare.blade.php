@extends('desk-editor::articles.layout')

@php
    use Atannex\DeskEditor\Articles\Support\Differ;
    $authorsInfo = app(\Atannex\DeskEditor\Articles\Support\Authors::class);
    $canRestore = $deskPolicy->allows('update', $article);
    $identical = $added === 0 && $removed === 0 && collect($sections)->every(fn ($s) => collect($s['rows'])->every(fn ($r) => $r['type'] === Differ::EQUAL));
    $select = 'h-10 w-full min-w-0 cursor-pointer rounded-xl border border-zinc-200 bg-white px-3 text-sm text-zinc-900 shadow-xs outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/15 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-100';
    $versionLabel = function ($revision) use ($authorsInfo) {
        $who = $authorsInfo->nameOf($revision->author);
        return $revision->created_at?->format('M j, H:i').($revision->note ? ' — '.\Illuminate\Support\Str::limit($revision->note, 40) : '').($who ? ' · '.$who : '');
    };
@endphp

@section('title', __('desk-editor::articles.diff.title').' · '.$article->title)

@section('header-actions')
    <a href="{{ route('desk-editor.articles.edit', $article) }}" class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-zinc-200 bg-white px-3 text-sm font-medium text-zinc-800 no-underline shadow-xs transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:hover:bg-zinc-800">
        <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m15 18-6-6 6-6"/></svg>
        <span class="hidden sm:inline">{{ __('desk-editor::articles.diff.back') }}</span>
    </a>
@endsection

@section('content')
<main class="mx-auto max-w-6xl px-4 pt-8 pb-24 sm:px-6" data-diff data-mode="{{ $mode }}">
    <div class="animate-de-rise mb-6">
        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $article->title }}</p>
        <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('desk-editor::articles.diff.title') }}</h1>
    </div>

    {{-- Version pickers --}}
    <form method="GET" class="animate-de-rise mb-6 grid gap-3 rounded-2xl border border-zinc-200 bg-white p-4 shadow-xs sm:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)_auto] sm:items-end dark:border-zinc-800 dark:bg-zinc-900" data-diff-form>
        <input type="hidden" name="mode" value="{{ $mode }}" data-mode-input>
        <label class="block min-w-0">
            <span class="mb-1.5 flex items-center gap-1.5 text-[13px] font-medium text-zinc-700 dark:text-zinc-300"><span class="size-2 rounded-full bg-rose-500"></span>{{ __('desk-editor::articles.diff.from') }}</span>
            <select name="from" class="{{ $select }}" onchange="this.form.submit()">
                <option value="current" @selected($from['key'] === 'current')>{{ __('desk-editor::articles.diff.current') }}</option>
                @foreach ($revisions as $revision)
                    <option value="{{ $revision->id }}" @selected($from['key'] === (string) $revision->id)>{{ $versionLabel($revision) }}</option>
                @endforeach
            </select>
        </label>
        <a href="{{ route('desk-editor.articles.revisions.compare', [$article, 'from' => $to['key'], 'to' => $from['key'], 'mode' => $mode]) }}" class="grid h-10 w-10 place-items-center self-end justify-self-center rounded-xl border border-zinc-200 text-zinc-500 no-underline transition hover:bg-zinc-50 hover:text-zinc-900 dark:border-zinc-700 dark:hover:bg-zinc-800 dark:hover:text-white" title="{{ __('desk-editor::articles.diff.swap') }}" aria-label="{{ __('desk-editor::articles.diff.swap') }}">
            <svg viewBox="0 0 24 24" class="size-4 rotate-90 sm:rotate-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m16 3 4 4-4 4M20 7H4M8 21l-4-4 4-4M4 17h16"/></svg>
        </a>
        <label class="block min-w-0">
            <span class="mb-1.5 flex items-center gap-1.5 text-[13px] font-medium text-zinc-700 dark:text-zinc-300"><span class="size-2 rounded-full bg-emerald-500"></span>{{ __('desk-editor::articles.diff.to') }}</span>
            <select name="to" class="{{ $select }}" onchange="this.form.submit()">
                <option value="current" @selected($to['key'] === 'current')>{{ __('desk-editor::articles.diff.current') }}</option>
                @foreach ($revisions as $revision)
                    <option value="{{ $revision->id }}" @selected($to['key'] === (string) $revision->id)>{{ $versionLabel($revision) }}</option>
                @endforeach
            </select>
        </label>
        <noscript><button type="submit" class="h-10 rounded-xl bg-indigo-600 px-4 text-sm font-medium text-white">{{ __('desk-editor::articles.diff.compare') }}</button></noscript>
    </form>

    {{-- Toolbar: stats + mode --}}
    <div class="animate-de-rise mb-4 flex flex-wrap items-center gap-2">
        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-800 ring-1 ring-emerald-600/20 ring-inset dark:bg-emerald-400/10 dark:text-emerald-300 dark:ring-emerald-400/20" data-diff-added>+ {{ __('desk-editor::articles.diff.added', ['count' => number_format($added)]) }}</span>
        <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-50 px-2.5 py-1 text-xs font-medium text-rose-800 ring-1 ring-rose-600/20 ring-inset dark:bg-rose-400/10 dark:text-rose-300 dark:ring-rose-400/20" data-diff-removed>− {{ __('desk-editor::articles.diff.removed', ['count' => number_format($removed)]) }}</span>
        <div class="flex-1"></div>
        <div class="inline-flex rounded-xl border border-zinc-200 bg-white p-0.5 shadow-xs dark:border-zinc-800 dark:bg-zinc-900" role="group" aria-label="View">
            @foreach (['inline', 'split'] as $option)
                <button type="button" data-mode-button="{{ $option }}" aria-pressed="{{ $mode === $option ? 'true' : 'false' }}"
                        class="h-8 cursor-pointer rounded-lg border-0 px-3 text-sm font-medium transition aria-pressed:bg-zinc-900 aria-pressed:text-white dark:aria-pressed:bg-white dark:aria-pressed:text-zinc-900 bg-transparent text-zinc-600 hover:text-zinc-900 dark:text-zinc-300 dark:hover:text-white">
                    {{ __('desk-editor::articles.diff.'.$option) }}
                </button>
            @endforeach
        </div>
    </div>

    @if ($identical)
        <div class="animate-de-rise rounded-2xl border border-dashed border-zinc-300 px-6 py-16 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">{{ __('desk-editor::articles.diff.identical') }}</div>
    @endif

    {{-- Version headers (split mode) --}}
    <div data-split-only @class(['mb-2 grid-cols-2 gap-4 text-xs text-zinc-500 dark:text-zinc-400', 'hidden' => $mode !== 'split', 'grid' => $mode === 'split'])>
        @foreach ([$from, $to] as $side)
            <div class="flex min-w-0 items-center justify-between gap-2 px-1">
                <span class="truncate"><span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $side['key'] === 'current' ? __('desk-editor::articles.diff.current') : $side['date']?->format('M j, Y H:i') }}</span> · {{ __('desk-editor::articles.words', ['count' => number_format($side['words'])]) }}</span>
                @if ($side['revision'] && $canRestore)
                    <button type="submit" form="restore-{{ $side['revision']->id }}" class="shrink-0 cursor-pointer rounded-md border-0 bg-transparent px-2 py-0.5 font-medium text-indigo-600 hover:bg-indigo-50 dark:text-indigo-400 dark:hover:bg-indigo-400/10">{{ __('desk-editor::articles.diff.restore_this') }}</button>
                @endif
            </div>
        @endforeach
    </div>

    <div class="flex flex-col gap-4">
        @foreach ($sections as $name => $section)
            @php($changed = collect($section['rows'])->contains(fn ($r) => $r['type'] !== Differ::EQUAL))
            @continue($name === 'excerpt' && ! $changed && $section['rows'] === [])
            <section class="animate-de-rise overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-800 dark:bg-zinc-900" data-diff-section="{{ $name }}">
                <header class="flex items-center justify-between gap-2 border-b border-zinc-100 px-4 py-2.5 dark:border-zinc-800">
                    <h2 class="text-sm font-semibold">{{ __('desk-editor::articles.diff.sections.'.$name) }}</h2>
                    @if ($changed)
                        <span class="text-[11px] text-zinc-500 tabular-nums dark:text-zinc-400"><span class="text-emerald-600 dark:text-emerald-400">+{{ $section['added'] }}</span> / <span class="text-rose-600 dark:text-rose-400">−{{ $section['removed'] }}</span></span>
                    @else
                        <span class="text-[11px] text-zinc-500 dark:text-zinc-400">{{ __('desk-editor::articles.diff.no_changes') }}</span>
                    @endif
                </header>

                {{-- Inline --}}
                <div data-inline-only @class(['divide-y divide-zinc-100 dark:divide-zinc-800/70', 'hidden' => $mode === 'split'])>
                    @forelse ($section['rows'] as $row)
                        <div @class([
                            'flex gap-3 px-4 py-2 text-[15px] leading-relaxed',
                            'text-lg font-semibold' => $name === 'title',
                            'bg-emerald-50/50 dark:bg-emerald-500/5' => $row['type'] === Differ::INSERT,
                            'bg-rose-50/50 dark:bg-rose-500/5' => $row['type'] === Differ::DELETE,
                            'bg-amber-50/30 dark:bg-amber-400/5' => $row['type'] === Differ::CHANGE,
                        ])>
                            <span @class(['w-3 shrink-0 pt-0.5 font-mono text-xs select-none', 'text-emerald-600' => $row['type'] === Differ::INSERT, 'text-rose-600' => $row['type'] === Differ::DELETE, 'text-amber-600' => $row['type'] === Differ::CHANGE, 'text-transparent' => $row['type'] === Differ::EQUAL]) aria-hidden="true">{{ ['insert' => '+', 'delete' => '−', 'change' => '~', 'equal' => '·'][$row['type']] }}</span>
                            <div class="min-w-0 flex-1 break-words">{!! $row['inline'] !!}</div>
                        </div>
                    @empty
                        <p class="px-4 py-3 text-sm text-zinc-500 dark:text-zinc-400">—</p>
                    @endforelse
                </div>

                {{-- Side by side --}}
                <div data-split-only @class(['divide-y divide-zinc-100 dark:divide-zinc-800/70', 'hidden' => $mode !== 'split'])>
                    @forelse ($section['rows'] as $row)
                        <div @class(['grid grid-cols-2 divide-x divide-zinc-100 text-[15px] leading-relaxed dark:divide-zinc-800/70', 'text-lg font-semibold' => $name === 'title'])>
                            <div @class(['min-w-0 px-4 py-2 break-words', 'bg-rose-50/60 dark:bg-rose-500/5' => in_array($row['type'], [Differ::DELETE, Differ::CHANGE], true), 'bg-zinc-50/60 dark:bg-zinc-950/40' => $row['type'] === Differ::INSERT])>{!! $row['old'] ?? '' !!}</div>
                            <div @class(['min-w-0 px-4 py-2 break-words', 'bg-emerald-50/60 dark:bg-emerald-500/5' => in_array($row['type'], [Differ::INSERT, Differ::CHANGE], true), 'bg-zinc-50/60 dark:bg-zinc-950/40' => $row['type'] === Differ::DELETE])>{!! $row['new'] ?? '' !!}</div>
                        </div>
                    @empty
                        <p class="px-4 py-3 text-sm text-zinc-500 dark:text-zinc-400">—</p>
                    @endforelse
                </div>
            </section>
        @endforeach
    </div>

    @if ($canRestore && ($from['revision'] || $to['revision']))
        <div class="mt-6 flex flex-wrap justify-end gap-2" data-inline-only>
            @foreach ([$from, $to] as $side)
                @if ($side['revision'])
                    <button type="submit" form="restore-{{ $side['revision']->id }}" class="inline-flex h-9 cursor-pointer items-center gap-1.5 rounded-lg border border-zinc-200 bg-white px-3.5 text-sm font-medium text-zinc-800 shadow-xs transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:hover:bg-zinc-800">
                        <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8M3 3v5h5"/></svg>
                        {{ __('desk-editor::articles.diff.restore_this') }} · {{ $side['date']?->format('M j, H:i') }}
                    </button>
                @endif
            @endforeach
        </div>
    @endif

    @if ($canRestore)
        @foreach (array_filter([$from['revision'], $to['revision']]) as $revision)
            <form id="restore-{{ $revision->id }}" method="POST" action="{{ route('desk-editor.articles.revisions.restore', [$article, $revision->id]) }}" data-confirm="{{ __('desk-editor::articles.confirm_restore') }}" hidden>@csrf</form>
        @endforeach
    @endif
</main>
@endsection

@push('desk-scripts')
<script>
    (() => {
        const root = document.querySelector('[data-diff]');
        const input = root.querySelector('[data-mode-input]');
        const apply = (mode) => {
            root.dataset.mode = mode;
            input.value = mode;
            root.querySelectorAll('[data-inline-only]').forEach((el) => el.classList.toggle('hidden', mode !== 'inline'));
            root.querySelectorAll('[data-split-only]').forEach((el) => {
                el.classList.toggle('hidden', mode !== 'split');
                if (el.classList.contains('grid-cols-2') && !el.classList.contains('divide-y')) el.classList.toggle('grid', mode === 'split');
            });
            root.querySelectorAll('[data-mode-button]').forEach((b) => b.setAttribute('aria-pressed', b.dataset.modeButton === mode ? 'true' : 'false'));
            const url = new URL(location.href);
            url.searchParams.set('mode', mode);
            history.replaceState(null, '', url);
            try { localStorage.setItem('desk-diff-mode', mode); } catch (e) {}
        };
        root.querySelectorAll('[data-mode-button]').forEach((b) => b.addEventListener('click', () => apply(b.dataset.modeButton)));
        if (!new URL(location.href).searchParams.has('mode')) {
            try { const saved = localStorage.getItem('desk-diff-mode'); if (saved && saved !== root.dataset.mode) apply(saved); } catch (e) {}
        }
        // Side-by-side needs room; fall back to inline on narrow screens.
        if (matchMedia('(max-width: 639px)').matches && root.dataset.mode === 'split') apply('inline');
        document.querySelectorAll('form[data-confirm]').forEach((f) => f.addEventListener('submit', (e) => { if (!confirm(f.dataset.confirm)) e.preventDefault(); }));
    })();
</script>
@endpush
