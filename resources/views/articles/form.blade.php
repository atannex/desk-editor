@extends('desk-editor::articles.layout')

@php
    use Atannex\DeskEditor\Enums\ArticleStatus;
    use Atannex\DeskEditor\Articles\Support\Authors;
    $exists = $article->exists;
    $status = old('status', $article->status?->value ?? 'draft');
    $publishedAt = old('published_at', $article->published_at?->format('Y-m-d\TH:i'));
    $tags = old('tags', implode(', ', $article->exists ? $article->tagNames() : []));
    $category = old('category', $article->category?->name);
    $locale = old('locale', $article->locale ?: config('desk-editor.articles.default_locale', 'en'));
    $canSave = $can['save'];
    $canReview = $exists && ($can['review'] ?? false);
    $inReview = $exists && $article->status === ArticleStatus::Review;
    $statusValues = array_map(fn ($s) => $s->value, $allowedStatuses);
    $usedLocales = $translations->pluck('locale')->push($article->locale)->all();
    $missingLocales = array_diff_key($locales, array_flip($usedLocales));
    $authorsInfo = app(Authors::class);
    $input = 'block w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-xs transition outline-none placeholder:text-zinc-400 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/15 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100';
    $label = 'mb-1.5 flex items-center justify-between text-[13px] font-medium text-zinc-700 dark:text-zinc-300';
    $panel = 'group rounded-2xl border border-zinc-200 bg-white shadow-xs open:shadow-sm dark:border-zinc-800 dark:bg-zinc-900';
    $summary = 'flex cursor-pointer list-none items-center justify-between gap-2 px-4 py-3 text-sm font-semibold select-none [&::-webkit-details-marker]:hidden';
    $chevron = '<svg viewBox="0 0 24 24" class="size-4 text-zinc-500 dark:text-zinc-400 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m6 9 6 6 6-6"/></svg>';
    $btn = 'inline-flex h-9 cursor-pointer items-center justify-center gap-1.5 rounded-lg border border-zinc-200 bg-white px-3.5 text-sm font-medium text-zinc-800 no-underline shadow-xs transition hover:bg-zinc-50 active:scale-[.98] dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:hover:bg-zinc-800';
    $btnPrimary = 'inline-flex h-9 cursor-pointer items-center justify-center gap-1.5 rounded-lg border-0 bg-indigo-600 px-3.5 text-sm font-medium text-white no-underline shadow-sm shadow-indigo-600/25 transition hover:bg-indigo-500 active:scale-[.98] dark:bg-indigo-500 dark:hover:bg-indigo-400';
@endphp

@section('title', $exists ? $article->title : __('desk-editor::articles.new'))

@section('header-actions')
    <div class="hidden items-center gap-2 text-xs whitespace-nowrap text-zinc-500 xl:flex dark:text-zinc-400" data-save-state>
        <span class="size-1.5 rounded-full bg-emerald-500" data-save-dot></span>
        <span data-save-text>{{ $exists ? __('desk-editor::articles.saved_at', ['time' => $article->updated_at?->diffForHumans()]) : __('desk-editor::articles.unsaved') }}</span>
    </div>
    @if ($exists)
        <a href="{{ route('desk-editor.articles.preview', $article) }}" target="_blank" class="hidden h-9 items-center gap-1.5 rounded-lg px-3 text-sm font-medium text-zinc-600 no-underline transition hover:bg-zinc-100 sm:inline-flex dark:text-zinc-300 dark:hover:bg-zinc-800">
            <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.06 12.35a1 1 0 0 1 0-.7 10.75 10.75 0 0 1 19.88 0 1 1 0 0 1 0 .7 10.75 10.75 0 0 1-19.88 0"/><circle cx="12" cy="12" r="3"/></svg>
            {{ __('desk-editor::articles.preview') }}
        </a>
    @endif
    @if ($canSave)
        <button type="submit" form="article-form" name="intent" value="save" class="{{ $btn }}">
            {{ __('desk-editor::articles.save') }}
        </button>
        @if ($needsReview)
            @unless ($inReview)
                <button type="submit" form="article-form" name="intent" value="submit" data-submit-review class="{{ $btnPrimary }}">
                    <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2 11 13M22 2l-7 20-4-9-9-4Z"/></svg>
                    <span class="hidden sm:inline">{{ __('desk-editor::articles.workflow.submit') }}</span>
                </button>
            @endunless
        @else
            <button type="submit" form="article-form" name="intent" value="publish" data-publish class="{{ $btnPrimary }}">
                {{ $article->status === ArticleStatus::Published ? __('desk-editor::articles.update') : __('desk-editor::articles.publish') }}
            </button>
        @endif
    @endif
@endsection

@section('content')
<form id="article-form" method="POST" action="{{ $exists ? route('desk-editor.articles.update', $article) : route('desk-editor.articles.store') }}" data-article-form
      @if ($exists) data-update-url="{{ route('desk-editor.articles.update', $article) }}" data-lock-url="{{ route('desk-editor.articles.lock', $article) }}" data-release-url="{{ route('desk-editor.articles.lock.release', $article) }}" @endif
      @if ($canSave) data-can-save @endif>
    @csrf
    @if ($exists)
        @method('PUT')
        <input type="hidden" name="loaded_at" value="{{ old('loaded_at', (string) $article->updated_at?->getTimestamp()) }}" data-version>
    @endif

    <div class="mx-auto grid max-w-7xl gap-8 px-4 pt-6 pb-24 sm:px-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        {{-- Writing canvas --}}
        <div class="animate-de-rise min-w-0">
            <a href="{{ route('desk-editor.articles.index') }}" class="mb-5 inline-flex items-center gap-1 text-sm text-zinc-500 no-underline transition hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white">
                <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m15 18-6-6 6-6"/></svg>
                {{ __('desk-editor::articles.back') }}
            </a>

            {{-- Edit lock --}}
            <div data-lock-banner @class(['animate-de-pop mb-5 flex flex-col gap-3 rounded-2xl border border-amber-300/70 bg-amber-50 p-4 text-sm text-amber-900 shadow-xs sm:flex-row sm:items-center dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-100', 'hidden' => ! $lock]) role="alert">
                <span class="relative grid size-10 shrink-0 place-items-center rounded-full bg-amber-200 text-sm font-semibold text-amber-900 dark:bg-amber-400/25 dark:text-amber-100" data-lock-initials>
                    {{ Authors::initials($lock['name'] ?? '') }}
                    <span class="absolute -right-0.5 -bottom-0.5 size-3 rounded-full bg-amber-500 ring-2 ring-amber-50 dark:ring-zinc-900"><span class="absolute inset-0 animate-ping rounded-full bg-amber-500"></span></span>
                </span>
                <div class="min-w-0 flex-1">
                    <p class="font-semibold" data-lock-title>{{ __('desk-editor::articles.lock.editing', ['name' => $lock['name'] ?? '']) }}</p>
                    <p class="text-amber-800/80 dark:text-amber-200/80" data-lock-since>{{ $lock ? __('desk-editor::articles.lock.opened', ['time' => $lock['since_human']]) : '' }}</p>
                </div>
                @if ($canSave && $exists)
                    <button type="submit" form="takeover-form" data-take-over class="inline-flex h-9 shrink-0 cursor-pointer items-center justify-center gap-1.5 rounded-lg border-0 bg-amber-600 px-3.5 text-sm font-medium text-white shadow-sm transition hover:bg-amber-500 active:scale-[.98]">
                        <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/></svg>
                        {{ __('desk-editor::articles.lock.take_over') }}
                    </button>
                @endif
            </div>

            {{-- Conflict (server-rendered after an HTML save, or shown by Ctrl+S) --}}
            <div data-conflict @class(['animate-de-pop mb-5 rounded-2xl border border-rose-300/70 bg-rose-50 p-4 text-sm text-rose-900 shadow-xs dark:border-rose-400/30 dark:bg-rose-500/10 dark:text-rose-100', 'hidden' => ! $errors->has('conflict')]) role="alert">
                <div class="flex items-start gap-3">
                    <svg viewBox="0 0 24 24" class="mt-0.5 size-5 shrink-0 text-rose-500" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold">{{ __('desk-editor::articles.conflict.title') }}</p>
                        <p class="mt-0.5" data-conflict-message>{{ $errors->first('conflict') }}</p>
                        <p class="mt-1 text-xs text-rose-800/70 dark:text-rose-200/70">{{ __('desk-editor::articles.conflict.hint') }}</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            @if ($exists)
                                <a href="{{ route('desk-editor.articles.edit', $article) }}" data-conflict-reload class="{{ $btn }}">
                                    <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-9-9c2.5 0 4.9 1 6.7 2.7L21 8M21 3v5h-5"/></svg>
                                    {{ __('desk-editor::articles.conflict.reload') }}
                                </a>
                            @endif
                            <button type="submit" form="article-form" name="overwrite" value="1" data-conflict-overwrite class="inline-flex h-9 cursor-pointer items-center justify-center rounded-lg border-0 bg-rose-600 px-3.5 text-sm font-medium text-white shadow-sm transition hover:bg-rose-500 active:scale-[.98]">
                                {{ __('desk-editor::articles.conflict.overwrite') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Review workflow --}}
            @if ($inReview)
                <div class="animate-de-pop mb-5 rounded-2xl border border-amber-200 bg-gradient-to-br from-amber-50 to-white p-4 shadow-xs dark:border-amber-400/20 dark:from-amber-400/10 dark:to-zinc-900" data-review-panel>
                    <div class="flex items-start gap-3">
                        <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-amber-500 text-white shadow-sm shadow-amber-500/30">
                            <svg viewBox="0 0 24 24" class="size-[18px]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.06 12.35a1 1 0 0 1 0-.7 10.75 10.75 0 0 1 19.88 0 1 1 0 0 1 0 .7 10.75 10.75 0 0 1-19.88 0"/><circle cx="12" cy="12" r="3"/></svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('desk-editor::articles.workflow.in_review') }}</p>
                            <p class="mt-0.5 text-sm text-zinc-600 dark:text-zinc-300">{{ $canReview ? __('desk-editor::articles.workflow.in_review_body') : __('desk-editor::articles.workflow.in_review_writer') }}</p>
                        </div>
                    </div>
                    @if ($canReview)
                        <div class="mt-4 flex flex-wrap gap-2" data-review-actions>
                            @if ($can['publish'] ?? false)
                                <button type="button" data-review-toggle="approve" class="inline-flex h-9 cursor-pointer items-center gap-1.5 rounded-lg border-0 bg-emerald-600 px-3.5 text-sm font-medium text-white shadow-sm shadow-emerald-600/25 transition hover:bg-emerald-500 active:scale-[.98]">
                                    <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                                    {{ __('desk-editor::articles.workflow.approve') }}
                                </button>
                            @endif
                            <button type="button" data-review-toggle="changes" class="{{ $btn }}">
                                <svg viewBox="0 0 24 24" class="size-4 text-rose-500" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 14 4 9l5-5"/><path d="M4 9h10.5a5.5 5.5 0 0 1 0 11H11"/></svg>
                                {{ __('desk-editor::articles.workflow.request_changes') }}
                            </button>
                        </div>
                        @if ($can['publish'] ?? false)
                            <div data-review-pane="approve" class="mt-4 hidden grid-cols-1 gap-3 rounded-xl border border-zinc-200 bg-white p-3 sm:grid-cols-2 dark:border-zinc-700 dark:bg-zinc-950">
                                <label class="block">
                                    <span class="{{ $label }}">{{ __('desk-editor::articles.workflow.publish_at') }}</span>
                                    <input type="datetime-local" name="publish_at" form="approve-form" class="{{ $input }}">
                                    <span class="mt-1 block text-xs text-zinc-500 dark:text-zinc-400">{{ __('desk-editor::articles.workflow.publish_at_hint') }}</span>
                                </label>
                                <label class="block">
                                    <span class="{{ $label }}">{{ __('desk-editor::articles.workflow.approve_note') }}</span>
                                    <input type="text" name="note" form="approve-form" maxlength="2000" class="{{ $input }}">
                                </label>
                                <div class="flex justify-end gap-2 sm:col-span-2">
                                    <button type="button" data-review-toggle="approve" class="{{ $btn }}">{{ __('desk-editor::articles.workflow.cancel') }}</button>
                                    <button type="submit" form="approve-form" name="decision" value="approve" class="inline-flex h-9 cursor-pointer items-center gap-1.5 rounded-lg border-0 bg-emerald-600 px-3.5 text-sm font-medium text-white shadow-sm transition hover:bg-emerald-500">{{ __('desk-editor::articles.workflow.approve') }}</button>
                                </div>
                            </div>
                        @endif
                        <div data-review-pane="changes" class="mt-4 hidden rounded-xl border border-zinc-200 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-950">
                            <label class="block">
                                <span class="{{ $label }}">{{ __('desk-editor::articles.workflow.note') }}</span>
                                <textarea name="note" form="changes-form" rows="3" required maxlength="2000" placeholder="{{ __('desk-editor::articles.workflow.note_placeholder') }}" class="{{ $input }} resize-y"></textarea>
                            </label>
                            <div class="mt-3 flex justify-end gap-2">
                                <button type="button" data-review-toggle="changes" class="{{ $btn }}">{{ __('desk-editor::articles.workflow.cancel') }}</button>
                                <button type="submit" form="changes-form" name="decision" value="request_changes" class="inline-flex h-9 cursor-pointer items-center gap-1.5 rounded-lg border-0 bg-rose-600 px-3.5 text-sm font-medium text-white shadow-sm transition hover:bg-rose-500">{{ __('desk-editor::articles.workflow.send') }}</button>
                            </div>
                        </div>
                    @endif
                </div>
            @elseif ($changesRequested)
                @php($reviewer = $authorsInfo->nameOf($changesRequested->causer) ?: __('desk-editor::articles.someone'))
                <div class="animate-de-pop mb-5 rounded-2xl border border-rose-200 bg-gradient-to-br from-rose-50 to-white p-4 shadow-xs dark:border-rose-400/20 dark:from-rose-500/10 dark:to-zinc-900" data-changes-requested>
                    <div class="flex items-start gap-3">
                        <span class="grid size-9 shrink-0 place-items-center rounded-full text-xs font-semibold {{ Authors::tone($reviewer) }}">{{ Authors::initials($reviewer) }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('desk-editor::articles.workflow.changes_requested') }}</p>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('desk-editor::articles.workflow.changes_requested_by', ['name' => $reviewer, 'time' => $changesRequested->created_at?->diffForHumans()]) }}</p>
                            @if ($changesRequested->description)
                                <blockquote class="mt-2 border-l-2 border-rose-300 pl-3 text-sm whitespace-pre-line text-zinc-700 dark:border-rose-400/50 dark:text-zinc-200">{{ $changesRequested->description }}</blockquote>
                            @endif
                        </div>
                        @if ($canSave)
                            <button type="submit" form="article-form" name="intent" value="submit" class="{{ $btn }} hidden shrink-0 sm:inline-flex">{{ __('desk-editor::articles.workflow.resubmit') }}</button>
                        @endif
                    </div>
                </div>
            @endif

            @if (! $canSave)
                <div class="mb-5 rounded-xl border border-zinc-200 bg-zinc-100/70 px-4 py-3 text-sm text-zinc-600 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300">{{ __('desk-editor::articles.read_only') }}</div>
            @endif

            @if ($errors->hasAny(array_diff($errors->keys(), ['conflict'])))
                <div class="animate-de-pop mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300" role="alert">
                    <ul class="list-disc space-y-0.5 pl-4">
                        @foreach ($errors->getMessages() as $key => $messages)
                            @continue($key === 'conflict')
                            @foreach ($messages as $error)<li>{{ $error }}</li>@endforeach
                        @endforeach
                    </ul>
                </div>
            @endif

            <div data-cover-hero @class(['group/cover relative mb-6 overflow-hidden rounded-2xl', 'hidden' => ! old('cover_image', $article->cover_image)])>
                <img data-cover-hero-img src="{{ old('cover_image', $article->cover_image) }}" alt="" class="aspect-[21/9] w-full object-cover">
                <div class="absolute inset-0 bg-gradient-to-t from-black/40 to-transparent opacity-0 transition-opacity group-hover/cover:opacity-100"></div>
            </div>

            <textarea name="title" rows="1" required maxlength="255" placeholder="{{ __('desk-editor::articles.fields.title') }}" aria-label="{{ __('desk-editor::articles.fields.title') }}" data-autogrow data-title
                class="block w-full resize-none border-0 bg-transparent p-0 text-3xl leading-tight font-bold tracking-tight text-zinc-950 outline-none placeholder:text-zinc-300 sm:text-[2.6rem] dark:text-white dark:placeholder:text-zinc-700">{{ old('title', $article->title) }}</textarea>
            <input type="text" name="subtitle" value="{{ old('subtitle', $article->subtitle) }}" maxlength="255" placeholder="{{ __('desk-editor::articles.fields.subtitle') }}" aria-label="{{ __('desk-editor::articles.fields.subtitle') }}"
                class="mt-3 block w-full border-0 bg-transparent p-0 text-lg text-zinc-500 outline-none placeholder:text-zinc-300 dark:text-zinc-400 dark:placeholder:text-zinc-700">

            <div class="mt-4 mb-6 flex items-center gap-1 rounded-lg bg-zinc-100/80 px-2.5 py-1.5 font-mono text-xs text-zinc-600 dark:bg-zinc-900 dark:text-zinc-400">
                <svg viewBox="0 0 24 24" class="size-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                <span class="truncate">{{ rtrim(str_replace('{slug}', '', (string) (config('desk-editor.articles.public_url') ?: url('/').'/')), '/') }}/</span>
                <input type="text" name="slug" value="{{ old('slug', $article->slug) }}" maxlength="200" data-slug @if ($exists) data-locked @endif placeholder="{{ __('desk-editor::articles.fields.slug') }}" aria-label="{{ __('desk-editor::articles.fields.slug') }}"
                    class="min-w-0 flex-1 border-0 bg-transparent p-0 font-mono text-xs text-zinc-800 outline-none dark:text-zinc-200">
            </div>

            <x-desk-editor name="body" :value="$article->body" :assets="false" :autosave="['key' => 'article-'.($article->id ?? 'new'), 'server' => true]" :comments="$article->exists ? 'article:'.$article->id : null" :collaboration="$article->exists ? 'article:'.$article->id : null" min-height="60vh" :label="__('desk-editor::articles.fields.title')" />
        </div>

        {{-- Sidebar --}}
        <aside class="flex flex-col gap-3 lg:sticky lg:top-20 lg:max-h-[calc(100vh-6rem)] lg:self-start lg:overflow-y-auto lg:pb-4 [scrollbar-width:thin]">
            <details open class="{{ $panel }} animate-de-rise">
                <summary class="{{ $summary }}">{{ __('desk-editor::articles.panels.publish') }} {!! $chevron !!}</summary>
                <div class="flex flex-col gap-4 border-t border-zinc-100 px-4 py-4 dark:border-zinc-800">
                    @if ($needsReview && $canSave)
                        <p class="flex gap-2 rounded-lg bg-indigo-50 px-3 py-2 text-xs text-indigo-800 dark:bg-indigo-400/10 dark:text-indigo-200">
                            <svg viewBox="0 0 24 24" class="mt-px size-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                            {{ __('desk-editor::articles.workflow.writer_hint') }}
                        </p>
                    @endif
                    <div>
                        <span class="{{ $label }}">{{ __('desk-editor::articles.fields.status') }}</span>
                        <div class="grid grid-cols-2 gap-1.5" role="radiogroup" aria-label="{{ __('desk-editor::articles.fields.status') }}">
                            @foreach (ArticleStatus::cases() as $case)
                                @continue(! in_array($case->value, $statusValues, true) && $status !== $case->value)
                                <label class="relative cursor-pointer">
                                    <input type="radio" name="status" value="{{ $case->value }}" @checked($status === $case->value) @disabled(! $canSave) class="peer sr-only" data-status>
                                    <span class="flex items-center gap-2 rounded-lg border border-zinc-200 px-2.5 py-2 text-[13px] transition peer-checked:border-indigo-500 peer-checked:bg-indigo-50 peer-checked:text-indigo-700 peer-focus-visible:ring-4 peer-focus-visible:ring-indigo-500/20 hover:bg-zinc-50 dark:border-zinc-700 dark:peer-checked:border-indigo-400 dark:peer-checked:bg-indigo-400/10 dark:peer-checked:text-indigo-300 dark:hover:bg-zinc-800">
                                        <span class="size-2 rounded-full {{ $case->dot() }}"></span>{{ $case->label() }}
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    @if (in_array(ArticleStatus::Scheduled->value, $statusValues, true) || in_array(ArticleStatus::Published->value, $statusValues, true))
                        <label class="block" data-schedule>
                            <span class="{{ $label }}">{{ __('desk-editor::articles.fields.published_at') }}</span>
                            <input type="datetime-local" name="published_at" value="{{ $publishedAt }}" class="{{ $input }}">
                            <span class="mt-1 block text-xs text-zinc-500 dark:text-zinc-400">{{ __('desk-editor::articles.fields.published_at_hint') }}</span>
                        </label>
                    @elseif ($publishedAt)
                        <input type="hidden" name="published_at" value="{{ $publishedAt }}">
                    @endif

                    {{-- Author --}}
                    <div>
                        <span class="{{ $label }}" id="author-label">{{ __('desk-editor::articles.author.label') }}</span>
                        @if ($can['assign_author'])
                            <div class="relative" data-combo="author" data-combo-url="{{ route('desk-editor.articles.authors') }}">
                                <input type="hidden" name="author_id" value="{{ old('author_id', $author['id'] ?? '') }}" data-combo-value>
                                <button type="button" data-combo-trigger aria-haspopup="listbox" aria-expanded="false" id="author-trigger" aria-labelledby="author-trigger author-label" class="flex h-10 w-full cursor-pointer items-center gap-2 rounded-lg border border-zinc-200 bg-white px-2 text-left text-sm text-zinc-900 shadow-xs transition hover:border-zinc-300 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/15 focus:outline-none dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                                    <span data-combo-avatar class="grid size-6 shrink-0 place-items-center rounded-full text-[10px] font-semibold {{ Authors::tone($author['name'] ?? '') }}">{{ $author ? $author['initials'] : '?' }}</span>
                                    <span data-combo-label class="flex-1 truncate">{{ $author['name'] ?? __('desk-editor::articles.author.none') }}</span>
                                    <svg viewBox="0 0 24 24" class="size-4 shrink-0 text-zinc-500 dark:text-zinc-400" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m7 15 5 5 5-5M7 9l5-5 5 5"/></svg>
                                </button>
                                <div data-combo-panel class="absolute inset-x-0 top-full z-30 mt-1.5 hidden overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-xl shadow-zinc-900/10 dark:border-zinc-700 dark:bg-zinc-900">
                                    <div class="border-b border-zinc-100 p-2 dark:border-zinc-800">
                                        <input type="search" data-combo-search placeholder="{{ __('desk-editor::articles.author.search') }}" aria-label="{{ __('desk-editor::articles.author.search') }}" class="{{ $input }} h-9">
                                    </div>
                                    <ul data-combo-list role="listbox" class="max-h-60 overflow-y-auto p-1 [scrollbar-width:thin]"></ul>
                                </div>
                                <script type="application/json" data-combo-options>{!! json_encode($authorOptions, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) !!}</script>
                            </div>
                        @else
                            <div class="flex h-10 items-center gap-2 rounded-lg border border-dashed border-zinc-200 px-2 text-sm dark:border-zinc-700">
                                <span class="grid size-6 place-items-center rounded-full text-[10px] font-semibold {{ Authors::tone($author['name'] ?? '') }}">{{ $author['initials'] ?? '?' }}</span>
                                <span class="truncate">{{ $author['name'] ?? __('desk-editor::articles.author.none') }}</span>
                            </div>
                        @endif
                    </div>

                    @if (count($locales) > 1 || ! array_key_exists($locale, $locales))
                        <label class="block">
                            <span class="{{ $label }}">{{ __('desk-editor::articles.fields.locale') }}</span>
                            <select name="locale" class="{{ $input }} cursor-pointer" data-locale>
                                @foreach ($locales as $code => $name)
                                    <option value="{{ $code }}" @selected($locale === $code) @disabled(in_array($code, $translations->pluck('locale')->all(), true))>{{ $name }} ({{ $code }})</option>
                                @endforeach
                                @unless (array_key_exists($locale, $locales))
                                    <option value="{{ $locale }}" selected>{{ $locale }}</option>
                                @endunless
                            </select>
                        </label>
                    @endif

                    <div class="flex flex-col gap-2.5">
                        @foreach (['featured', 'allow_comments'] as $toggle)
                            <label class="flex cursor-pointer items-center justify-between gap-3 text-sm select-none">
                                {{ __('desk-editor::articles.fields.'.$toggle) }}
                                <input type="hidden" name="{{ $toggle }}" value="0">
                                <input type="checkbox" name="{{ $toggle }}" value="1" @checked(old($toggle, $article->{$toggle})) class="peer sr-only">
                                <span class="relative h-5 w-9 shrink-0 rounded-full bg-zinc-200 transition peer-checked:bg-indigo-600 peer-focus-visible:ring-4 peer-focus-visible:ring-indigo-500/20 after:absolute after:top-0.5 after:left-0.5 after:size-4 after:rounded-full after:bg-white after:shadow after:transition-transform peer-checked:after:translate-x-4 dark:bg-zinc-700 dark:peer-checked:bg-indigo-500"></span>
                            </label>
                        @endforeach
                    </div>
                    <input type="text" name="revision_note" maxlength="255" placeholder="{{ __('desk-editor::articles.fields.revision_note') }}" aria-label="{{ __('desk-editor::articles.fields.revision_note') }}" class="{{ $input }}">
                </div>
            </details>

            <details open class="{{ $panel }} animate-de-rise [animation-delay:40ms]">
                <summary class="{{ $summary }}">{{ __('desk-editor::articles.panels.stats') }} {!! $chevron !!}</summary>
                <div class="grid grid-cols-2 gap-2 border-t border-zinc-100 p-3 dark:border-zinc-800">
                    @foreach (['words' => 'Words', 'reading' => 'Min read', 'headings' => 'Headings', 'media' => 'Media'] as $key => $caption)
                        <div class="rounded-xl bg-zinc-50 px-3 py-2.5 dark:bg-zinc-800/60">
                            <div class="text-lg font-semibold tabular-nums" data-stat="{{ $key }}">0</div>
                            <div class="text-[11px] tracking-wide text-zinc-500 uppercase dark:text-zinc-400">{{ $caption }}</div>
                        </div>
                    @endforeach
                </div>
            </details>

            <details open class="{{ $panel }} animate-de-rise [animation-delay:80ms]">
                <summary class="{{ $summary }}">{{ __('desk-editor::articles.panels.cover') }} {!! $chevron !!}</summary>
                <div class="flex flex-col gap-3 border-t border-zinc-100 px-4 py-4 dark:border-zinc-800">
                    <input type="hidden" name="cover_image" value="{{ old('cover_image', $article->cover_image) }}" data-cover-input>
                    <button type="button" data-cover-drop class="group/drop relative flex aspect-video w-full cursor-pointer flex-col items-center justify-center gap-1.5 overflow-hidden rounded-xl border-2 border-dashed border-zinc-200 bg-zinc-50 text-center text-xs text-zinc-500 transition hover:border-indigo-400 hover:text-indigo-600 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-400">
                        <img data-cover-preview src="{{ old('cover_image', $article->cover_image) }}" alt="" @class(['absolute inset-0 size-full object-cover', 'hidden' => ! old('cover_image', $article->cover_image)])>
                        <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 5h6M19 2v6M21 11.5V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7.5"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/><circle cx="9" cy="9" r="2"/></svg>
                        <span class="font-medium">{{ __('desk-editor::articles.fields.cover_upload') }}</span>
                        <span class="px-6 text-[11px] text-zinc-500 dark:text-zinc-400">{{ __('desk-editor::articles.fields.cover_hint') }}</span>
                        <span data-cover-progress class="absolute inset-x-0 bottom-0 h-1 origin-left scale-x-0 bg-indigo-500 transition-transform"></span>
                    </button>
                    <div class="flex gap-2">
                        <input type="url" placeholder="https://…" data-cover-url class="{{ $input }}" aria-label="Cover URL">
                        <button type="button" data-cover-remove class="shrink-0 cursor-pointer rounded-lg border-0 bg-transparent px-2.5 text-xs font-medium text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10">{{ __('desk-editor::articles.fields.cover_remove') }}</button>
                    </div>
                    <input type="text" name="cover_alt" value="{{ old('cover_alt', $article->cover_alt) }}" maxlength="255" placeholder="{{ __('desk-editor::articles.fields.cover_alt') }}" aria-label="{{ __('desk-editor::articles.fields.cover_alt') }}" class="{{ $input }}">
                </div>
            </details>

            <details open class="{{ $panel }} animate-de-rise [animation-delay:120ms]">
                <summary class="{{ $summary }}">{{ __('desk-editor::articles.panels.organise') }} {!! $chevron !!}</summary>
                <div class="flex flex-col gap-4 border-t border-zinc-100 px-4 py-4 dark:border-zinc-800">
                    {{-- Category picker --}}
                    <div>
                        <span class="{{ $label }}" id="category-label">
                            {{ __('desk-editor::articles.fields.category') }}
                            @if (\Illuminate\Support\Facades\Route::has('desk-editor.categories.index') && ($can['manage-taxonomy'] ?? false))
                                <a href="{{ route('desk-editor.categories.index') }}" class="text-[11px] font-normal text-indigo-600 no-underline hover:underline dark:text-indigo-400">{{ __('desk-editor::articles.category_picker.manage') }}</a>
                            @endif
                        </span>
                        <div class="relative" data-combo="category">
                            <input type="hidden" name="category" value="{{ $category }}" data-combo-value>
                            <button type="button" data-combo-trigger aria-haspopup="listbox" aria-expanded="false" id="category-trigger" aria-labelledby="category-trigger category-label" class="flex h-10 w-full cursor-pointer items-center gap-2 rounded-lg border border-zinc-200 bg-white px-3 text-left text-sm text-zinc-900 shadow-xs transition hover:border-zinc-300 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/15 focus:outline-none dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                                <span data-combo-avatar class="size-2.5 shrink-0 rounded-full bg-zinc-300 dark:bg-zinc-600" @if ($category && ($color = collect($categories)->firstWhere('name', $category)['color'] ?? null)) style="background: {{ $color }}" @endif></span>
                                <span data-combo-label @class(['flex-1 truncate', 'text-zinc-500 dark:text-zinc-400' => ! $category])>{{ $category ?: __('desk-editor::articles.category_picker.none') }}</span>
                                <svg viewBox="0 0 24 24" class="size-4 shrink-0 text-zinc-500 dark:text-zinc-400" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m7 15 5 5 5-5M7 9l5-5 5 5"/></svg>
                            </button>
                            <div data-combo-panel class="absolute inset-x-0 top-full z-30 mt-1.5 hidden overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-xl shadow-zinc-900/10 dark:border-zinc-700 dark:bg-zinc-900">
                                <div class="border-b border-zinc-100 p-2 dark:border-zinc-800">
                                    <input type="search" data-combo-search maxlength="100" placeholder="{{ __('desk-editor::articles.category_picker.search') }}" aria-label="{{ __('desk-editor::articles.category_picker.search') }}" class="{{ $input }} h-9">
                                </div>
                                <ul data-combo-list role="listbox" class="max-h-60 overflow-y-auto p-1 [scrollbar-width:thin]"></ul>
                            </div>
                            <script type="application/json" data-combo-options>{!! json_encode($categories, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) !!}</script>
                        </div>
                    </div>

                    {{-- Tags --}}
                    <div class="relative">
                        <span class="{{ $label }}">{{ __('desk-editor::articles.fields.tags') }}</span>
                        <input type="hidden" name="tags" value="{{ $tags }}" data-tags-input>
                        <div data-tags class="flex min-h-10 flex-wrap items-center gap-1.5 rounded-lg border border-zinc-200 bg-white p-1.5 transition focus-within:border-indigo-500 focus-within:ring-4 focus-within:ring-indigo-500/15 dark:border-zinc-700 dark:bg-zinc-950">
                            <input type="text" data-tags-entry autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="desk-tag-suggestions" class="min-w-24 flex-1 border-0 bg-transparent px-1 py-0.5 text-sm text-zinc-900 outline-none dark:text-zinc-100" aria-label="{{ __('desk-editor::articles.fields.tags') }}">
                        </div>
                        <ul id="desk-tag-suggestions" data-tags-suggestions role="listbox" class="absolute inset-x-0 z-30 mt-1 hidden max-h-52 overflow-y-auto rounded-xl border border-zinc-200 bg-white p-1 shadow-xl shadow-zinc-900/10 dark:border-zinc-700 dark:bg-zinc-900"></ul>
                        <span class="mt-1 block text-xs text-zinc-500 dark:text-zinc-400">{{ __('desk-editor::articles.fields.tags_hint') }}</span>
                        <script type="application/json" data-tags-options>{!! json_encode($tagSuggestions, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) !!}</script>
                    </div>
                    <label class="block">
                        <span class="{{ $label }}">{{ __('desk-editor::articles.fields.excerpt') }} <span class="text-[11px] font-normal text-zinc-500 dark:text-zinc-400 tabular-nums" data-count="excerpt"></span></span>
                        <textarea name="excerpt" rows="3" maxlength="1000" data-counted="excerpt" class="{{ $input }} resize-y">{{ old('excerpt', $article->excerpt) }}</textarea>
                        <span class="mt-1 block text-xs text-zinc-500 dark:text-zinc-400">{{ __('desk-editor::articles.fields.excerpt_hint') }}</span>
                    </label>
                </div>
            </details>

            <details class="{{ $panel }} animate-de-rise [animation-delay:160ms]">
                <summary class="{{ $summary }}">{{ __('desk-editor::articles.panels.seo') }} {!! $chevron !!}</summary>
                <div class="flex flex-col gap-4 border-t border-zinc-100 px-4 py-4 dark:border-zinc-800">
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-950">
                        <div class="mb-1 text-[11px] font-semibold tracking-wider text-zinc-500 dark:text-zinc-400 uppercase">{{ __('desk-editor::articles.seo_preview') }}</div>
                        <div class="truncate text-xs text-emerald-700 dark:text-emerald-400" data-seo-url></div>
                        <div class="truncate text-[15px] text-blue-700 dark:text-blue-400" data-seo-title></div>
                        <div class="line-clamp-2 text-xs text-zinc-600 dark:text-zinc-400" data-seo-desc></div>
                    </div>
                    <label class="block">
                        <span class="{{ $label }}">{{ __('desk-editor::articles.fields.meta_title') }} <span class="text-[11px] font-normal text-zinc-500 dark:text-zinc-400 tabular-nums" data-count="meta_title" data-max="60"></span></span>
                        <input type="text" name="meta_title" value="{{ old('meta_title', $article->meta_title) }}" maxlength="255" data-counted="meta_title" class="{{ $input }}">
                    </label>
                    <label class="block">
                        <span class="{{ $label }}">{{ __('desk-editor::articles.fields.meta_description') }} <span class="text-[11px] font-normal text-zinc-500 dark:text-zinc-400 tabular-nums" data-count="meta_description" data-max="160"></span></span>
                        <textarea name="meta_description" rows="3" maxlength="320" data-counted="meta_description" class="{{ $input }} resize-y">{{ old('meta_description', $article->meta_description) }}</textarea>
                    </label>
                    <label class="block">
                        <span class="{{ $label }}">{{ __('desk-editor::articles.fields.canonical_url') }}</span>
                        <input type="url" name="canonical_url" value="{{ old('canonical_url', $article->canonical_url) }}" placeholder="https://" class="{{ $input }}">
                    </label>
                </div>
            </details>

            @if ($exists)
                {{-- Translations --}}
                @if (count($locales) > 1 || $translations->isNotEmpty())
                    <details @if ($translations->isNotEmpty()) open @endif class="{{ $panel }} animate-de-rise [animation-delay:180ms]" data-translations>
                        <summary class="{{ $summary }}">
                            <span class="flex items-center gap-2">{{ __('desk-editor::articles.translations.title') }} <span class="rounded-md bg-zinc-100 px-1.5 text-[11px] font-medium text-zinc-500 tabular-nums dark:bg-zinc-800">{{ $translations->count() + 1 }}</span></span>
                            {!! $chevron !!}
                        </summary>
                        <div class="flex flex-col gap-3 border-t border-zinc-100 px-4 py-3 dark:border-zinc-800">
                            <ul class="flex flex-col gap-1">
                                <li class="flex items-center gap-2 rounded-lg bg-indigo-50/70 px-2 py-1.5 text-sm dark:bg-indigo-400/10">
                                    <span class="rounded bg-indigo-600 px-1.5 font-mono text-[10px] font-semibold text-white uppercase">{{ $article->locale }}</span>
                                    <span class="flex-1 truncate font-medium">{{ $locales[$article->locale] ?? $article->locale }}</span>
                                    <span class="text-[11px] text-indigo-700 dark:text-indigo-300">{{ __('desk-editor::articles.translations.current') }}</span>
                                </li>
                                @foreach ($translations as $translation)
                                    <li>
                                        <a href="{{ $translation->trashed() ? '#' : route('desk-editor.articles.edit', $translation) }}" class="group/tr flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm text-zinc-800 no-underline transition hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-800">
                                            <span class="rounded bg-zinc-200 px-1.5 font-mono text-[10px] font-semibold text-zinc-600 uppercase dark:bg-zinc-700 dark:text-zinc-200">{{ $translation->locale }}</span>
                                            <span class="min-w-0 flex-1 truncate" title="{{ $translation->title }}">{{ $translation->title }}</span>
                                            <span class="size-2 shrink-0 rounded-full {{ $translation->status->dot() }}" title="{{ $translation->status->label() }}"></span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                            @if ($missingLocales !== [] && ($can['create'] ?? false))
                                <div class="rounded-xl border border-dashed border-zinc-200 p-2.5 dark:border-zinc-700">
                                    <div class="flex gap-2">
                                        <select name="locale" form="translation-form" aria-label="{{ __('desk-editor::articles.translations.language') }}" class="{{ $input }} h-9 cursor-pointer py-0">
                                            @foreach ($missingLocales as $code => $name)
                                                <option value="{{ $code }}">{{ $name }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" form="translation-form" class="{{ $btn }} shrink-0">{{ __('desk-editor::articles.translations.add') }}</button>
                                    </div>
                                    <p class="mt-1.5 text-[11px] text-zinc-500 dark:text-zinc-400">{{ __('desk-editor::articles.translations.add_hint') }}</p>
                                </div>
                            @elseif ($missingLocales === [])
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('desk-editor::articles.translations.all_done') }}</p>
                            @endif
                        </div>
                    </details>
                @endif

                {{-- Activity timeline --}}
                <details open class="{{ $panel }} animate-de-rise [animation-delay:200ms]" data-activity>
                    <summary class="{{ $summary }}">
                        <span class="flex items-center gap-2">{{ __('desk-editor::articles.panels.activity') }} <span class="rounded-md bg-zinc-100 px-1.5 text-[11px] font-medium text-zinc-500 tabular-nums dark:bg-zinc-800">{{ $activities->count() }}</span></span>
                        {!! $chevron !!}
                    </summary>
                    <div class="max-h-96 overflow-y-auto border-t border-zinc-100 px-4 py-3 [scrollbar-width:thin] dark:border-zinc-800">
                        @forelse ($activities as $activity)
                            @php($who = $authorsInfo->nameOf($activity->causer) ?: __('desk-editor::articles.activity.system'))
                            <div class="relative flex gap-3 pb-4 last:pb-0">
                                <div class="relative flex flex-col items-center">
                                    <span class="grid size-6 shrink-0 place-items-center rounded-full ring-4 ring-white dark:ring-zinc-900 {{ $activity->tone() }}">
                                        <svg viewBox="0 0 24 24" class="size-3" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $activity->icon() }}"/></svg>
                                    </span>
                                    @unless ($loop->last)<span class="mt-1 w-px flex-1 bg-zinc-200 dark:bg-zinc-700"></span>@endunless
                                </div>
                                <div class="min-w-0 flex-1 pt-0.5">
                                    <p class="text-[13px] leading-snug"><span class="font-medium">{{ $who }}</span> <span class="text-zinc-500 dark:text-zinc-400">· {{ \Illuminate\Support\Str::lower($activity->label()) }}</span>
                                        @if ($activity->type === 'scheduled' && ! empty($activity->properties['at']))
                                            <span class="text-zinc-500 dark:text-zinc-400">{{ __('desk-editor::articles.activity.scheduled_for', ['date' => \Illuminate\Support\Carbon::parse($activity->properties['at'])->toDayDateTimeString()]) }}</span>
                                        @endif
                                    </p>
                                    <time class="text-[11px] text-zinc-500 dark:text-zinc-400" datetime="{{ $activity->created_at?->toIso8601String() }}" title="{{ $activity->created_at }}">{{ $activity->created_at?->diffForHumans() }}</time>
                                    @if ($activity->description)
                                        <p @class(['mt-1 text-xs whitespace-pre-line', 'rounded-lg bg-rose-50 px-2.5 py-1.5 text-rose-900 dark:bg-rose-500/10 dark:text-rose-200' => $activity->type === 'changes_requested', 'text-zinc-500 dark:text-zinc-400' => $activity->type !== 'changes_requested'])>{{ $activity->description }}</p>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <p class="text-xs text-zinc-500">{{ __('desk-editor::articles.activity.empty') }}</p>
                        @endforelse
                    </div>
                </details>

                {{-- Revisions --}}
                <details class="{{ $panel }} animate-de-rise [animation-delay:220ms]" data-revisions>
                    <summary class="{{ $summary }}">
                        <span class="flex items-center gap-2">{{ __('desk-editor::articles.panels.history') }} <span class="rounded-md bg-zinc-100 px-1.5 text-[11px] font-medium text-zinc-500 tabular-nums dark:bg-zinc-800">{{ $revisions->count() }}</span></span>
                        {!! $chevron !!}
                    </summary>
                    <div class="border-t border-zinc-100 px-4 py-3 dark:border-zinc-800">
                        @if ($revisions->count() > 1)
                            <a href="{{ route('desk-editor.articles.revisions.compare', $article) }}" class="mb-3 inline-flex items-center gap-1.5 text-xs font-medium text-indigo-600 no-underline hover:underline dark:text-indigo-400">
                                <svg viewBox="0 0 24 24" class="size-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 21V9a3 3 0 0 0-3-3H9M6 3v12a3 3 0 0 0 3 3h6M15 3l3 3-3 3M9 15l-3 3 3 3"/></svg>
                                {{ __('desk-editor::articles.diff.title') }}
                            </a>
                        @endif
                        @forelse ($revisions as $index => $revision)
                            <div class="group/rev relative flex gap-3 pb-4 last:pb-0">
                                <div class="relative flex flex-col items-center">
                                    <span @class(['mt-1.5 size-2.5 rounded-full ring-4 ring-white dark:ring-zinc-900', 'bg-indigo-500' => $index === 0, 'bg-zinc-300 dark:bg-zinc-600' => $index > 0])></span>
                                    @unless ($loop->last)<span class="mt-1 w-px flex-1 bg-zinc-200 dark:bg-zinc-700"></span>@endunless
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="truncate text-[13px] font-medium" title="{{ $revision->created_at }}">{{ $revision->created_at?->diffForHumans() }}</span>
                                        <span class="flex shrink-0 gap-0.5 opacity-100 transition sm:opacity-0 sm:group-hover/rev:opacity-100 sm:focus-within:opacity-100">
                                            <a href="{{ route('desk-editor.articles.revisions.compare', [$article, 'from' => $revision->id, 'to' => 'current']) }}" class="rounded-md px-2 py-0.5 text-xs font-medium text-zinc-600 no-underline hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800">{{ __('desk-editor::articles.diff.compare') }}</a>
                                            @if ($index > 0 && $canSave)
                                                <button type="submit" form="restore-{{ $revision->id }}" class="cursor-pointer rounded-md border-0 bg-transparent px-2 py-0.5 text-xs font-medium text-indigo-600 hover:bg-indigo-50 dark:text-indigo-400 dark:hover:bg-indigo-400/10">{{ __('desk-editor::articles.restore_revision') }}</button>
                                            @endif
                                        </span>
                                    </div>
                                    <div class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $revision->note ?: $revision->title }}</div>
                                    <div class="text-[11px] text-zinc-500">
                                        {{ __('desk-editor::articles.words', ['count' => number_format($revision->word_count)]) }} · {{ ArticleStatus::tryFrom($revision->status)?->label() }}
                                        @if ($revisionAuthor = $authorsInfo->nameOf($revision->author)) · {{ $revisionAuthor }} @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <p class="text-xs text-zinc-500">{{ __('desk-editor::articles.no_revisions') }}</p>
                        @endforelse
                    </div>
                </details>

                <div class="flex flex-wrap gap-2 px-1 pt-1 text-xs">
                    @if ($can['create'] ?? false)
                        <button type="submit" form="duplicate-form" class="cursor-pointer rounded-lg border-0 bg-transparent px-2.5 py-1.5 font-medium text-zinc-600 transition hover:bg-zinc-200/60 dark:text-zinc-300 dark:hover:bg-zinc-800">{{ __('desk-editor::articles.duplicate') }}</button>
                    @endif
                    @if ($url = $article->publicUrl())
                        <a href="{{ $url }}" target="_blank" rel="noopener" class="rounded-lg px-2.5 py-1.5 font-medium text-zinc-600 no-underline transition hover:bg-zinc-200/60 dark:text-zinc-300 dark:hover:bg-zinc-800">{{ __('desk-editor::articles.view_live') }}</a>
                    @endif
                    @if ($can['delete'] ?? false)
                        <button type="submit" form="delete-form" class="ml-auto cursor-pointer rounded-lg border-0 bg-transparent px-2.5 py-1.5 font-medium text-red-600 transition hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10">{{ __('desk-editor::articles.delete') }}</button>
                    @endif
                </div>
            @endif
        </aside>
    </div>
</form>

{{-- Floating conflict notice for keyboard saves --}}
@if ($exists)
    @foreach ($revisions as $revision)
        <form id="restore-{{ $revision->id }}" method="POST" action="{{ route('desk-editor.articles.revisions.restore', [$article, $revision->id]) }}" data-confirm="{{ __('desk-editor::articles.confirm_restore') }}" hidden>@csrf</form>
    @endforeach
    <form id="duplicate-form" method="POST" action="{{ route('desk-editor.articles.duplicate', $article) }}" hidden>@csrf</form>
    <form id="delete-form" method="POST" action="{{ route('desk-editor.articles.destroy', $article) }}" data-confirm="{{ __('desk-editor::articles.confirm_delete') }}" hidden>@csrf @method('DELETE')</form>
    <form id="translation-form" method="POST" action="{{ route('desk-editor.articles.translations.store', $article) }}" hidden>@csrf</form>
    <form id="takeover-form" method="POST" action="{{ route('desk-editor.articles.lock', $article) }}" hidden>@csrf <input type="hidden" name="take_over" value="1"></form>
    <form id="approve-form" method="POST" action="{{ route('desk-editor.articles.review', $article) }}" hidden>@csrf</form>
    <form id="changes-form" method="POST" action="{{ route('desk-editor.articles.review', $article) }}" hidden>@csrf</form>
@endif
@endsection

@push('desk-scripts')
<script>
(() => {
    const form = document.querySelector('[data-article-form]');
    const $ = (s) => form.querySelector(s);
    const t = {{ \Illuminate\Support\Js::from([
        'saving' => __('desk-editor::articles.saving'),
        'saved' => __('desk-editor::articles.saved_at', ['time' => ':time']),
        'unsaved' => __('desk-editor::articles.unsaved'),
        'leave' => __('desk-editor::articles.leave_warning'),
        'editing' => __('desk-editor::articles.lock.editing', ['name' => ':name']),
        'opened' => __('desk-editor::articles.lock.opened', ['time' => ':time']),
        'lost' => __('desk-editor::articles.lock.lost', ['name' => ':name']),
        'noAuthor' => __('desk-editor::articles.author.none'),
        'noResults' => __('desk-editor::articles.author.no_results'),
        'noCategory' => __('desk-editor::articles.category_picker.none'),
        'createCategory' => __('desk-editor::articles.category_picker.create', ['name' => ':name']),
    ]) }};
    const uploadUrl = {{ \Illuminate\Support\Js::from(\Atannex\DeskEditor\Facades\DeskEditor::uploadsEnabled() ? route('desk-editor.upload') : null) }};
    const baseUrl = {{ \Illuminate\Support\Js::from(rtrim(str_replace('{slug}', '', (string) (config('desk-editor.articles.public_url') ?: url('/').'/')), '/')) }};
    const csrf = document.querySelector('meta[name=csrf-token]').content;
    const lockSeconds = {{ (int) $lockSeconds }};
    let dirty = false;

    /* ---------- save state ---------- */
    const stateText = document.querySelector('[data-save-text]');
    const stateDot = document.querySelector('[data-save-dot]');
    const markDirty = () => {
        dirty = true;
        if (stateText) stateText.textContent = t.unsaved;
        stateDot?.classList.replace('bg-emerald-500', 'bg-amber-500');
    };
    form.addEventListener('input', markDirty);
    document.addEventListener('desk-editor:change', markDirty);
    window.addEventListener('beforeunload', (e) => { if (dirty) { e.preventDefault(); e.returnValue = t.leave; } });

    form.addEventListener('submit', (e) => {
        dirty = false;
        const intent = e.submitter?.value;
        const pick = (value) => { const radio = form.querySelector(`[data-status][value="${value}"]`); if (radio) radio.checked = true; };
        if (intent === 'publish') {
            const status = form.querySelector('[data-status]:checked');
            if (status?.value !== 'scheduled') pick('published');
        }
        if (intent === 'submit') pick('review');
    });

    /* ---------- conflicts (optimistic concurrency) ---------- */
    const version = $('[data-version]');
    const conflict = document.querySelector('[data-conflict]');
    const showConflict = (message) => {
        if (!conflict) return;
        conflict.querySelector('[data-conflict-message]').textContent = message || '';
        conflict.classList.remove('hidden');
        conflict.scrollIntoView({ behavior: 'smooth', block: 'center' });
    };
    conflict?.querySelector('[data-conflict-reload]')?.addEventListener('click', () => { dirty = false; });

    // Ctrl/Cmd + S saves in place without leaving the page.
    const quickSave = async (overwrite = false) => {
        form.dispatchEvent(new Event('submit')); // lets the editor flush its value
        if (stateText) stateText.textContent = t.saving;
        const body = new FormData(form);
        if (overwrite) body.set('overwrite', '1');
        let response;
        try {
            response = await fetch(form.dataset.updateUrl, { method: 'POST', body, headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        } catch {
            markDirty();
            return;
        }
        if (response.ok) {
            const data = await response.json().catch(() => ({}));
            dirty = false;
            if (version && data.article?.version) version.value = data.article.version;
            conflict?.classList.add('hidden');
            if (stateText) stateText.textContent = t.saved.replace(':time', new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }));
            stateDot?.classList.replace('bg-amber-500', 'bg-emerald-500');
        } else if (response.status === 409) {
            const data = await response.json().catch(() => ({}));
            markDirty();
            showConflict(data.message);
        } else {
            form.requestSubmit();
        }
    };
    conflict?.querySelector('[data-conflict-overwrite]')?.addEventListener('click', (e) => {
        if (!form.dataset.updateUrl) return;
        e.preventDefault();
        quickSave(true);
    });
    document.addEventListener('keydown', (e) => {
        if (!((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 's')) return;
        e.preventDefault();
        if (!form.hasAttribute('data-can-save')) return;
        if (!form.dataset.updateUrl) return form.requestSubmit();
        quickSave();
    });

    /* ---------- edit lock heartbeat ---------- */
    const lockBanner = document.querySelector('[data-lock-banner]');
    const showLock = (holder, lost = false) => {
        if (!lockBanner) return;
        lockBanner.classList.toggle('hidden', !holder);
        if (!holder) return;
        const initials = holder.name.split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0].toUpperCase()).join('') || '?';
        lockBanner.querySelector('[data-lock-initials]').firstChild.textContent = initials;
        lockBanner.querySelector('[data-lock-title]').textContent = lost ? t.lost.replace(':name', holder.name) : t.editing.replace(':name', holder.name);
        lockBanner.querySelector('[data-lock-since]').textContent = t.opened.replace(':time', holder.since_human);
    };
    let hadLock = form.hasAttribute('data-can-save') && lockBanner?.classList.contains('hidden');
    const heartbeat = async (takeOver = false) => {
        if (!form.dataset.lockUrl || !form.hasAttribute('data-can-save')) return;
        try {
            const body = new FormData();
            body.append('_token', csrf);
            if (takeOver) body.append('take_over', '1');
            const response = await fetch(form.dataset.lockUrl, { method: 'POST', body, headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) return;
            const data = await response.json();
            showLock(data.holder, hadLock && !!data.holder);
            hadLock = data.acquired;
        } catch {}
    };
    lockBanner?.querySelector('[data-take-over]')?.addEventListener('click', (e) => {
        e.preventDefault();
        heartbeat(true);
    });
    if (form.dataset.lockUrl) {
        setInterval(() => { if (!document.hidden) heartbeat(); }, Math.max(10, Math.min(30, lockSeconds / 3)) * 1000);
        document.addEventListener('visibilitychange', () => { if (!document.hidden) heartbeat(); });
        window.addEventListener('pagehide', () => {
            if (!hadLock || !navigator.sendBeacon) return;
            const body = new FormData();
            body.append('_token', csrf);
            navigator.sendBeacon(form.dataset.releaseUrl, body);
        });
    }

    /* ---------- review panes ---------- */
    document.querySelectorAll('[data-review-toggle]').forEach((button) => button.addEventListener('click', () => {
        const name = button.dataset.reviewToggle;
        document.querySelectorAll('[data-review-pane]').forEach((pane) => {
            const show = pane.dataset.reviewPane === name && pane.classList.contains('hidden');
            pane.classList.toggle('hidden', !show);
            pane.classList.toggle(pane.dataset.reviewPane === 'approve' ? 'grid' : 'block', show);
            if (show) pane.querySelector('textarea, input')?.focus();
        });
    }));
    ['approve-form', 'changes-form', 'translation-form', 'takeover-form'].forEach((id) => document.getElementById(id)?.addEventListener('submit', () => { dirty = false; }));

    /* ---------- title autogrow + slug ---------- */
    const title = $('[data-title]');
    const slug = $('[data-slug]');
    const grow = () => { title.style.height = 'auto'; title.style.height = `${title.scrollHeight}px`; };
    const slugify = (v) => v.normalize('NFKD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 180);
    title.addEventListener('input', () => {
        grow();
        if (!slug.dataset.locked && !slug.dataset.touched) slug.value = slugify(title.value);
        seo();
    });
    title.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); document.querySelector('.desk-editor')?.querySelector('.ProseMirror')?.focus(); } });
    slug.addEventListener('input', () => { slug.dataset.touched = '1'; slug.value = slugify(slug.value); seo(); });
    grow();

    /* ---------- schedule visibility ---------- */
    const schedule = $('[data-schedule]');
    const syncStatus = () => {
        const value = form.querySelector('[data-status]:checked')?.value;
        schedule?.classList.toggle('opacity-50', !['scheduled', 'published'].includes(value));
    };
    form.querySelectorAll('[data-status]').forEach((r) => r.addEventListener('change', syncStatus));
    syncStatus();

    /* ---------- counters + SEO preview ---------- */
    const counted = (name) => $(`[data-counted="${name}"]`);
    const updateCount = (name) => {
        const el = $(`[data-count="${name}"]`);
        const max = +el.dataset.max || 0;
        const n = counted(name).value.length;
        el.textContent = max ? `${n} / ${max}` : n;
        el.classList.toggle('text-amber-500', max && n > max);
    };
    const seo = () => {
        document.querySelector('[data-seo-url]').textContent = `${baseUrl}/${slug.value || 'article'}`;
        document.querySelector('[data-seo-title]').textContent = counted('meta_title').value || title.value || 'Untitled';
        document.querySelector('[data-seo-desc]').textContent = counted('meta_description').value || counted('excerpt').value || '—';
    };
    ['excerpt', 'meta_title', 'meta_description'].forEach((name) => {
        counted(name).addEventListener('input', () => { updateCount(name); seo(); });
        updateCount(name);
    });
    seo();

    /* ---------- live insights from the editor ---------- */
    const stats = (detail) => {
        const desk = window.DeskEditor?.get(document.querySelector('.desk-editor'));
        const dom = desk?.editor?.view.dom;
        const s = detail || desk?.stats?.() || {};
        document.querySelector('[data-stat="words"]').textContent = (s.words ?? 0).toLocaleString();
        document.querySelector('[data-stat="reading"]').textContent = s.words ? Math.max(1, Math.ceil(s.words / 230)) : 0;
        document.querySelector('[data-stat="headings"]').textContent = dom ? dom.querySelectorAll('h1,h2,h3,h4,h5,h6').length : 0;
        document.querySelector('[data-stat="media"]').textContent = dom ? dom.querySelectorAll('img,video,audio,iframe').length : 0;
    };
    document.addEventListener('desk-editor:change', (e) => stats(e.detail));
    document.addEventListener('desk-editor:ready', () => setTimeout(() => stats(), 50));
    setTimeout(() => stats(), 400);

    /* ---------- combobox (author + category pickers) ---------- */
    const optionClass = 'flex w-full cursor-pointer items-center gap-2 rounded-lg border-0 bg-transparent px-2 py-1.5 text-left text-sm text-zinc-800 transition hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-800';
    const activeClass = ['bg-indigo-50', 'text-indigo-700', 'dark:bg-indigo-400/10', 'dark:text-indigo-300'];
    const initialsOf = (name) => (name || '').split(/[\s._@-]+/).filter(Boolean).slice(0, 2).map((w) => w[0].toUpperCase()).join('') || '?';
    const combo = (root, { render, matches, choose, create, remote }) => {
        if (!root) return;
        const trigger = root.querySelector('[data-combo-trigger]');
        const panel = root.querySelector('[data-combo-panel]');
        const search = root.querySelector('[data-combo-search]');
        const list = root.querySelector('[data-combo-list]');
        let options = JSON.parse(root.querySelector('[data-combo-options]').textContent || '[]');
        let items = [];
        let active = 0;
        let timer;
        const close = () => { panel.classList.add('hidden'); trigger.setAttribute('aria-expanded', 'false'); };
        const highlight = (i) => {
            active = Math.max(0, Math.min(items.length - 1, i));
            items.forEach((el, n) => { activeClass.forEach((c) => el.classList.toggle(c, n === active)); el.setAttribute('aria-selected', n === active ? 'true' : 'false'); });
            items[active]?.scrollIntoView({ block: 'nearest' });
        };
        const draw = () => {
            const q = search.value.trim();
            list.replaceChildren();
            const rows = render(options.filter((o) => matches(o, q.toLowerCase())), q);
            rows.forEach(({ node, value }) => {
                const li = document.createElement('li');
                const button = document.createElement('button');
                button.type = 'button';
                button.setAttribute('role', 'option');
                button.className = optionClass;
                button.append(...node);
                button.addEventListener('click', () => { choose(value); close(); trigger.focus(); markDirty(); });
                li.append(button);
                list.append(li);
            });
            if (!rows.length) {
                const li = document.createElement('li');
                li.className = 'px-2 py-3 text-center text-xs text-zinc-500';
                li.textContent = t.noResults;
                list.append(li);
            }
            items = [...list.querySelectorAll('button')];
            highlight(0);
        };
        trigger.addEventListener('click', () => {
            const open = panel.classList.contains('hidden');
            if (!open) return close();
            panel.classList.remove('hidden');
            trigger.setAttribute('aria-expanded', 'true');
            search.value = '';
            draw();
            search.focus();
        });
        search.addEventListener('input', () => {
            draw();
            if (remote) {
                clearTimeout(timer);
                timer = setTimeout(async () => {
                    try {
                        const r = await fetch(`${remote}?q=${encodeURIComponent(search.value.trim())}`, { headers: { Accept: 'application/json' } });
                        if (r.ok) { const fresh = (await r.json()).data || []; const ids = new Set(options.map((o) => o.id)); options = [...options, ...fresh.filter((o) => !ids.has(o.id))]; draw(); }
                    } catch {}
                }, 200);
            }
        });
        search.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowDown') { e.preventDefault(); highlight(active + 1); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); highlight(active - 1); }
            else if (e.key === 'Enter') { e.preventDefault(); items[active]?.click(); }
            else if (e.key === 'Escape') { e.preventDefault(); close(); trigger.focus(); }
        });
        document.addEventListener('click', (e) => { if (!root.contains(e.target)) close(); });
    };
    const span = (cls, text = '', style = '') => { const s = document.createElement('span'); s.className = cls; s.textContent = text; if (style) s.style.background = style; return s; };

    const authorRoot = form.querySelector('[data-combo="author"]');
    if (authorRoot) {
        const value = authorRoot.querySelector('[data-combo-value]');
        const label = authorRoot.querySelector('[data-combo-label]');
        const avatar = authorRoot.querySelector('[data-combo-avatar]');
        combo(authorRoot, {
            remote: authorRoot.dataset.comboUrl,
            matches: (o, q) => !q || o.name.toLowerCase().includes(q),
            render: (opts) => opts.slice(0, 50).map((o) => ({ value: o, node: [span('grid size-6 shrink-0 place-items-center rounded-full bg-zinc-200 text-[10px] font-semibold text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200', o.initials), span('flex-1 truncate', o.name), ...(String(o.id) === value.value ? [span('text-indigo-600 dark:text-indigo-400', '✓')] : [])] })),
            choose: (o) => { value.value = o.id; label.textContent = o.name; avatar.textContent = o.initials || initialsOf(o.name); },
        });
    }

    const categoryRoot = form.querySelector('[data-combo="category"]');
    if (categoryRoot) {
        const value = categoryRoot.querySelector('[data-combo-value]');
        const label = categoryRoot.querySelector('[data-combo-label]');
        const dot = categoryRoot.querySelector('[data-combo-avatar]');
        combo(categoryRoot, {
            matches: (o, q) => !q || o.name.toLowerCase().includes(q),
            render: (opts, q) => {
                const rows = [{ value: null, node: [span('size-2.5 shrink-0 rounded-full border border-dashed border-zinc-300 dark:border-zinc-600'), span('flex-1 truncate text-zinc-500', t.noCategory)] }];
                opts.forEach((o) => rows.push({ value: o, node: [span('shrink-0', '', ''), span('size-2.5 shrink-0 rounded-full bg-zinc-300 dark:bg-zinc-600', '', o.color || ''), span('flex-1 truncate', o.name), ...(o.name === value.value ? [span('text-indigo-600 dark:text-indigo-400', '✓')] : [])] }));
                rows.forEach((r) => { if (r.value?.depth) r.node[0].style.width = `${r.value.depth * 12}px`; });
                if (q && !opts.some((o) => o.name.toLowerCase() === q.toLowerCase())) {
                    rows.push({ value: { name: q, color: null, depth: 0, created: true }, node: [span('grid size-5 shrink-0 place-items-center rounded-md bg-indigo-600 text-xs font-bold text-white', '+'), span('flex-1 truncate font-medium text-indigo-700 dark:text-indigo-300', t.createCategory.replace(':name', q))] });
                }
                return rows;
            },
            choose: (o) => {
                value.value = o ? o.name : '';
                label.textContent = o ? o.name : t.noCategory;
                label.classList.toggle('text-zinc-500 dark:text-zinc-400', !o);
                dot.style.background = o?.color || '';
            },
        });
    }

    /* ---------- tags (chips + autocomplete) ---------- */
    const tagsBox = $('[data-tags]');
    const tagsEntry = $('[data-tags-entry]');
    const tagsInput = $('[data-tags-input]');
    const suggestBox = $('[data-tags-suggestions]');
    const known = JSON.parse($('[data-tags-options]').textContent || '[]');
    let tags = tagsInput.value.split(',').map((s) => s.trim()).filter(Boolean);
    let suggestions = [];
    let suggestActive = -1;
    const renderTags = () => {
        tagsBox.querySelectorAll('[data-tag]').forEach((n) => n.remove());
        tags.forEach((tag, i) => {
            const chip = document.createElement('span');
            chip.dataset.tag = '';
            chip.className = 'animate-de-pop inline-flex items-center gap-1 rounded-md bg-indigo-50 py-0.5 pr-1 pl-2 text-xs font-medium text-indigo-700 dark:bg-indigo-400/10 dark:text-indigo-300';
            chip.textContent = tag;
            const x = document.createElement('button');
            x.type = 'button';
            x.className = 'grid size-4 cursor-pointer place-items-center rounded border-0 bg-transparent text-current hover:bg-indigo-100 dark:hover:bg-indigo-400/20';
            x.innerHTML = '&times;';
            x.setAttribute('aria-label', `Remove ${tag}`);
            x.onclick = () => { tags.splice(i, 1); renderTags(); markDirty(); };
            chip.append(x);
            tagsBox.insertBefore(chip, tagsEntry);
        });
        tagsInput.value = tags.join(', ');
    };
    const hideSuggestions = () => { suggestBox.classList.add('hidden'); tagsEntry.setAttribute('aria-expanded', 'false'); suggestActive = -1; };
    const drawSuggestions = () => {
        const q = tagsEntry.value.trim().toLowerCase();
        const chosen = new Set(tags.map((x) => x.toLowerCase()));
        suggestions = known.filter((name) => !chosen.has(name.toLowerCase()) && (!q || name.toLowerCase().includes(q))).slice(0, 8);
        suggestBox.replaceChildren();
        if (!suggestions.length || document.activeElement !== tagsEntry) return hideSuggestions();
        suggestions.forEach((name, i) => {
            const li = document.createElement('li');
            li.setAttribute('role', 'option');
            li.className = 'cursor-pointer rounded-lg px-2 py-1.5 text-sm text-zinc-800 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-800';
            li.textContent = `#${name}`;
            li.addEventListener('mousedown', (e) => { e.preventDefault(); addTag(name); });
            suggestBox.append(li);
        });
        suggestBox.classList.remove('hidden');
        tagsEntry.setAttribute('aria-expanded', 'true');
        suggestActive = -1;
    };
    const highlightSuggestion = (i) => {
        const items = [...suggestBox.children];
        if (!items.length) return;
        suggestActive = (i + items.length) % items.length;
        items.forEach((el, n) => activeClass.forEach((c) => el.classList.toggle(c, n === suggestActive)));
    };
    const addTag = (forced) => {
        const value = (forced ?? tagsEntry.value).replace(/,/g, '').trim();
        if (value && !tags.some((x) => x.toLowerCase() === value.toLowerCase()) && tags.length < 20) { tags.push(value); markDirty(); }
        tagsEntry.value = '';
        renderTags();
        drawSuggestions();
    };
    tagsEntry.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowDown') { e.preventDefault(); highlightSuggestion(suggestActive + 1); return; }
        if (e.key === 'ArrowUp') { e.preventDefault(); highlightSuggestion(suggestActive - 1); return; }
        if (e.key === 'Escape') { hideSuggestions(); return; }
        if (e.key === 'Enter' || e.key === ',' || e.key === 'Tab') {
            if (e.key === 'Tab' && !tagsEntry.value && suggestActive < 0) return;
            e.preventDefault();
            addTag(suggestActive >= 0 ? suggestions[suggestActive] : undefined);
        }
        if (e.key === 'Backspace' && !tagsEntry.value && tags.length) { tags.pop(); renderTags(); markDirty(); }
    });
    tagsEntry.addEventListener('input', drawSuggestions);
    tagsEntry.addEventListener('focus', drawSuggestions);
    tagsEntry.addEventListener('blur', () => { addTag(); hideSuggestions(); });
    renderTags();

    /* ---------- cover ---------- */
    const coverInput = $('[data-cover-input]');
    const coverPreview = $('[data-cover-preview]');
    const coverDrop = $('[data-cover-drop]');
    const coverProgress = $('[data-cover-progress]');
    const hero = document.querySelector('[data-cover-hero]');
    const setCover = (url) => {
        coverInput.value = url || '';
        coverPreview.src = url || '';
        coverPreview.classList.toggle('hidden', !url);
        hero.classList.toggle('hidden', !url);
        hero.querySelector('img').src = url || '';
        markDirty();
    };
    const uploadCover = (file) => {
        if (!uploadUrl || !file?.type.startsWith('image/')) return;
        const body = new FormData();
        body.append('file', file);
        body.append('kind', 'image');
        const xhr = new XMLHttpRequest();
        xhr.open('POST', uploadUrl);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-CSRF-TOKEN', csrf);
        xhr.upload.onprogress = (e) => { coverProgress.style.transform = `scaleX(${e.loaded / e.total})`; };
        xhr.onload = () => {
            coverProgress.style.transform = 'scaleX(0)';
            try {
                const data = JSON.parse(xhr.responseText);
                if (xhr.status < 300 && data.url) setCover(data.url);
                else alert(data.message || 'Upload failed');
            } catch { alert('Upload failed'); }
        };
        xhr.send(body);
    };
    coverDrop.addEventListener('click', () => {
        const picker = document.createElement('input');
        picker.type = 'file';
        picker.accept = 'image/*';
        picker.onchange = () => uploadCover(picker.files[0]);
        picker.click();
    });
    coverDrop.addEventListener('dragover', (e) => { e.preventDefault(); coverDrop.classList.add('border-indigo-500'); });
    coverDrop.addEventListener('dragleave', () => coverDrop.classList.remove('border-indigo-500'));
    coverDrop.addEventListener('drop', (e) => { e.preventDefault(); coverDrop.classList.remove('border-indigo-500'); uploadCover(e.dataTransfer.files[0]); });
    $('[data-cover-url]').addEventListener('change', (e) => setCover(e.target.value.trim()));
    $('[data-cover-remove]').addEventListener('click', () => setCover(''));

    /* ---------- confirmations for auxiliary forms ---------- */
    document.querySelectorAll('form[data-confirm]').forEach((f) => f.addEventListener('submit', (e) => {
        if (!confirm(f.dataset.confirm)) e.preventDefault();
        else dirty = false;
    }));
    document.getElementById('duplicate-form')?.addEventListener('submit', () => { dirty = false; });
})();
</script>
@endpush
