<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Articles;

use Atannex\DeskEditor\Articles\Console\PublishScheduledCommand;
use Atannex\DeskEditor\Articles\Policies\DeskArticlePolicy;
use Atannex\DeskEditor\Articles\Support\ArticleWorkflow;
use Atannex\DeskEditor\Articles\Support\Authors;
use Atannex\DeskEditor\Articles\Support\Differ;
use Atannex\DeskEditor\Articles\Support\EditLock;
use Atannex\DeskEditor\Articles\Support\Navigation;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Article manager module: permissions, editorial workflow, edit locks,
 * revision diffs and the scheduled-publishing command.
 */
class ArticleManagerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DeskArticlePolicy::class);
        $this->app->singleton(ArticleWorkflow::class);
        $this->app->singleton(Authors::class);
        $this->app->singleton(Differ::class);
        $this->app->singleton(Navigation::class);
        $this->app->singleton(EditLock::class, fn ($app) => new EditLock($app['cache']->store()));
    }

    public function boot(): void
    {
        if (! config('desk-editor.articles.enabled')) {
            return;
        }

        $this->app->make(DeskArticlePolicy::class)->defineDefaultGates();

        View::composer('desk-editor::articles.*', function ($view): void {
            $view->with('deskPolicy', $this->app->make(DeskArticlePolicy::class));
        });

        if ($this->app->runningInConsole()) {
            $this->commands([PublishScheduledCommand::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('desk-editor:publish-scheduled')->everyMinute()->withoutOverlapping();
        });
    }
}
