<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Articles\Http\Controllers;

use Atannex\DeskEditor\Articles\Http\Concerns\ResolvesArticles;
use Atannex\DeskEditor\Articles\Support\EditLock;
use Atannex\DeskEditor\Models\DeskArticle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * Edit-lock heartbeat, take-over and release.
 */
class LockController extends Controller
{
    use ResolvesArticles;

    public function __construct(private readonly EditLock $locks) {}

    public function heartbeat(Request $request, DeskArticle|string $article): JsonResponse|RedirectResponse
    {
        $article = $this->find($article);
        $this->authorizeAbility('update', $article);
        $user = $request->user();
        abort_if($user === null, 401);

        $takeOver = $request->boolean('take_over');
        $previous = $takeOver ? $this->locks->heldByOther($article, $user) : null;
        $acquired = $this->locks->acquire($article, $user, $takeOver);

        if ($takeOver && $previous !== null) {
            $article->logActivity('lock_taken', null, ['from' => $previous['name']]);
        }

        if (! $request->wantsJson()) {
            return redirect()->route('desk-editor.articles.edit', $article)
                ->with('desk-status', $acquired ? __('desk-editor::articles.lock.taken_over') : __('desk-editor::articles.lock.busy'));
        }

        return response()->json([
            'acquired' => $acquired,
            'holder' => EditLock::present($this->locks->heldByOther($article, $user)),
            'version' => (string) $article->updated_at?->getTimestamp(),
            'expires_in' => $this->locks->seconds(),
        ]);
    }

    public function release(Request $request, DeskArticle|string $article): Response
    {
        $article = $this->find($article);
        if ($user = $request->user()) {
            $this->locks->release($article, $user);
        }

        return response()->noContent();
    }
}
