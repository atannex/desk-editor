@extends('desk-editor::articles.layout')

@section('title', __('desk-editor::media.title'))

@php
    $icons = [
        'image' => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.1-3.1a2 2 0 0 0-2.8 0L6 21"/>',
        'video' => '<path d="m16 13 5.2 3.5a.5.5 0 0 0 .8-.4V7.9a.5.5 0 0 0-.8-.4L16 10.5"/><rect x="2" y="6" width="14" height="12" rx="2"/>',
        'audio' => '<path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>',
        'file' => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/>',
        'all' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
    ];
    $tabs = ['' => [__('desk-editor::media.all'), $total, 'all']];
    foreach (\Atannex\DeskEditor\Media\Models\DeskMedia::KINDS as $k) {
        $tabs[$k] = [__('desk-editor::media.kinds.'.$k), (int) ($counts[$k] ?? 0), $k];
    }
    $filtered = $kind !== null || $search !== '';
    $duration = fn (?float $s) => $s === null ? null : sprintf('%d:%02d', intdiv((int) round($s), 60), (int) round($s) % 60);
    $endpoints = ['upload' => route('desk-editor.upload'), 'item' => route('desk-editor.media.show', ['media' => '__ID__'])];
    $strings = ['copied' => __('desk-editor::media.copied'), 'copy' => __('desk-editor::media.copy'), 'saving' => __('desk-editor::media.saving'), 'saved' => __('desk-editor::media.saved'), 'saveFailed' => __('desk-editor::media.save_failed'), 'usageLoading' => __('desk-editor::media.usage_loading'), 'usageNone' => __('desk-editor::media.usage_none'), 'usageTypes' => __('desk-editor::media.usage_types'), 'confirmDelete' => __('desk-editor::media.confirm_delete'), 'confirmDeleteUsed' => __('desk-editor::media.confirm_delete_used'), 'deleted' => __('desk-editor::media.deleted'), 'deleteFailed' => __('desk-editor::media.delete_failed'), 'uploading' => __('desk-editor::media.uploading'), 'processing' => __('desk-editor::media.processing'), 'uploaded' => __('desk-editor::media.uploaded'), 'uploadFailed' => __('desk-editor::media.upload_failed'), 'dismiss' => __('desk-editor::media.dismiss'), 'status' => __('desk-editor::media.status'), 'kind' => __('desk-editor::media.kind')];
@endphp

@section('header-actions')
    @if (\Illuminate\Support\Facades\Route::has('desk-editor.articles.index'))
        <a href="{{ route('desk-editor.articles.index') }}" class="hidden h-9 items-center gap-1.5 rounded-lg px-3 text-sm font-medium text-zinc-600 transition hover:bg-zinc-100 hover:text-zinc-900 sm:inline-flex dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-white">
            <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16v16H4zM8 8h8M8 12h8M8 16h5"/></svg>
            {{ __('desk-editor::media.articles') }}
        </a>
    @endif
    <button type="button" data-upload-trigger class="inline-flex h-9 cursor-pointer items-center gap-1.5 rounded-lg bg-indigo-600 px-3.5 text-sm font-medium text-white shadow-sm shadow-indigo-600/25 transition hover:bg-indigo-500 active:scale-[.98] dark:bg-indigo-500 dark:hover:bg-indigo-400">
        <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 15V3M7 8l5-5 5 5M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/></svg>
        {{ __('desk-editor::media.upload') }}
    </button>
@endsection

@section('content')
<main class="mx-auto max-w-7xl px-4 pt-8 pb-28 sm:px-6" data-media-page>
    <div class="animate-de-rise mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('desk-editor::media.title') }}</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('desk-editor::media.subtitle') }}</p>
        </div>
        <span class="inline-flex items-center gap-2 rounded-full border border-zinc-200 bg-white px-3 py-1 text-xs font-medium text-zinc-600 shadow-xs dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300">
            <span class="size-1.5 rounded-full bg-indigo-500"></span>
            {{ __('desk-editor::media.stats', ['count' => number_format($total), 'size' => $storage]) }}
        </span>
    </div>

    {{-- Upload zone --}}
    <section class="animate-de-rise mb-6" style="animation-delay: 40ms">
        <label data-dropzone class="group relative flex cursor-pointer flex-col items-center justify-center gap-3 overflow-hidden rounded-2xl border-2 border-dashed border-zinc-300 bg-white px-6 py-8 text-center transition hover:border-indigo-400 hover:bg-indigo-50/40 sm:flex-row sm:gap-4 sm:text-left dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-indigo-500/60 dark:hover:bg-indigo-500/5">
            <input type="file" multiple data-upload-input class="sr-only">
            <span class="pointer-events-none absolute inset-0 bg-gradient-to-br from-indigo-500/0 via-indigo-500/0 to-violet-500/0 transition group-hover:from-indigo-500/5 group-hover:to-violet-500/10"></span>
            <span class="relative grid size-12 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 text-white shadow-lg shadow-indigo-600/25 transition-transform duration-300 group-hover:-translate-y-0.5 group-hover:rotate-3">
                <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 15V3M7 8l5-5 5 5M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/></svg>
            </span>
            <span class="relative">
                <span class="block text-sm font-semibold" data-drop-title>{{ __('desk-editor::media.drop_title') }}</span>
                <span class="mt-0.5 block text-[13px] text-zinc-500 dark:text-zinc-400">{{ __('desk-editor::media.drop_body') }}</span>
            </span>
        </label>
        <ul data-upload-list class="mt-3 grid gap-2 empty:hidden sm:grid-cols-2 lg:grid-cols-3"></ul>
    </section>

    {{-- Kind tabs --}}
    <nav data-kind-tabs class="-mx-4 mb-4 flex gap-1 overflow-x-auto px-4 sm:mx-0 sm:px-0" aria-label="{{ __('desk-editor::media.title') }}">
        @foreach ($tabs as $value => [$label, $count, $icon])
            @php $active = (string) $kind === (string) $value; @endphp
            <a href="{{ route('desk-editor.media.manage', array_filter(['kind' => $value ?: null, 'q' => $search ?: null])) }}"
               @class([
                   'inline-flex shrink-0 items-center gap-2 rounded-lg px-3 py-1.5 text-sm font-medium transition',
                   'bg-zinc-900 text-white shadow-sm dark:bg-white dark:text-zinc-900' => $active,
                   'text-zinc-600 hover:bg-zinc-200/60 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white' => ! $active,
               ]) @if ($active) aria-current="page" @endif>
                <svg viewBox="0 0 24 24" class="size-4 opacity-80" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $icons[$icon] !!}</svg>
                {{ $label }}
                <span @class(['rounded-md px-1.5 text-[11px] tabular-nums', 'bg-white/20 dark:bg-zinc-900/10' => $active, 'bg-zinc-200/70 dark:bg-zinc-800' => ! $active])>{{ $count }}</span>
            </a>
        @endforeach
    </nav>

    {{-- Search --}}
    <form method="GET" class="mb-6 flex gap-2" data-filters>
        @if ($kind)<input type="hidden" name="kind" value="{{ $kind }}">@endif
        <label class="relative flex-1">
            <svg viewBox="0 0 24 24" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-zinc-500 dark:text-zinc-400" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input type="search" name="q" value="{{ $search }}" placeholder="{{ __('desk-editor::media.search') }}" class="h-10 w-full rounded-xl border border-zinc-200 bg-white pr-3 pl-9 text-sm shadow-xs transition outline-none placeholder:text-zinc-400 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/15 dark:border-zinc-800 dark:bg-zinc-900">
        </label>
    </form>

    {{-- Library (re-fetched in place after uploads) --}}
    <div data-library>
        <script type="application/json" data-items>@json((object) $items)</script>

        @if ($media->isEmpty())
            <div class="animate-de-rise flex flex-col items-center rounded-2xl border border-zinc-200 bg-white px-6 py-20 text-center shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                <div class="relative mb-5">
                    <div class="absolute inset-0 animate-pulse rounded-full bg-indigo-500/20 blur-2xl"></div>
                    <div class="relative grid size-16 place-items-center rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                        <svg viewBox="0 0 24 24" class="size-7 text-indigo-500" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $icons['image'] !!}</svg>
                    </div>
                </div>
                <h2 class="text-base font-semibold">{{ $filtered ? __('desk-editor::media.empty_filtered') : __('desk-editor::media.empty_title') }}</h2>
                <p class="mt-1 max-w-sm text-sm text-zinc-500 dark:text-zinc-400">{{ __('desk-editor::media.empty_body') }}</p>
                <div class="mt-6 flex gap-2">
                    @if ($filtered)
                        <a href="{{ route('desk-editor.media.manage') }}" class="inline-flex h-9 items-center rounded-lg border border-zinc-200 px-3.5 text-sm font-medium transition hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800">{{ __('desk-editor::media.clear_filters') }}</a>
                    @endif
                    <button type="button" data-upload-trigger class="inline-flex h-9 cursor-pointer items-center rounded-lg bg-indigo-600 px-3.5 text-sm font-medium text-white transition hover:bg-indigo-500">{{ __('desk-editor::media.upload') }}</button>
                </div>
            </div>
        @else
            <div class="columns-2 gap-3 sm:columns-3 lg:columns-4 xl:columns-5">
                @foreach ($media as $i => $item)
                    @php
                        $ratio = ($item->width && $item->height) ? max(0.5, min(2.4, $item->width / $item->height)) : ($item->kind === 'video' ? 16 / 9 : 4 / 3);
                        $thumb = $item->thumbUrl();
                        $ext = \Illuminate\Support\Str::upper(pathinfo($item->path, PATHINFO_EXTENSION) ?: 'file');
                    @endphp
                    <button type="button" data-media-card="{{ $item->id }}"
                        class="group animate-de-rise relative mb-3 block w-full cursor-pointer break-inside-avoid overflow-hidden rounded-xl border border-zinc-200 bg-white text-left shadow-xs transition duration-200 hover:-translate-y-0.5 hover:shadow-lg hover:shadow-zinc-900/5 focus-visible:ring-4 focus-visible:ring-indigo-500/30 focus-visible:outline-none dark:border-zinc-800 dark:bg-zinc-900 dark:hover:shadow-black/30"
                        style="animation-delay: {{ min($i, 16) * 20 }}ms">
                        <div class="relative overflow-hidden bg-zinc-100 dark:bg-zinc-800" style="aspect-ratio: {{ round($ratio, 4) }}">
                            @if ($item->kind === 'image')
                                <img src="{{ $thumb }}" @if ($item->variantsSrcset()) srcset="{{ $item->variantsSrcset() }}" sizes="(min-width: 1280px) 20vw, (min-width: 1024px) 25vw, (min-width: 640px) 33vw, 50vw" @endif alt="{{ $item->alt }}" loading="lazy" decoding="async" class="size-full object-cover transition duration-500 group-hover:scale-[1.03]">
                            @elseif ($item->kind === 'video')
                                @if ($thumb)
                                    <img src="{{ $thumb }}" alt="" loading="lazy" decoding="async" class="size-full object-cover transition duration-500 group-hover:scale-[1.03]">
                                @else
                                    <div class="grid size-full place-items-center bg-gradient-to-br from-zinc-800 to-zinc-950 text-zinc-500">
                                        <svg viewBox="0 0 24 24" class="size-8" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">{!! $icons['video'] !!}</svg>
                                    </div>
                                @endif
                                <span class="absolute inset-0 grid place-items-center">
                                    <span class="grid size-11 place-items-center rounded-full bg-black/45 text-white ring-1 ring-white/30 backdrop-blur-sm transition duration-300 group-hover:scale-110 group-hover:bg-indigo-600/90">
                                        <svg viewBox="0 0 24 24" class="ml-0.5 size-4" fill="currentColor"><path d="M8 5.1v13.8a1 1 0 0 0 1.5.9l10.6-6.9a1 1 0 0 0 0-1.7L9.5 4.2A1 1 0 0 0 8 5.1Z"/></svg>
                                    </span>
                                </span>
                            @elseif ($item->kind === 'audio')
                                <div class="flex size-full flex-col items-center justify-center gap-3 bg-gradient-to-br from-violet-500/15 via-fuchsia-500/10 to-indigo-500/15 text-violet-600 dark:text-violet-300">
                                    <svg viewBox="0 0 24 24" class="size-8" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $icons['audio'] !!}</svg>
                                    <span class="flex h-6 items-end gap-0.5 opacity-70" aria-hidden="true">
                                        @foreach ([40, 75, 55, 100, 65, 85, 45, 70, 35, 60] as $h)
                                            <span class="w-1 rounded-full bg-current transition-all duration-500 group-hover:opacity-100" style="height: {{ $h }}%"></span>
                                        @endforeach
                                    </span>
                                </div>
                            @else
                                <div class="flex size-full flex-col items-center justify-center gap-2 bg-gradient-to-br from-sky-500/10 via-zinc-500/5 to-indigo-500/10 text-sky-600 dark:text-sky-300">
                                    <svg viewBox="0 0 24 24" class="size-8" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $icons['file'] !!}</svg>
                                    <span class="rounded-md bg-white/80 px-1.5 py-0.5 text-[11px] font-semibold tracking-wide text-zinc-700 ring-1 ring-black/5 dark:bg-zinc-900/80 dark:text-zinc-200 dark:ring-white/10">{{ \Illuminate\Support\Str::limit($ext, 5, '') }}</span>
                                </div>
                            @endif

                            @if (in_array($item->kind, ['video', 'audio'], true) && $item->duration)
                                <span class="absolute right-2 bottom-2 rounded-md bg-black/65 px-1.5 py-0.5 text-[11px] font-medium text-white tabular-nums backdrop-blur-sm">{{ $duration($item->duration) }}</span>
                            @endif
                            @if ($item->status !== 'ready')
                                <span @class([
                                    'absolute top-2 left-2 inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-medium shadow-sm backdrop-blur',
                                    'bg-amber-50/90 text-amber-700 ring-1 ring-amber-600/20 dark:bg-amber-500/20 dark:text-amber-300' => $item->status === 'processing',
                                    'bg-red-50/90 text-red-700 ring-1 ring-red-600/20 dark:bg-red-500/20 dark:text-red-300' => $item->status === 'failed',
                                ])>
                                    <span @class(['size-1.5 rounded-full', 'animate-pulse bg-amber-500' => $item->status === 'processing', 'bg-red-500' => $item->status === 'failed'])></span>
                                    {{ __('desk-editor::media.status.'.$item->status) }}
                                </span>
                            @endif
                        </div>
                        <div class="flex items-center gap-2 px-3 py-2">
                            <svg viewBox="0 0 24 24" class="size-3.5 shrink-0 text-zinc-500 dark:text-zinc-400" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $icons[$item->kind] ?? $icons['file'] !!}</svg>
                            <span class="min-w-0 flex-1 truncate text-[13px] font-medium text-zinc-800 dark:text-zinc-100" data-card-name>{{ $item->name }}</span>
                            <span class="shrink-0 text-[11px] text-zinc-500 tabular-nums">{{ \Atannex\DeskEditor\Media\Models\DeskMedia::humanSize($item->size) }}</span>
                        </div>
                    </button>
                @endforeach
            </div>

            @if ($media->hasPages())
                <nav class="mt-6 flex items-center justify-between gap-4 text-sm" aria-label="Pagination">
                    <p class="text-zinc-500 dark:text-zinc-400">{{ $media->firstItem() }}–{{ $media->lastItem() }} / {{ $media->total() }}</p>
                    <div class="flex items-center gap-1">
                        @if ($media->onFirstPage())
                            <span class="inline-flex h-9 items-center rounded-lg px-3 text-zinc-300 dark:text-zinc-600">‹</span>
                        @else
                            <a href="{{ $media->previousPageUrl() }}" class="inline-flex h-9 items-center rounded-lg px-3 hover:bg-zinc-200/60 dark:hover:bg-zinc-800">‹</a>
                        @endif
                        @foreach ($media->getUrlRange(max(1, $media->currentPage() - 2), min($media->lastPage(), $media->currentPage() + 2)) as $page => $url)
                            <a href="{{ $url }}" @class(['inline-flex h-9 min-w-9 items-center justify-center rounded-lg px-2 tabular-nums transition', 'bg-zinc-900 font-semibold text-white dark:bg-white dark:text-zinc-900' => $page === $media->currentPage(), 'hover:bg-zinc-200/60 dark:hover:bg-zinc-800' => $page !== $media->currentPage()])>{{ $page }}</a>
                        @endforeach
                        @if ($media->hasMorePages())
                            <a href="{{ $media->nextPageUrl() }}" class="inline-flex h-9 items-center rounded-lg px-3 hover:bg-zinc-200/60 dark:hover:bg-zinc-800">›</a>
                        @else
                            <span class="inline-flex h-9 items-center rounded-lg px-3 text-zinc-300 dark:text-zinc-600">›</span>
                        @endif
                    </div>
                </nav>
            @endif
        @endif
    </div>
</main>

{{-- Full-page drop overlay --}}
<div data-drop-overlay class="pointer-events-none fixed inset-0 z-[60] grid place-items-center bg-indigo-600/10 opacity-0 backdrop-blur-[2px] transition-opacity duration-200 dark:bg-indigo-500/10">
    <div class="rounded-2xl border-2 border-dashed border-indigo-500 bg-white/90 px-8 py-6 text-center shadow-2xl dark:bg-zinc-900/90">
        <svg viewBox="0 0 24 24" class="mx-auto mb-2 size-7 animate-bounce text-indigo-500" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 15V3M7 8l5-5 5 5M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/></svg>
        <p class="text-sm font-semibold">{{ __('desk-editor::media.drop_here') }}</p>
    </div>
</div>

{{-- Details drawer --}}
<div data-drawer class="fixed inset-0 z-50 hidden">
    <div data-drawer-backdrop class="absolute inset-0 bg-zinc-950/40 opacity-0 backdrop-blur-sm transition-opacity duration-300"></div>
    <aside data-drawer-panel role="dialog" aria-modal="true" aria-labelledby="desk-media-drawer-title" class="absolute inset-y-0 right-0 flex w-full max-w-md translate-x-full flex-col border-l border-zinc-200 bg-white shadow-2xl transition-transform duration-300 ease-out dark:border-zinc-800 dark:bg-zinc-900">
        <header class="flex h-14 shrink-0 items-center gap-3 border-b border-zinc-200 px-4 dark:border-zinc-800">
            <h2 id="desk-media-drawer-title" class="flex-1 text-sm font-semibold">{{ __('desk-editor::media.details') }}</h2>
            <span data-field="status" class="hidden rounded-full px-2 py-0.5 text-[11px] font-medium ring-1 ring-inset"></span>
            <button type="button" data-drawer-close class="grid size-8 cursor-pointer place-items-center rounded-lg text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white" aria-label="{{ __('desk-editor::media.close') }}">
                <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </header>

        <div class="flex-1 overflow-y-auto">
            <div data-field="preview" class="relative grid min-h-48 place-items-center border-b border-zinc-200 bg-zinc-100 bg-[radial-gradient(circle,--theme(--color-zinc-300)_1px,transparent_1px)] bg-[length:14px_14px] p-4 dark:border-zinc-800 dark:bg-zinc-950 dark:bg-[radial-gradient(circle,--theme(--color-zinc-800)_1px,transparent_1px)]"></div>

            <div class="space-y-6 p-5">
                <form data-edit-form class="space-y-4">
                    <label class="block">
                        <span class="mb-1.5 block text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('desk-editor::media.name') }}</span>
                        <input type="text" name="name" maxlength="250" required class="h-10 w-full rounded-xl border border-zinc-200 bg-white px-3 text-sm shadow-xs transition outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/15 dark:border-zinc-700 dark:bg-zinc-950">
                    </label>
                    <label class="block" data-alt-field>
                        <span class="mb-1.5 block text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('desk-editor::media.alt') }}</span>
                        <textarea name="alt" rows="2" maxlength="500" class="w-full resize-y rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm shadow-xs transition outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/15 dark:border-zinc-700 dark:bg-zinc-950"></textarea>
                        <span class="mt-1 block text-[11px] text-zinc-500 dark:text-zinc-400">{{ __('desk-editor::media.alt_hint') }}</span>
                    </label>
                    <div class="flex items-center gap-3">
                        <button type="submit" data-save class="inline-flex h-9 cursor-pointer items-center gap-1.5 rounded-lg bg-zinc-900 px-3.5 text-sm font-medium text-white shadow-sm transition hover:bg-zinc-700 active:scale-[.98] disabled:cursor-default disabled:opacity-50 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">{{ __('desk-editor::media.save') }}</button>
                        <span data-save-status class="text-xs text-zinc-500 dark:text-zinc-400" role="status"></span>
                    </div>
                </form>

                <dl class="grid grid-cols-2 gap-px overflow-hidden rounded-xl border border-zinc-200 bg-zinc-200 text-sm dark:border-zinc-800 dark:bg-zinc-800">
                    @foreach (['dimensions', 'duration', 'size', 'mime', 'created'] as $field)
                        <div data-meta="{{ $field }}" class="bg-white px-3 py-2.5 dark:bg-zinc-900">
                            <dt class="text-[11px] font-medium tracking-wide text-zinc-500 dark:text-zinc-400 uppercase">{{ __('desk-editor::media.'.$field) }}</dt>
                            <dd data-field="{{ $field }}" class="mt-0.5 truncate font-medium text-zinc-800 tabular-nums dark:text-zinc-100"></dd>
                        </div>
                    @endforeach
                    <div class="bg-white px-3 py-2.5 dark:bg-zinc-900" data-meta-filler></div>
                </dl>

                <div>
                    <span class="mb-1.5 block text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('desk-editor::media.url') }}</span>
                    <div class="flex gap-2">
                        <input type="text" readonly data-field="url" class="h-10 min-w-0 flex-1 rounded-xl border border-zinc-200 bg-zinc-50 px-3 font-mono text-xs text-zinc-600 outline-none focus:border-indigo-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-300" onfocus="this.select()">
                        <button type="button" data-copy class="inline-flex h-10 shrink-0 cursor-pointer items-center gap-1.5 rounded-xl border border-zinc-200 bg-white px-3 text-sm font-medium shadow-xs transition hover:bg-zinc-50 active:scale-95 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:bg-zinc-800">
                            <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                            <span data-copy-label>{{ __('desk-editor::media.copy') }}</span>
                        </button>
                    </div>
                </div>

                <div>
                    <h3 class="mb-2 text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('desk-editor::media.usage') }}</h3>
                    <ul data-field="usage" class="divide-y divide-zinc-100 overflow-hidden rounded-xl border border-zinc-200 text-sm dark:divide-zinc-800 dark:border-zinc-800"></ul>
                </div>
            </div>
        </div>

        <footer class="flex shrink-0 items-center gap-2 border-t border-zinc-200 px-4 py-3 dark:border-zinc-800">
            <button type="button" data-delete class="inline-flex h-9 cursor-pointer items-center gap-1.5 rounded-lg px-3 text-sm font-medium text-red-600 transition hover:bg-red-50 active:scale-95 disabled:opacity-50 dark:text-red-400 dark:hover:bg-red-500/10">
                <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6M10 11v6M14 11v6"/></svg>
                {{ __('desk-editor::media.delete') }}
            </button>
            <div class="flex-1"></div>
            <a data-field="open" href="#" target="_blank" rel="noopener" class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-zinc-200 px-3 text-sm font-medium transition hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800">
                {{ __('desk-editor::media.open') }}
                <svg viewBox="0 0 24 24" class="size-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6M10 14 21 3M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg>
            </a>
        </footer>
    </aside>
</div>

<div data-toast class="pointer-events-none fixed bottom-6 left-1/2 z-[70] flex -translate-x-1/2 translate-y-4 items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white opacity-0 shadow-xl transition duration-300 dark:bg-white dark:text-zinc-900" role="status" aria-live="polite"></div>
@endsection

@push('desk-scripts')
<script>
(() => {
    const page = document.querySelector('[data-media-page]');
    if (!page) return;

    const t = @json($strings);
    const urls = @json($endpoints);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const itemUrl = (id) => urls.item.replace('__ID__', encodeURIComponent(id));
    const $ = (sel, root = document) => root.querySelector(sel);

    let items = {};
    const readItems = () => { try { items = JSON.parse($('[data-library] [data-items]')?.textContent || '{}'); } catch (e) { items = {}; } };
    readItems();

    /* ---------- helpers ---------- */
    const el = (tag, className = '', text = null) => {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== null) node.textContent = text;
        return node;
    };
    const fmtDuration = (s) => s == null ? null : `${Math.floor(Math.round(s) / 60)}:${String(Math.round(s) % 60).padStart(2, '0')}`;
    const fmtSize = (b) => { const u = ['B', 'KB', 'MB', 'GB']; let i = 0; while (b >= 1024 && i < u.length - 1) { b /= 1024; i++; } return (i ? b.toFixed(1).replace(/\.0$/, '') : b) + ' ' + u[i]; };
    const json = async (url, options = {}) => {
        const res = await fetch(url, { credentials: 'same-origin', ...options, headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', ...(options.body ? { 'Content-Type': 'application/json' } : {}), ...(options.headers || {}) } });
        if (!res.ok) throw Object.assign(new Error(res.statusText), { status: res.status, body: await res.json().catch(() => ({})) });
        return res.status === 204 ? null : res.json();
    };
    let toastTimer;
    const toast = (message) => {
        const node = $('[data-toast]');
        node.textContent = message;
        node.classList.remove('opacity-0', 'translate-y-4');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => node.classList.add('opacity-0', 'translate-y-4'), 2600);
    };

    /* ---------- drawer ---------- */
    const drawer = $('[data-drawer]');
    const panel = $('[data-drawer-panel]');
    const backdrop = $('[data-drawer-backdrop]');
    const form = $('[data-edit-form]');
    const field = (name) => $(`[data-field="${name}"]`, drawer);
    let current = null;
    let currentUsage = [];
    let lastFocus = null;

    const statusTone = { processing: 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300', failed: 'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-500/10 dark:text-red-300' };

    const renderPreview = (item) => {
        const box = field('preview');
        box.replaceChildren();
        let node;
        if (item.kind === 'image') {
            node = el('img', 'animate-de-pop max-h-72 w-auto max-w-full rounded-lg object-contain shadow-lg ring-1 ring-black/5 dark:ring-white/10');
            node.src = item.url;
            node.alt = item.alt || '';
        } else if (item.kind === 'video') {
            node = el('video', 'animate-de-pop max-h-72 w-full rounded-lg bg-black shadow-lg');
            node.controls = true;
            node.preload = 'metadata';
            node.playsInline = true;
            if (item.poster_url) node.poster = item.poster_url;
            node.src = item.transcoded_url || item.url;
        } else if (item.kind === 'audio') {
            node = el('div', 'animate-de-pop flex w-full flex-col items-center gap-4 py-4');
            const badge = el('span', 'grid size-16 place-items-center rounded-2xl bg-gradient-to-br from-violet-500 to-indigo-600 text-white shadow-lg shadow-violet-600/30');
            badge.innerHTML = '<svg viewBox="0 0 24 24" class="size-7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $icons['audio'] !!}</svg>';
            const audio = el('audio', 'w-full');
            audio.controls = true;
            audio.preload = 'metadata';
            audio.src = item.url;
            node.append(badge, audio);
        } else {
            node = el('div', 'animate-de-pop flex flex-col items-center gap-3 py-6 text-sky-600 dark:text-sky-300');
            const badge = el('span', 'grid size-16 place-items-center rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-800');
            badge.innerHTML = '<svg viewBox="0 0 24 24" class="size-7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $icons['file'] !!}</svg>';
            node.append(badge, el('span', 'text-xs font-semibold tracking-wide text-zinc-500 uppercase', (item.url.split('?')[0].split('.').pop() || 'file').slice(0, 6)));
        }
        box.append(node);
    };

    const renderUsage = (usage, loading = false) => {
        const list = field('usage');
        list.replaceChildren();
        if (loading || !usage.length) {
            const row = el('li', 'px-3 py-3 text-[13px] text-zinc-500 dark:text-zinc-400', loading ? t.usageLoading : t.usageNone);
            if (loading) row.classList.add('animate-pulse');
            list.append(row);
            return;
        }
        usage.forEach((use) => {
            const row = el('li', 'animate-de-rise');
            const inner = el(use.url ? 'a' : 'div', 'flex items-center gap-3 px-3 py-2.5 transition hover:bg-zinc-50 dark:hover:bg-zinc-800/50');
            if (use.url) inner.href = use.url;
            inner.append(
                el('span', 'shrink-0 rounded-md bg-indigo-50 px-1.5 py-0.5 text-[11px] font-medium text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300', (t.usageTypes && t.usageTypes[use.type]) || use.type),
                el('span', 'min-w-0 flex-1 truncate font-medium', use.title),
            );
            row.append(inner);
            list.append(row);
        });
    };

    const fill = (item) => {
        renderPreview(item);
        form.elements.name.value = item.name || '';
        form.elements.alt.value = item.alt || '';
        $('[data-alt-field]', drawer).classList.toggle('hidden', !['image', 'video'].includes(item.kind));
        $('[data-save-status]', drawer).textContent = '';
        const status = field('status');
        status.className = 'rounded-full px-2 py-0.5 text-[11px] font-medium ring-1 ring-inset ' + (statusTone[item.status] || '');
        status.classList.toggle('hidden', item.status === 'ready');
        status.textContent = (t.status && t.status[item.status]) || item.status;
        const dims = item.width && item.height ? `${item.width} × ${item.height}` : null;
        const values = { dimensions: dims, duration: fmtDuration(item.duration), size: item.size_human || fmtSize(item.size || 0), mime: item.mime || '—', created: item.created_at_human || '—' };
        let shown = 0;
        Object.entries(values).forEach(([key, value]) => {
            const cell = $(`[data-meta="${key}"]`, drawer);
            cell.classList.toggle('hidden', value == null);
            if (value != null) shown++;
            field(key).textContent = value ?? '';
            if (key === 'created') field(key).title = item.created_at || '';
        });
        $('[data-meta-filler]', drawer).classList.toggle('hidden', shown % 2 === 0);
        field('url').value = item.url;
        field('open').href = item.url;
    };

    const highlight = (id) => document.querySelectorAll('[data-media-card]').forEach((card) => {
        const on = String(card.dataset.mediaCard) === String(id);
        card.classList.toggle('ring-2', on);
        card.classList.toggle('ring-indigo-500', on);
        card.classList.toggle('border-indigo-500', on);
    });

    const open = async (id) => {
        const item = items[id];
        if (!item) return;
        current = item;
        currentUsage = [];
        lastFocus = document.activeElement;
        fill(item);
        renderUsage([], true);
        highlight(id);
        drawer.classList.remove('hidden');
        requestAnimationFrame(() => {
            backdrop.classList.remove('opacity-0');
            panel.classList.remove('translate-x-full');
        });
        document.body.classList.add('overflow-hidden');
        history.replaceState(null, '', `#media-${id}`);
        setTimeout(() => $('[data-drawer-close]', drawer).focus({ preventScroll: true }), 50);
        try {
            const fresh = await json(itemUrl(id));
            if (current?.id !== item.id) return;
            currentUsage = fresh.usage || [];
            const { usage, ...rest } = fresh;
            items[id] = current = rest;
            renderUsage(currentUsage);
        } catch (e) {
            renderUsage([]);
        }
    };

    const close = () => {
        if (drawer.classList.contains('hidden')) return;
        backdrop.classList.add('opacity-0');
        panel.classList.add('translate-x-full');
        document.body.classList.remove('overflow-hidden');
        drawer.querySelectorAll('video, audio').forEach((m) => m.pause());
        highlight(null);
        current = null;
        history.replaceState(null, '', location.pathname + location.search);
        setTimeout(() => drawer.classList.add('hidden'), 300);
        lastFocus?.focus?.({ preventScroll: true });
    };

    page.addEventListener('click', (e) => {
        const card = e.target.closest('[data-media-card]');
        if (card) open(card.dataset.mediaCard);
    });
    backdrop.addEventListener('click', close);
    $('[data-drawer-close]', drawer).addEventListener('click', close);
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') close();
        if (e.key === '/' && !/input|textarea|select/i.test(document.activeElement.tagName)) {
            e.preventDefault();
            $('[data-filters] input[name=q]').focus();
        }
        if (e.key === 'Tab' && !drawer.classList.contains('hidden')) {
            const focusables = [...panel.querySelectorAll('a[href], button:not([disabled]), input, textarea, video, audio')].filter((n) => n.offsetParent !== null);
            if (!focusables.length) return;
            const [first, last] = [focusables[0], focusables[focusables.length - 1]];
            if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
            else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
        }
    });

    $('[data-copy]', drawer).addEventListener('click', async () => {
        const label = $('[data-copy-label]', drawer);
        try { await navigator.clipboard.writeText(field('url').value); } catch (e) { field('url').select(); document.execCommand('copy'); }
        label.textContent = t.copied;
        setTimeout(() => (label.textContent = t.copy), 1600);
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!current) return;
        const button = $('[data-save]', drawer);
        const status = $('[data-save-status]', drawer);
        button.disabled = true;
        status.textContent = t.saving;
        try {
            const updated = await json(itemUrl(current.id), { method: 'PATCH', body: JSON.stringify({ name: form.elements.name.value, alt: form.elements.alt.value }) });
            items[updated.id] = current = updated;
            const card = document.querySelector(`[data-media-card="${updated.id}"]`);
            if (card) {
                $('[data-card-name]', card).textContent = updated.name;
                card.setAttribute('aria-label', updated.name);
                const img = card.querySelector('img');
                if (img && updated.kind === 'image') img.alt = updated.alt || '';
            }
            status.textContent = t.saved;
            toast(t.saved);
        } catch (err) {
            status.textContent = Object.values(err.body?.errors || {})[0]?.[0] || err.body?.message || t.saveFailed;
        } finally {
            button.disabled = false;
        }
    });

    const deleteButton = $('[data-delete]', drawer);
    deleteButton.addEventListener('click', async () => {
        if (!current) return;
        const message = currentUsage.length ? t.confirmDeleteUsed.replace(':count', currentUsage.length) : t.confirmDelete;
        if (!confirm(message)) return;
        const id = current.id;
        deleteButton.disabled = true;
        try {
            await json(itemUrl(id), { method: 'DELETE' });
            delete items[id];
            close();
            const card = document.querySelector(`[data-media-card="${id}"]`);
            card?.animate([{ opacity: 1, transform: 'scale(1)' }, { opacity: 0, transform: 'scale(.94)' }], { duration: 220, easing: 'ease-in' }).finished.then(() => card.remove());
            toast(t.deleted);
        } catch (err) {
            toast(err.body?.message || t.deleteFailed);
        } finally {
            deleteButton.disabled = false;
        }
    });

    /* ---------- uploads ---------- */
    const input = $('[data-upload-input]');
    const list = $('[data-upload-list]');
    const overlay = $('[data-drop-overlay]');
    const kindOf = (file) => ['image', 'video', 'audio'].find((k) => (file.type || '').startsWith(k + '/')) || 'file';

    const refreshLibrary = async () => {
        try {
            const res = await fetch(location.pathname + location.search, { credentials: 'same-origin', headers: { Accept: 'text/html' } });
            const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
            ['[data-library]', '[data-kind-tabs]'].forEach((sel) => {
                const fresh = doc.querySelector(sel);
                const stale = document.querySelector(sel);
                if (fresh && stale) stale.replaceWith(document.importNode(fresh, true));
            });
            readItems();
        } catch (e) { location.reload(); }
    };

    const row = (file) => {
        const li = el('li', 'animate-de-rise relative overflow-hidden rounded-xl border border-zinc-200 bg-white px-3 py-2.5 shadow-xs dark:border-zinc-800 dark:bg-zinc-900');
        const top = el('div', 'flex items-center gap-2.5');
        const icon = el('span', 'grid size-8 shrink-0 place-items-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-300');
        icon.innerHTML = '<svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 15V3M7 8l5-5 5 5M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/></svg>';
        const text = el('div', 'min-w-0 flex-1');
        const name = el('div', 'truncate text-[13px] font-medium', file.name);
        const status = el('div', 'text-[11px] text-zinc-500 tabular-nums dark:text-zinc-400', `${t.uploading} · ${fmtSize(file.size)}`);
        text.append(name, status);
        const dismiss = el('button', 'hidden size-7 cursor-pointer place-items-center rounded-md text-zinc-500 dark:text-zinc-400 hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-800 dark:hover:text-zinc-200');
        dismiss.type = 'button';
        dismiss.setAttribute('aria-label', t.dismiss);
        dismiss.innerHTML = '<svg viewBox="0 0 24 24" class="size-3.5" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>';
        dismiss.addEventListener('click', () => li.remove());
        top.append(icon, text, dismiss);
        const track = el('div', 'mt-2 h-1 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800');
        const bar = el('div', 'h-full w-0 rounded-full bg-gradient-to-r from-indigo-500 to-violet-500 transition-[width] duration-200');
        track.append(bar);
        li.append(top, track);
        list.prepend(li);
        return {
            progress: (ratio) => { bar.style.width = `${Math.round(ratio * 100)}%`; status.textContent = ratio < 1 ? `${t.uploading} ${Math.round(ratio * 100)}% · ${fmtSize(file.size)}` : t.processing; },
            done: () => {
                bar.style.width = '100%';
                bar.className = 'h-full w-full rounded-full bg-emerald-500 transition-[width] duration-200';
                icon.className = 'grid size-8 shrink-0 place-items-center rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-300';
                icon.innerHTML = '<svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>';
                status.textContent = `${t.uploaded} · ${fmtSize(file.size)}`;
                setTimeout(() => li.animate([{ opacity: 1 }, { opacity: 0 }], { duration: 250, fill: 'forwards' }).finished.then(() => li.remove()), 2500);
            },
            fail: (message) => {
                bar.className = 'h-full w-full rounded-full bg-red-500';
                icon.className = 'grid size-8 shrink-0 place-items-center rounded-lg bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-300';
                icon.innerHTML = '<svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 8v5M12 16.5v.01"/><circle cx="12" cy="12" r="9"/></svg>';
                status.className = 'truncate text-[11px] text-red-600 dark:text-red-400';
                status.textContent = message || t.uploadFailed;
                status.title = status.textContent;
                dismiss.classList.remove('hidden');
                dismiss.classList.add('grid');
            },
        };
    };

    const send = (file) => new Promise((resolve) => {
        const ui = row(file);
        const data = new FormData();
        data.append('file', file);
        data.append('kind', kindOf(file));
        const xhr = new XMLHttpRequest();
        xhr.open('POST', urls.upload);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-CSRF-TOKEN', csrf);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.upload.addEventListener('progress', (e) => e.lengthComputable && ui.progress(e.loaded / e.total));
        xhr.addEventListener('load', () => {
            let body = {};
            try { body = JSON.parse(xhr.responseText); } catch (e) {}
            if (xhr.status >= 200 && xhr.status < 300) { ui.done(); resolve(true); return; }
            ui.fail(Object.values(body.errors || {})[0]?.[0] || body.message || `${t.uploadFailed} (${xhr.status})`);
            resolve(false);
        });
        xhr.addEventListener('error', () => { ui.fail(t.uploadFailed); resolve(false); });
        xhr.send(data);
    });

    const upload = async (files) => {
        const queue = [...files];
        if (!queue.length) return;
        let ok = 0;
        const worker = async () => { while (queue.length) { if (await send(queue.shift())) ok++; } };
        await Promise.all(Array.from({ length: Math.min(3, queue.length) }, worker));
        if (ok) await refreshLibrary();
    };

    document.addEventListener('click', (e) => { if (e.target.closest('[data-upload-trigger]')) input.click(); });
    input.addEventListener('change', () => { upload(input.files); input.value = ''; });

    let depth = 0;
    const hasFiles = (e) => [...(e.dataTransfer?.types || [])].includes('Files');
    window.addEventListener('dragenter', (e) => { if (!hasFiles(e)) return; e.preventDefault(); depth++; overlay.classList.remove('opacity-0'); });
    window.addEventListener('dragleave', (e) => { if (!hasFiles(e)) return; depth = Math.max(0, depth - 1); if (!depth) overlay.classList.add('opacity-0'); });
    window.addEventListener('dragover', (e) => { if (hasFiles(e)) e.preventDefault(); });
    window.addEventListener('drop', (e) => {
        if (!hasFiles(e)) return;
        e.preventDefault();
        depth = 0;
        overlay.classList.add('opacity-0');
        upload(e.dataTransfer.files);
    });

    const hash = location.hash.match(/^#media-(\d+)$/);
    if (hash) open(hash[1]);
})();
</script>
@endpush
