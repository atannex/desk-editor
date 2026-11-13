<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Tests\Feature\Articles;

use Atannex\DeskEditor\Articles\Events\ArticlePublished;
use Atannex\DeskEditor\Enums\ArticleStatus;
use Atannex\DeskEditor\Models\DeskArticle;
use Atannex\DeskEditor\Tests\TestCase;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;

class ScheduledPublishingTest extends TestCase
{
    public function test_command_publishes_due_articles(): void
    {
        Event::fake([ArticlePublished::class]);
        $due = DeskArticle::create(['title' => 'Due', 'status' => ArticleStatus::Scheduled, 'published_at' => now()->addMinutes(5)]);
        $later = DeskArticle::create(['title' => 'Later', 'status' => ArticleStatus::Scheduled, 'published_at' => now()->addDay()]);

        $this->travel(10)->minutes();
        $this->artisan('desk-editor:publish-scheduled')->assertSuccessful();

        $this->assertSame(ArticleStatus::Published, $due->fresh()->status);
        $this->assertSame(ArticleStatus::Scheduled, $later->fresh()->status);
        $this->assertSame('published', $due->activities()->value('type'));
        Event::assertDispatchedTimes(ArticlePublished::class, 1);
        Event::assertDispatched(ArticlePublished::class, fn ($e) => $e->article->is($due) && $e->user === null);

        // Running again is a no-op.
        $this->artisan('desk-editor:publish-scheduled')->assertSuccessful();
        Event::assertDispatchedTimes(ArticlePublished::class, 1);
    }

    public function test_command_is_scheduled_every_minute(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event) => str_contains((string) $event->command, 'desk-editor:publish-scheduled'));

        $this->assertCount(1, $events);
        $this->assertSame('* * * * *', $events->first()->expression);
    }
}
