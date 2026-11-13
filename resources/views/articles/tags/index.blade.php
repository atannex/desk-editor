@extends('desk-editor::articles.layout')

@php
    $input = 'h-10 rounded-xl border border-zinc-200 bg-white px-3 text-sm text-zinc-900 shadow-xs outline-none placeholder:text-zinc-400 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/15 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-100';
@endphp

@section('title', __('desk-editor::articles.tags.title'))

@section('content')
<main class="mx-auto max-w-4xl px-4 pt-8 pb-32 sm:px-6">
    <div class="animate-de-rise mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('desk-editor::articles.tags.title') }}</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('desk-editor::articles.tags.subtitle') }}</p>
        </div>
        <p class="flex gap-3 text-xs text-zinc-500 tabular-nums dark:text-zinc-400">
            <span>{{ __('desk-editor::articles.tags.total', ['count' => number_format($total)]) }}</span>
            @if ($unused)<span class="text-amber-600 dark:text-amber-400">{{ __('desk-editor::articles.tags.unused', ['count' => $unused]) }}</span>@endif
        </p>
    </div>

    <form method="GET" class="mb-4 flex flex-col gap-2 sm:flex-row" data-tag-filters>
        <label class="relative flex-1">
            <svg viewBox="0 0 24 24" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-zinc-500 dark:text-zinc-400" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('desk-editor::articles.tags.search') }}" aria-label="{{ __('desk-editor::articles.tags.search') }}" class="{{ $input }} w-full pl-9">
        </label>
        <select name="sort" onchange="this.form.submit()" class="{{ $input }} cursor-pointer" aria-label="Sort">
            @foreach (['articles_count', 'name', 'created_at'] as $option)
                <option value="{{ $option }}" @selected($sort === $option)>{{ __('desk-editor::articles.tags.sort.'.$option) }}</option>
            @endforeach
        </select>
    </form>

    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
        @if ($tags->isEmpty())
            <div class="animate-de-rise flex flex-col items-center px-6 py-20 text-center">
                <div class="relative mb-5 grid size-16 place-items-center rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                    <svg viewBox="0 0 24 24" class="size-7 text-indigo-500" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12.586 2.586A2 2 0 0 0 11.172 2H4a2 2 0 0 0-2 2v7.172a2 2 0 0 0 .586 1.414l8.704 8.704a2.426 2.426 0 0 0 3.42 0l6.58-6.58a2.426 2.426 0 0 0 0-3.42z"/><circle cx="7.5" cy="7.5" r=".5" fill="currentColor"/></svg>
                </div>
                <h2 class="text-base font-semibold">{{ request('q') ? __('desk-editor::articles.tags.empty_search') : __('desk-editor::articles.tags.empty_title') }}</h2>
                @unless (request('q'))<p class="mt-1 max-w-sm text-sm text-zinc-500 dark:text-zinc-400">{{ __('desk-editor::articles.tags.empty_body') }}</p>@endunless
            </div>
        @else
            <div class="grid grid-cols-[2.5rem_minmax(0,1fr)_6rem_5.5rem] items-center gap-3 border-b border-zinc-200 bg-zinc-50/80 px-4 py-2.5 text-[11px] font-semibold tracking-wider text-zinc-600 uppercase dark:border-zinc-800 dark:bg-zinc-900/60 dark:text-zinc-400">
                <input type="checkbox" data-select-all class="size-4 cursor-pointer accent-indigo-600" aria-label="Select all">
                <span>{{ __('desk-editor::articles.tags.name') }}</span>
                <span class="text-right">{{ __('desk-editor::articles.tags.articles') }}</span>
                <span></span>
            </div>
            <ul class="divide-y divide-zinc-100 dark:divide-zinc-800" data-tag-list>
                @foreach ($tags as $i => $tag)
                    <li class="animate-de-rise group grid grid-cols-[2.5rem_minmax(0,1fr)_6rem_5.5rem] items-center gap-3 px-4 py-2.5 transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/40" style="animation-delay: {{ min($i, 12) * 20 }}ms" data-tag-row data-name="{{ $tag->name }}">
                        <input type="checkbox" value="{{ $tag->id }}" data-row-check data-name="{{ $tag->name }}" class="size-4 cursor-pointer accent-indigo-600" aria-label="Select {{ $tag->name }}">
                        <div class="min-w-0">
                            <div data-tag-view class="flex min-w-0 items-center gap-2">
                                <span class="truncate font-medium">#{{ $tag->name }}</span>
                                <span class="hidden truncate font-mono text-xs text-zinc-500 dark:text-zinc-400 sm:inline">{{ $tag->slug }}</span>
                            </div>
                            <form data-tag-rename method="POST" action="{{ route('desk-editor.tags.update', $tag) }}" class="hidden items-center gap-1.5">
                                @csrf @method('PUT')
                                <input type="text" name="name" value="{{ $tag->name }}" required maxlength="100" class="h-8 min-w-0 flex-1 rounded-lg border border-indigo-400 bg-white px-2 text-sm text-zinc-900 outline-none focus:ring-4 focus:ring-indigo-500/15 dark:bg-zinc-950 dark:text-zinc-100" aria-label="{{ __('desk-editor::articles.tags.rename') }}">
                                <button type="submit" class="h-8 shrink-0 cursor-pointer rounded-lg border-0 bg-indigo-600 px-2.5 text-xs font-medium text-white hover:bg-indigo-500">{{ __('desk-editor::articles.tags.save') }}</button>
                                <button type="button" data-rename-cancel class="h-8 shrink-0 cursor-pointer rounded-lg border-0 bg-transparent px-2 text-xs font-medium text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-800">{{ __('desk-editor::articles.tags.cancel') }}</button>
                            </form>
                        </div>
                        <a href="{{ route('desk-editor.articles.index', ['q' => $tag->name]) }}" @class(['justify-self-end rounded-full px-2 py-0.5 text-xs font-medium no-underline tabular-nums transition', 'bg-zinc-100 text-zinc-700 hover:bg-indigo-50 hover:text-indigo-700 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-indigo-400/10 dark:hover:text-indigo-300' => $tag->articles_count, 'bg-amber-50 text-amber-700 dark:bg-amber-400/10 dark:text-amber-300' => ! $tag->articles_count])>{{ number_format($tag->articles_count ?? 0) }}</a>
                        <div class="flex justify-end gap-0.5 opacity-100 transition sm:opacity-0 sm:group-hover:opacity-100 sm:focus-within:opacity-100">
                            <button type="button" data-rename class="grid size-8 cursor-pointer place-items-center rounded-lg border-0 bg-transparent text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-900 dark:hover:bg-zinc-800 dark:hover:text-white" aria-label="{{ __('desk-editor::articles.tags.rename') }}: {{ $tag->name }}" title="{{ __('desk-editor::articles.tags.rename') }}">
                                <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.4 3.6a2.1 2.1 0 1 1 3 3L7 19l-4 1 1-4Z"/></svg>
                            </button>
                            <form method="POST" action="{{ route('desk-editor.tags.destroy', $tag) }}" data-confirm="{{ __('desk-editor::articles.tags.confirm_delete') }}">
                                @csrf @method('DELETE')
                                <button type="submit" class="grid size-8 cursor-pointer place-items-center rounded-lg border-0 bg-transparent text-zinc-500 transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10 dark:hover:text-red-400" aria-label="{{ __('desk-editor::articles.tags.delete') }}: {{ $tag->name }}" title="{{ __('desk-editor::articles.tags.delete') }}">
                                    <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                </button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    @if ($tags->hasPages())
        <nav class="mt-6 flex items-center justify-between gap-4 text-sm" aria-label="Pagination">
            <p class="text-zinc-500 dark:text-zinc-400">{{ $tags->firstItem() }}–{{ $tags->lastItem() }} / {{ $tags->total() }}</p>
            <div class="flex gap-1">
                @if (! $tags->onFirstPage())<a href="{{ $tags->previousPageUrl() }}" class="inline-flex h-9 items-center rounded-lg px-3 text-zinc-700 no-underline hover:bg-zinc-200/60 dark:text-zinc-300 dark:hover:bg-zinc-800">‹</a>@endif
                @if ($tags->hasMorePages())<a href="{{ $tags->nextPageUrl() }}" class="inline-flex h-9 items-center rounded-lg px-3 text-zinc-700 no-underline hover:bg-zinc-200/60 dark:text-zinc-300 dark:hover:bg-zinc-800">›</a>@endif
            </div>
        </nav>
    @endif

    {{-- Bulk bar: merge / delete --}}
    <div data-bulk-bar class="pointer-events-none fixed inset-x-0 bottom-6 z-40 flex translate-y-28 justify-center px-4 opacity-0 transition duration-300 ease-out">
        <div class="pointer-events-auto flex w-full max-w-2xl flex-col gap-2 rounded-2xl border border-zinc-200 bg-white/95 p-2 shadow-2xl shadow-zinc-900/20 backdrop-blur sm:flex-row sm:items-center sm:pl-4 dark:border-zinc-700 dark:bg-zinc-900/95">
            <span class="text-sm font-medium whitespace-nowrap" data-bulk-count></span>
            <form method="POST" action="{{ route('desk-editor.tags.merge') }}" class="flex flex-1 gap-1.5" data-merge-form>
                @csrf
                <div data-merge-ids></div>
                <input type="text" name="target" maxlength="100" list="desk-tag-names" placeholder="{{ __('desk-editor::articles.tags.merge_into') }}" aria-label="{{ __('desk-editor::articles.tags.merge_into') }}" title="{{ __('desk-editor::articles.tags.merge_hint') }}" class="h-9 min-w-0 flex-1 rounded-lg border border-zinc-200 bg-white px-3 text-sm text-zinc-900 outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/15 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                <datalist id="desk-tag-names">@foreach ($tags as $tag)<option value="{{ $tag->name }}">@endforeach</datalist>
                <button type="submit" class="h-9 shrink-0 cursor-pointer rounded-lg border-0 bg-indigo-600 px-3 text-sm font-medium text-white transition hover:bg-indigo-500 active:scale-95">{{ __('desk-editor::articles.tags.merge') }}</button>
            </form>
            <form method="POST" action="{{ route('desk-editor.tags.bulk-destroy') }}" data-confirm="{{ __('desk-editor::articles.tags.confirm_bulk_delete') }}" data-delete-form>
                @csrf
                <div data-delete-ids></div>
                <button type="submit" class="h-9 w-full cursor-pointer rounded-lg border-0 bg-transparent px-3 text-sm font-medium text-red-600 transition hover:bg-red-50 active:scale-95 dark:text-red-400 dark:hover:bg-red-500/10">{{ __('desk-editor::articles.tags.delete') }}</button>
            </form>
        </div>
    </div>
</main>
@endsection

@push('desk-scripts')
<script>
(() => {
    const list = document.querySelector('[data-tag-list]');
    document.querySelectorAll('form[data-confirm]').forEach((f) => f.addEventListener('submit', (e) => { if (!confirm(f.dataset.confirm)) e.preventDefault(); }));
    if (!list) return;

    // Inline rename
    list.addEventListener('click', (e) => {
        const row = e.target.closest('[data-tag-row]');
        if (!row) return;
        const view = row.querySelector('[data-tag-view]');
        const rename = row.querySelector('[data-tag-rename]');
        if (e.target.closest('[data-rename]')) {
            view.classList.add('hidden');
            rename.classList.replace('hidden', 'flex');
            rename.querySelector('input').select();
        }
        if (e.target.closest('[data-rename-cancel]')) {
            rename.classList.replace('flex', 'hidden');
            view.classList.remove('hidden');
        }
    });
    list.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') e.target.closest('[data-tag-row]')?.querySelector('[data-rename-cancel]')?.click();
    });

    // Selection + bulk bar
    const bar = document.querySelector('[data-bulk-bar]');
    const count = document.querySelector('[data-bulk-count]');
    const all = document.querySelector('[data-select-all]');
    const rows = () => [...list.querySelectorAll('[data-row-check]')];
    const label = @json(__('desk-editor::articles.selected'));
    const fill = (container, ids) => {
        container.replaceChildren(...ids.map((id) => Object.assign(document.createElement('input'), { type: 'hidden', name: 'ids[]', value: id })));
    };
    const refresh = () => {
        const checked = rows().filter((c) => c.checked);
        bar.classList.toggle('translate-y-28', !checked.length);
        bar.classList.toggle('opacity-0', !checked.length);
        count.textContent = label.replace(':count', checked.length);
        if (all) all.checked = checked.length && checked.length === rows().length;
        const ids = checked.map((c) => c.value);
        fill(document.querySelector('[data-merge-ids]'), ids);
        fill(document.querySelector('[data-delete-ids]'), ids);
        const target = document.querySelector('[data-merge-form] [name=target]');
        if (checked.length && !target.value) target.placeholder = checked[0].dataset.name;
    };
    all?.addEventListener('change', () => { rows().forEach((c) => (c.checked = all.checked)); refresh(); });
    list.addEventListener('change', refresh);
    document.querySelector('[data-merge-form]').addEventListener('submit', (e) => {
        const target = e.target.querySelector('[name=target]');
        if (!target.value.trim()) target.value = target.placeholder;
    });
})();
</script>
@endpush
