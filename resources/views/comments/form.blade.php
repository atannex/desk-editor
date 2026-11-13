@php
    $user = auth()->user();
    $userName = $user ? (string) ($user->{config('desk-editor.articles.authors.label', 'name')} ?? '') : '';
    $replyTo = (int) (old('parent_id') ?: request()->integer('reply'));
    $field = 'w-full rounded-xl border border-zinc-200 bg-white px-4 text-[15px] shadow-xs transition outline-none placeholder:text-zinc-400 focus:border-desk/60 focus:ring-4 focus:ring-desk/15 dark:border-zinc-800 dark:bg-zinc-900';
@endphp
<form id="comment-form" method="POST" action="{{ route('desk-editor.blog.comments.store', \Atannex\DeskEditor\Blog\Support\Blog::params(['slug' => $article->slug], $article->locale)) }}" class="mt-6 scroll-mt-24 rounded-3xl border border-zinc-200 bg-zinc-50/60 p-4 sm:p-6 dark:border-zinc-800 dark:bg-zinc-900/40" data-comment-form novalidate>
    @csrf
    <input type="hidden" name="parent_id" value="{{ $replyTo ?: '' }}">
    <input type="hidden" name="_desk_ts" value="{{ $commentToken }}">
    <div class="absolute -left-[9999px] h-px w-px overflow-hidden" aria-hidden="true">
        <label for="comment-website">{{ __('desk-editor::blog.comments.website') }}</label>
        <input id="comment-website" type="text" name="website" value="" tabindex="-1" autocomplete="off">
    </div>

    <div data-replying @class(['mb-3 flex items-center justify-between gap-2 rounded-xl bg-desk/10 px-3 py-2 text-sm text-desk', 'hidden' => ! $replyTo])>
        <span>{!! __('desk-editor::blog.comments.reply_to', ['name' => '<strong data-reply-name class="font-semibold"></strong>']) !!}</span>
        <a href="#comment-form" data-cancel-reply class="text-xs font-semibold underline-offset-2 hover:underline">{{ __('desk-editor::blog.comments.cancel') }}</a>
    </div>

    @if ($errors->any())
        <div class="motion-safe:animate-de-pop mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-400/20 dark:bg-rose-400/10 dark:text-rose-200" role="alert">
            <ul class="space-y-1">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <label for="comment-body" class="sr-only">{{ __('desk-editor::blog.comments.placeholder') }}</label>
    <textarea id="comment-body" name="body" rows="4" maxlength="5000" required placeholder="{{ __('desk-editor::blog.comments.placeholder') }}"
              class="{{ $field }} block min-h-28 resize-none py-3 leading-relaxed" @error('body') aria-invalid="true" @enderror>{{ old('body') }}</textarea>

    @guest
        <div class="mt-3 grid gap-3 sm:grid-cols-2">
            <div>
                <label for="comment-name" class="mb-1.5 block text-xs font-semibold text-zinc-600 dark:text-zinc-400">{{ __('desk-editor::blog.comments.name_label') }}</label>
                <input id="comment-name" type="text" name="name" value="{{ old('name') }}" maxlength="80" required autocomplete="name" class="{{ $field }} h-11" @error('name') aria-invalid="true" @enderror>
            </div>
            <div>
                <label for="comment-email" class="mb-1.5 flex items-center justify-between text-xs font-semibold text-zinc-600 dark:text-zinc-400">
                    {{ __('desk-editor::blog.comments.email_label') }}
                    <span class="font-normal text-zinc-500 dark:text-zinc-400">{{ __('desk-editor::blog.comments.email_hint') }}</span>
                </label>
                <input id="comment-email" type="email" name="email" value="{{ old('email') }}" maxlength="191" required autocomplete="email" class="{{ $field }} h-11" @error('email') aria-invalid="true" @enderror>
            </div>
        </div>
    @endguest

    <div class="mt-4 flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-xs text-zinc-500 dark:text-zinc-400">
            @auth{{ __('desk-editor::blog.comments.signed_in_as', ['name' => $userName]) }} · @endauth{{ __('desk-editor::blog.comments.guidelines') }}
            <span data-counter data-template="{{ __('desk-editor::blog.comments.characters_left', ['count' => ':count']) }}" class="ml-1 tabular-nums" aria-live="polite"></span>
        </p>
        <button type="submit" class="inline-flex h-11 shrink-0 cursor-pointer items-center justify-center gap-2 rounded-full bg-zinc-900 px-6 text-sm font-semibold text-white shadow-sm transition hover:bg-zinc-700 focus-visible:ring-4 focus-visible:ring-desk/30 active:scale-[.98] disabled:cursor-wait disabled:opacity-60 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">
            {{ __('desk-editor::blog.comments.submit') }}
            <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
        </button>
    </div>
</form>
