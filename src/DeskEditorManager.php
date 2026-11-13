<?php

declare(strict_types=1);

namespace Atannex\DeskEditor;

use Atannex\DeskEditor\Collaboration\CollaborationServiceProvider;
use Atannex\DeskEditor\Contracts\MentionResolver;
use Atannex\DeskEditor\Support\DeskContent;
use Atannex\DeskEditor\Support\Sanitizer\Sanitizer;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class DeskEditorManager
{
    private ?Sanitizer $sanitizer = null;

    public function __construct(private readonly Repository $config) {}

    /**
     * Clean untrusted HTML down to what the editor can produce.
     */
    public function sanitize(?string $html): string
    {
        return ($this->sanitizer ??= new Sanitizer((array) $this->config->get('desk-editor.sanitizer', [])))->sanitize($html);
    }

    /**
     * Wrap HTML as DeskContent, sanitising it unless told otherwise.
     */
    public function content(?string $html, bool $sanitize = true): DeskContent
    {
        return DeskContent::make($sanitize ? $this->sanitize($html) : (string) $html);
    }

    /**
     * Build the client configuration for one editor instance.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public function clientConfig(array $overrides = []): array
    {
        $c = fn (string $key, mixed $default = null) => $this->config->get("desk-editor.{$key}", $default);

        $features = collect((array) $c('features', []))
            ->mapWithKeys(fn ($value, $key) => [Str::camel((string) $key) => (bool) $value])
            ->all();

        $config = [
            'format' => $c('format', 'html'),
            'dir' => in_array(substr(app()->getLocale(), 0, 2), ['ar', 'he', 'fa', 'ur', 'ps', 'yi'], true) ? 'rtl' : null,
            'currentUser' => ($user = auth()->user()) ? CollaborationServiceProvider::member($user) : null,
            'theme' => $c('theme', 'auto'),
            'accent' => $c('accent'),
            'toolbar' => $this->toolbar($c('toolbar', 'full')),
            'features' => $features,
            'headings' => array_values((array) $c('headings', [1, 2, 3, 4, 5, 6])),
            'minHeight' => $c('min_height'),
            'maxHeight' => $c('max_height'),
            'spellcheck' => (bool) $c('spellcheck', true),
            'limit' => [
                'characters' => $c('limit.characters'),
                'words' => $c('limit.words'),
            ],
            'autosave' => [
                'enabled' => (bool) $c('autosave.enabled', false),
                'debounce' => (int) $c('autosave.debounce', 800),
            ],
            'upload' => $this->uploadsEnabled() ? [
                'url' => route('desk-editor.upload'),
                'maxSize' => (int) $c('uploads.max_size', 10240),
                'maxMediaSize' => (int) $c('uploads.max_media_size', 204800),
                'images' => array_values((array) $c('uploads.images', [])),
                'videos' => array_values((array) $c('uploads.videos', [])),
                'audio' => array_values((array) $c('uploads.audio', [])),
                'files' => array_values((array) $c('uploads.files', [])),
            ] : ['url' => null],
            'mentions' => $this->mentionsEnabled() ? [
                'url' => route('desk-editor.mentions'),
                'trigger' => $c('mentions.trigger', '@'),
            ] : ['url' => null],
            'endpoints' => array_filter([
                'drafts' => Route::has('desk-editor.drafts.show') ? url(trim((string) $c('route_prefix', 'desk-editor'), '/').'/drafts') : null,
                'threads' => Route::has('desk-editor.threads.index') ? route('desk-editor.threads.index') : null,
                'unfurl' => Route::has('desk-editor.unfurl') ? route('desk-editor.unfurl') : null,
                'media' => Route::has('desk-editor.media.index') ? route('desk-editor.media.index') : null,
            ]),
            'drafts' => ['interval' => (int) $c('drafts.interval', 4000)],
            'suggestions' => (bool) $c('suggestions.enabled', true),
            'collaboration' => $this->collaborationConfig(),
            'link' => [
                'newTab' => (bool) $c('links.new_tab', true),
                'autolink' => (bool) $c('links.autolink', true),
            ],
            'fonts' => array_values((array) $c('fonts', [])),
            'fontSizes' => array_values((array) $c('font_sizes', [])),
            'colors' => array_values((array) $c('colors', [])),
            'highlights' => array_values((array) $c('highlights', [])),
            'strings' => $this->strings(),
        ];

        if (isset($overrides['toolbar'])) {
            $overrides['toolbar'] = $this->toolbar($overrides['toolbar']);
        }

        return static::merge($config, array_filter($overrides, fn ($value) => $value !== null));
    }

    /**
     * Deep-merge associative arrays while replacing lists (toolbars, fonts, …) wholesale.
     *
     * @param  array<array-key, mixed>  $base
     * @param  array<array-key, mixed>  $overrides
     * @return array<array-key, mixed>
     */
    public static function merge(array $base, array $overrides): array
    {
        foreach ($overrides as $key => $value) {
            $base[$key] = is_array($value) && ! array_is_list($value) && isset($base[$key]) && is_array($base[$key]) && ! array_is_list($base[$key])
                ? static::merge($base[$key], $value)
                : $value;
        }

        return $base;
    }

    /**
     * Resolve a toolbar preset name (or pass an explicit list through).
     *
     * @param  string|array<int, string>  $toolbar
     * @return list<string>
     */
    public function toolbar(string|array $toolbar): array
    {
        if (is_array($toolbar)) {
            return array_values($toolbar);
        }

        $presets = (array) $this->config->get('desk-editor.toolbars', []);

        if (str_contains($toolbar, ',')) {
            return array_map('trim', explode(',', $toolbar));
        }

        return array_values($presets[$toolbar] ?? $presets['full'] ?? []);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function collaborationConfig(): ?array
    {
        if (! $this->config->get('desk-editor.collaboration.enabled')) {
            return null;
        }

        $user = auth()->user();

        return [
            'channelPrefix' => (string) $this->config->get('desk-editor.collaboration.channel_prefix', 'desk.document'),
            'broadcaster' => (array) $this->config->get('desk-editor.collaboration.broadcaster', []),
            'authEndpoint' => url('/broadcasting/auth'),
            'user' => $user ? CollaborationServiceProvider::member($user) : null,
        ];
    }

    public function uploadsEnabled(): bool
    {
        return (bool) $this->config->get('desk-editor.uploads.enabled', true);
    }

    public function mentionsEnabled(): bool
    {
        $resolver = $this->config->get('desk-editor.mentions.resolver');

        return is_string($resolver) && is_subclass_of($resolver, MentionResolver::class);
    }

    /**
     * Interface strings translated through Laravel's translator.
     *
     * @return array<string, string>
     */
    public function strings(): array
    {
        $strings = trans('desk-editor::editor');

        return is_array($strings) ? Arr::map($strings, fn ($value) => (string) $value) : [];
    }

    public function styles(): HtmlString
    {
        return new HtmlString(sprintf('<link rel="stylesheet" href="%s" data-desk-editor-styles>', e($this->asset('desk-editor.css'))));
    }

    public function scripts(): HtmlString
    {
        return new HtmlString(sprintf('<script type="module" src="%s" data-desk-editor-scripts></script>', e($this->asset('desk-editor.js'))));
    }

    public function assets(): HtmlString
    {
        return new HtmlString($this->styles()->toHtml()."\n".$this->scripts()->toHtml());
    }

    public function asset(string $file): string
    {
        if ($base = $this->config->get('desk-editor.asset_url')) {
            return rtrim((string) $base, '/').'/'.$file;
        }

        $published = public_path("vendor/desk-editor/{$file}");
        $source = __DIR__."/../dist/{$file}";

        if (is_file($published)) {
            return asset("vendor/desk-editor/{$file}").'?v='.substr((string) md5_file($published), 0, 10);
        }

        $version = is_file($source) ? substr((string) md5_file($source), 0, 10) : 'dev';

        return route('desk-editor.asset', ['path' => $file]).'?v='.$version;
    }
}
