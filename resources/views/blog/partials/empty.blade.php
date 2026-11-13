@php
    $title ??= __('desk-editor::blog.empty_title');
    $body ??= __('desk-editor::blog.empty_body');
    $action ??= null;
@endphp
<div class="motion-safe:animate-de-rise flex flex-col items-center px-6 py-24 text-center">
    <div class="relative mb-6">
        <div class="absolute inset-0 rounded-full bg-desk/25 blur-2xl motion-safe:animate-pulse"></div>
        <div class="relative grid size-16 place-items-center rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <svg viewBox="0 0 24 24" class="size-7 text-desk" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/><path d="M9 7h7M9 11h5"/></svg>
        </div>
    </div>
    <h2 class="font-serif text-2xl font-semibold tracking-tight">{{ $title }}</h2>
    <p class="mt-2 max-w-sm text-sm/6 text-zinc-500 dark:text-zinc-400">{{ $body }}</p>
    @if ($action)
        <a href="{{ $action[1] }}" class="mt-6 inline-flex h-10 items-center gap-2 rounded-full bg-zinc-900 px-5 text-sm font-medium text-white transition hover:bg-zinc-700 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">{{ $action[0] }}</a>
    @endif
</div>
