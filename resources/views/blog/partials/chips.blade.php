@php
    use Atannex\DeskEditor\Blog\Support\Blog;
    /** @var \Illuminate\Support\Collection $categories */
    $activeCategory ??= null;
    $chipBase = 'inline-flex shrink-0 items-center gap-1.5 rounded-full border px-4 py-2 text-sm font-medium transition outline-none focus-visible:ring-2 focus-visible:ring-desk/60';
    $on = 'border-zinc-900 bg-zinc-900 text-white shadow-sm dark:border-white dark:bg-white dark:text-zinc-900';
    $off = 'border-zinc-200 bg-white text-zinc-600 hover:border-zinc-300 hover:text-zinc-900 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-400 dark:hover:border-zinc-700 dark:hover:text-white';
@endphp
@if ($categories->isNotEmpty())
    <nav class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 [scrollbar-width:none] sm:mx-0 sm:flex-wrap sm:px-0" aria-label="{{ __('desk-editor::blog.topics') }}">
        <a href="{{ route('desk-editor.blog.home', Blog::params()) }}" @class([$chipBase, $on => ! $activeCategory, $off => $activeCategory]) @if (! $activeCategory) aria-current="page" @endif>{{ __('desk-editor::blog.all') }}</a>
        @foreach ($categories as $category)
            <a href="{{ route('desk-editor.blog.home', Blog::params(['category' => $category->slug])) }}" @class([$chipBase, $on => $activeCategory === $category->slug, $off => $activeCategory !== $category->slug]) @if ($activeCategory === $category->slug) aria-current="page" @endif>
                @if (Blog::color($category->color))<span class="size-2 rounded-full" style="background: {{ Blog::color($category->color) }}" aria-hidden="true"></span>@endif
                {{ $category->name }}
            </a>
        @endforeach
    </nav>
@endif
