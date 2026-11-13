<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Blog;

use Atannex\DeskEditor\Blog\Http\Controllers\CommentModerationController;
use Atannex\DeskEditor\Blog\Support\FeedBuilder;
use Atannex\DeskEditor\Blog\Support\RelatedArticles;
use Atannex\DeskEditor\Blog\Support\ViewRecorder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Public blog, reader comments and analytics.
 *
 * Routes live in routes/blog.php (required by routes/web.php). Gates are
 * defined after boot only when the host app (or another module) has not
 * defined them already.
 */
class BlogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ViewRecorder::class);
        $this->app->singleton(RelatedArticles::class);
        $this->app->singleton(FeedBuilder::class);
    }

    public function boot(): void
    {
        if (config('desk-editor.blog.enabled') || config('desk-editor.articles.enabled')) {
            $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        }

        $this->app->booted(function (): void {
            if (! Gate::has(CommentModerationController::GATE)) {
                Gate::define(CommentModerationController::GATE, fn ($user = null) => $user !== null);
            }
        });
    }
}
