<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Tests\Feature\Articles;

use Atannex\DeskEditor\Enums\ArticleStatus;
use Atannex\DeskEditor\Models\DeskArticle;
use Atannex\DeskEditor\Models\DeskTag;
use Atannex\DeskEditor\Tests\TestCase;

class TagsTest extends TestCase
{
    private function article(string $title, array $tags): DeskArticle
    {
        $article = DeskArticle::create(['title' => $title, 'status' => ArticleStatus::Draft]);
        $article->syncTagNames($tags);

        return $article;
    }

    public function test_index_lists_tags_with_counts_and_search(): void
    {
        $this->article('One', ['laravel', 'php']);
        $this->article('Two', ['laravel']);
        DeskTag::create(['name' => 'unused']);
        $this->actingAs($this->user());

        $this->get(route('desk-editor.tags.index'))->assertOk()
            ->assertSeeInOrder(['#laravel', '#php', '#unused'])
            ->assertSee('1 unused');
        $this->get(route('desk-editor.tags.index', ['q' => 'ph']))->assertOk()->assertSee('#php')->assertDontSee('#laravel');
    }

    public function test_rename(): void
    {
        $this->article('One', ['js']);
        $tag = DeskTag::firstOrFail();

        $this->actingAs($this->user())->put(route('desk-editor.tags.update', $tag), ['name' => 'JavaScript'])->assertRedirect();

        $tag->refresh();
        $this->assertSame('JavaScript', $tag->name);
        $this->assertSame('javascript', $tag->slug);
    }

    public function test_renaming_onto_an_existing_tag_merges(): void
    {
        $a = $this->article('A', ['js']);
        $b = $this->article('B', ['javascript']);
        $js = DeskTag::where('name', 'js')->firstOrFail();

        $this->actingAs($this->user())->put(route('desk-editor.tags.update', $js), ['name' => 'JavaScript'])->assertRedirect();

        $this->assertSame(['javascript'], DeskTag::pluck('name')->all());
        $this->assertSame(['javascript'], $a->fresh()->tagNames());
        $this->assertSame(['javascript'], $b->fresh()->tagNames());
    }

    public function test_merge_several_tags_into_one(): void
    {
        $a = $this->article('A', ['js', 'node']);
        $b = $this->article('B', ['ecmascript', 'js']);
        $this->article('C', ['php']);
        $ids = DeskTag::whereIn('name', ['js', 'ecmascript', 'node'])->pluck('id')->all();

        $this->actingAs($this->user())
            ->post(route('desk-editor.tags.merge'), ['ids' => $ids, 'target' => 'JavaScript'])
            ->assertRedirect()
            ->assertSessionHas('desk-status');

        $this->assertEqualsCanonicalizing(['JavaScript', 'php'], DeskTag::pluck('name')->all());
        $this->assertSame(['JavaScript'], $a->fresh()->tagNames());
        $this->assertSame(['JavaScript'], $b->fresh()->tagNames());
        $this->assertSame(1, DeskTag::where('name', 'JavaScript')->firstOrFail()->articles()->where('desk_articles.id', $a->id)->count());
    }

    public function test_merge_into_existing_tag(): void
    {
        $a = $this->article('A', ['js']);
        $this->article('B', ['javascript']);
        $js = DeskTag::where('name', 'js')->firstOrFail();

        $this->actingAs($this->user())->postJson(route('desk-editor.tags.merge'), ['ids' => [$js->id], 'target' => 'javascript'])->assertOk();

        $this->assertSame(['javascript'], DeskTag::pluck('name')->all());
        $this->assertSame(['javascript'], $a->fresh()->tagNames());
    }

    public function test_delete_and_bulk_delete(): void
    {
        $a = $this->article('A', ['one', 'two', 'three']);
        $this->actingAs($this->user());

        $this->delete(route('desk-editor.tags.destroy', DeskTag::where('name', 'one')->firstOrFail()))->assertRedirect();
        $this->assertSame(['three', 'two'], $a->fresh()->tagNames());

        $this->post(route('desk-editor.tags.bulk-destroy'), ['ids' => DeskTag::pluck('id')->all()])->assertRedirect();
        $this->assertSame(0, DeskTag::count());
        $this->assertSame([], $a->fresh()->tagNames());
    }

    public function test_form_offers_tag_autocomplete(): void
    {
        $this->article('A', ['laravel']);
        $this->actingAs($this->user());

        $this->get('/desk/articles/create')->assertOk()->assertSee('data-tags-options', false)->assertSee('laravel');
        $this->getJson(route('desk-editor.tags.suggest', ['q' => 'lar']))->assertOk()->assertJsonPath('data.0', 'laravel');
    }
}
