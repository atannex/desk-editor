<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Tests\Feature;

use Atannex\DeskEditor\Tests\TestCase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

class ComponentsTest extends TestCase
{
    public function test_editor_renders_input_config_and_assets_once(): void
    {
        $html = Blade::render('<x-desk-editor name="body" value="<p>Hello</p>" toolbar="minimal" :max-words="500" class="mb-4" /><x-desk-editor name="other" />');

        $this->assertSame(1, substr_count($html, 'data-desk-editor-styles'));
        $this->assertStringContainsString('name="body"', $html);
        $this->assertStringContainsString('id="desk-body"', $html);
        $this->assertStringContainsString('&lt;p&gt;Hello&lt;/p&gt;', $html);
        $this->assertStringContainsString('class="desk-editor mb-4"', $html);

        preg_match('/data-desk-editor="([^"]+)"/', $html, $m);
        $config = json_decode(html_entity_decode($m[1]), true);

        $this->assertSame(['bold', 'italic', 'underline', '|', 'bulletList', 'orderedList', '|', 'link'], $config['toolbar']);
        $this->assertSame(500, $config['limit']['words']);
        $this->assertSame('desk-body', $config['input']);
        $this->assertStringEndsWith('/desk-editor/upload', $config['upload']['url']);
        $this->assertNull($config['mentions']['url']);
        $this->assertSame('Bold', $config['strings']['bold']);
    }

    public function test_livewire_model_is_bound_to_the_hidden_input(): void
    {
        $html = Blade::render('<x-desk-editor wire:model.live="content" />');

        $this->assertMatchesRegularExpression('/<textarea[^>]*wire:model\.live="content"/s', $html);
        $this->assertStringContainsString('wire:ignore', $html);
    }

    public function test_validation_errors_are_shown(): void
    {
        $errors = (new ViewErrorBag)->put('default', new MessageBag(['body' => ['Body is required.']]));
        view()->share('errors', $errors);

        $html = Blade::render('<x-desk-editor name="body" />');

        $this->assertStringContainsString('is-invalid', $html);
        $this->assertStringContainsString('Body is required.', $html);
    }

    public function test_content_component_sanitises_by_default(): void
    {
        $html = Blade::render('<x-desk-editor::content :html="$html" />', ['html' => '<p>Safe</p><script>alert(1)</script>']);

        $this->assertStringContainsString('<article class="desk-content de-prose" data-desk-content', $html);
        $this->assertStringContainsString('<p>Safe</p>', $html);
        $this->assertStringNotContainsString('<script>alert', $html);
    }

    public function test_blade_directives(): void
    {
        $html = Blade::render('@deskEditorAssets');

        $this->assertStringContainsString('/desk-editor/assets/desk-editor.css?v=', $html);
        $this->assertStringContainsString('/desk-editor/assets/desk-editor.js?v=', $html);
    }
}
