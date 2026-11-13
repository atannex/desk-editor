@php
    $errorKey = $errorKey();
    $errorBag = isset($errors) ? $errors : new \Illuminate\Support\ViewErrorBag;
    $hasError = $errorKey && $errorBag->has($errorKey);
@endphp
@if ($shouldRenderAssets())
    @deskEditorAssets
@endif
<div
    {{ $attributes->whereDoesntStartWith('wire:model')->class(['desk-editor', 'is-invalid' => $hasError]) }}
    data-desk-editor="{{ json_encode($clientConfig(), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}"
    @if ($attributes->whereStartsWith('wire:model')->isNotEmpty()) wire:ignore @endif
    @if ($hasError) aria-invalid="true" @endif
>
    <textarea
        id="{{ $inputId }}"
        @if ($name) name="{{ $name }}" @endif
        data-desk-input
        hidden
        {{ $attributes->whereStartsWith('wire:model') }}
    >{{ $content() }}</textarea>
</div>
@if ($hasError)
    <p class="de-error" id="{{ $inputId }}-error" role="alert">{{ $errorBag->first($errorKey) }}</p>
@endif
