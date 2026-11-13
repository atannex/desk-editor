<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\View\Components;

use Atannex\DeskEditor\DeskEditorManager;
use Atannex\DeskEditor\Support\ContentRenderer;
use Atannex\DeskEditor\Support\DeskContent;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Renders stored content for readers with the editor's typography:
 * <x-desk-editor::content :html="$post->body" />
 */
class Content extends Component
{
    public string $body;

    public function __construct(
        DeskContent|string|null $html = null,
        public bool $sanitize = true,
        public ?string $theme = null,
        public string $as = 'article',
        public bool $assets = true,
    ) {
        $body = match (true) {
            $html instanceof DeskContent => $html->html(),
            $sanitize => app(DeskEditorManager::class)->sanitize($html),
            default => (string) $html,
        };

        $this->body = app(ContentRenderer::class)->render($body);
    }

    public function shouldRenderAssets(): bool
    {
        if (! $this->assets || Editor::$assetsRendered || ! config('desk-editor.inject_assets', true)) {
            return false;
        }

        return Editor::$assetsRendered = true;
    }

    public function render(): View
    {
        return view('desk-editor::components.content');
    }
}
