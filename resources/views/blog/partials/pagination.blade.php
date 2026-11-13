@php
    /** @var \Illuminate\Pagination\LengthAwarePaginator $paginator */
    $current = $paginator->currentPage();
    $last = $paginator->lastPage();
    $window = collect(range(max(1, $current - 1), min($last, $current + 1)))
        ->merge([1, $last])->unique()->sort()->values();
    $base = 'inline-flex h-10 min-w-10 items-center justify-center rounded-full px-3 text-sm font-medium transition outline-none focus-visible:ring-2 focus-visible:ring-desk/60';
@endphp
@if ($paginator->hasPages())
    <nav class="mt-16 flex flex-col items-center gap-4" aria-label="{{ __('desk-editor::blog.pagination') }}">
        <div class="flex items-center gap-1 rounded-full border border-zinc-200 bg-white/80 p-1 shadow-xs backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/80">
            @if ($paginator->onFirstPage())
                <span class="{{ $base }} cursor-not-allowed text-zinc-300 dark:text-zinc-700" aria-disabled="true">
                    <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                    <span class="sr-only">{{ __('desk-editor::blog.previous') }}</span>
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $base }} text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-white">
                    <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                    <span class="sr-only">{{ __('desk-editor::blog.previous') }}</span>
                </a>
            @endif

            @php $prev = 0; @endphp
            @foreach ($window as $page)
                @if ($page - $prev > 1)
                    <span class="{{ $base }} hidden text-zinc-500 sm:inline-flex" aria-hidden="true">…</span>
                @endif
                @if ($page === $current)
                    <span class="{{ $base }} bg-zinc-900 text-white shadow-sm dark:bg-white dark:text-zinc-900" aria-current="page">{{ $page }}</span>
                @else
                    <a href="{{ $paginator->url($page) }}" @class([$base, 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-white', 'hidden sm:inline-flex' => abs($page - $current) > 1])>{{ $page }}</a>
                @endif
                @php $prev = $page; @endphp
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $base }} text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-white">
                    <span class="sr-only">{{ __('desk-editor::blog.next') }}</span>
                    <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                </a>
            @else
                <span class="{{ $base }} cursor-not-allowed text-zinc-300 dark:text-zinc-700" aria-disabled="true">
                    <span class="sr-only">{{ __('desk-editor::blog.next') }}</span>
                    <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                </span>
            @endif
        </div>
        <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('desk-editor::blog.page_of', ['page' => $current, 'pages' => $last]) }}</p>
    </nav>
@endif
