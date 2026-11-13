@php
    $deskNavigation = app(\Atannex\DeskEditor\Articles\Support\Navigation::class);
    $deskNav = $deskNavigation->items();
    $deskBlog = $deskNavigation->blog();
    $deskUser = auth()->user();
    $deskUserName = $deskUser ? \Atannex\DeskEditor\Articles\Support\EditLock::nameOf($deskUser) : null;
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('desk-editor::articles.title')) · {{ config('app.name') }}</title>
    <script>
        (() => {
            let theme = null;
            try { theme = localStorage.getItem('desk-editor-theme'); } catch (e) {}
            if (!theme) theme = matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            document.documentElement.dataset.theme = theme;
        })();
    </script>
    @deskEditorStyles
</head>
<body class="de-app font-desk min-h-screen bg-zinc-50 text-zinc-900 antialiased transition-colors dark:bg-zinc-950 dark:text-zinc-100">
    <a href="#desk-main" class="sr-only rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-[60]">Skip to content</a>

    <header class="sticky top-0 z-40 border-b border-zinc-200/80 bg-white/80 backdrop-blur-xl dark:border-zinc-800 dark:bg-zinc-950/80" data-desk-header>
        <div class="mx-auto flex h-14 max-w-7xl items-center gap-2 px-4 sm:gap-3 sm:px-6">
            <button type="button" data-nav-open class="-ml-1.5 grid size-9 shrink-0 cursor-pointer place-items-center rounded-lg border-0 bg-transparent text-zinc-600 transition hover:bg-zinc-100 hover:text-zinc-900 lg:hidden dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-white" aria-label="{{ __('desk-editor::articles.nav.menu') }}" aria-controls="desk-mobile-nav" aria-expanded="false">
                <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>

            <a href="{{ route('desk-editor.articles.index') }}" class="group flex shrink-0 items-center gap-2.5 font-semibold tracking-tight text-zinc-900 no-underline dark:text-white" aria-label="Desk — {{ __('desk-editor::articles.title') }}">
                <span class="grid size-8 place-items-center rounded-lg bg-gradient-to-br from-indigo-500 to-violet-600 text-white shadow-sm shadow-indigo-600/30 transition-transform group-hover:-rotate-6">
                    <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.4 3.6a2.1 2.1 0 1 1 3 3L7 19l-4 1 1-4Z"/></svg>
                </span>
                <span class="hidden sm:inline">Desk</span>
            </a>

            <nav class="ml-3 hidden items-center gap-0.5 lg:flex" aria-label="{{ __('desk-editor::articles.nav.label') }}" data-desk-nav>
                @foreach ($deskNav as $item)
                    <a href="{{ $item['url'] }}" @if ($item['active']) aria-current="page" @endif title="{{ $item['label'] }}"
                       @class([
                           'relative inline-flex h-8 shrink-0 items-center gap-1.5 rounded-lg px-2.5 text-[13px] font-medium whitespace-nowrap no-underline transition',
                           'bg-zinc-900/5 text-zinc-900 dark:bg-white/10 dark:text-white' => $item['active'],
                           'text-zinc-500 hover:bg-zinc-900/5 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-white/5 dark:hover:text-white' => ! $item['active'],
                       ])>
                        <svg viewBox="0 0 24 24" @class(['size-4', 'text-indigo-600 dark:text-indigo-400' => $item['active']]) fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $item['icon'] }}"/></svg>
                        <span class="hidden xl:inline">{{ $item['label'] }}</span>
                        <span class="sr-only xl:hidden">{{ $item['label'] }}</span>
                        @if ($item['badge'])
                            <span class="min-w-5 rounded-full bg-rose-500 px-1.5 text-center text-[10px] leading-4 font-semibold text-white tabular-nums" data-nav-badge="{{ $item['key'] }}">{{ $item['badge'] > 99 ? '99+' : $item['badge'] }}</span>
                        @endif
                        @if ($item['active'])
                            <span class="absolute inset-x-2.5 -bottom-[11px] h-0.5 rounded-full bg-indigo-500"></span>
                        @endif
                    </a>
                @endforeach
                @if ($deskBlog)
                    <a href="{{ $deskBlog['url'] }}" target="_blank" rel="noopener" title="{{ $deskBlog['label'] }}" class="inline-flex h-8 shrink-0 items-center gap-1 rounded-lg px-2.5 text-[13px] font-medium whitespace-nowrap text-zinc-500 no-underline transition hover:bg-zinc-900/5 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-white/5 dark:hover:text-white">
                        <span class="hidden xl:inline">{{ $deskBlog['label'] }}</span>
                        <span class="sr-only xl:hidden">{{ $deskBlog['label'] }}</span>
                        <svg viewBox="0 0 24 24" class="size-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 7h10v10M7 17 17 7"/></svg>
                    </a>
                @endif
            </nav>

            <div class="min-w-0 flex-1"></div>
            <div class="flex min-w-0 items-center gap-2">
                @yield('header-actions')
            </div>
            <button type="button" data-theme-toggle class="grid size-9 shrink-0 cursor-pointer place-items-center rounded-lg border-0 bg-transparent text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white" title="{{ __('desk-editor::articles.theme') }}" aria-label="{{ __('desk-editor::articles.theme') }}">
                <svg class="size-[18px] dark:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
                <svg class="hidden size-[18px] dark:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2m-7.07-14.07 1.41 1.41m11.32 11.32 1.41 1.41M2 12h2m16 0h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
            </button>
            @auth
                <span class="hidden size-8 shrink-0 place-items-center rounded-full text-xs font-semibold sm:grid {{ \Atannex\DeskEditor\Articles\Support\Authors::tone($deskUserName) }}" title="{{ $deskUserName }}">{{ \Atannex\DeskEditor\Articles\Support\Authors::initials($deskUserName) }}</span>
            @endauth
        </div>
    </header>

    {{-- Mobile navigation (slide-in panel) --}}
    <div id="desk-mobile-nav" data-nav-panel class="fixed inset-0 z-50 hidden lg:hidden" role="dialog" aria-modal="true" aria-label="{{ __('desk-editor::articles.nav.label') }}">
        <div data-nav-backdrop class="absolute inset-0 bg-zinc-950/40 opacity-0 backdrop-blur-sm transition-opacity duration-300"></div>
        <div data-nav-sheet class="absolute inset-y-0 left-0 flex w-[min(20rem,85vw)] -translate-x-full flex-col border-r border-zinc-200 bg-white shadow-2xl transition-transform duration-300 ease-out dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex h-14 items-center justify-between border-b border-zinc-100 px-4 dark:border-zinc-800">
                <span class="flex items-center gap-2.5 font-semibold tracking-tight">
                    <span class="grid size-8 place-items-center rounded-lg bg-gradient-to-br from-indigo-500 to-violet-600 text-white shadow-sm shadow-indigo-600/30">
                        <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.4 3.6a2.1 2.1 0 1 1 3 3L7 19l-4 1 1-4Z"/></svg>
                    </span>
                    Desk
                </span>
                <button type="button" data-nav-close class="grid size-9 cursor-pointer place-items-center rounded-lg border-0 bg-transparent text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-900 dark:hover:bg-zinc-800 dark:hover:text-white" aria-label="{{ __('desk-editor::articles.nav.close') }}">
                    <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>
            <nav class="flex flex-1 flex-col gap-1 overflow-y-auto p-3" aria-label="{{ __('desk-editor::articles.nav.label') }}">
                @foreach ($deskNav as $item)
                    <a href="{{ $item['url'] }}" @if ($item['active']) aria-current="page" @endif
                       @class([
                           'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium no-underline transition',
                           'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300' => $item['active'],
                           'text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800' => ! $item['active'],
                       ])>
                        <svg viewBox="0 0 24 24" class="size-[18px]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $item['icon'] }}"/></svg>
                        <span class="flex-1">{{ $item['label'] }}</span>
                        @if ($item['badge'])
                            <span class="min-w-5 rounded-full bg-rose-500 px-1.5 text-center text-[11px] leading-5 font-semibold text-white tabular-nums">{{ $item['badge'] > 99 ? '99+' : $item['badge'] }}</span>
                        @endif
                    </a>
                @endforeach
                @if ($deskBlog)
                    <a href="{{ $deskBlog['url'] }}" target="_blank" rel="noopener" class="mt-2 flex items-center gap-3 rounded-xl border border-dashed border-zinc-200 px-3 py-2.5 text-sm font-medium text-zinc-600 no-underline transition hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800">
                        <svg viewBox="0 0 24 24" class="size-[18px]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6M10 14 21 3M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg>
                        {{ $deskBlog['label'] }}
                    </a>
                @endif
            </nav>
            @auth
                <div class="flex items-center gap-3 border-t border-zinc-100 p-4 dark:border-zinc-800">
                    <span class="grid size-9 place-items-center rounded-full text-xs font-semibold {{ \Atannex\DeskEditor\Articles\Support\Authors::tone($deskUserName) }}">{{ \Atannex\DeskEditor\Articles\Support\Authors::initials($deskUserName) }}</span>
                    <div class="min-w-0">
                        <div class="text-[11px] text-zinc-500 dark:text-zinc-400">{{ __('desk-editor::articles.nav.signed_in_as') }}</div>
                        <div class="truncate text-sm font-medium">{{ $deskUserName }}</div>
                    </div>
                </div>
            @endauth
        </div>
    </div>

    @if (session('desk-status'))
        <div data-flash class="animate-de-pop fixed top-16 left-1/2 z-50 flex max-w-[calc(100vw-2rem)] -translate-x-1/2 items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white shadow-xl dark:bg-white dark:text-zinc-900" role="status">
            <svg viewBox="0 0 24 24" class="size-4 shrink-0 text-emerald-400 dark:text-emerald-600" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
            {{ session('desk-status') }}
        </div>
    @endif

    <div id="desk-main">
        @yield('content')
    </div>

    <script>
        (() => {
            document.querySelector('[data-theme-toggle]')?.addEventListener('click', () => {
                const next = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
                document.documentElement.dataset.theme = next;
                try { localStorage.setItem('desk-editor-theme', next); } catch (e) {}
            });
            setTimeout(() => document.querySelector('[data-flash]')?.animate([{ opacity: 1 }, { opacity: 0, transform: 'translate(-50%, -8px)' }], { duration: 300, fill: 'forwards' }), 3200);

            const panel = document.querySelector('[data-nav-panel]');
            const sheet = panel?.querySelector('[data-nav-sheet]');
            const backdrop = panel?.querySelector('[data-nav-backdrop]');
            const opener = document.querySelector('[data-nav-open]');
            if (!panel || !opener) return;
            const open = () => {
                panel.classList.remove('hidden');
                opener.setAttribute('aria-expanded', 'true');
                requestAnimationFrame(() => {
                    sheet.classList.remove('-translate-x-full');
                    backdrop.classList.remove('opacity-0');
                    panel.querySelector('a, button')?.focus();
                });
                document.body.classList.add('overflow-hidden');
            };
            const close = () => {
                sheet.classList.add('-translate-x-full');
                backdrop.classList.add('opacity-0');
                opener.setAttribute('aria-expanded', 'false');
                document.body.classList.remove('overflow-hidden');
                setTimeout(() => panel.classList.add('hidden'), 300);
                opener.focus();
            };
            opener.addEventListener('click', open);
            backdrop.addEventListener('click', close);
            panel.querySelector('[data-nav-close]').addEventListener('click', close);
            document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !panel.classList.contains('hidden')) close(); });
        })();
    </script>
    @deskEditorScripts
    @stack('desk-scripts')
</body>
</html>
