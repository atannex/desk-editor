<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Media;

use Atannex\DeskEditor\Media\Console\PruneMediaCommand;
use Atannex\DeskEditor\Media\Processing\AvProcessor;
use Atannex\DeskEditor\Media\Processing\Ffmpeg;
use Atannex\DeskEditor\Media\Processing\ImageProcessor;
use Atannex\DeskEditor\Media\Processing\MediaProcessor;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Media library & processing module: desk_media table, image variants,
 * ffmpeg posters/transcodes, the JSON library, the admin page and pruning.
 * Routes live in routes/media.php (loaded by the main provider).
 */
class MediaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Ffmpeg::class);
        $this->app->singleton(ImageProcessor::class);
        $this->app->singleton(AvProcessor::class);
        $this->app->singleton(MediaProcessor::class);
        $this->app->scoped(MediaReferences::class);
        $this->app->scoped(ContentEnricher::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations/2026_01_01_000200_create_desk_media_table.php');

        if (! Gate::has('manage-media')) {
            // Any signed-in user manages all media by default; define the gate to restrict.
            Gate::define('manage-media', fn ($user = null) => $user !== null);
        }

        if ($this->app->runningInConsole()) {
            $this->commands([PruneMediaCommand::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('desk-editor:prune-media')->daily()->onOneServer()->withoutOverlapping();
        });
    }
}
