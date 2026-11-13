<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Tests\Feature\Blog;

use Atannex\DeskEditor\Enums\ArticleStatus;
use Atannex\DeskEditor\Models\DeskArticle;
use Atannex\DeskEditor\Models\DeskCategory;
use Atannex\DeskEditor\Tests\Fixtures\User;
use Atannex\DeskEditor\Tests\TestCase;
use Illuminate\Support\Facades\Crypt;

abstract class BlogTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('desk-editor.blog.enabled', true);
        $app['config']->set('desk-editor.blog.name', 'The Desk Journal');
        $app['config']->set('desk-editor.blog.comments.middleware', ['web']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $tags
     */
    protected function article(array $attributes = [], array $tags = [], ?User $author = null): DeskArticle
    {
        $article = new DeskArticle(array_merge([
            'title' => 'Hello Readers',
            'body' => '<h2 id="intro">Intro</h2><p>Welcome to the journal.</p><h2 id="details">Details</h2><p>More words here.</p>',
            'status' => ArticleStatus::Published,
            'published_at' => now()->subDay(),
        ], $attributes));

        if ($author) {
            $article->author()->associate($author);
        }

        $article->save();

        if ($tags) {
            $article->syncTagNames($tags);
        }

        return $article->refresh();
    }

    protected function category(string $name): DeskCategory
    {
        /** @var DeskCategory */
        return DeskCategory::findOrCreateByName($name);
    }

    protected function formToken(int $secondsAgo): string
    {
        return Crypt::encryptString((string) now()->subSeconds($secondsAgo)->getTimestamp());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function commentPayload(array $overrides = []): array
    {
        return array_merge([
            'body' => 'What a lovely read.',
            'name' => 'Grace Hopper',
            'email' => 'grace@example.com',
            '_desk_ts' => $this->formToken(10),
        ], $overrides);
    }
}
