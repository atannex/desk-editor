@php
    /** @var string $shareUrl */
    /** @var string $shareTitle */
    $u = rawurlencode($shareUrl);
    $t = rawurlencode($shareTitle);
    $networks = [
        ['X', 'https://x.com/intent/post?url='.$u.'&text='.$t, 'M18.9 2H22l-7.6 8.7L23.3 22h-7l-5.5-7.1L4.5 22H1.4l8.1-9.3L1 2h7.2l5 6.6Zm-1.2 18h1.7L7.4 3.9H5.6Z', true],
        ['LinkedIn', 'https://www.linkedin.com/sharing/share-offsite/?url='.$u, 'M4.98 3.5a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5ZM3 9.75h4V21H3Zm6.5 0h3.8v1.6h.1c.5-1 1.8-2 3.8-2 4 0 4.8 2.6 4.8 6V21h-4v-5c0-1.2 0-2.8-1.7-2.8s-2 1.3-2 2.7V21h-4Z', true],
        ['Facebook', 'https://www.facebook.com/sharer/sharer.php?u='.$u, 'M14 8.5V6.8c0-.8.2-1.3 1.4-1.3H17V2.2c-.3 0-1.3-.2-2.5-.2-2.5 0-4.2 1.5-4.2 4.3v2.2H7.5V12h2.8v10H14V12h2.8l.4-3.5Z', true],
    ];
    $btn = 'grid size-10 cursor-pointer place-items-center rounded-full border border-zinc-200 bg-white text-zinc-600 transition outline-none hover:-translate-y-0.5 hover:border-zinc-300 hover:text-zinc-900 hover:shadow-md focus-visible:ring-2 focus-visible:ring-desk/60 motion-reduce:hover:translate-y-0 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-400 dark:hover:border-zinc-700 dark:hover:text-white';
    $vertical ??= false;
@endphp
<div @class(['flex gap-2', 'flex-col items-start' => $vertical, 'flex-wrap items-center' => ! $vertical]) data-share data-share-url="{{ $shareUrl }}" data-share-title="{{ $shareTitle }}">
    @foreach ($networks as [$network, $href, $path])
        <a href="{{ $href }}" target="_blank" rel="noopener noreferrer" class="{{ $btn }}" aria-label="{{ __('desk-editor::blog.share_on', ['network' => $network]) }}" title="{{ __('desk-editor::blog.share_on', ['network' => $network]) }}">
            <svg viewBox="0 0 24 24" class="size-4" fill="currentColor" aria-hidden="true"><path d="{{ $path }}"/></svg>
        </a>
    @endforeach
    <a href="mailto:?subject={{ $t }}&amp;body={{ $u }}" class="{{ $btn }}" aria-label="{{ __('desk-editor::blog.share_email') }}" title="{{ __('desk-editor::blog.share_email') }}">
        <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/></svg>
    </a>
    <button type="button" data-share-copy class="{{ $btn }} relative" aria-label="{{ __('desk-editor::blog.copy_link') }}" title="{{ __('desk-editor::blog.copy_link') }}">
        <svg data-copy-icon viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/></svg>
        <svg data-copied-icon viewBox="0 0 24 24" class="hidden size-4 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
        <span data-copied-tip class="hidden animate-de-pop pointer-events-none absolute bottom-full left-1/2 mb-2 -translate-x-1/2 rounded-lg bg-zinc-900 px-2.5 py-1 text-xs font-medium whitespace-nowrap text-white shadow-lg dark:bg-white dark:text-zinc-900" role="status">{{ __('desk-editor::blog.copied') }}</span>
    </button>
    <button type="button" data-share-native class="{{ $btn }} hidden!" aria-label="{{ __('desk-editor::blog.share_native') }}" title="{{ __('desk-editor::blog.share_native') }}">
        <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 12v7a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-7M16 6l-4-4-4 4M12 2v13"/></svg>
    </button>
</div>
