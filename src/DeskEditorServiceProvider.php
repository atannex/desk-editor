<?php

declare(strict_types=1);

namespace Atannex\DeskEditor;

use Atannex\DeskEditor\Console\InstallCommand;
use Atannex\DeskEditor\View\Components\Content;
use Atannex\DeskEditor\View\Components\Editor;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class DeskEditorServiceProvider extends ServiceProvider
{
    /**
     * Optional module providers, registered when present.
     *
     * @var list<string>
     */
    public const MODULES = [
        'Atannex\\DeskEditor\\Media\\MediaServiceProvider',
        'Atannex\\DeskEditor\\Articles\\ArticleManagerServiceProvider',
        'Atannex\\DeskEditor\\Blog\\BlogServiceProvider',
        'Atannex\\DeskEditor\\Collaboration\\CollaborationServiceProvider',
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/desk-editor.php', 'desk-editor');

        foreach (self::MODULES as $module) {
            if (class_exists($module)) {
                $this->app->register($module);
            }
        }

        $this->app->singleton(DeskEditorManager::class, fn ($app) => new DeskEditorManager($app['config']));
        $this->app->alias(DeskEditorManager::class, 'desk-editor');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'desk-editor');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'desk-editor');

        $this->registerRoutes();
        $this->registerBlade();
        $this->registerArticles();

        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__.'/../config/desk-editor.php' => config_path('desk-editor.php')], 'desk-editor-config');
            $this->publishes([__DIR__.'/../dist' => public_path('vendor/desk-editor')], 'desk-editor-assets');
            $this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/desk-editor')], 'desk-editor-views');
            $this->publishes([__DIR__.'/../resources/lang' => $this->app->langPath('vendor/desk-editor')], 'desk-editor-lang');
            $this->publishes([__DIR__.'/../database/migrations' => database_path('migrations')], 'desk-editor-migrations');

            $this->commands([InstallCommand::class]);
        }
    }

    private function registerRoutes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        Route::group([], fn () => $this->loadRoutesFrom(__DIR__.'/../routes/web.php'));
    }

    private function registerBlade(): void
    {
        Blade::component('desk-editor', Editor::class);
        Blade::component('desk-editor::editor', Editor::class);
        Blade::component('desk-editor::content', Content::class);

        Blade::directive('deskEditorStyles', fn () => "<?php echo app('desk-editor')->styles(); ?>");
        Blade::directive('deskEditorScripts', fn () => "<?php echo app('desk-editor')->scripts(); ?>");
        Blade::directive('deskEditorAssets', fn () => "<?php echo app('desk-editor')->assets(); ?>");
    }

    private function registerArticles(): void
    {
        if (! config('desk-editor.articles.enabled')) {
            return;
        }

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if (! Gate::has('manage-desk-articles')) {
            Gate::define('manage-desk-articles', fn ($user = null) => $user !== null);
        }
    }
}
