@php
    use Atannex\DeskEditor\Blog\Support\Blog;
    /** @var \Atannex\DeskEditor\Blog\Support\Seo $seo */
    $seo ??= \Atannex\DeskEditor\Blog\Support\Seo::make();
    $blogName = Blog::name();
    $accent = Blog::accent();
    $navLocale = Blog::locale(request());
    $navCategories = Blog::navCategories($navLocale);
    $htmlLang = str_replace('_', '-', $seo->locale ?? $navLocale ?? app()->getLocale());
@endphp
<!doctype html>
<html lang="{{ $htmlLang }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $seo->documentTitle() }}</title>
    <script>
        (() => {
            const root = document.documentElement;
            let pref = null;
            try { pref = localStorage.getItem('desk-editor-theme'); } catch (e) {}
            if (pref !== 'light' && pref !== 'dark') pref = 'system';
            root.dataset.themePref = pref;
            root.dataset.theme = pref === 'system' ? (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light') : pref;
        })();
    </script>
    @if ($seo->description)<meta name="description" content="{{ $seo->description }}">@endif
    @if ($seo->robots)<meta name="robots" content="{{ $seo->robots }}">@endif
    @if ($seo->canonical)<link rel="canonical" href="{{ $seo->canonical }}">@endif
    @foreach ($seo->alternates as $hreflang => $href)
        <link rel="alternate" hreflang="{{ $hreflang }}" href="{{ $href }}">
    @endforeach
    <link rel="alternate" type="application/rss+xml" title="{{ $blogName }}" href="{{ route('desk-editor.blog.feed', Blog::params()) }}">
    <link rel="alternate" type="application/atom+xml" title="{{ $blogName }}" href="{{ route('desk-editor.blog.atom', Blog::params()) }}">
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#09090b" media="(prefers-color-scheme: dark)">

    {{-- Open Graph / Twitter --}}
    <meta property="og:site_name" content="{{ $blogName }}">
    <meta property="og:type" content="{{ $seo->type }}">
    <meta property="og:title" content="{{ $seo->title }}">
    @if ($seo->description)<meta property="og:description" content="{{ $seo->description }}">@endif
    @if ($seo->canonical)<meta property="og:url" content="{{ $seo->canonical }}">@endif
    <meta property="og:locale" content="{{ str_replace('-', '_', $htmlLang) }}">
    @if ($seo->image)
        <meta property="og:image" content="{{ $seo->image }}">
        <meta property="og:image:alt" content="{{ $seo->imageAlt }}">
    @endif
    @foreach ($seo->properties as $property => $content)
        <meta property="{{ \Illuminate\Support\Str::startsWith($property, 'article:tag:') ? 'article:tag' : $property }}" content="{{ $content }}">
    @endforeach
    <meta name="twitter:card" content="{{ $seo->image ? 'summary_large_image' : 'summary' }}">
    <meta name="twitter:title" content="{{ $seo->title }}">
    @if ($seo->description)<meta name="twitter:description" content="{{ $seo->description }}">@endif
    @if ($seo->image)<meta name="twitter:image" content="{{ $seo->image }}">@endif
    @if ($seo->schemas)
        <script type="application/ld+json">{!! $seo->jsonLd() !!}</script>
    @endif

    @deskEditorStyles
    @stack('desk-blog-head')
</head>
<body class="de-app de-blog font-desk min-h-screen bg-white text-zinc-900 antialiased transition-colors duration-300 selection:bg-desk/20 dark:bg-zinc-950 dark:text-zinc-100" @if ($accent) style="--de-accent: {{ $accent }}" @endif>
    <a href="#main" class="sr-only z-[60] rounded-lg bg-desk px-4 py-2 text-sm font-semibold text-white shadow-lg focus:not-sr-only focus:fixed focus:top-3 focus:left-3">{{ __('desk-editor::blog.skip') }}</a>

    @yield('progress')

    <header class="sticky top-0 z-40 border-b border-zinc-200/70 bg-white/75 backdrop-blur-xl backdrop-saturate-150 supports-[backdrop-filter]:bg-white/60 dark:border-zinc-800/70 dark:bg-zinc-950/70 dark:supports-[backdrop-filter]:bg-zinc-950/55">
        <div class="mx-auto flex h-16 max-w-7xl items-center gap-3 px-4 sm:px-6 lg:px-8">
            <a href="{{ route('desk-editor.blog.home', Blog::params()) }}" class="group flex min-w-0 items-center gap-2.5 rounded-lg outline-none focus-visible:ring-2 focus-visible:ring-desk/60">
                <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-desk font-serif text-lg font-bold text-white shadow-sm shadow-desk/30 transition-transform duration-300 group-hover:-rotate-6 motion-reduce:transition-none">{{ \Illuminate\Support\Str::upper(mb_substr($blogName, 0, 1)) }}</span>
                <span class="truncate font-serif text-lg font-semibold tracking-tight sm:text-xl">{{ $blogName }}</span>
            </a>

            <nav class="ml-6 hidden min-w-0 flex-1 items-center gap-1 overflow-hidden lg:flex" aria-label="{{ __('desk-editor::blog.topics') }}">
                @foreach ($navCategories->take(6) as $navCategory)
                    @php $current = request()->routeIs('desk-editor.blog.category') && request()->route('slug') === $navCategory->slug; @endphp
                    <a href="{{ route('desk-editor.blog.category', Blog::params(['slug' => $navCategory->slug])) }}"
                       @if ($current) aria-current="page" @endif
                       @class([
                           'shrink-0 rounded-full px-3 py-1.5 text-sm font-medium transition outline-none focus-visible:ring-2 focus-visible:ring-desk/60',
                           'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' => $current,
                           'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800/80 dark:hover:text-white' => ! $current,
                       ])>{{ $navCategory->name }}</a>
                @endforeach
            </nav>
            <div class="flex-1 lg:hidden"></div>

            <form action="{{ route('desk-editor.blog.search') }}" method="GET" role="search" class="relative hidden md:block">
                @if (request('lang'))<input type="hidden" name="lang" value="{{ request('lang') }}">@endif
                <label for="desk-blog-search" class="sr-only">{{ __('desk-editor::blog.search') }}</label>
                <svg viewBox="0 0 24 24" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-zinc-500 dark:text-zinc-400" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <input id="desk-blog-search" type="search" name="q" value="{{ request()->routeIs('desk-editor.blog.search') ? request('q') : '' }}" placeholder="{{ __('desk-editor::blog.search_placeholder') }}"
                       class="h-9 w-44 rounded-full border border-zinc-200 bg-zinc-50/80 pr-3 pl-9 text-sm transition-[width,box-shadow,border-color] duration-300 outline-none placeholder:text-zinc-400 focus:w-64 focus:border-desk/60 focus:bg-white focus:ring-4 focus:ring-desk/15 dark:border-zinc-800 dark:bg-zinc-900/80 dark:focus:bg-zinc-900">
            </form>

            <a href="{{ route('desk-editor.blog.search', Blog::params()) }}" class="grid size-9 place-items-center rounded-full text-zinc-500 transition outline-none hover:bg-zinc-100 hover:text-zinc-900 focus-visible:ring-2 focus-visible:ring-desk/60 md:hidden dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white" aria-label="{{ __('desk-editor::blog.search') }}">
                <svg viewBox="0 0 24 24" class="size-[18px]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            </a>

            <button type="button" data-theme-cycle class="grid size-9 shrink-0 cursor-pointer place-items-center rounded-full text-zinc-500 transition outline-none hover:bg-zinc-100 hover:text-zinc-900 focus-visible:ring-2 focus-visible:ring-desk/60 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white" aria-label="{{ __('desk-editor::blog.theme') }}" title="{{ __('desk-editor::blog.theme') }}">
                <svg data-theme-icon="light" class="size-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2m-7.07-14.07 1.41 1.41m11.32 11.32 1.41 1.41M2 12h2m16 0h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
                <svg data-theme-icon="dark" class="hidden size-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
                <svg data-theme-icon="system" class="hidden size-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
                <span class="sr-only" data-theme-label aria-live="polite"></span>
            </button>

            @if ($navCategories->isNotEmpty())
                <details class="group relative lg:hidden">
                    <summary class="grid size-9 cursor-pointer list-none place-items-center rounded-full text-zinc-500 transition outline-none hover:bg-zinc-100 hover:text-zinc-900 focus-visible:ring-2 focus-visible:ring-desk/60 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white [&::-webkit-details-marker]:hidden" aria-label="{{ __('desk-editor::blog.menu') }}">
                        <svg viewBox="0 0 24 24" class="size-5 group-open:hidden" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h10"/></svg>
                        <svg viewBox="0 0 24 24" class="hidden size-5 group-open:block" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
                    </summary>
                    <nav class="animate-de-pop absolute top-12 right-0 w-64 rounded-2xl border border-zinc-200 bg-white/95 p-2 shadow-2xl shadow-zinc-900/10 backdrop-blur-xl dark:border-zinc-800 dark:bg-zinc-900/95 dark:shadow-black/40" aria-label="{{ __('desk-editor::blog.topics') }}">
                        <div class="px-3 pt-2 pb-1 text-[11px] font-semibold tracking-widest text-zinc-500 dark:text-zinc-400 uppercase">{{ __('desk-editor::blog.topics') }}</div>
                        @foreach ($navCategories as $navCategory)
                            <a href="{{ route('desk-editor.blog.category', Blog::params(['slug' => $navCategory->slug])) }}" class="flex items-center justify-between rounded-xl px-3 py-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800">
                                {{ $navCategory->name }}
                                <span class="text-xs text-zinc-500 tabular-nums">{{ $navCategory->getAttribute('published_count') }}</span>
                            </a>
                        @endforeach
                    </nav>
                </details>
            @endif
        </div>
    </header>

    <div id="main" tabindex="-1" class="outline-none">
        @yield('content')
    </div>

    <footer class="mt-24 border-t border-zinc-200 bg-zinc-50/60 dark:border-zinc-800/80 dark:bg-zinc-900/30" role="contentinfo">
        <div class="mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:px-6 md:grid-cols-[1.4fr_1fr] lg:px-8">
            <div class="max-w-md">
                <a href="{{ route('desk-editor.blog.home', Blog::params()) }}" class="inline-flex items-center gap-2.5">
                    <span class="grid size-8 place-items-center rounded-lg bg-desk font-serif text-base font-bold text-white">{{ \Illuminate\Support\Str::upper(mb_substr($blogName, 0, 1)) }}</span>
                    <span class="font-serif text-lg font-semibold tracking-tight">{{ $blogName }}</span>
                </a>
                @if (Blog::description())
                    <p class="mt-3 text-sm/6 text-zinc-500 dark:text-zinc-400">{{ Blog::description() }}</p>
                @endif
                <div class="mt-5 flex flex-wrap gap-2">
                    <a href="{{ route('desk-editor.blog.feed', Blog::params()) }}" class="inline-flex items-center gap-1.5 rounded-full border border-zinc-200 bg-white px-3 py-1.5 text-xs font-medium text-zinc-700 transition hover:border-orange-300 hover:text-orange-600 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:border-orange-500/50 dark:hover:text-orange-400">
                        <svg viewBox="0 0 24 24" class="size-3.5" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M4 11a9 9 0 0 1 9 9M4 4a16 16 0 0 1 16 16"/><circle cx="5" cy="19" r="1"/></svg>
                        {{ __('desk-editor::blog.rss') }}
                    </a>
                    <a href="{{ route('desk-editor.blog.atom', Blog::params()) }}" class="inline-flex items-center rounded-full border border-zinc-200 bg-white px-3 py-1.5 text-xs font-medium text-zinc-700 transition hover:text-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:text-white">{{ __('desk-editor::blog.atom') }}</a>
                    @if (Blog::multilingual())
                        <div class="inline-flex items-center gap-1 rounded-full border border-zinc-200 bg-white p-0.5 dark:border-zinc-700 dark:bg-zinc-900" role="group" aria-label="{{ __('desk-editor::blog.language') }}">
                            @foreach (Blog::locales() as $code => $label)
                                <a href="{{ route('desk-editor.blog.home', $code === Blog::defaultLocale() ? [] : ['lang' => $code]) }}" hreflang="{{ $code }}" @class(['rounded-full px-2.5 py-1 text-xs font-medium transition', 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' => $navLocale === $code, 'text-zinc-500 hover:text-zinc-900 dark:hover:text-white' => $navLocale !== $code])>{{ $label }}</a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
            @if ($navCategories->isNotEmpty())
                <nav aria-label="{{ __('desk-editor::blog.topics') }}">
                    <h2 class="text-[11px] font-semibold tracking-widest text-zinc-500 dark:text-zinc-400 uppercase">{{ __('desk-editor::blog.topics') }}</h2>
                    <ul class="mt-4 grid grid-cols-2 gap-x-6 gap-y-2.5 text-sm">
                        @foreach ($navCategories as $navCategory)
                            <li><a href="{{ route('desk-editor.blog.category', Blog::params(['slug' => $navCategory->slug])) }}" class="text-zinc-600 transition hover:text-desk dark:text-zinc-400">{{ $navCategory->name }}</a></li>
                        @endforeach
                    </ul>
                </nav>
            @endif
        </div>
        <div class="border-t border-zinc-200/80 dark:border-zinc-800/80">
            <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-2 px-4 py-6 text-xs text-zinc-500 sm:flex-row sm:px-6 lg:px-8 dark:text-zinc-500">
                <p>{{ __('desk-editor::blog.rights', ['year' => now()->year, 'name' => $blogName]) }}</p>
                <div class="flex items-center gap-4">
                    <a href="{{ route('desk-editor.blog.sitemap') }}" class="transition hover:text-zinc-900 dark:hover:text-white">{{ __('desk-editor::blog.sitemap') }}</a>
                    <span>{{ __('desk-editor::blog.powered') }}</span>
                </div>
            </div>
        </div>
    </footer>

    <script>
        (() => {
            const root = document.documentElement;
            const button = document.querySelector('[data-theme-cycle]');
            const media = matchMedia('(prefers-color-scheme: dark)');
            const labels = { light: @json(__('desk-editor::blog.theme_light')), dark: @json(__('desk-editor::blog.theme_dark')), system: @json(__('desk-editor::blog.theme_system')) };
            const apply = (pref) => {
                root.dataset.themePref = pref;
                root.dataset.theme = pref === 'system' ? (media.matches ? 'dark' : 'light') : pref;
                button?.querySelectorAll('[data-theme-icon]').forEach((icon) => { icon.classList.toggle('hidden', icon.dataset.themeIcon !== pref); });
                const label = button?.querySelector('[data-theme-label]');
                if (label) label.textContent = labels[pref];
                button?.setAttribute('title', @json(__('desk-editor::blog.theme')) + ': ' + labels[pref]);
            };
            apply(root.dataset.themePref || 'system');
            media.addEventListener?.('change', () => root.dataset.themePref === 'system' && apply('system'));
            button?.addEventListener('click', () => {
                const order = ['light', 'dark', 'system'];
                const next = order[(order.indexOf(root.dataset.themePref || 'system') + 1) % order.length];
                try { next === 'system' ? localStorage.removeItem('desk-editor-theme') : localStorage.setItem('desk-editor-theme', next); } catch (e) {}
                apply(next);
            });
        })();
    </script>
    @deskEditorScripts
    @stack('desk-blog-scripts')
</body>
</html>
