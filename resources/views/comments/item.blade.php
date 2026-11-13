@php
    /** @var \Atannex\DeskEditor\Blog\Models\DeskComment $comment */
    $name = $comment->authorName();
    $isAuthor = $comment->user_id !== null && $comment->user_type === $article->author_type && (string) $comment->user_id === (string) $article->author_id;
    $canReply = $commentsOpen && $canComment && $level + 1 < $maxDepth;
@endphp
<li id="comment-{{ $comment->id }}" class="scroll-mt-24 motion-safe:animate-de-rise" data-comment>
    <div class="flex gap-3 sm:gap-4">
        <span class="grid size-10 shrink-0 place-items-center rounded-full text-sm font-semibold text-white shadow-sm ring-2 ring-white dark:ring-zinc-950"
              style="background: linear-gradient(135deg, hsl({{ $comment->hue() }} 55% 30%), hsl({{ ($comment->hue() + 40) % 360 }} 55% 24%))" aria-hidden="true">{{ $comment->initials() }}</span>
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
                <span class="font-semibold text-zinc-900 dark:text-white">{{ $name }}</span>
                @if ($comment->is_staff)
                    <span class="rounded-full bg-desk/10 px-2 py-0.5 text-[11px] font-semibold text-desk">{{ __('desk-editor::blog.comments.staff') }}</span>
                @elseif ($isAuthor)
                    <span class="rounded-full bg-violet-500/10 px-2 py-0.5 text-[11px] font-semibold text-violet-600 dark:text-violet-400">{{ __('desk-editor::blog.comments.author_badge') }}</span>
                @endif
                <a href="#comment-{{ $comment->id }}" class="text-zinc-500 transition hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white">
                    <time datetime="{{ $comment->created_at?->toAtomString() }}" title="{{ $comment->created_at?->toDayDateTimeString() }}">{{ $comment->created_at?->diffForHumans() }}</time>
                </a>
            </div>
            <div class="mt-1.5 space-y-3 text-[15px]/relaxed break-words text-zinc-700 dark:text-zinc-300 [&_a]:text-desk [&_a]:underline [&_a]:decoration-desk/30 [&_a]:underline-offset-2 hover:[&_a]:decoration-desk">{{ $comment->renderedBody() }}</div>
            @if ($canReply)
                <a href="?reply={{ $comment->id }}#comment-form" data-reply-to="{{ $comment->id }}" data-reply-name="{{ $name }}" class="mt-2 inline-flex items-center gap-1.5 rounded-md text-xs font-semibold text-zinc-500 transition hover:text-desk focus-visible:ring-2 focus-visible:ring-desk/60 dark:text-zinc-400">
                    <svg viewBox="0 0 24 24" class="size-3.5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 17 4 12l5-5"/><path d="M20 18v-2a4 4 0 0 0-4-4H4"/></svg>
                    {{ __('desk-editor::blog.comments.reply') }}
                </a>
            @endif
            <div data-reply-slot></div>
            @if ($comment->replies->isNotEmpty())
                <ol class="mt-6 space-y-6 border-l-2 border-zinc-100 pl-4 sm:pl-6 dark:border-zinc-800/80">
                    @foreach ($comment->replies as $reply)
                        @include('desk-editor::comments.item', ['comment' => $reply, 'level' => $level + 1])
                    @endforeach
                </ol>
            @endif
        </div>
    </div>
</li>
