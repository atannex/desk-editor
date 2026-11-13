@extends('desk-editor::blog.layout')

@php
    use Atannex\DeskEditor\Blog\Support\Blog;
    /** @var \Atannex\DeskEditor\Models\DeskArticle|null $featured */
@endphp

@section('content')
<main class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    @if ($featured)
        @php
            $featuredUrl = Blog::url($featured);
            $featuredAuthor = Blog::authorName($featured);
        @endphp
        <section class="pt-8 sm:pt-12" aria-labelledby="featured-title">
            <article class="group relative grid overflow-hidden rounded-[2rem] bg-zinc-950 text-white shadow-2xl shadow-zinc-900/20 motion-safe:animate-de-rise lg:grid-cols-12 dark:ring-1 dark:ring-white/10">
                <div class="relative min-h-72 overflow-hidden sm:min-h-96 lg:col-span-7 lg:min-h-[32rem]">
                    @if ($featured->cover_image)
                        <img src="{{ $featured->cover_image }}" alt="{{ $featured->cover_alt ?: '' }}" fetchpriority="high" decoding="async" class="absolute inset-0 size-full object-cover transition duration-[1.2s] ease-out group-hover:scale-[1.03] motion-reduce:transition-none motion-reduce:group-hover:scale-100">
                    @else
                        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_left,var(--de-accent),transparent_60%),radial-gradient(ellipse_at_bottom_right,#7c3aed,transparent_55%)] opacity-80"></div>
                        <div class="absolute inset-0 flex items-center justify-center font-serif text-[12rem] leading-none font-bold text-white/10 select-none">{{ \Illuminate\Support\Str::upper(mb_substr($featured->title, 0, 1)) }}</div>
                    @endif
                    <div class="absolute inset-0 bg-gradient-to-t from-zinc-950 via-zinc-950/20 to-transparent lg:bg-gradient-to-r lg:from-transparent lg:via-transparent lg:to-zinc-950"></div>
                </div>
                <div class="relative flex flex-col justify-end p-6 sm:p-10 lg:col-span-5 lg:justify-center lg:p-12">
                    <div class="flex flex-wrap items-center gap-2 text-xs font-semibold tracking-wider uppercase">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1 text-white/90 ring-1 ring-white/15 backdrop-blur">
                            <svg viewBox="0 0 24 24" class="size-3.5 text-amber-300" fill="currentColor" aria-hidden="true"><path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1Z"/></svg>
                            {{ __('desk-editor::blog.featured') }}
                        </span>
                        @if ($featured->category)
                            <a href="{{ route('desk-editor.blog.category', Blog::params(['slug' => $featured->category->slug])) }}" class="relative z-10 rounded-full bg-desk px-3 py-1 text-white transition hover:brightness-110">{{ $featured->category->name }}</a>
                        @endif
                    </div>
                    <h1 id="featured-title" class="mt-5 font-serif text-3xl/[1.1] font-bold tracking-tight text-balance sm:text-4xl/[1.08] xl:text-5xl/[1.05]">
                        <a href="{{ $featuredUrl }}" class="outline-none after:absolute after:inset-0 focus-visible:underline">{{ $featured->title }}</a>
                    </h1>
                    @if ($featured->excerpt || $featured->subtitle)
                        <p class="mt-4 line-clamp-4 text-base/relaxed text-white/70 sm:text-lg/relaxed">{{ $featured->subtitle ?: $featured->excerpt }}</p>
                    @endif
                    <div class="mt-8 flex flex-wrap items-center gap-3 text-sm text-white/70">
                        @if ($featuredAuthor)
                            <span class="grid size-9 place-items-center rounded-full bg-white/15 text-xs font-semibold text-white ring-1 ring-white/20" aria-hidden="true">{{ Blog::initials($featuredAuthor) }}</span>
                            <span class="font-medium text-white">{{ $featuredAuthor }}</span>
                            <span aria-hidden="true" class="text-white/30">/</span>
                        @endif
                        <time datetime="{{ $featured->published_at?->toAtomString() }}">{{ $featured->published_at?->isoFormat('MMMM D, YYYY') }}</time>
                        <span aria-hidden="true" class="text-white/30">/</span>
                        <span>{{ __('desk-editor::blog.min_read', ['count' => max(1, $featured->reading_time)]) }}</span>
                    </div>
                    <span class="mt-8 inline-flex w-fit items-center gap-2 rounded-full bg-white px-5 py-2.5 text-sm font-semibold text-zinc-900 shadow-lg transition group-hover:gap-3 motion-reduce:transition-none" aria-hidden="true">
                        {{ __('desk-editor::blog.read_story') }}
                        <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                    </span>
                </div>
            </article>
        </section>
    @else
        <header class="pt-12 pb-2 motion-safe:animate-de-rise sm:pt-16">
            <h1 class="font-serif text-4xl font-bold tracking-tight text-balance sm:text-5xl">{{ Blog::name() }}</h1>
            @if (Blog::description())
                <p class="mt-3 max-w-2xl text-lg/relaxed text-zinc-600 dark:text-zinc-400">{{ Blog::description() }}</p>
            @endif
        </header>
    @endif

    @if ($articles->isNotEmpty() || ! $featured || $activeCategory || $articles->currentPage() > 1)
    <section class="pt-16" aria-labelledby="latest-title">
        <div class="mb-8 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold tracking-[0.2em] text-desk uppercase">{{ Blog::name() }}</p>
                <h2 id="latest-title" class="mt-2 font-serif text-3xl font-bold tracking-tight">{{ __('desk-editor::blog.latest') }}</h2>
            </div>
        </div>
        <div class="mb-10">
            @include('desk-editor::blog.partials.chips', ['categories' => $categories, 'activeCategory' => $activeCategory])
        </div>

        @if ($articles->isEmpty())
            @include('desk-editor::blog.partials.empty', ['action' => $activeCategory ? [__('desk-editor::blog.back_home'), route('desk-editor.blog.home', Blog::params())] : null])
        @else
            <div class="grid gap-x-8 gap-y-14 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($articles as $article)
                    @include('desk-editor::blog.partials.card', ['article' => $article, 'i' => $loop->index])
                @endforeach
            </div>
            @include('desk-editor::blog.partials.pagination', ['paginator' => $articles])
        @endif
    </section>
    @endif
</main>
@endsection
