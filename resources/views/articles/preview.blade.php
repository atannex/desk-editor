@extends('desk-editor::articles.layout')

@section('title', $article->title)

@section('header-actions')
    <a href="{{ route('desk-editor.articles.edit', $article) }}" class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-indigo-600 px-3.5 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-500 dark:bg-indigo-500">
        <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.4 3.6a2.1 2.1 0 1 1 3 3L7 19l-4 1 1-4Z"/></svg>
        {{ __('desk-editor::articles.edit') }}
    </a>
@endsection

@section('content')
    <div class="sticky top-14 z-30 h-0.5 bg-transparent"><div data-progress class="h-full origin-left scale-x-0 bg-gradient-to-r from-indigo-500 to-violet-500"></div></div>
    <article class="animate-de-rise mx-auto max-w-3xl px-5 pt-12 pb-24 sm:px-6">
        <header class="mb-10">
            <div class="mb-4 flex flex-wrap items-center gap-2 text-xs">
                <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 font-medium ring-1 ring-inset {{ $article->status->badge() }}"><span class="size-1.5 rounded-full {{ $article->status->dot() }}"></span>{{ $article->status->label() }}</span>
                @if ($article->category)<span class="rounded-full bg-indigo-50 px-2 py-0.5 font-medium text-indigo-700 dark:bg-indigo-400/10 dark:text-indigo-300">{{ $article->category->name }}</span>@endif
            </div>
            <h1 class="text-4xl leading-[1.1] font-bold tracking-tight text-balance text-zinc-950 sm:text-5xl dark:text-white">{{ $article->title }}</h1>
            @if ($article->subtitle)<p class="mt-4 text-xl text-pretty text-zinc-500 dark:text-zinc-400">{{ $article->subtitle }}</p>@endif
            <div class="mt-6 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-zinc-500 dark:text-zinc-400">
                @if ($article->author?->name)<span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $article->author->name }}</span><span>·</span>@endif
                <time datetime="{{ ($article->published_at ?? $article->updated_at)?->toIso8601String() }}">{{ ($article->published_at ?? $article->updated_at)?->toFormattedDateString() }}</time>
                <span>·</span>
                <span>{{ __('desk-editor::articles.min_read', ['count' => max(1, $article->reading_time)]) }}</span>
            </div>
        </header>

        @if ($article->cover_image)
            <figure class="-mx-5 mb-12 sm:mx-0">
                <img src="{{ $article->cover_image }}" alt="{{ $article->cover_alt }}" class="aspect-[16/9] w-full object-cover shadow-xl sm:rounded-2xl">
            </figure>
        @endif

        <x-desk-editor::content :html="$article->body" :assets="false" class="text-[1.075rem] leading-8" />

        @if ($article->tags->isNotEmpty())
            <footer class="mt-14 flex flex-wrap gap-2 border-t border-zinc-200 pt-6 dark:border-zinc-800">
                @foreach ($article->tags as $tag)
                    <span class="rounded-full bg-zinc-100 px-3 py-1 text-sm text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">#{{ $tag->name }}</span>
                @endforeach
            </footer>
        @endif
    </article>
@endsection

@push('desk-scripts')
<script>
    (() => {
        const bar = document.querySelector('[data-progress]');
        const update = () => {
            const max = document.documentElement.scrollHeight - innerHeight;
            bar.style.transform = `scaleX(${max > 0 ? scrollY / max : 0})`;
        };
        addEventListener('scroll', update, { passive: true });
        update();
    })();
</script>
@endpush
