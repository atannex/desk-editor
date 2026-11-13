<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Tests\Feature\Articles;

use Atannex\DeskEditor\Articles\Events\ArticleChangesRequested;
use Atannex\DeskEditor\Articles\Events\ArticlePublished;
use Atannex\DeskEditor\Articles\Events\ArticleSubmittedForReview;
use Atannex\DeskEditor\Enums\ArticleStatus;
use Atannex\DeskEditor\Models\DeskArticle;
use Atannex\DeskEditor\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;

class WorkflowTest extends TestCase
{
    private function types(DeskArticle $article): array
    {
        return $article->activities()->reorder('id')->pluck('type')->all();
    }

    public function test_writers_without_publish_can_only_draft_or_submit(): void
    {
        Event::fake([ArticleSubmittedForReview::class, ArticlePublished::class]);
        Gate::define('desk-articles.publish', fn () => false);
        $this->actingAs($this->user());

        $this->get('/desk/articles/create')->assertOk()
            ->assertSee('value="submit"', false)
            ->assertDontSee('data-publish', false)
            ->assertDontSee('value="published"', false);

        $this->post('/desk/articles', ['title' => 'Sneaky', 'status' => 'published'])->assertSessionHasErrors('status');
        $this->assertSame(0, DeskArticle::count());

        // "Publish" intent from a writer becomes a submission.
        $this->post('/desk/articles', ['title' => 'Ready', 'status' => 'draft', 'intent' => 'publish'])->assertRedirect();
        $article = DeskArticle::firstOrFail();
        $this->assertSame(ArticleStatus::Review, $article->status);
        $this->assertSame(['created', 'submitted'], $this->types($article));
        Event::assertDispatched(ArticleSubmittedForReview::class, fn ($e) => $e->article->is($article));
        Event::assertNotDispatched(ArticlePublished::class);
    }

    public function test_require_review_applies_to_users_without_review_ability(): void
    {
        $this->app['config']->set('desk-editor.articles.require_review', true);
        Gate::define('desk-articles.review', fn () => false);
        $this->actingAs($this->user());

        $this->post('/desk/articles', ['title' => 'Gated', 'status' => 'published'])->assertSessionHasErrors('status');
        $this->post('/desk/articles', ['title' => 'Gated', 'status' => 'review'])->assertRedirect();
        $this->assertSame(ArticleStatus::Review, DeskArticle::firstOrFail()->status);
    }

    public function test_submit_then_approve_publishes_and_logs_everything(): void
    {
        Event::fake([ArticleSubmittedForReview::class, ArticlePublished::class]);
        $this->actingAs($this->user());
        $article = DeskArticle::create(['title' => 'Story', 'body' => '<p>Text</p>', 'status' => ArticleStatus::Draft]);

        $this->put(route('desk-editor.articles.update', $article), ['title' => 'Story', 'status' => 'draft', 'intent' => 'submit'])
            ->assertSessionHas('desk-status', __('desk-editor::articles.submitted'));
        $this->assertSame(ArticleStatus::Review, $article->fresh()->status);

        $this->get(route('desk-editor.articles.edit', $article))->assertOk()
            ->assertSee(__('desk-editor::articles.workflow.approve'))
            ->assertSee(__('desk-editor::articles.workflow.request_changes'));

        $this->post(route('desk-editor.articles.review', $article), ['decision' => 'approve', 'note' => 'Great piece'])->assertRedirect();

        $article->refresh();
        $this->assertSame(ArticleStatus::Published, $article->status);
        $this->assertNotNull($article->published_at);
        $this->assertSame(['submitted', 'approved', 'published'], $this->types($article));
        $this->assertSame('Great piece', $article->activities()->where('type', 'approved')->value('description'));
        Event::assertDispatched(ArticleSubmittedForReview::class);
        Event::assertDispatched(ArticlePublished::class, fn ($e) => $e->article->is($article) && $e->note === 'Great piece');
    }

    public function test_approving_with_a_future_date_schedules(): void
    {
        Event::fake([ArticlePublished::class]);
        $this->actingAs($this->user());
        $article = DeskArticle::create(['title' => 'Later', 'status' => ArticleStatus::Review]);

        $this->post(route('desk-editor.articles.review', $article), ['decision' => 'approve', 'publish_at' => now()->addDays(2)->format('Y-m-d H:i')])->assertRedirect();

        $article->refresh();
        $this->assertSame(ArticleStatus::Scheduled, $article->status);
        $this->assertTrue($article->published_at->isFuture());
        $this->assertSame(['approved', 'scheduled'], $this->types($article));
        Event::assertNotDispatched(ArticlePublished::class);
    }

    public function test_request_changes_requires_a_note_and_returns_to_draft(): void
    {
        Event::fake([ArticleChangesRequested::class]);
        $this->actingAs($this->user());
        $article = DeskArticle::create(['title' => 'Rough', 'status' => ArticleStatus::Review]);

        $this->post(route('desk-editor.articles.review', $article), ['decision' => 'request_changes'])->assertSessionHasErrors('note');
        $this->assertSame(ArticleStatus::Review, $article->fresh()->status);

        $this->post(route('desk-editor.articles.review', $article), ['decision' => 'request_changes', 'note' => 'Tighten the intro'])->assertRedirect();

        $article->refresh();
        $this->assertSame(ArticleStatus::Draft, $article->status);
        $activity = $article->activities()->firstOrFail();
        $this->assertSame('changes_requested', $activity->type);
        $this->assertSame('Tighten the intro', $activity->description);
        Event::assertDispatched(ArticleChangesRequested::class, fn ($e) => $e->note === 'Tighten the intro');

        // The writer sees the note on the edit screen and in the timeline.
        $this->get(route('desk-editor.articles.edit', $article))->assertOk()
            ->assertSee(__('desk-editor::articles.workflow.changes_requested'))
            ->assertSee('Tighten the intro');
    }

    public function test_review_actions_require_review_ability(): void
    {
        Gate::define('desk-articles.review', fn () => false);
        $this->actingAs($this->user());
        $article = DeskArticle::create(['title' => 'Pending', 'status' => ArticleStatus::Review]);

        $this->post(route('desk-editor.articles.review', $article), ['decision' => 'approve'])->assertForbidden();
        $this->get(route('desk-editor.articles.edit', $article))->assertOk()
            ->assertSee(__('desk-editor::articles.workflow.in_review_writer'))
            ->assertDontSee(__('desk-editor::articles.workflow.request_changes'));
    }

    public function test_status_transitions_are_logged(): void
    {
        Event::fake([ArticlePublished::class]);
        $this->actingAs($this->user());
        $article = DeskArticle::create(['title' => 'Cycle', 'status' => ArticleStatus::Draft]);
        $put = fn (array $data) => $this->put(route('desk-editor.articles.update', $article), ['title' => 'Cycle', ...$data])->assertRedirect();

        $put(['status' => 'published']);
        $put(['status' => 'draft']);
        $put(['status' => 'scheduled', 'published_at' => now()->addDay()->format('Y-m-d H:i')]);
        $put(['status' => 'archived']);
        $put(['status' => 'archived', 'revision_note' => 'Typo']);

        $this->assertSame(['published', 'unpublished', 'scheduled', 'archived', 'updated'], $this->types($article));
        Event::assertDispatchedTimes(ArticlePublished::class, 1);

        $this->get(route('desk-editor.articles.edit', $article))->assertOk()->assertSee('data-activity', false)->assertSee('Typo');
    }

    public function test_bulk_publish_uses_the_workflow(): void
    {
        Event::fake([ArticlePublished::class]);
        $this->actingAs($this->user());
        $a = DeskArticle::create(['title' => 'A', 'status' => ArticleStatus::Draft]);

        $this->post(route('desk-editor.articles.bulk'), ['action' => 'publish', 'ids' => [$a->id]])->assertRedirect();

        $this->assertSame(['published'], $this->types($a));
        Event::assertDispatched(ArticlePublished::class);
    }

    public function test_restoring_a_revision_and_trash_are_logged(): void
    {
        $this->actingAs($this->user());
        $article = DeskArticle::create(['title' => 'V1', 'status' => ArticleStatus::Draft]);
        $first = $article->revisions()->firstOrFail();
        $article->update(['title' => 'V2']);

        $this->post(route('desk-editor.articles.revisions.restore', [$article, $first->id]))->assertRedirect();
        $this->delete(route('desk-editor.articles.destroy', $article));
        $this->post(route('desk-editor.articles.restore', $article));

        $this->assertSame(['revision_restored', 'trashed', 'restored'], $this->types($article));
    }
}
