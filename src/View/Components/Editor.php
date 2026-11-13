<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\View\Components;

use Atannex\DeskEditor\DeskEditorManager;
use Atannex\DeskEditor\Support\DeskContent;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\View\Component;

/**
 * <x-desk-editor name="body" :value="$post->body" toolbar="standard" />
 */
class Editor extends Component
{
    public static bool $assetsRendered = false;

    public string $inputId;

    /**
     * @param  string|array<int, string>|null  $toolbar
     * @param  bool|string|array{key?: string, server?: bool}|null  $autosave
     * @param  array<string, bool>|null  $features
     * @param  array<string, mixed>  $options  any other client option, merged last
     */
    public function __construct(
        public ?string $name = null,
        public mixed $value = null,
        ?string $id = null,
        public ?string $label = null,
        public string|array|null $toolbar = null,
        public ?string $format = null,
        public ?string $placeholder = null,
        public ?string $theme = null,
        public ?string $accent = null,
        public ?string $minHeight = null,
        public ?string $maxHeight = null,
        public bool $readonly = false,
        public bool $autofocus = false,
        public ?int $maxCharacters = null,
        public ?int $maxWords = null,
        public bool|string|array|null $autosave = null,
        public ?bool $uploads = null,
        public ?bool $mentions = null,
        public ?array $features = null,
        public ?string $comments = null,
        public ?string $collaboration = null,
        public array $options = [],
        public bool $assets = true,
    ) {
        $this->inputId = $id ?? 'desk-'.($name ? Str::slug(str_replace(['[', ']', '.'], '-', $name)) : Str::random(8));
    }

    public function errorKey(): ?string
    {
        return $this->name === null ? null : trim(str_replace(['[', ']'], ['.', ''], $this->name), '.');
    }

    public function content(): string
    {
        $old = $this->name ? old($this->errorKey()) : null;
        $value = $old ?? $this->value;

        if ($value instanceof DeskContent) {
            return $value->html();
        }

        if (is_array($value)) {
            return (string) json_encode($value);
        }

        return (string) $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function clientConfig(): array
    {
        $manager = app(DeskEditorManager::class);

        $overrides = [
            'input' => $this->inputId,
            'label' => $this->label,
            'toolbar' => $this->toolbar,
            'format' => $this->format,
            'placeholder' => $this->placeholder,
            'theme' => $this->theme,
            'accent' => $this->accent,
            'minHeight' => $this->minHeight,
            'maxHeight' => $this->maxHeight,
            'readonly' => $this->readonly ?: null,
            'autofocus' => $this->autofocus ?: null,
            'features' => $this->features,
            'limit' => array_filter(['characters' => $this->maxCharacters, 'words' => $this->maxWords]) ?: null,
            'csrf' => csrf_token(),
        ];

        if ($this->autosave !== null) {
            $overrides['autosave'] = is_array($this->autosave)
                ? ['enabled' => true, 'key' => $this->autosave['key'] ?? null, 'server' => (bool) ($this->autosave['server'] ?? false)]
                : ['enabled' => $this->autosave !== false, 'key' => is_string($this->autosave) ? $this->autosave : null];
        }

        $overrides['documentKey'] = $this->comments ?? $this->collaboration;
        $overrides['commentsEnabled'] = $this->comments !== null && (bool) config('desk-editor.comments.enabled', true);
        $overrides['collaborationEnabled'] = $this->collaboration !== null && (bool) config('desk-editor.collaboration.enabled', false);

        $config = $manager->clientConfig($overrides);

        if ($this->uploads === false) {
            $config['upload'] = ['url' => null];
        }
        if ($this->mentions === false) {
            $config['mentions'] = ['url' => null];
        }

        return DeskEditorManager::merge($config, $this->options);
    }

    public function shouldRenderAssets(): bool
    {
        if (! $this->assets || static::$assetsRendered || ! config('desk-editor.inject_assets', true)) {
            return false;
        }

        return static::$assetsRendered = true;
    }

    public function render(): View
    {
        return view('desk-editor::components.editor');
    }
}
