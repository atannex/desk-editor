@extends('desk-editor::blog.layout')

@php
    use Atannex\DeskEditor\Blog\Support\Blog;
    /** @var \Atannex\DeskEditor\Models\DeskArticle $article */
    $url = Blog::url($article);
    $author = Blog::authorName($article);
    $authorUrl = Blog::authorUrl($article);
    $modified = $article->updated_at && $article->published_at && $article->updated_at->gt($article->published_at->copy()->addDay()) ? $article->updated_at : null;
@endphp

@section('progress')
    <div class="fixed inset-x-0 top-0 z-50 h-[3px] bg-transparent" aria-hidden="true">
        <div data-reading-progress class="h-full origin-left bg-gradient-to-r from-desk to-violet-500 will-change-transform" style="transform: scaleX(0)"></div>
    </div>
@endsection

@section('content')
<main>
    <article data-article-id="{{ $article->id }}">
        {{-- Header --}}
        <header class="mx-auto max-w-4xl px-4 pt-12 text-center motion-safe:animate-de-rise sm:px-6 sm:pt-20">
            <nav aria-label="Breadcrumb" class="mb-8 flex justify-center text-sm text-zinc-500 dark:text-zinc-400">
                <ol class="flex flex-wrap items-center justify-center gap-2">
                    <li><a href="{{ route('desk-editor.blog.home', Blog::params()) }}" class="transition hover:text-zinc-900 dark:hover:text-white">{{ __('desk-editor::blog.home') }}</a></li>
                    @if ($article->category)
                        <li aria-hidden="true" class="text-zinc-300 dark:text-zinc-700">/</li>
                        <li><a href="{{ route('desk-editor.blog.category', Blog::params(['slug' => $article->category->slug])) }}" class="font-semibold text-desk transition hover:brightness-110">{{ $article->category->name }}</a></li>
                    @endif
                </ol>
            </nav>
            <h1 itemprop="headline" class="font-serif text-4xl/[1.1] font-bold tracking-tight text-balance text-zinc-950 sm:text-5xl/[1.08] lg:text-6xl/[1.05] dark:text-white">{{ $article->title }}</h1>
            @if ($article->subtitle)
                <p class="mx-auto mt-6 max-w-2xl text-lg/relaxed text-pretty text-zinc-600 sm:text-xl/relaxed dark:text-zinc-400">{{ $article->subtitle }}</p>
            @endif
            <div class="mt-10 flex flex-wrap items-center justify-center gap-x-4 gap-y-3 text-sm text-zinc-500 dark:text-zinc-400">
                @if ($author)
                    <a @if ($authorUrl) href="{{ $authorUrl }}" @endif class="group/author flex items-center gap-3" rel="author">
                        <span class="grid size-10 place-items-center rounded-full bg-gradient-to-br from-desk to-violet-600 text-sm font-semibold text-white shadow-sm" aria-hidden="true">{{ Blog::initials($author) }}</span>
                        <span class="font-semibold text-zinc-900 transition group-hover/author:text-desk dark:text-white" itemprop="author">{{ $author }}</span>
                    </a>
                    <span aria-hidden="true" class="hidden size-1 rounded-full bg-zinc-300 sm:block dark:bg-zinc-700"></span>
                @endif
                <time datetime="{{ $article->published_at?->toAtomString() }}" itemprop="datePublished">{{ $article->published_at?->isoFormat('MMMM D, YYYY') }}</time>
                <span aria-hidden="true" class="size-1 rounded-full bg-zinc-300 dark:bg-zinc-700"></span>
                <span>{{ __('desk-editor::blog.min_read', ['count' => max(1, $article->reading_time)]) }}</span>
                @if ($modified)
                    <span aria-hidden="true" class="size-1 rounded-full bg-zinc-300 dark:bg-zinc-700"></span>
                    <time datetime="{{ $modified->toAtomString() }}" itemprop="dateModified">{{ __('desk-editor::blog.updated', ['date' => $modified->isoFormat('MMM D, YYYY')]) }}</time>
                @endif
            </div>

            @if ($translations->isNotEmpty())
                <div class="mt-6 inline-flex flex-wrap items-center justify-center gap-1.5 rounded-full border border-zinc-200 bg-white/80 p-1 text-xs dark:border-zinc-800 dark:bg-zinc-900/80" role="group" aria-label="{{ __('desk-editor::blog.translations') }}">
                    <span class="px-2 font-medium text-zinc-500 dark:text-zinc-400">{{ __('desk-editor::blog.translations') }}</span>
                    <span class="rounded-full bg-zinc-900 px-2.5 py-1 font-semibold text-white dark:bg-white dark:text-zinc-900" aria-current="true" lang="{{ $article->locale }}">{{ Blog::localeLabel($article->locale) }}</span>
                    @foreach ($translations as $translation)
                        <a href="{{ Blog::url($translation) }}" hreflang="{{ $translation->locale }}" lang="{{ $translation->locale }}" class="rounded-full px-2.5 py-1 font-medium text-zinc-600 transition hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-white">{{ Blog::localeLabel($translation->locale) }}</a>
                    @endforeach
                </div>
            @endif
        </header>

        {{-- Cover --}}
        @if ($article->cover_image)
            <figure class="mx-auto mt-14 max-w-6xl px-4 motion-safe:animate-de-rise sm:px-6" style="animation-delay: 80ms">
                <img src="{{ $article->cover_image }}" alt="{{ $article->cover_alt ?: '' }}" fetchpriority="high" decoding="async" itemprop="image" class="aspect-[21/10] w-full rounded-3xl object-cover shadow-2xl shadow-zinc-900/15 ring-1 ring-zinc-900/5 dark:shadow-black/40 dark:ring-white/10">
                @if ($article->cover_alt)
                    <figcaption class="mt-3 text-center text-sm text-zinc-500 dark:text-zinc-400">{{ $article->cover_alt }}</figcaption>
                @endif
            </figure>
        @endif

        {{-- Body + sidebar --}}
        <div class="mx-auto mt-14 max-w-6xl px-4 sm:px-6 lg:grid lg:grid-cols-[minmax(0,1fr)_15rem] lg:gap-16">
            <div class="mx-auto w-full max-w-[44rem] min-w-0">
                @if (count($outline) > 1)
                    <details class="group mb-10 rounded-2xl border border-zinc-200 bg-zinc-50/70 lg:hidden dark:border-zinc-800 dark:bg-zinc-900/50">
                        <summary class="flex cursor-pointer list-none items-center justify-between px-5 py-4 text-sm font-semibold [&::-webkit-details-marker]:hidden">
                            {{ __('desk-editor::blog.on_this_page') }}
                            <svg viewBox="0 0 24 24" class="size-4 text-zinc-500 dark:text-zinc-400 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                        </summary>
                        <ol class="space-y-2 border-t border-zinc-200 px-5 py-4 text-sm dark:border-zinc-800">
                            @foreach ($outline as $heading)
                                <li @class(['pl-4' => $heading['level'] === 3])><a href="#{{ $heading['id'] }}" class="text-zinc-600 transition hover:text-desk dark:text-zinc-400">{{ $heading['text'] }}</a></li>
                            @endforeach
                        </ol>
                    </details>
                @endif

                <div itemprop="articleBody" class="text-[1.075rem] sm:text-lg">
                    <x-desk-editor::content :html="$article->body" :assets="false" class="[&_h2]:scroll-mt-24 [&_h3]:scroll-mt-24" />
                </div>

                {{-- Tags --}}
                @if ($article->tags->isNotEmpty())
                    <div class="mt-14 flex flex-wrap items-center gap-2" aria-label="{{ __('desk-editor::blog.tags') }}">
                        @foreach ($article->tags as $tag)
                            <a href="{{ route('desk-editor.blog.tag', Blog::params(['slug' => $tag->slug])) }}" rel="tag" class="rounded-full border border-zinc-200 bg-white px-3.5 py-1.5 text-sm font-medium text-zinc-700 transition hover:-translate-y-0.5 hover:border-desk/40 hover:text-desk motion-reduce:hover:translate-y-0 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300">#{{ $tag->name }}</a>
                        @endforeach
                    </div>
                @endif

                {{-- Share --}}
                <div class="mt-10 flex flex-col gap-4 border-y border-zinc-200 py-6 sm:flex-row sm:items-center sm:justify-between dark:border-zinc-800">
                    <span class="text-sm font-semibold">{{ __('desk-editor::blog.share') }}</span>
                    @include('desk-editor::blog.partials.share', ['shareUrl' => $url, 'shareTitle' => $article->title])
                </div>

                {{-- Author card --}}
                @if ($author)
                    <aside class="mt-10 flex flex-col gap-5 rounded-3xl border border-zinc-200 bg-gradient-to-br from-zinc-50 to-white p-6 sm:flex-row sm:items-center sm:p-8 dark:border-zinc-800 dark:from-zinc-900 dark:to-zinc-950" aria-label="{{ __('desk-editor::blog.written_by') }}">
                        <span class="grid size-16 shrink-0 place-items-center rounded-full bg-gradient-to-br from-desk to-violet-600 font-serif text-xl font-bold text-white shadow-lg shadow-desk/25" aria-hidden="true">{{ Blog::initials($author) }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-semibold tracking-[0.18em] text-zinc-500 uppercase dark:text-zinc-400">{{ __('desk-editor::blog.written_by') }}</p>
                            <p class="mt-1 font-serif text-xl font-semibold">{{ $author }}</p>
                        </div>
                        @if ($authorUrl)
                            <a href="{{ $authorUrl }}" class="inline-flex h-10 shrink-0 items-center gap-2 self-start rounded-full border border-zinc-200 bg-white px-4 text-sm font-medium transition hover:border-zinc-300 hover:shadow-sm sm:self-center dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-zinc-600">
                                {{ __('desk-editor::blog.more_by', ['name' => \Illuminate\Support\Str::before($author, ' ')]) }}
                                <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                            </a>
                        @endif
                    </aside>
                @endif

                {{-- Previous / next --}}
                @if ($previous || $next)
                    <nav class="mt-10 grid gap-4 sm:grid-cols-2" aria-label="{{ __('desk-editor::blog.pagination') }}">
                        @if ($next)
                            <a href="{{ Blog::url($next) }}" rel="next" class="group rounded-2xl border border-zinc-200 p-5 transition hover:border-desk/40 hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-900">
                                <span class="flex items-center gap-1.5 text-xs font-semibold tracking-wide text-zinc-500 uppercase dark:text-zinc-400">
                                    <svg viewBox="0 0 24 24" class="size-3.5 transition-transform group-hover:-translate-x-1" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                                    {{ __('desk-editor::blog.newer') }}
                                </span>
                                <span class="mt-2 line-clamp-2 block font-serif text-lg/snug font-semibold transition group-hover:text-desk">{{ $next->title }}</span>
                            </a>
                        @else
                            <span class="hidden sm:block"></span>
                        @endif
                        @if ($previous)
                            <a href="{{ Blog::url($previous) }}" rel="prev" class="group rounded-2xl border border-zinc-200 p-5 text-right transition hover:border-desk/40 hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-900">
                                <span class="flex items-center justify-end gap-1.5 text-xs font-semibold tracking-wide text-zinc-500 uppercase dark:text-zinc-400">
                                    {{ __('desk-editor::blog.older') }}
                                    <svg viewBox="0 0 24 24" class="size-3.5 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                                </span>
                                <span class="mt-2 line-clamp-2 block font-serif text-lg/snug font-semibold transition group-hover:text-desk">{{ $previous->title }}</span>
                            </a>
                        @endif
                    </nav>
                @endif

                {{-- Comments --}}
                @include('desk-editor::comments.section')
            </div>

            {{-- Sidebar --}}
            <aside class="hidden lg:block" aria-label="{{ __('desk-editor::blog.on_this_page') }}">
                <div class="sticky top-28 space-y-10">
                    @if (count($outline) > 1)
                        <nav data-toc>
                            <p class="text-[11px] font-semibold tracking-[0.18em] text-zinc-500 uppercase dark:text-zinc-400">{{ __('desk-editor::blog.on_this_page') }}</p>
                            <ol class="relative mt-4 space-y-1 border-l border-zinc-200 text-sm dark:border-zinc-800">
                                @foreach ($outline as $heading)
                                    <li>
                                        <a href="#{{ $heading['id'] }}" data-toc-link="{{ $heading['id'] }}"
                                           @class([
                                               '-ml-px block border-l-2 border-transparent py-1 leading-snug text-zinc-500 transition-colors hover:text-zinc-900 data-[active=true]:border-desk data-[active=true]:font-medium data-[active=true]:text-desk dark:text-zinc-400 dark:hover:text-white',
                                               'pl-4' => $heading['level'] === 2,
                                               'pl-8 text-[13px]' => $heading['level'] === 3,
                                           ])>{{ $heading['text'] }}</a>
                                    </li>
                                @endforeach
                            </ol>
                        </nav>
                    @endif
                    <div>
                        <p class="mb-4 text-[11px] font-semibold tracking-[0.18em] text-zinc-500 uppercase dark:text-zinc-400">{{ __('desk-editor::blog.share') }}</p>
                        @include('desk-editor::blog.partials.share', ['shareUrl' => $url, 'shareTitle' => $article->title])
                    </div>
                    <a href="#main" class="inline-flex items-center gap-1.5 text-sm text-zinc-500 transition hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white">
                        <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="m18 15-6-6-6 6"/></svg>
                        {{ __('desk-editor::blog.back_to_top') }}
                    </a>
                </div>
            </aside>
        </div>
    </article>

    {{-- Related --}}
    @if ($related->isNotEmpty())
        <section class="mx-auto mt-24 max-w-7xl border-t border-zinc-200 px-4 pt-16 sm:px-6 lg:px-8 dark:border-zinc-800" aria-labelledby="related-title">
            <h2 id="related-title" class="font-serif text-3xl font-bold tracking-tight">{{ __('desk-editor::blog.related') }}</h2>
            <div class="mt-10 grid gap-x-8 gap-y-14 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($related as $item)
                    @include('desk-editor::blog.partials.card', ['article' => $item, 'i' => $loop->index])
                @endforeach
            </div>
        </section>
    @endif
</main>
@endsection

@push('desk-blog-scripts')
<script>
    (() => {
        // Reading progress.
        const bar = document.querySelector('[data-reading-progress]');
        const body = document.querySelector('[itemprop="articleBody"]');
        if (bar && body) {
            let ticking = false;
            const update = () => {
                const rect = body.getBoundingClientRect();
                const total = rect.height - innerHeight * 0.6;
                const progress = Math.min(1, Math.max(0, (-rect.top + innerHeight * 0.2) / Math.max(1, total)));
                bar.style.transform = `scaleX(${progress})`;
                ticking = false;
            };
            addEventListener('scroll', () => { if (!ticking) { ticking = true; requestAnimationFrame(update); } }, { passive: true });
            addEventListener('resize', update);
            update();
        }

        // Table of contents: highlight the section being read.
        const links = [...document.querySelectorAll('[data-toc-link]')];
        if (links.length && 'IntersectionObserver' in window) {
            const headings = links.map((l) => document.getElementById(l.dataset.tocLink)).filter(Boolean);
            const visible = new Set();
            const setActive = (id) => links.forEach((l) => {
                const on = l.dataset.tocLink === id;
                l.dataset.active = on ? 'true' : 'false';
                on ? l.setAttribute('aria-current', 'location') : l.removeAttribute('aria-current');
            });
            const observer = new IntersectionObserver((entries) => {
                entries.forEach((e) => e.isIntersecting ? visible.add(e.target.id) : visible.delete(e.target.id));
                const first = headings.find((h) => visible.has(h.id));
                if (first) { setActive(first.id); return; }
                const above = headings.filter((h) => h.getBoundingClientRect().top < 120).pop();
                if (above) setActive(above.id);
            }, { rootMargin: '-96px 0px -55% 0px' });
            headings.forEach((h) => observer.observe(h));
        }

        // Sharing.
        document.querySelectorAll('[data-share]').forEach((box) => {
            const url = box.dataset.shareUrl;
            const title = box.dataset.shareTitle;
            const copy = box.querySelector('[data-share-copy]');
            copy?.addEventListener('click', async () => {
                try { await navigator.clipboard.writeText(url); } catch (e) {
                    const input = Object.assign(document.createElement('textarea'), { value: url });
                    document.body.append(input); input.select(); document.execCommand('copy'); input.remove();
                }
                const tip = copy.querySelector('[data-copied-tip]');
                copy.querySelector('[data-copy-icon]')?.classList.add('hidden');
                copy.querySelector('[data-copied-icon]')?.classList.remove('hidden');
                tip?.classList.remove('hidden');
                clearTimeout(copy._t);
                copy._t = setTimeout(() => {
                    copy.querySelector('[data-copy-icon]')?.classList.remove('hidden');
                    copy.querySelector('[data-copied-icon]')?.classList.add('hidden');
                    tip?.classList.add('hidden');
                }, 1800);
            });
            const native = box.querySelector('[data-share-native]');
            if (native && navigator.share) {
                native.classList.remove('hidden!');
                native.addEventListener('click', () => navigator.share({ title, url }).catch(() => {}));
            }
        });
    })();
</script>
@endpush
