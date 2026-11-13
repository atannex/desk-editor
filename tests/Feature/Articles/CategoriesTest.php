<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Tests\Feature\Articles;

use Atannex\DeskEditor\Enums\ArticleStatus;
use Atannex\DeskEditor\Models\DeskArticle;
use Atannex\DeskEditor\Models\DeskCategory;
use Atannex\DeskEditor\Tests\TestCase;

class CategoriesTest extends TestCase
{
    public function test_index_lists_categories_with_counts(): void
    {
        $news = DeskCategory::create(['name' => 'News', 'color' => '#ef4444']);
        DeskCategory::create(['name' => 'Local', 'parent_id' => $news->id]);
        DeskArticle::create(['title' => 'One', 'status' => ArticleStatus::Draft, 'category_id' => $news->id]);
        DeskArticle::create(['title' => 'Two', 'status' => ArticleStatus::Draft, 'category_id' => $news->id]);

        $this->actingAs($this->user())
            ->get(route('desk-editor.categories.index'))
            ->assertOk()
            ->assertSee('News')
            ->assertSee('Local')
            ->assertSee('2 articles')
            ->assertSee('background: #ef4444', false)
            ->assertSee('data-category-list', false);
    }

    public function test_create_update_and_validate(): void
    {
        $this->actingAs($this->user());

        $this->post(route('desk-editor.categories.store'), ['name' => 'Guides', 'color' => '#10b981', 'description' => 'How-tos'])
            ->assertRedirect(route('desk-editor.categories.index'));
        $guides = DeskCategory::where('name', 'Guides')->firstOrFail();
        $this->assertSame('guides', $guides->slug);
        $this->assertSame('#10b981', $guides->color);

        $parent = DeskCategory::create(['name' => 'Docs']);
        $this->put(route('desk-editor.categories.update', $guides), ['name' => 'Tutorials', 'color' => '#3b82f6', 'parent_id' => $parent->id])->assertRedirect();
        $guides->refresh();
        $this->assertSame('Tutorials', $guides->name);
        $this->assertSame('tutorials', $guides->slug);
        $this->assertSame($parent->id, $guides->parent_id);

        $this->post(route('desk-editor.categories.store'), ['name' => 'Tutorials'])->assertSessionHasErrors('name');
        $this->post(route('desk-editor.categories.store'), ['name' => 'Bad', 'color' => 'red;}'])->assertSessionHasErrors('color');
        $this->put(route('desk-editor.categories.update', $parent), ['name' => 'Docs', 'parent_id' => $guides->id])->assertSessionHasErrors('parent_id');
        $this->put(route('desk-editor.categories.update', $parent), ['name' => 'Docs', 'parent_id' => $parent->id])->assertSessionHasErrors('parent_id');
    }

    public function test_json_responses(): void
    {
        $this->actingAs($this->user());

        $this->postJson(route('desk-editor.categories.store'), ['name' => 'Design'])
            ->assertOk()
            ->assertJsonPath('category.slug', 'design');
    }

    public function test_reorder_sets_sort_order(): void
    {
        $a = DeskCategory::create(['name' => 'A', 'sort_order' => 0]);
        $b = DeskCategory::create(['name' => 'B', 'sort_order' => 1]);
        $c = DeskCategory::create(['name' => 'C', 'sort_order' => 2]);

        $this->actingAs($this->user())
            ->postJson(route('desk-editor.categories.reorder'), ['order' => [$c->id, $a->id, $b->id]])
            ->assertOk();

        $this->assertSame(['C', 'A', 'B'], DeskCategory::orderBy('sort_order')->pluck('name')->all());
    }

    public function test_deleting_uncategorises_articles_and_lifts_children(): void
    {
        $parent = DeskCategory::create(['name' => 'Parent']);
        $doomed = DeskCategory::create(['name' => 'Doomed', 'parent_id' => $parent->id]);
        $child = DeskCategory::create(['name' => 'Child', 'parent_id' => $doomed->id]);
        $article = DeskArticle::create(['title' => 'Orphan', 'status' => ArticleStatus::Draft, 'category_id' => $doomed->id]);

        $this->actingAs($this->user())->delete(route('desk-editor.categories.destroy', $doomed))->assertRedirect();

        $this->assertModelMissing($doomed);
        $this->assertNull($article->fresh()->category_id);
        $this->assertSame($parent->id, $child->fresh()->parent_id);
    }

    public function test_article_form_category_picker(): void
    {
        DeskCategory::create(['name' => 'Engineering', 'color' => '#6366f1']);
        $this->actingAs($this->user());

        $this->get('/desk/articles/create')->assertOk()->assertSee('data-combo="category"', false)->assertSee('Engineering');

        // Typing a new name in the picker creates it on save.
        $this->post('/desk/articles', ['title' => 'Hi', 'status' => 'draft', 'category' => 'Brand New'])->assertRedirect();
        $this->assertSame('Brand New', DeskArticle::firstOrFail()->category->name);
    }
}
