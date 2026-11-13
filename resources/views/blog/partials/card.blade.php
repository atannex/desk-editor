@php
    use Atannex\DeskEditor\Blog\Support\Blog;
    /** @var \Atannex\DeskEditor\Models\DeskArticle $article */
    $term ??= null;
    $i ??= 0;
    $url = Blog::url($article);
    $author = Blog::authorName($article);
@endphp
<article class="group relative flex flex-col motion-safe:animate-de-rise" style="animation-delay: {{ min($i, 8) * 60 }}ms">
    <a href="{{ $url }}" class="relative block aspect-[16/10] overflow-hidden rounded-2xl bg-zinc-100 ring-1 ring-zinc-900/5 outline-none focus-visible:ring-4 focus-visible:ring-desk/40 dark:bg-zinc-900 dark:ring-white/10" tabindex="-1" aria-hidden="true">
        @if ($article->cover_image)
            <img src="{{ $article->cover_image }}" alt="" loading="lazy" decoding="async" class="size-full object-cover transition duration-700 ease-out group-hover:scale-[1.04] motion-reduce:transition-none motion-reduce:group-hover:scale-100">
        @else
            <div class="flex size-full items-end bg-gradient-to-br from-desk/25 via-desk/5 to-transparent p-5">
                <span class="font-serif text-5xl leading-none font-bold text-desk/30 select-none">{{ \Illuminate\Support\Str::upper(mb_substr($article->title, 0, 1)) }}</span>
            </div>
        @endif
        <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/20 to-transparent opacity-0 transition-opacity duration-500 group-hover:opacity-100"></div>
    </a>
    <div class="flex flex-1 flex-col pt-5">
        <div class="flex items-center gap-2 text-xs font-medium">
            @if ($article->category)
                <a href="{{ route('desk-editor.blog.category', Blog::params(['slug' => $article->category->slug])) }}" class="relative z-10 rounded-full bg-desk/10 px-2.5 py-1 font-semibold tracking-wide text-desk uppercase transition hover:bg-desk/15 text-[11px]">{{ $article->category->name }}</a>
            @endif
            <time datetime="{{ $article->published_at?->toAtomString() }}" class="text-zinc-500 dark:text-zinc-400">{{ $article->published_at?->isoFormat('MMM D, YYYY') }}</time>
        </div>
        <h3 class="mt-3 font-serif text-xl/snug font-semibold tracking-tight text-zinc-950 text-balance dark:text-white">
            <a href="{{ $url }}" class="outline-none after:absolute after:inset-0 after:rounded-2xl focus-visible:underline">
                <span class="bg-gradient-to-r from-current to-current bg-[length:0%_1px] bg-left-bottom bg-no-repeat transition-[background-size] duration-500 group-hover:bg-[length:100%_1px] motion-reduce:transition-none">{!! Blog::highlight($article->title, $term) !!}</span>
            </a>
        </h3>
        @if ($article->excerpt || $article->subtitle)
            <p class="mt-2 line-clamp-3 text-[15px]/relaxed text-zinc-600 dark:text-zinc-400">{!! Blog::highlight($article->excerpt ?: $article->subtitle, $term) !!}</p>
        @endif
        <div class="mt-auto flex items-center gap-2.5 pt-5 text-xs text-zinc-500 dark:text-zinc-400">
            @if ($author)
                <span class="grid size-6 place-items-center rounded-full bg-zinc-200 text-[10px] font-semibold text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300" aria-hidden="true">{{ Blog::initials($author) }}</span>
                <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $author }}</span>
                <span aria-hidden="true">·</span>
            @endif
            <span>{{ __('desk-editor::blog.min_read', ['count' => max(1, $article->reading_time)]) }}</span>
        </div>
    </div>
</article>
