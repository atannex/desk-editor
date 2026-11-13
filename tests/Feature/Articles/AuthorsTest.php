<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Tests\Feature\Articles;

use Atannex\DeskEditor\Enums\ArticleStatus;
use Atannex\DeskEditor\Models\DeskArticle;
use Atannex\DeskEditor\Tests\Fixtures\User;
use Atannex\DeskEditor\Tests\TestCase;
use Illuminate\Support\Facades\Gate;

class AuthorsTest extends TestCase
{
    public function test_articles_can_be_attributed_to_another_author(): void
    {
        $editor = $this->user();
        $grace = User::query()->create(['name' => 'Grace Hopper', 'email' => 'grace@example.com']);
        $this->actingAs($editor);

        $this->post('/desk/articles', ['title' => 'Guest post', 'status' => 'draft', 'author_id' => $grace->id])->assertRedirect();
        $article = DeskArticle::firstOrFail();
        $this->assertTrue($article->author->is($grace));
        $this->assertTrue($article->editor->is($editor));

        $this->put(route('desk-editor.articles.update', $article), ['title' => 'Guest post', 'status' => 'draft', 'author_id' => $editor->id])->assertRedirect();
        $this->assertTrue($article->fresh()->author->is($editor));

        $this->put(route('desk-editor.articles.update', $article), ['title' => 'Guest post', 'status' => 'draft', 'author_id' => 999])->assertSessionHasErrors('author_id');
    }

    public function test_writers_without_review_or_publish_cannot_reassign(): void
    {
        Gate::define('desk-articles.review', fn () => false);
        Gate::define('desk-articles.publish', fn () => false);
        $writer = $this->user();
        $other = User::query()->create(['name' => 'Other', 'email' => 'other@example.com']);
        $this->actingAs($writer);

        $this->post('/desk/articles', ['title' => 'Mine', 'status' => 'draft', 'author_id' => $other->id])->assertRedirect();
        $this->assertTrue(DeskArticle::firstOrFail()->author->is($writer));
    }

    public function test_author_search_endpoint(): void
    {
        $this->user();
        User::query()->create(['name' => 'Grace Hopper', 'email' => 'grace@example.com']);
        $this->actingAs($this->user());

        $this->getJson(route('desk-editor.articles.authors', ['q' => 'grace']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Grace Hopper')
            ->assertJsonPath('data.0.initials', 'GH');
    }

    public function test_form_shows_author_picker_and_index_filters_by_author(): void
    {
        $ada = $this->user();
        $grace = User::query()->create(['name' => 'Grace Hopper', 'email' => 'grace@example.com']);
        $a = DeskArticle::create(['title' => 'By Ada', 'status' => ArticleStatus::Draft]);
        $a->author()->associate($ada)->save();
        $b = DeskArticle::create(['title' => 'By Grace', 'status' => ArticleStatus::Draft]);
        $b->author()->associate($grace)->save();
        $this->actingAs($ada);

        $this->get(route('desk-editor.articles.edit', $a))->assertOk()->assertSee('data-combo="author"', false)->assertSee('name="author_id"', false);

        $this->get('/desk/articles')->assertOk()->assertSee('data-author-filter', false)->assertSee('GH')->assertSee('Grace Hopper');
        $this->get('/desk/articles?author='.$grace->id)->assertOk()->assertSee('By Grace')->assertDontSee('By Ada');
    }

    public function test_custom_author_label_column(): void
    {
        $this->app['config']->set('desk-editor.articles.authors.label', 'email');
        $this->actingAs($this->user());
        User::query()->create(['name' => 'Grace', 'email' => 'grace@example.com']);

        $this->getJson(route('desk-editor.articles.authors', ['q' => 'grace@']))->assertJsonPath('data.0.name', 'grace@example.com');
    }
}
