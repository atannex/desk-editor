<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Console;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'desk-editor:install
        {--assets : Publish the compiled CSS/JS to public/vendor/desk-editor}
        {--views : Publish the Blade views for customisation}
        {--lang : Publish the translation files}
        {--migrate : Run the article manager migrations}
        {--force : Overwrite previously published files}';

    protected $description = 'Install Desk Editor: publish config (and optionally assets, views, translations) and prepare storage';

    public function handle(): int
    {
        $this->components->info('Installing Desk Editor');

        $this->publish('desk-editor-config');

        if ($this->option('assets')) {
            $this->publish('desk-editor-assets');
        }
        if ($this->option('views')) {
            $this->publish('desk-editor-views');
        }
        if ($this->option('lang')) {
            $this->publish('desk-editor-lang');
        }

        $disk = config('desk-editor.uploads.disk');
        if ($disk === 'public' && ! is_link(public_path('storage')) && ! is_dir(public_path('storage'))) {
            $this->components->task('Linking public storage', fn () => $this->callSilently('storage:link') === 0);
        }

        if (config('desk-editor.articles.enabled') && ($this->option('migrate') || $this->confirmMigrate())) {
            $this->call('migrate', ['--force' => true]);
        }

        $this->newLine();
        $this->components->bulletList([
            'Add the editor to a form:  <x-desk-editor name="body" :value="$post->body" />',
            'Render content for readers:  <x-desk-editor::content :html="$post->body" />',
            'Sanitise on save with the cast:  \'body\' => AsDeskContent::class',
            config('desk-editor.articles.enabled') ? 'Article manager: '.url(config('desk-editor.articles.prefix', 'desk/articles')) : 'Article manager is disabled',
        ]);

        return self::SUCCESS;
    }

    private function publish(string $tag): void
    {
        $this->callSilently('vendor:publish', ['--tag' => $tag, '--force' => (bool) $this->option('force')]);
        $this->components->task("Published [{$tag}]");
    }

    private function confirmMigrate(): bool
    {
        return $this->input->isInteractive() && $this->components->confirm('Run the article manager migrations now?', true);
    }
}
