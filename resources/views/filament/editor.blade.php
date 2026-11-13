<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    @once
        @deskEditorAssets
    @endonce
    <div
        wire:ignore
        x-data="{
            state: $wire.$entangle(@js($getStatePath())),
            init() {
                const root = this.$refs.desk;
                root.addEventListener('desk-editor:change', (event) => {
                    this.state = event.detail.value;
                });
                this.$watch('state', (value) => {
                    const desk = window.DeskEditor?.get(root);
                    if (desk && value !== desk.getValue()) desk.setContent(value ?? '', { emit: false });
                });
            },
        }"
        {{ $attributes->merge($getExtraAttributes(), escape: false)->class(['fi-fo-desk-editor']) }}
    >
        <div x-ref="desk" class="desk-editor" data-desk-editor="{{ json_encode($getEditorConfig(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}">
            <textarea data-desk-input hidden id="{{ $getId() }}">{{ $getState() }}</textarea>
        </div>
    </div>
</x-dynamic-component>
