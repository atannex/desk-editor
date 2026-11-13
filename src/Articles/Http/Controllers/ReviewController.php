<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Articles\Http\Controllers;

use Atannex\DeskEditor\Articles\Http\Concerns\ResolvesArticles;
use Atannex\DeskEditor\Articles\Support\ArticleWorkflow;
use Atannex\DeskEditor\Enums\ArticleStatus;
use Atannex\DeskEditor\Models\DeskArticle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Reviewer decisions: approve (publish now or on a date) or request changes.
 */
class ReviewController extends Controller
{
    use ResolvesArticles;

    public function __construct(private readonly ArticleWorkflow $workflow) {}

    public function __invoke(Request $request, DeskArticle|string $article): RedirectResponse|JsonResponse
    {
        $article = $this->find($article);
        $this->authorizeAbility('review', $article);

        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'request_changes'])],
            'note' => ['nullable', 'string', 'max:2000', Rule::requiredIf($request->input('decision') === 'request_changes')],
            'publish_at' => ['nullable', 'date'],
        ], [
            'note.required' => __('desk-editor::articles.review.note_required'),
        ]);

        $note = isset($data['note']) && trim((string) $data['note']) !== '' ? trim((string) $data['note']) : null;

        if ($data['decision'] === 'approve') {
            $this->authorizeAbility('publish', $article);
            $at = ! empty($data['publish_at']) ? Carbon::parse($data['publish_at']) : null;
            $this->workflow->approve($article, $at, $note);
            $message = $article->status === ArticleStatus::Scheduled
                ? __('desk-editor::articles.review.approved_scheduled', ['date' => $article->published_at?->locale(app()->getLocale())->isoFormat('LLL')])
                : __('desk-editor::articles.review.approved');
        } else {
            $this->workflow->requestChanges($article, (string) $note);
            $message = __('desk-editor::articles.review.changes_requested');
        }

        if ($request->wantsJson()) {
            return response()->json(['message' => $message, 'status' => $article->status->value]);
        }

        return redirect()->route('desk-editor.articles.edit', $article)->with('desk-status', $message);
    }
}
