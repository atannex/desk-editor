<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Tests\Feature\Articles;

use Atannex\DeskEditor\Articles\Policies\DeskArticlePolicy;
use Atannex\DeskEditor\Enums\ArticleStatus;
use Atannex\DeskEditor\Models\DeskArticle;
use Atannex\DeskEditor\Tests\TestCase;
use Illuminate\Support\Facades\Gate;

class PermissionsTest extends TestCase
{
    public function test_default_gates_allow_any_signed_in_user(): void
    {
        foreach (config('desk-editor.articles.abilities') as $ability) {
            $this->assertTrue(Gate::has('desk-articles.'.$ability), $ability);
        }

        $user = $this->user();
        $policy = app(DeskArticlePolicy::class);
        $this->assertTrue($policy->allows('publish', null, $user));
        $this->assertFalse($policy->allows('publish'));
    }

    public function test_view_ability_guards_the_index(): void
    {
        Gate::define('desk-articles.view', fn () => false);

        $this->actingAs($this->user())->get('/desk/articles')->assertForbidden();
    }

    public function test_create_ability_guards_creating(): void
    {
        Gate::define('desk-articles.create', fn () => false);
        $this->actingAs($this->user());

        $this->get('/desk/articles/create')->assertForbidden();
        $this->post('/desk/articles', ['title' => 'Nope', 'status' => 'draft'])->assertForbidden();
        $this->get('/desk/articles')->assertOk()->assertDontSee(route('desk-editor.articles.create'));
    }

    public function test_update_ability_receives_the_article(): void
    {
        $mine = DeskArticle::create(['title' => 'Mine', 'status' => ArticleStatus::Draft]);
        $theirs = DeskArticle::create(['title' => 'Theirs', 'status' => ArticleStatus::Draft]);
        Gate::define('desk-articles.update', fn ($user, $article = null) => $article === null || $article->is($mine));
        $this->actingAs($this->user());

        $this->put(route('desk-editor.articles.update', $mine), ['title' => 'Mine!', 'status' => 'draft'])->assertRedirect();
        $this->put(route('desk-editor.articles.update', $theirs), ['title' => 'Hijack', 'status' => 'draft'])->assertForbidden();

        // Read-only form: no save buttons.
        $this->get(route('desk-editor.articles.edit', $theirs))->assertOk()
            ->assertSee(__('desk-editor::articles.read_only'))
            ->assertDontSee('name="intent" value="save"', false);
    }

    public function test_delete_ability_guards_trash_and_bulk(): void
    {
        Gate::define('desk-articles.delete', fn () => false);
        $article = DeskArticle::create(['title' => 'Keep', 'status' => ArticleStatus::Draft]);
        $this->actingAs($this->user());

        $this->delete(route('desk-editor.articles.destroy', $article))->assertForbidden();
        $this->post(route('desk-editor.articles.bulk'), ['action' => 'trash', 'ids' => [$article->id]])->assertForbidden();
        $this->assertNotSoftDeleted($article);
    }

    public function test_publish_ability_guards_bulk_publish(): void
    {
        Gate::define('desk-articles.publish', fn () => false);
        $article = DeskArticle::create(['title' => 'Wait', 'status' => ArticleStatus::Draft]);
        $this->actingAs($this->user());

        $this->post(route('desk-editor.articles.bulk'), ['action' => 'publish', 'ids' => [$article->id]])->assertForbidden();
        $this->get('/desk/articles')->assertOk()->assertDontSee('value="publish"', false);
    }

    public function test_taxonomy_ability_guards_categories_and_tags(): void
    {
        Gate::define('desk-articles.manage-taxonomy', fn () => false);
        $this->actingAs($this->user());

        $this->get(route('desk-editor.categories.index'))->assertForbidden();
        $this->get(route('desk-editor.tags.index'))->assertForbidden();
        $this->post(route('desk-editor.categories.store'), ['name' => 'X'])->assertForbidden();
        $this->get('/desk/articles')->assertDontSee(route('desk-editor.categories.index'));
    }

    public function test_host_defined_gates_are_not_overridden(): void
    {
        $this->app['config']->set('desk-editor.articles.abilities', ['view']);
        Gate::define('desk-articles.view', fn () => false);
        app(DeskArticlePolicy::class)->defineDefaultGates();

        $this->assertFalse(Gate::forUser($this->user())->allows('desk-articles.view'));
    }

    public function test_legacy_manage_gate_still_protects_every_page(): void
    {
        Gate::define('manage-desk-articles', fn () => false);
        $this->actingAs($this->user());

        $this->get('/desk/articles')->assertForbidden();
        $this->get('/desk/categories')->assertForbidden();
        $this->get('/desk/tags')->assertForbidden();
    }
}
