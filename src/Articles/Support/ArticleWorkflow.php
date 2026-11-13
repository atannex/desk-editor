<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Articles\Support;

use Atannex\DeskEditor\Articles\Events\ArticleChangesRequested;
use Atannex\DeskEditor\Articles\Events\ArticlePublished;
use Atannex\DeskEditor\Articles\Events\ArticleSubmittedForReview;
use Atannex\DeskEditor\Enums\ArticleStatus;
use Atannex\DeskEditor\Models\DeskArticle;
use Atannex\DeskEditor\Models\DeskArticleActivity;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Editorial state machine: records every status transition in the activity
 * timeline and dispatches the matching events.
 */
class ArticleWorkflow
{
    /** Consecutive "updated" entries by the same person within this window are merged. */
    private const COALESCE_MINUTES = 10;

    /**
     * Log what a save did, given the status the article had before it.
     */
    public function recorded(DeskArticle $article, ?ArticleStatus $from, bool $created = false, ?string $note = null): void
    {
        $to = $article->status;

        if ($created) {
            $article->logActivity('created', $note);
            if ($to === ArticleStatus::Draft) {
                return;
            }
            $from = ArticleStatus::Draft;
        }

        if ($from === $to) {
            $this->logUpdate($article, $note);

            return;
        }

        $user = $this->user();
        $properties = ['from' => $from?->value, 'to' => $to->value];

        switch ($to) {
            case ArticleStatus::Review:
                $article->logActivity('submitted', $note, $properties);
                ArticleSubmittedForReview::dispatch($article, $user, $note);
                break;

            case ArticleStatus::Published:
                if ($from === ArticleStatus::Review) {
                    $article->logActivity('approved', $note, $properties);
                }
                $article->logActivity('published', $from === ArticleStatus::Review ? null : $note, $properties);
                ArticlePublished::dispatch($article, $user, $note);
                break;

            case ArticleStatus::Scheduled:
                if ($from === ArticleStatus::Review) {
                    $article->logActivity('approved', $note, $properties);
                }
                $article->logActivity('scheduled', null, [...$properties, 'at' => $article->published_at?->toIso8601String()]);
                break;

            case ArticleStatus::Archived:
                $article->logActivity('archived', $note, $properties);
                break;

            case ArticleStatus::Draft:
                $article->logActivity($from === ArticleStatus::Published || $from === ArticleStatus::Scheduled ? 'unpublished' : 'updated', $note, $properties);
                break;
        }
    }

    /**
     * Reviewer approves: publish now, or schedule when a future date is given.
     */
    public function approve(DeskArticle $article, ?Carbon $at = null, ?string $note = null): void
    {
        $from = $article->status;
        $article->status = $at && $at->isFuture() ? ArticleStatus::Scheduled : ArticleStatus::Published;
        $article->published_at = $at ?? ($article->published_at?->isPast() ? $article->published_at : now());
        $article->revisionNote = $note ?: __('desk-editor::articles.activity.approved');
        $this->stampEditor($article);
        $article->save();

        $properties = ['from' => $from->value, 'to' => $article->status->value];
        $article->logActivity('approved', $note, $properties);

        if ($article->status === ArticleStatus::Published) {
            $article->logActivity('published', null, $properties);
            ArticlePublished::dispatch($article, $this->user(), $note);
        } else {
            $article->logActivity('scheduled', null, [...$properties, 'at' => $article->published_at?->toIso8601String()]);
        }
    }

    /**
     * Reviewer sends the article back to its writer with a (required) note.
     */
    public function requestChanges(DeskArticle $article, string $note): void
    {
        $from = $article->status;
        $article->status = ArticleStatus::Draft;
        $article->revisionNote = __('desk-editor::articles.activity.changes_requested');
        $this->stampEditor($article);
        $article->save();

        $article->logActivity('changes_requested', $note, ['from' => $from->value, 'to' => ArticleStatus::Draft->value]);
        ArticleChangesRequested::dispatch($article, $this->user(), $note);
    }

    /**
     * Move an article to a status outside the editor (bulk actions, quick actions).
     */
    public function moveTo(DeskArticle $article, ArticleStatus $status): void
    {
        $from = $article->status;
        if ($from === $status) {
            return;
        }

        $article->status = $status;
        $this->stampEditor($article);
        $article->save();
        $this->recorded($article, $from);
    }

    /**
     * Publish every scheduled article that is due. Returns how many went live.
     */
    public function publishDue(): int
    {
        $model = DeskArticle::modelClass();
        $count = 0;

        $model::query()
            ->status(ArticleStatus::Scheduled)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderBy('published_at')
            ->each(function (DeskArticle $article) use (&$count): void {
                $article->status = ArticleStatus::Published;
                $article->revisionNote = __('desk-editor::articles.activity.published');
                $article->save();
                $article->logActivity('published', __('desk-editor::articles.activity.scheduler'), ['from' => ArticleStatus::Scheduled->value, 'to' => ArticleStatus::Published->value, 'scheduled' => true]);
                ArticlePublished::dispatch($article, null, null);
                $count++;
            });

        return $count;
    }

    private function logUpdate(DeskArticle $article, ?string $note): void
    {
        $user = $this->user();
        /** @var DeskArticleActivity|null $last */
        $last = $article->activities()->first();

        if ($note === null && $last !== null && $last->type === 'updated' && $last->description === null
            && $user instanceof Model && $last->causer_type === $user->getMorphClass() && (string) $last->causer_id === (string) $user->getKey()
            && $last->created_at?->gt(now()->subMinutes(self::COALESCE_MINUTES))) {
            return;
        }

        $article->logActivity('updated', $note);
    }

    private function stampEditor(DeskArticle $article): void
    {
        $user = $this->user();
        if ($user instanceof Model) {
            $article->editor()->associate($user);
        }
    }

    private function user(): ?Authenticatable
    {
        return auth()->user();
    }
}
