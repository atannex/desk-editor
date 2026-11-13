<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Tests\Feature\Articles;

use Atannex\DeskEditor\Tests\TestCase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

class LayoutTest extends TestCase
{
    public function test_primary_navigation_with_active_state(): void
    {
        $this->actingAs($this->user());

        $response = $this->get('/desk/articles')->assertOk()
            ->assertSee('data-desk-nav', false)
            ->assertSee(route('desk-editor.categories.index'))
            ->assertSee(route('desk-editor.tags.index'))
            ->assertSee('data-nav-open', false)
            ->assertSee('data-nav-panel', false)
            ->assertSee('data-theme-toggle', false);

        $this->assertMatchesRegularExpression('~href="'.preg_quote(route('desk-editor.articles.index'), '~').'"\s+aria-current="page"~', $response->getContent());

        $categories = $this->get('/desk/categories')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('~href="'.preg_quote(route('desk-editor.categories.index'), '~').'"\s+aria-current="page"~', $categories);
    }

    public function test_optional_modules_appear_when_their_routes_exist(): void
    {
        $this->actingAs($this->user());
        $content = $this->get('/desk/articles')->getContent();

        foreach (['desk-editor.media.manage', 'desk-editor.comments.index', 'desk-editor.analytics', 'desk-editor.blog.home'] as $name) {
            if (Route::has($name)) {
                $this->assertStringContainsString(route($name), $content, $name);
            }
        }
    }

    public function test_nav_hides_taxonomy_without_permission(): void
    {
        Gate::define('desk-articles.manage-taxonomy', fn () => false);

        $this->actingAs($this->user())->get('/desk/articles')->assertOk()
            ->assertDontSee(route('desk-editor.tags.index'));
    }

    public function test_layout_keeps_sections_and_stacks_for_other_modules(): void
    {
        $this->actingAs($this->user());
        $view = view('desk-editor::articles.layout');
        $html = $view->render();

        $this->assertStringContainsString('data-theme', $html);
        $this->assertStringContainsString('localStorage.getItem(\'desk-editor-theme\')', $html);
        $this->assertStringContainsString('id="desk-main"', $html);
    }

    public function test_flash_toast(): void
    {
        $this->actingAs($this->user())->withSession(['desk-status' => 'All good'])->get('/desk/articles')
            ->assertSee('data-flash', false)->assertSee('All good');
    }
}
