<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Tests\Feature\Articles;

use Atannex\DeskEditor\Enums\ArticleStatus;
use Atannex\DeskEditor\Models\DeskArticle;
use Atannex\DeskEditor\Tests\TestCase;

class TranslationsTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('desk-editor.articles.locales', ['en' => 'English', 'fr' => 'Français', 'de' => 'Deutsch']);
    }

    public function test_add_translation_creates_a_linked_draft(): void
    {
        $this->actingAs($this->user());
        $source = DeskArticle::create(['title' => 'Hello', 'body' => '<p>World</p>', 'status' => ArticleStatus::Published]);
        $source->syncTagNames(['greeting']);
        $before = $source->updated_at;

        $this->post(route('desk-editor.articles.translations.store', $source), ['locale' => 'fr'])->assertRedirect();

        $source->refresh();
        $copy = DeskArticle::where('locale', 'fr')->firstOrFail();
        $this->assertNotNull($source->translation_group);
        $this->assertSame($source->translation_group, $copy->translation_group);
        $this->assertSame(ArticleStatus::Draft, $copy->status);
        $this->assertSame('hello', $copy->slug);
        $this->assertSame(['greeting'], $copy->tagNames());
        $this->assertTrue($before->eq($source->updated_at), 'Adding a translation must not bump the source version.');
        $this->assertSame('translated', $copy->activities()->value('type'));
        $this->assertSame('translated', $source->activities()->value('type'));
        $this->assertSame([$copy->id], $source->translations()->pluck('id')->all());

        $this->post(route('desk-editor.articles.translations.store', $source), ['locale' => 'fr'])->assertSessionHasErrors('locale');
        $this->post(route('desk-editor.articles.translations.store', $source), ['locale' => 'xx'])->assertSessionHasErrors('locale');
        $this->assertSame(2, DeskArticle::count());
    }

    public function test_translations_panel_and_locale_select(): void
    {
        $this->actingAs($this->user());
        $source = DeskArticle::create(['title' => 'Hello', 'status' => ArticleStatus::Draft]);
        $this->post(route('desk-editor.articles.translations.store', $source), ['locale' => 'de']);

        $this->get(route('desk-editor.articles.edit', $source))->assertOk()
            ->assertSee('data-translations', false)
            ->assertSee('Deutsch')
            ->assertSee('name="locale"', false)
            ->assertSee(route('desk-editor.articles.translations.store', $source));
    }

    public function test_locale_badge_and_filter_on_index(): void
    {
        DeskArticle::create(['title' => 'English story', 'status' => ArticleStatus::Draft, 'locale' => 'en']);
        DeskArticle::create(['title' => 'Histoire', 'status' => ArticleStatus::Draft, 'locale' => 'fr']);
        $this->actingAs($this->user());

        $this->get('/desk/articles')->assertOk()->assertSee('data-locale-filter', false);
        $this->get('/desk/articles?locale=fr')->assertOk()->assertSee('Histoire')->assertDontSee('English story');
    }

    public function test_changing_locale_cannot_collide_with_a_sibling(): void
    {
        $this->actingAs($this->user());
        $source = DeskArticle::create(['title' => 'Hello', 'status' => ArticleStatus::Draft]);
        $this->post(route('desk-editor.articles.translations.store', $source), ['locale' => 'fr']);

        $this->put(route('desk-editor.articles.update', $source->fresh()), ['title' => 'Hello', 'status' => 'draft', 'locale' => 'fr'])->assertSessionHasErrors('locale');
        $this->put(route('desk-editor.articles.update', $source->fresh()), ['title' => 'Hello', 'status' => 'draft', 'locale' => 'de'])->assertSessionHasNoErrors();
        $this->assertSame('de', $source->fresh()->locale);
    }
}
