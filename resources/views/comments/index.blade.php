@extends('desk-editor::articles.layout')

@php
    use Atannex\DeskEditor\Blog\Enums\CommentStatus;
    use Atannex\DeskEditor\Blog\Support\Blog;
    $tabs = [];
    foreach (CommentStatus::cases() as $case) {
        $tabs[$case->value] = [$case->label(), (int) ($counts[$case->value] ?? 0)];
    }
    $tabs['all'] = [__('desk-editor::blog.moderation.all'), $total];
    $current = $status?->value ?? 'all';
    $blogOn = (bool) config('desk-editor.blog.enabled');
    $action = 'inline-flex h-8 cursor-pointer items-center gap-1.5 rounded-lg px-2.5 text-xs font-medium transition';
@endphp

@section('title', __('desk-editor::blog.moderation.title'))

@section('header-actions')
    @if (Route::has('desk-editor.analytics'))
        <a href="{{ route('desk-editor.analytics') }}" class="hidden h-9 items-center gap-1.5 rounded-lg px-3 text-sm font-medium text-zinc-600 transition hover:bg-zinc-100 hover:text-zinc-900 sm:inline-flex dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-white">
            <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="m7 15 4-4 3 3 5-6"/></svg>
            {{ __('desk-editor::blog.moderation.analytics') }}
        </a>
    @endif
@endsection

@section('content')
<main class="mx-auto max-w-7xl px-4 pt-8 pb-28 sm:px-6">
    <div class="animate-de-rise mb-8">
        <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('desk-editor::blog.moderation.title') }}</h1>
        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('desk-editor::blog.moderation.subtitle') }}</p>
    </div>

    {{-- Status tabs --}}
    <nav class="-mx-4 mb-4 flex gap-1 overflow-x-auto px-4 sm:mx-0 sm:px-0" aria-label="Status">
        @foreach ($tabs as $value => [$label, $count])
            @php $active = $current === $value; @endphp
            <a href="{{ route('desk-editor.comments.index', array_filter(['status' => $value, 'q' => $term ?: null])) }}"
               @if ($active) aria-current="page" @endif
               @class([
                   'inline-flex shrink-0 items-center gap-2 rounded-lg px-3 py-1.5 text-sm font-medium transition',
                   'bg-zinc-900 text-white shadow-sm dark:bg-white dark:text-zinc-900' => $active,
                   'text-zinc-600 hover:bg-zinc-200/60 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white' => ! $active,
               ])>
                {{ $label }}
                <span @class([
                    'rounded-md px-1.5 text-[11px] tabular-nums',
                    'bg-white/20 dark:bg-zinc-900/10' => $active,
                    'bg-amber-100 text-amber-800 dark:bg-amber-400/15 dark:text-amber-300' => ! $active && $value === 'pending' && $count > 0,
                    'bg-zinc-200/70 dark:bg-zinc-800' => ! $active && ! ($value === 'pending' && $count > 0),
                ])>{{ $count }}</span>
            </a>
        @endforeach
    </nav>

    {{-- Search --}}
    <form method="GET" class="mb-4" role="search">
        <input type="hidden" name="status" value="{{ $current }}">
        <label class="relative block">
            <span class="sr-only">{{ __('desk-editor::blog.moderation.search') }}</span>
            <svg viewBox="0 0 24 24" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-zinc-500 dark:text-zinc-400" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input type="search" name="q" value="{{ $term }}" placeholder="{{ __('desk-editor::blog.moderation.search') }}" class="h-10 w-full rounded-xl border border-zinc-200 bg-white pr-3 pl-9 text-sm shadow-xs transition outline-none placeholder:text-zinc-400 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/15 dark:border-zinc-800 dark:bg-zinc-900">
        </label>
    </form>

    <form method="POST" action="{{ route('desk-editor.comments.bulk') }}" id="desk-bulk-comments" data-bulk-form>
        @csrf
    </form>

    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
        @if ($comments->isEmpty())
            <div class="animate-de-rise flex flex-col items-center px-6 py-20 text-center">
                <div class="relative mb-5">
                    <div class="absolute inset-0 animate-pulse rounded-full bg-indigo-500/20 blur-2xl"></div>
                    <div class="relative grid size-16 place-items-center rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                        <svg viewBox="0 0 24 24" class="size-7 text-indigo-500" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><path d="m9 10 2 2 4-4"/></svg>
                    </div>
                </div>
                <h2 class="text-base font-semibold">{{ $term ? __('desk-editor::blog.moderation.empty_search') : __('desk-editor::blog.moderation.empty_title') }}</h2>
                @unless ($term)
                    <p class="mt-1 max-w-sm text-sm text-zinc-500 dark:text-zinc-400">{{ __('desk-editor::blog.moderation.empty_body') }}</p>
                @endunless
            </div>
        @else
            <div class="flex items-center gap-3 border-b border-zinc-200 bg-zinc-50/80 px-4 py-2.5 text-[11px] font-semibold tracking-wider text-zinc-600 uppercase dark:border-zinc-800 dark:bg-zinc-900/60 dark:text-zinc-400">
                <input type="checkbox" data-select-all class="size-4 cursor-pointer accent-indigo-600" aria-label="{{ __('desk-editor::blog.moderation.select_all') }}">
                <span>{{ __('desk-editor::blog.moderation.title') }}</span>
            </div>
            <ul class="divide-y divide-zinc-200 dark:divide-zinc-800">
                @foreach ($comments as $comment)
                    @php
                        $name = $comment->authorName();
                        $article = $comment->article;
                        $publicUrl = $blogOn && $article && $article->isLive() ? Blog::url($article).'#comment-'.$comment->id : null;
                    @endphp
                    <li class="group/row animate-de-rise flex gap-3 px-4 py-4 transition hover:bg-zinc-50/70 dark:hover:bg-zinc-800/30" style="animation-delay: {{ min($loop->index, 10) * 25 }}ms" data-comment-row>
                        <input type="checkbox" name="ids[]" value="{{ $comment->id }}" form="desk-bulk-comments" data-select class="mt-1 size-4 shrink-0 cursor-pointer accent-indigo-600" aria-label="{{ $name }}">
                        <span class="grid size-9 shrink-0 place-items-center rounded-full text-xs font-semibold text-white" style="background: hsl({{ $comment->hue() }} 55% 27%)" aria-hidden="true">{{ $comment->initials() }}</span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
                                <span class="font-semibold">{{ $name }}</span>
                                @if ($comment->guest_email)
                                    <a href="mailto:{{ $comment->guest_email }}" class="text-xs text-zinc-500 hover:text-indigo-600 dark:text-zinc-400">{{ $comment->guest_email }}</a>
                                @endif
                                @if ($comment->is_staff)
                                    <span class="rounded-md bg-indigo-50 px-1.5 py-0.5 text-[11px] font-medium text-indigo-700 dark:bg-indigo-400/10 dark:text-indigo-300">{{ __('desk-editor::blog.comments.staff') }}</span>
                                @endif
                                <span class="inline-flex items-center rounded-md px-1.5 py-0.5 text-[11px] font-medium ring-1 ring-inset {{ $comment->status->badge() }}">{{ $comment->status->label() }}</span>
                                <span class="text-xs text-zinc-500" title="{{ $comment->created_at?->toDayDateTimeString() }}">{{ $comment->created_at?->diffForHumans() }}</span>
                            </div>
                            <div class="mt-0.5 truncate text-xs text-zinc-500 dark:text-zinc-400">
                                {{ __('desk-editor::blog.moderation.on') }}
                                @if ($article)
                                    @if (Route::has('desk-editor.articles.edit'))
                                        <a href="{{ route('desk-editor.articles.edit', $article) }}" class="font-medium text-zinc-700 hover:text-indigo-600 dark:text-zinc-300">{{ $article->title }}</a>
                                    @else
                                        <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $article->title }}</span>
                                    @endif
                                @endif
                                @if ($comment->parent)
                                    · {{ __('desk-editor::blog.moderation.in_reply_to', ['name' => $comment->parent->authorName()]) }}
                                @endif
                            </div>
                            <div class="mt-2 max-w-3xl space-y-2 text-sm/relaxed break-words text-zinc-700 dark:text-zinc-300 [&_a]:text-indigo-600 [&_a]:underline dark:[&_a]:text-indigo-400">{{ $comment->renderedBody() }}</div>

                            <div class="mt-3 flex flex-wrap items-center gap-1">
                                @if ($comment->status !== CommentStatus::Approved)
                                    <form method="POST" action="{{ route('desk-editor.comments.approve', $comment) }}">@csrf
                                        <button class="{{ $action }} bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-400/10 dark:text-emerald-300 dark:hover:bg-emerald-400/20">
                                            <svg viewBox="0 0 24 24" class="size-3.5" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                                            {{ __('desk-editor::blog.moderation.actions.approve') }}
                                        </button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('desk-editor.comments.restore', $comment) }}">@csrf
                                        <button class="{{ $action }} text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800">{{ __('desk-editor::blog.moderation.actions.unapprove') }}</button>
                                    </form>
                                @endif
                                @if ($comment->status !== CommentStatus::Spam)
                                    <form method="POST" action="{{ route('desk-editor.comments.spam', $comment) }}">@csrf
                                        <button class="{{ $action }} text-zinc-600 hover:bg-rose-50 hover:text-rose-700 dark:text-zinc-300 dark:hover:bg-rose-400/10 dark:hover:text-rose-300">{{ __('desk-editor::blog.moderation.actions.spam') }}</button>
                                    </form>
                                @endif
                                @if ($comment->status === CommentStatus::Trash || $comment->status === CommentStatus::Spam)
                                    <form method="POST" action="{{ route('desk-editor.comments.restore', $comment) }}">@csrf
                                        <button class="{{ $action }} text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800">{{ __('desk-editor::blog.moderation.actions.restore') }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('desk-editor.comments.destroy', $comment) }}" onsubmit="return confirm(@js(__('desk-editor::blog.moderation.confirm_delete')))">@csrf @method('DELETE')
                                        <button class="{{ $action }} text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-400/10">{{ __('desk-editor::blog.moderation.actions.delete') }}</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('desk-editor.comments.trash', $comment) }}">@csrf
                                        <button class="{{ $action }} text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800">{{ __('desk-editor::blog.moderation.actions.trash') }}</button>
                                    </form>
                                @endif
                                <button type="button" data-reply-toggle class="{{ $action }} text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800" aria-expanded="false">
                                    <svg viewBox="0 0 24 24" class="size-3.5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 17 4 12l5-5"/><path d="M20 18v-2a4 4 0 0 0-4-4H4"/></svg>
                                    {{ __('desk-editor::blog.moderation.actions.reply') }}
                                </button>
                                @if ($publicUrl)
                                    <a href="{{ $publicUrl }}" target="_blank" rel="noopener" class="{{ $action }} text-zinc-500 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800">
                                        {{ __('desk-editor::blog.moderation.actions.view') }}
                                        <svg viewBox="0 0 24 24" class="size-3" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7M8 7h9v9"/></svg>
                                    </a>
                                @endif
                            </div>

                            <form method="POST" action="{{ route('desk-editor.comments.reply', $comment) }}" class="mt-3 hidden max-w-3xl animate-de-pop" data-reply-form>
                                @csrf
                                <textarea name="body" rows="3" required maxlength="5000" placeholder="{{ __('desk-editor::blog.moderation.staff_reply') }}" class="block w-full resize-y rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm shadow-xs outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/15 dark:border-zinc-700 dark:bg-zinc-950"></textarea>
                                <div class="mt-2 flex justify-end gap-2">
                                    <button type="button" data-reply-cancel class="inline-flex h-8 cursor-pointer items-center rounded-lg px-3 text-xs font-medium text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800">{{ __('desk-editor::blog.comments.cancel') }}</button>
                                    <button class="inline-flex h-8 cursor-pointer items-center rounded-lg bg-indigo-600 px-3 text-xs font-medium text-white shadow-sm transition hover:bg-indigo-500">{{ __('desk-editor::blog.moderation.actions.send_reply') }}</button>
                                </div>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    @if ($comments->hasPages())
        <div class="mt-6 flex items-center justify-between text-sm text-zinc-500 dark:text-zinc-400">
            <span>{{ $comments->firstItem() }}–{{ $comments->lastItem() }} / {{ $comments->total() }}</span>
            <div class="flex gap-2">
                @if ($comments->previousPageUrl())
                    <a href="{{ $comments->previousPageUrl() }}" class="inline-flex h-9 items-center rounded-lg border border-zinc-200 bg-white px-3 font-medium transition hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900 dark:hover:bg-zinc-800">{{ __('desk-editor::blog.previous') }}</a>
                @endif
                @if ($comments->nextPageUrl())
                    <a href="{{ $comments->nextPageUrl() }}" class="inline-flex h-9 items-center rounded-lg border border-zinc-200 bg-white px-3 font-medium transition hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900 dark:hover:bg-zinc-800">{{ __('desk-editor::blog.next') }}</a>
                @endif
            </div>
        </div>
    @endif

    {{-- Bulk actions bar --}}
    <div data-bulk-bar class="pointer-events-none fixed inset-x-0 bottom-6 z-40 flex translate-y-24 justify-center px-4 opacity-0 transition duration-300 ease-out">
        <div class="pointer-events-auto flex flex-wrap items-center gap-1 rounded-2xl border border-zinc-200 bg-white/95 p-1.5 shadow-2xl shadow-zinc-900/15 backdrop-blur-xl dark:border-zinc-700 dark:bg-zinc-900/95 dark:shadow-black/50">
            <span class="px-3 text-sm font-medium" data-bulk-count data-template="{{ __('desk-editor::blog.moderation.selected', ['count' => ':count']) }}"></span>
            @foreach (['approve' => 'text-emerald-700 hover:bg-emerald-50 dark:text-emerald-300 dark:hover:bg-emerald-400/10', 'spam' => 'hover:bg-zinc-100 dark:hover:bg-zinc-800', 'trash' => 'hover:bg-zinc-100 dark:hover:bg-zinc-800', 'restore' => 'hover:bg-zinc-100 dark:hover:bg-zinc-800', 'delete' => 'text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-400/10'] as $bulk => $tone)
                <button type="submit" form="desk-bulk-comments" name="action" value="{{ $bulk }}" @if ($bulk === 'delete') data-confirm="{{ __('desk-editor::blog.moderation.confirm_delete') }}" @endif class="inline-flex h-9 cursor-pointer items-center rounded-xl px-3 text-sm font-medium transition {{ $tone }}">{{ __('desk-editor::blog.moderation.actions.'.$bulk) }}</button>
            @endforeach
        </div>
    </div>
</main>
@endsection

@push('desk-scripts')
<script>
    (() => {
        const boxes = [...document.querySelectorAll('[data-select]')];
        const all = document.querySelector('[data-select-all]');
        const bar = document.querySelector('[data-bulk-bar]');
        const count = document.querySelector('[data-bulk-count]');
        const sync = () => {
            const n = boxes.filter((b) => b.checked).length;
            if (count) count.textContent = count.dataset.template.replace(':count', n);
            bar?.classList.toggle('translate-y-24', n === 0);
            bar?.classList.toggle('opacity-0', n === 0);
            if (all) { all.checked = n > 0 && n === boxes.length; all.indeterminate = n > 0 && n < boxes.length; }
        };
        boxes.forEach((b) => b.addEventListener('change', sync));
        all?.addEventListener('change', () => { boxes.forEach((b) => { b.checked = all.checked; }); sync(); });
        document.querySelectorAll('[data-confirm]').forEach((btn) => btn.addEventListener('click', (e) => { if (!confirm(btn.dataset.confirm)) e.preventDefault(); }));
        document.querySelectorAll('[data-comment-row]').forEach((row) => {
            const toggle = row.querySelector('[data-reply-toggle]');
            const form = row.querySelector('[data-reply-form]');
            const set = (open) => { form.classList.toggle('hidden', !open); toggle.setAttribute('aria-expanded', String(open)); if (open) form.querySelector('textarea')?.focus(); };
            toggle?.addEventListener('click', () => set(form.classList.contains('hidden')));
            row.querySelector('[data-reply-cancel]')?.addEventListener('click', () => set(false));
        });
        sync();
    })();
</script>
@endpush
