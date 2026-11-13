@extends('desk-editor::articles.layout')

@php
    $input = 'block w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-xs transition outline-none placeholder:text-zinc-400 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/15 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100';
    $label = 'mb-1.5 block text-[13px] font-medium text-zinc-700 dark:text-zinc-300';
    $reopen = $errors->any() ? (old('_editing') ?: 'new') : null;
    $payload = $all->map(fn ($c) => [
        'id' => $c->id,
        'name' => $c->name,
        'slug' => $c->slug,
        'description' => $c->description,
        'color' => \Atannex\DeskEditor\Models\DeskCategory::sanitizeColor($c->color),
        'parent_id' => $c->parent_id,
        'update_url' => route('desk-editor.categories.update', $c),
    ])->keyBy('id');
@endphp

@section('title', __('desk-editor::articles.categories.title'))

@section('header-actions')
    <button type="button" data-category-new class="inline-flex h-9 cursor-pointer items-center gap-1.5 rounded-lg border-0 bg-indigo-600 px-2.5 text-sm font-medium text-white shadow-sm shadow-indigo-600/25 transition hover:bg-indigo-500 active:scale-[.98] sm:px-3.5 dark:bg-indigo-500 dark:hover:bg-indigo-400" aria-label="{{ __('desk-editor::articles.categories.new') }}">
        <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
        <span class="hidden sm:inline">{{ __('desk-editor::articles.categories.new') }}</span>
    </button>
@endsection

@section('content')
<main class="mx-auto max-w-4xl px-4 pt-8 pb-24 sm:px-6">
    <div class="animate-de-rise mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ __('desk-editor::articles.categories.title') }}</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('desk-editor::articles.categories.subtitle') }}</p>
        </div>
        @if ($uncategorised)
            <a href="{{ route('desk-editor.articles.index', ['uncategorised' => 1]) }}" class="text-xs text-zinc-500 no-underline hover:text-indigo-600 dark:text-zinc-400 dark:hover:text-indigo-400">{{ __('desk-editor::articles.categories.uncategorised', ['count' => $uncategorised]) }}</a>
        @endif
    </div>

    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
        @if ($categories === [])
            <div class="animate-de-rise flex flex-col items-center px-6 py-20 text-center">
                <div class="relative mb-5">
                    <div class="absolute inset-0 animate-pulse rounded-full bg-indigo-500/20 blur-2xl"></div>
                    <div class="relative grid size-16 place-items-center rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                        <svg viewBox="0 0 24 24" class="size-7 text-indigo-500" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2ZM12 10v6M9 13h6"/></svg>
                    </div>
                </div>
                <h2 class="text-base font-semibold">{{ __('desk-editor::articles.categories.empty_title') }}</h2>
                <p class="mt-1 max-w-sm text-sm text-zinc-500 dark:text-zinc-400">{{ __('desk-editor::articles.categories.empty_body') }}</p>
                <button type="button" data-category-new class="mt-6 inline-flex h-9 cursor-pointer items-center rounded-lg border-0 bg-indigo-600 px-3.5 text-sm font-medium text-white transition hover:bg-indigo-500">{{ __('desk-editor::articles.categories.new') }}</button>
            </div>
        @else
            <ul class="divide-y divide-zinc-100 dark:divide-zinc-800" data-category-list data-reorder-url="{{ route('desk-editor.categories.reorder') }}">
                @foreach ($categories as $i => ['category' => $category, 'depth' => $depth])
                    <li class="animate-de-rise group relative flex items-center gap-3 bg-white px-3 py-3 transition-colors hover:bg-zinc-50 sm:px-4 dark:bg-zinc-900 dark:hover:bg-zinc-800/40" style="animation-delay: {{ min($i, 12) * 25 }}ms" draggable="true" data-category-row data-id="{{ $category->id }}">
                        <button type="button" data-drag-handle class="grid size-7 shrink-0 cursor-grab place-items-center rounded-md border-0 bg-transparent text-zinc-300 transition hover:bg-zinc-100 hover:text-zinc-600 active:cursor-grabbing dark:text-zinc-600 dark:hover:bg-zinc-800 dark:hover:text-zinc-300" title="{{ __('desk-editor::articles.categories.drag') }}" aria-label="{{ __('desk-editor::articles.categories.drag') }}: {{ $category->name }}">
                            <svg viewBox="0 0 24 24" class="size-4" fill="currentColor"><circle cx="9" cy="6" r="1.5"/><circle cx="15" cy="6" r="1.5"/><circle cx="9" cy="12" r="1.5"/><circle cx="15" cy="12" r="1.5"/><circle cx="9" cy="18" r="1.5"/><circle cx="15" cy="18" r="1.5"/></svg>
                        </button>
                        <div class="flex min-w-0 flex-1 items-center gap-3" style="padding-left: {{ $depth * 1.25 }}rem">
                            @if ($depth)<svg viewBox="0 0 24 24" class="-ml-1 size-3.5 shrink-0 text-zinc-300 dark:text-zinc-600" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 4v8a4 4 0 0 0 4 4h12"/></svg>@endif
                            <span class="grid size-9 shrink-0 place-items-center rounded-xl text-sm font-semibold text-white shadow-sm" style="background: {{ $category->cssColor() }}">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($category->name, 0, 1)) }}</span>
                            <div class="min-w-0">
                                <button type="button" data-category-edit="{{ $category->id }}" class="block max-w-full cursor-pointer truncate border-0 bg-transparent p-0 text-left font-medium text-zinc-900 hover:text-indigo-600 dark:text-white dark:hover:text-indigo-400">{{ $category->name }}</button>
                                <p class="truncate text-xs text-zinc-500 dark:text-zinc-400"><span class="font-mono">/{{ $category->slug }}</span>@if ($category->description) · {{ $category->description }}@endif</p>
                            </div>
                        </div>
                        <a href="{{ route('desk-editor.articles.index', ['category' => $category->name]) }}" class="hidden shrink-0 rounded-full bg-zinc-100 px-2.5 py-0.5 text-xs font-medium text-zinc-600 no-underline tabular-nums transition hover:bg-indigo-50 hover:text-indigo-700 sm:inline-flex dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-indigo-400/10 dark:hover:text-indigo-300" title="{{ __('desk-editor::articles.categories.view_articles') }}">{{ trans_choice('desk-editor::articles.categories.articles', $category->articles_count ?? 0, ['count' => $category->articles_count ?? 0]) }}</a>
                        <span class="text-xs text-zinc-500 tabular-nums sm:hidden">{{ $category->articles_count }}</span>
                        <div class="flex shrink-0 items-center gap-0.5 opacity-100 transition sm:opacity-0 sm:group-hover:opacity-100 sm:focus-within:opacity-100">
                            <button type="button" data-category-edit="{{ $category->id }}" class="grid size-8 cursor-pointer place-items-center rounded-lg border-0 bg-transparent text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-900 dark:hover:bg-zinc-800 dark:hover:text-white" aria-label="{{ __('desk-editor::articles.categories.edit') }}: {{ $category->name }}">
                                <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.4 3.6a2.1 2.1 0 1 1 3 3L7 19l-4 1 1-4Z"/></svg>
                            </button>
                            <form method="POST" action="{{ route('desk-editor.categories.destroy', $category) }}" data-confirm="{{ __('desk-editor::articles.categories.confirm_delete') }}">
                                @csrf @method('DELETE')
                                <button type="submit" class="grid size-8 cursor-pointer place-items-center rounded-lg border-0 bg-transparent text-zinc-500 transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10 dark:hover:text-red-400" aria-label="{{ __('desk-editor::articles.categories.delete') }}: {{ $category->name }}">
                                    <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                </button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
    <p class="mt-3 hidden text-center text-xs text-zinc-500 dark:text-zinc-400" data-reorder-status role="status"></p>
</main>

{{-- Slide-over editor --}}
<div data-slideover class="fixed inset-0 z-50 hidden" role="dialog" aria-modal="true" aria-labelledby="category-panel-title">
    <div data-slideover-backdrop class="absolute inset-0 bg-zinc-950/40 opacity-0 backdrop-blur-sm transition-opacity duration-300"></div>
    <div data-slideover-panel class="absolute inset-y-0 right-0 flex w-full max-w-md translate-x-full flex-col border-l border-zinc-200 bg-white shadow-2xl transition-transform duration-300 ease-out dark:border-zinc-800 dark:bg-zinc-900">
        <form method="POST" action="{{ route('desk-editor.categories.store') }}" data-category-form data-store-url="{{ route('desk-editor.categories.store') }}" class="flex h-full flex-col">
            @csrf
            <input type="hidden" name="_method" value="POST" data-method>
            <input type="hidden" name="_editing" value="{{ old('_editing') }}" data-editing>
            <div class="flex h-14 shrink-0 items-center justify-between border-b border-zinc-100 px-5 dark:border-zinc-800">
                <h2 id="category-panel-title" class="text-base font-semibold" data-panel-title data-new-title="{{ __('desk-editor::articles.categories.new') }}" data-edit-title="{{ __('desk-editor::articles.categories.edit') }}">{{ __('desk-editor::articles.categories.new') }}</h2>
                <button type="button" data-slideover-close class="grid size-9 cursor-pointer place-items-center rounded-lg border-0 bg-transparent text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-900 dark:hover:bg-zinc-800 dark:hover:text-white" aria-label="{{ __('desk-editor::articles.categories.cancel') }}">
                    <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="flex flex-1 flex-col gap-5 overflow-y-auto p-5">
                @if ($errors->any())
                    <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300" role="alert">
                        <ul class="list-disc space-y-0.5 pl-4">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif
                <div class="flex items-center gap-3 rounded-2xl bg-zinc-50 p-3 dark:bg-zinc-950">
                    <span data-preview-swatch class="grid size-11 shrink-0 place-items-center rounded-xl text-base font-semibold text-white shadow-sm" style="background: {{ old('color', '#6366f1') }}">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr(old('name', 'A'), 0, 1)) }}</span>
                    <div class="min-w-0">
                        <div class="truncate font-medium" data-preview-name>{{ old('name') ?: __('desk-editor::articles.categories.name') }}</div>
                        <div class="truncate font-mono text-xs text-zinc-500" data-preview-slug>/{{ old('slug') }}</div>
                    </div>
                </div>
                <label class="block">
                    <span class="{{ $label }}">{{ __('desk-editor::articles.categories.name') }}</span>
                    <input type="text" name="name" value="{{ old('name') }}" required maxlength="100" class="{{ $input }}" data-field="name">
                </label>
                <label class="block">
                    <span class="{{ $label }}">{{ __('desk-editor::articles.categories.slug') }}</span>
                    <input type="text" name="slug" value="{{ old('slug') }}" maxlength="120" pattern="[A-Za-z0-9_-]*" class="{{ $input }} font-mono" data-field="slug">
                    <span class="mt-1 block text-xs text-zinc-500 dark:text-zinc-400">{{ __('desk-editor::articles.categories.slug_hint') }}</span>
                </label>
                <label class="block">
                    <span class="{{ $label }}">{{ __('desk-editor::articles.categories.parent') }}</span>
                    <select name="parent_id" class="{{ $input }} cursor-pointer" data-field="parent_id">
                        <option value="">{{ __('desk-editor::articles.categories.no_parent') }}</option>
                        @foreach ($categories as ['category' => $option, 'depth' => $depth])
                            <option value="{{ $option->id }}" @selected((string) old('parent_id') === (string) $option->id)>{{ str_repeat('— ', $depth) }}{{ $option->name }}</option>
                        @endforeach
                    </select>
                </label>
                <fieldset>
                    <legend class="{{ $label }}">{{ __('desk-editor::articles.categories.color') }}</legend>
                    <div class="flex flex-wrap gap-2" data-swatches>
                        @foreach ($swatches as $swatch)
                            <label class="relative cursor-pointer">
                                <input type="radio" name="color" value="{{ $swatch }}" class="peer sr-only" @checked(old('color', '#6366f1') === $swatch) data-swatch>
                                <span class="block size-8 rounded-full ring-2 ring-transparent ring-offset-2 ring-offset-white transition peer-checked:scale-110 peer-checked:ring-zinc-900 peer-focus-visible:ring-indigo-500 hover:scale-110 dark:ring-offset-zinc-900 dark:peer-checked:ring-white" style="background: {{ $swatch }}" title="{{ $swatch }}"></span>
                                <span class="sr-only">{{ $swatch }}</span>
                            </label>
                        @endforeach
                        <label class="relative flex cursor-pointer items-center gap-2 rounded-full border border-dashed border-zinc-300 py-0.5 pr-3 pl-0.5 text-xs text-zinc-600 dark:border-zinc-600 dark:text-zinc-300" title="{{ __('desk-editor::articles.categories.custom_color') }}">
                            <input type="radio" name="color" value="{{ old('color', '#6366f1') }}" class="peer sr-only" data-swatch-custom @checked(old('color') && ! in_array(old('color'), $swatches, true))>
                            <input type="color" value="{{ old('color', '#6366f1') }}" class="size-7 cursor-pointer rounded-full border-0 bg-transparent p-0" data-color-picker aria-label="{{ __('desk-editor::articles.categories.custom_color') }}">
                            {{ __('desk-editor::articles.categories.custom_color') }}
                        </label>
                    </div>
                </fieldset>
                <label class="block">
                    <span class="{{ $label }}">{{ __('desk-editor::articles.categories.description') }}</span>
                    <textarea name="description" rows="3" maxlength="1000" class="{{ $input }} resize-y" data-field="description">{{ old('description') }}</textarea>
                </label>
            </div>
            <div class="flex shrink-0 justify-end gap-2 border-t border-zinc-100 px-5 py-4 dark:border-zinc-800">
                <button type="button" data-slideover-close class="inline-flex h-9 cursor-pointer items-center rounded-lg border border-zinc-200 bg-white px-3.5 text-sm font-medium text-zinc-800 shadow-xs transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:hover:bg-zinc-800">{{ __('desk-editor::articles.categories.cancel') }}</button>
                <button type="submit" class="inline-flex h-9 cursor-pointer items-center rounded-lg border-0 bg-indigo-600 px-3.5 text-sm font-medium text-white shadow-sm shadow-indigo-600/25 transition hover:bg-indigo-500 active:scale-[.98]">{{ __('desk-editor::articles.categories.save') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('desk-scripts')
<script>
(() => {
    const categories = {{ \Illuminate\Support\Js::from($payload) }};
    const reopen = {{ \Illuminate\Support\Js::from($reopen ?? $editing) }};
    const over = document.querySelector('[data-slideover]');
    const panel = over.querySelector('[data-slideover-panel]');
    const backdrop = over.querySelector('[data-slideover-backdrop]');
    const form = over.querySelector('[data-category-form]');
    const field = (name) => form.querySelector(`[data-field="${name}"]`);
    const title = form.querySelector('[data-panel-title]');
    const swatch = form.querySelector('[data-preview-swatch]');
    const picker = form.querySelector('[data-color-picker]');
    const custom = form.querySelector('[data-swatch-custom]');
    let lastFocus = null;

    const preview = () => {
        const name = field('name').value.trim();
        const color = form.querySelector('[name=color]:checked')?.value || '#a1a1aa';
        swatch.style.background = color;
        swatch.textContent = (name[0] || 'A').toUpperCase();
        form.querySelector('[data-preview-name]').textContent = name || field('name').closest('label').querySelector('span').textContent;
        form.querySelector('[data-preview-slug]').textContent = '/' + (field('slug').value || name.toLowerCase().normalize('NFKD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, ''));
    };
    const setColor = (color) => {
        const preset = form.querySelector(`[data-swatch][value="${color}"]`);
        if (preset) preset.checked = true;
        else if (color) { custom.value = color; picker.value = color; custom.checked = true; }
        preview();
    };
    const open = (id, keepValues = false) => {
        const category = id && id !== 'new' ? categories[id] : null;
        form.action = category ? category.update_url : form.dataset.storeUrl;
        form.querySelector('[data-method]').value = category ? 'PUT' : 'POST';
        form.querySelector('[data-editing]').value = category ? category.id : 'new';
        title.textContent = category ? title.dataset.editTitle : title.dataset.newTitle;
        if (!keepValues) {
            field('name').value = category?.name || '';
            field('slug').value = category?.slug || '';
            field('description').value = category?.description || '';
            field('parent_id').value = category?.parent_id || '';
            setColor(category?.color || '#6366f1');
        }
        [...field('parent_id').options].forEach((o) => { o.disabled = !!category && o.value === String(category.id); });
        preview();
        lastFocus = document.activeElement;
        over.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
        requestAnimationFrame(() => { panel.classList.remove('translate-x-full'); backdrop.classList.remove('opacity-0'); field('name').focus(); });
    };
    const close = () => {
        panel.classList.add('translate-x-full');
        backdrop.classList.add('opacity-0');
        document.body.classList.remove('overflow-hidden');
        setTimeout(() => over.classList.add('hidden'), 300);
        lastFocus?.focus?.();
    };
    document.querySelectorAll('[data-category-new]').forEach((b) => b.addEventListener('click', () => open('new')));
    document.querySelectorAll('[data-category-edit]').forEach((b) => b.addEventListener('click', () => open(b.dataset.categoryEdit)));
    over.querySelectorAll('[data-slideover-close]').forEach((b) => b.addEventListener('click', close));
    backdrop.addEventListener('click', close);
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !over.classList.contains('hidden')) close(); });
    form.addEventListener('input', preview);
    form.addEventListener('change', preview);
    picker.addEventListener('input', () => { custom.value = picker.value; custom.checked = true; preview(); });
    if (reopen) open(String(reopen), {{ $errors->any() ? 'true' : 'false' }});

    document.querySelectorAll('form[data-confirm]').forEach((f) => f.addEventListener('submit', (e) => { if (!confirm(f.dataset.confirm)) e.preventDefault(); }));

    /* ---------- drag to reorder (mouse + keyboard) ---------- */
    const list = document.querySelector('[data-category-list]');
    if (!list) return;
    const status = document.querySelector('[data-reorder-status]');
    let dragged = null;
    let timer;
    const save = () => {
        clearTimeout(timer);
        timer = setTimeout(async () => {
            const order = [...list.querySelectorAll('[data-category-row]')].map((row) => row.dataset.id);
            try {
                const response = await fetch(list.dataset.reorderUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ order }),
                });
                const data = await response.json();
                status.textContent = data.message || '';
                status.classList.remove('hidden');
                setTimeout(() => status.classList.add('hidden'), 2000);
            } catch {}
        }, 250);
    };
    list.addEventListener('dragstart', (e) => {
        dragged = e.target.closest('[data-category-row]');
        if (!dragged) return;
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', dragged.dataset.id);
        requestAnimationFrame(() => dragged.classList.add('opacity-40'));
    });
    list.addEventListener('dragover', (e) => {
        if (!dragged) return;
        e.preventDefault();
        const row = e.target.closest('[data-category-row]');
        if (!row || row === dragged) return;
        const rect = row.getBoundingClientRect();
        row.parentNode.insertBefore(dragged, e.clientY > rect.top + rect.height / 2 ? row.nextSibling : row);
    });
    list.addEventListener('dragend', () => {
        dragged?.classList.remove('opacity-40');
        dragged = null;
        save();
    });
    list.addEventListener('keydown', (e) => {
        const handle = e.target.closest('[data-drag-handle]');
        if (!handle || !['ArrowUp', 'ArrowDown'].includes(e.key)) return;
        e.preventDefault();
        const row = handle.closest('[data-category-row]');
        const sibling = e.key === 'ArrowUp' ? row.previousElementSibling : row.nextElementSibling;
        if (!sibling) return;
        list.insertBefore(row, e.key === 'ArrowUp' ? sibling : sibling.nextSibling);
        handle.focus();
        save();
    });
})();
</script>
@endpush
