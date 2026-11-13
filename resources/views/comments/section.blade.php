@php
    /** @var \Atannex\DeskEditor\Models\DeskArticle $article */
    /** @var \Illuminate\Database\Eloquent\Collection $comments */
    $maxDepth = max(1, (int) config('desk-editor.blog.comments.max_depth', 3));
    $flash = session('desk-comment');
    $enabled = (bool) config('desk-editor.blog.comments.enabled', true);
@endphp
@if ($enabled)
    <section id="comments" class="mt-16 scroll-mt-24" aria-labelledby="comments-title">
        <div class="flex items-baseline justify-between gap-4">
            <h2 id="comments-title" class="font-serif text-2xl font-bold tracking-tight">{{ __('desk-editor::blog.comments.title') }}</h2>
            <span class="text-sm text-zinc-500 tabular-nums dark:text-zinc-400">{{ trans_choice('desk-editor::blog.comments.count', $article->comments_count, ['count' => $article->comments_count]) }}</span>
        </div>

        @if ($flash)
            <div @class([
                'motion-safe:animate-de-pop mt-6 flex items-start gap-3 rounded-2xl border px-4 py-3 text-sm',
                'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-400/20 dark:bg-amber-400/10 dark:text-amber-200' => $flash === 'pending',
                'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-400/20 dark:bg-emerald-400/10 dark:text-emerald-200' => $flash !== 'pending',
            ]) role="status">
                <svg viewBox="0 0 24 24" class="mt-0.5 size-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    @if ($flash === 'pending')<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>@else<path d="M20 6 9 17l-5-5"/>@endif
                </svg>
                {{ $flash === 'pending' ? __('desk-editor::blog.comments.pending') : __('desk-editor::blog.comments.approved') }}
            </div>
        @endif

        @if ($commentsOpen)
            @if ($canComment)
                @include('desk-editor::comments.form')
            @else
                <p class="mt-6 rounded-2xl border border-dashed border-zinc-300 px-5 py-4 text-sm text-zinc-600 dark:border-zinc-700 dark:text-zinc-400">{{ __('desk-editor::blog.comments.sign_in') }}</p>
            @endif
        @else
            <p class="mt-6 rounded-2xl border border-dashed border-zinc-300 px-5 py-4 text-sm text-zinc-600 dark:border-zinc-700 dark:text-zinc-400">{{ __('desk-editor::blog.comments.closed') }}</p>
        @endif

        @if ($comments->isEmpty())
            @if ($commentsOpen)
                <p class="mt-10 text-center text-sm text-zinc-500 dark:text-zinc-400">{{ __('desk-editor::blog.comments.empty') }}</p>
            @endif
        @else
            <ol class="mt-10 space-y-8" data-comment-list>
                @foreach ($comments as $comment)
                    @include('desk-editor::comments.item', ['comment' => $comment, 'level' => 0])
                @endforeach
            </ol>
        @endif
    </section>

    @if ($commentsOpen && $canComment)
        @push('desk-blog-scripts')
            <script>
                (() => {
                    const form = document.querySelector('[data-comment-form]');
                    if (!form) return;
                    const home = form.parentElement;
                    const marker = document.createComment('comment-form');
                    home.insertBefore(marker, form);
                    const parent = form.querySelector('[name="parent_id"]');
                    const replying = form.querySelector('[data-replying]');
                    const replyName = form.querySelector('[data-reply-name]');
                    const textarea = form.querySelector('textarea');
                    const counter = form.querySelector('[data-counter]');
                    const max = Number(textarea?.getAttribute('maxlength') || 5000);
                    const tpl = counter?.dataset.template || ':count';

                    const reset = () => {
                        parent.value = '';
                        replying?.classList.add('hidden');
                        marker.parentNode.insertBefore(form, marker.nextSibling);
                    };

                    document.querySelectorAll('[data-reply-to]').forEach((link) => link.addEventListener('click', (event) => {
                        event.preventDefault();
                        const item = link.closest('[data-comment]');
                        parent.value = link.dataset.replyTo;
                        if (replyName) replyName.textContent = link.dataset.replyName;
                        replying?.classList.remove('hidden');
                        item.querySelector('[data-reply-slot]')?.append(form);
                        textarea?.focus();
                    }));
                    form.querySelector('[data-cancel-reply]')?.addEventListener('click', (event) => { event.preventDefault(); reset(); });

                    const count = () => { if (counter && textarea) counter.textContent = tpl.replace(':count', (max - textarea.value.length).toLocaleString()); };
                    textarea?.addEventListener('input', () => {
                        count();
                        textarea.style.height = 'auto';
                        textarea.style.height = Math.min(480, textarea.scrollHeight + 2) + 'px';
                    });
                    count();
                    form.addEventListener('submit', () => form.querySelector('[type="submit"]')?.setAttribute('disabled', 'disabled'));
                })();
            </script>
        @endpush
    @endif
@endif
