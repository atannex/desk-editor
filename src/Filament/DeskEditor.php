<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Filament;

use Atannex\DeskEditor\DeskEditorManager;
use Atannex\DeskEditor\Support\DeskContent;
use Closure;
use Filament\Forms\Components\Field;

/**
 * Filament form field: DeskEditor::make('body')->toolbar('standard')->maxWords(2000)
 *
 * Requires filament/forms ^4.0 (or ^3.2). The value is sanitised HTML.
 */
class DeskEditor extends Field
{
    protected string $view = 'desk-editor::filament.editor';

    /** @var string|array<int, string>|Closure|null */
    protected string|array|Closure|null $toolbar = null;

    protected string|Closure|null $placeholderText = null;

    protected int|Closure|null $maxCharacters = null;

    protected int|Closure|null $maxWords = null;

    protected string|Closure|null $minHeight = null;

    protected string|Closure|null $documentKey = null;

    protected bool|Closure $sanitize = true;

    /** @var array<string, mixed>|Closure */
    protected array|Closure $editorOptions = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->default('');

        $this->afterStateHydrated(function (DeskEditor $component, mixed $state): void {
            if ($state instanceof DeskContent) {
                $component->state($state->html());
            }
        });

        $this->dehydrateStateUsing(fn (DeskEditor $component, ?string $state): string => $component->shouldSanitize()
            ? app(DeskEditorManager::class)->sanitize($state)
            : (string) $state);
    }

    /**
     * @param  string|array<int, string>|Closure|null  $toolbar  preset name, comma list or array
     */
    public function toolbar(string|array|Closure|null $toolbar): static
    {
        $this->toolbar = $toolbar;

        return $this;
    }

    public function placeholder(string|Closure|null $placeholder): static
    {
        $this->placeholderText = $placeholder;

        return $this;
    }

    public function maxCharacters(int|Closure|null $characters): static
    {
        $this->maxCharacters = $characters;

        return $this;
    }

    public function maxWords(int|Closure|null $words): static
    {
        $this->maxWords = $words;

        return $this;
    }

    public function minHeight(string|Closure|null $height): static
    {
        $this->minHeight = $height;

        return $this;
    }

    /**
     * Enable comment threads / collaboration for this document key, e.g. "post:12".
     */
    public function document(string|Closure|null $key): static
    {
        $this->documentKey = $key;

        return $this;
    }

    public function sanitize(bool|Closure $condition = true): static
    {
        $this->sanitize = $condition;

        return $this;
    }

    /**
     * @param  array<string, mixed>|Closure  $options  any client option (see config/desk-editor.php)
     */
    public function editorOptions(array|Closure $options): static
    {
        $this->editorOptions = $options;

        return $this;
    }

    public function shouldSanitize(): bool
    {
        return (bool) $this->evaluate($this->sanitize);
    }

    /**
     * @return array<string, mixed>
     */
    public function getEditorConfig(): array
    {
        $document = $this->evaluate($this->documentKey);
        $limit = array_filter(['characters' => $this->evaluate($this->maxCharacters), 'words' => $this->evaluate($this->maxWords)]);

        return DeskEditorManager::merge(app(DeskEditorManager::class)->clientConfig(array_filter([
            'toolbar' => $this->evaluate($this->toolbar),
            'placeholder' => $this->evaluate($this->placeholderText),
            'minHeight' => $this->evaluate($this->minHeight),
            'readonly' => $this->isDisabled() ?: null,
            'limit' => $limit ?: null,
            'label' => $this->getLabel() ? (string) $this->getLabel() : null,
            'documentKey' => $document,
            'commentsEnabled' => $document !== null && config('desk-editor.comments.enabled', true),
            'csrf' => csrf_token(),
            'autosave' => ['enabled' => false],
            'features' => ['stickyToolbar' => false],
        ], fn ($value) => $value !== null)), (array) $this->evaluate($this->editorOptions));
    }
}
