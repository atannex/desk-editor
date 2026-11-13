<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Articles\Console;

use Atannex\DeskEditor\Articles\Support\ArticleWorkflow;
use Illuminate\Console\Command;

class PublishScheduledCommand extends Command
{
    protected $signature = 'desk-editor:publish-scheduled';

    protected $description = 'Publish scheduled Desk articles whose publish date has passed';

    public function handle(ArticleWorkflow $workflow): int
    {
        $count = $workflow->publishDue();

        $this->components->info($count === 1 ? '1 scheduled article published.' : "{$count} scheduled articles published.");

        return self::SUCCESS;
    }
}
