@extends('desk-editor::blog.layout')

@php
    use Atannex\DeskEditor\Blog\Support\Blog;
    $swatch = Blog::color($color ?? null);
@endphp

@section('content')
<main class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    <header class="relative pt-14 pb-10 motion-safe:animate-de-rise sm:pt-20">
        <div class="pointer-events-none absolute -top-10 left-1/2 -z-10 h-64 w-[36rem] -translate-x-1/2 rounded-full bg-desk/10 blur-3xl" aria-hidden="true"></div>
        <nav aria-label="Breadcrumb" class="mb-6 text-sm text-zinc-500 dark:text-zinc-400">
            <ol class="flex items-center gap-2">
                <li><a href="{{ route('desk-editor.blog.home', Blog::params()) }}" class="transition hover:text-zinc-900 dark:hover:text-white">{{ __('desk-editor::blog.home') }}</a></li>
                <li aria-hidden="true" class="text-zinc-300 dark:text-zinc-700">/</li>
                <li aria-current="page" class="font-medium text-zinc-700 dark:text-zinc-300">{{ $eyebrow }}</li>
            </ol>
        </nav>
        <div class="flex items-start gap-5">
            @if ($avatar)
                <span class="grid size-16 shrink-0 place-items-center rounded-full bg-gradient-to-br from-desk to-violet-600 font-serif text-xl font-bold text-white shadow-lg shadow-desk/25 sm:size-20 sm:text-2xl" aria-hidden="true">{{ $avatar }}</span>
            @endif
            <div class="min-w-0">
                <p class="flex items-center gap-2 text-xs font-semibold tracking-[0.2em] text-desk uppercase">
                    @if ($swatch)<span class="size-2 rounded-full" style="background: {{ $swatch }}" aria-hidden="true"></span>@endif
                    {{ $eyebrow }}
                </p>
                <h1 class="mt-2 font-serif text-4xl font-bold tracking-tight break-words text-balance sm:text-5xl">{{ $heading }}</h1>
                @if ($intro)
                    <p class="mt-3 max-w-2xl text-lg/relaxed text-zinc-600 dark:text-zinc-400">{{ $intro }}</p>
                @endif
            </div>
        </div>

        @if ($isSearch)
            <form action="{{ route('desk-editor.blog.search') }}" method="GET" role="search" class="mt-8 max-w-2xl">
                @if (request('lang'))<input type="hidden" name="lang" value="{{ request('lang') }}">@endif
                <label for="desk-blog-search-page" class="sr-only">{{ __('desk-editor::blog.search') }}</label>
                <div class="relative">
                    <svg viewBox="0 0 24 24" class="pointer-events-none absolute top-1/2 left-5 size-5 -translate-y-1/2 text-zinc-500 dark:text-zinc-400" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                    <input id="desk-blog-search-page" type="search" name="q" value="{{ $term }}" placeholder="{{ __('desk-editor::blog.search_placeholder') }}" @if (! $term) autofocus @endif
                           class="h-14 w-full rounded-full border border-zinc-200 bg-white pr-32 pl-13 text-base shadow-sm transition outline-none placeholder:text-zinc-400 focus:border-desk/60 focus:ring-4 focus:ring-desk/15 dark:border-zinc-800 dark:bg-zinc-900">
                    <button type="submit" class="absolute top-1.5 right-1.5 h-11 cursor-pointer rounded-full bg-zinc-900 px-5 text-sm font-semibold text-white transition hover:bg-zinc-700 focus-visible:ring-2 focus-visible:ring-desk/60 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">{{ __('desk-editor::blog.search') }}</button>
                </div>
            </form>
        @endif
    </header>

    @if ($categories && $categories->isNotEmpty())
        <div class="mb-10">@include('desk-editor::blog.partials.chips', ['categories' => $categories, 'activeCategory' => $activeCategory])</div>
    @endif

    @if ($articles->isEmpty())
        @if (! $isSearch || $term)
            @include('desk-editor::blog.partials.empty', [
                'title' => $isSearch ? __('desk-editor::blog.results', ['count' => 0]) : __('desk-editor::blog.empty_title'),
                'body' => $isSearch ? __('desk-editor::blog.empty_search') : __('desk-editor::blog.empty_body'),
                'action' => [__('desk-editor::blog.back_home'), route('desk-editor.blog.home', Blog::params())],
            ])
        @endif
    @else
        <div class="grid gap-x-8 gap-y-14 border-t border-zinc-200 pt-12 sm:grid-cols-2 lg:grid-cols-3 dark:border-zinc-800">
            @foreach ($articles as $article)
                @include('desk-editor::blog.partials.card', ['article' => $article, 'i' => $loop->index, 'term' => $term])
            @endforeach
        </div>
        @include('desk-editor::blog.partials.pagination', ['paginator' => $articles])
    @endif
</main>
@endsection
