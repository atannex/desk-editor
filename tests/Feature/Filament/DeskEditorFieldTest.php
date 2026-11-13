<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Tests\Feature\Filament;

use Atannex\DeskEditor\Filament\DeskEditor;
use Atannex\DeskEditor\Tests\TestCase;
use Filament\Forms\Components\Field;
use Filament\Schemas\Schema;

class DeskEditorFieldTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(Field::class)) {
            $this->markTestSkipped('filament/forms is not installed.');
        }
    }

    public function test_it_builds_client_config_from_fluent_options(): void
    {
        $field = DeskEditor::make('body')
            ->toolbar('minimal')
            ->placeholder('Write…')
            ->maxWords(500)
            ->document('post:9')
            ->editorOptions(['headings' => [2, 3]]);
        Schema::make()->components([$field])->getComponents();

        $config = $field->getEditorConfig();

        $this->assertSame(['bold', 'italic', 'underline', '|', 'bulletList', 'orderedList', '|', 'link'], $config['toolbar']);
        $this->assertSame('Write…', $config['placeholder']);
        $this->assertSame(500, $config['limit']['words']);
        $this->assertSame('post:9', $config['documentKey']);
        $this->assertTrue($config['commentsEnabled']);
        $this->assertSame([2, 3], $config['headings']);
        $this->assertFalse($config['autosave']['enabled']);
        $this->assertArrayNotHasKey('readonly', array_filter($config, fn ($v) => $v !== null && $v !== false));

        $field->disabled();
        $this->assertTrue($field->getEditorConfig()['readonly']);
    }

    public function test_state_is_sanitised_on_dehydrate(): void
    {
        $field = DeskEditor::make('body');
        Schema::make()->components([$field])->getComponents();

        $this->assertTrue($field->shouldSanitize());
        $this->assertFalse(DeskEditor::make('body')->sanitize(false)->shouldSanitize());
    }
}
