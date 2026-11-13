@if ($shouldRenderAssets())
    @deskEditorAssets
@endif
<{{ $as }} {{ $attributes->class(['desk-content de-prose']) }} data-desk-content @if ($theme) data-theme="{{ $theme }}" @endif>{!! $body !!}</{{ $as }}>
