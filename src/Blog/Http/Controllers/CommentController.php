<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Blog\Http\Controllers;

use Atannex\DeskEditor\Blog\Enums\CommentStatus;
use Atannex\DeskEditor\Blog\Http\Requests\StoreCommentRequest;
use Atannex\DeskEditor\Blog\Models\DeskComment;
use Atannex\DeskEditor\Blog\Support\Blog;
use Atannex\DeskEditor\Models\DeskArticle;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Stores reader comments.
 *
 * Spam protection: a hidden honeypot field (`website`) silently discards bot
 * submissions, an encrypted form timestamp rejects posts submitted faster than
 * MIN_SECONDS (or with a missing/forged token), the route is throttled by
 * `blog.comments.middleware`, and comments with many links are marked spam.
 *
 * Moderation: with `blog.comments.moderation = auto` everything is approved;
 * with `manual`, guest comments wait in "pending" while signed-in readers are
 * approved immediately (they are accountable through their account).
 */
class CommentController extends Controller
{
    public const MIN_SECONDS = 3;

    public const MAX_LINKS = 2;

    public function store(StoreCommentRequest $request, string $slug): RedirectResponse
    {
        $article = Blog::model()::query()->published()->where('slug', $slug)
            ->when($request->query('lang'), fn ($q, $lang) => $q->where('locale', $lang))
            ->firstOrFail();
        $back = Blog::url($article);

        abort_unless(config('desk-editor.blog.comments.enabled', true) && $article->allow_comments, 403, __('desk-editor::blog.comments.closed'));

        $user = $request->user();
        abort_if($user === null && ! config('desk-editor.blog.comments.guests', true), 403, __('desk-editor::blog.comments.sign_in'));

        // Honeypot: pretend everything went fine so bots learn nothing.
        if (filled($request->input('website'))) {
            return redirect()->to($back.'#comments')->with('desk-comment', 'pending');
        }

        $this->guardTiming($request->input('_desk_ts'));

        $parent = $this->parent($article, $request->integer('parent_id') ?: null);
        $body = Str::of((string) $request->input('body'))->replace("\r\n", "\n")->trim()->toString();

        $status = match (true) {
            DeskComment::linkCount($body) > self::MAX_LINKS => CommentStatus::Spam,
            config('desk-editor.blog.comments.moderation', 'manual') === 'auto' => CommentStatus::Approved,
            $user !== null => CommentStatus::Approved,
            default => CommentStatus::Pending,
        };

        $comment = DeskComment::query()->create([
            'article_id' => $article->getKey(),
            'parent_id' => $parent?->getKey(),
            'depth' => $parent ? $parent->depth + 1 : 0,
            'user_type' => $user instanceof Model ? $user->getMorphClass() : null,
            'user_id' => $user instanceof Model ? $user->getKey() : null,
            'guest_name' => $user ? null : Str::limit(trim((string) $request->input('name')), 80, ''),
            'guest_email' => $user ? null : Str::lower(trim((string) $request->input('email'))),
            'body' => $body,
            'status' => $status,
            'ip_hash' => $request->ip() ? hash_hmac('sha256', (string) $request->ip(), (string) config('app.key')) : null,
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
        ]);

        DeskComment::syncCount((int) $article->getKey());

        return redirect()
            ->to($back.($status === CommentStatus::Approved ? '#comment-'.$comment->id : '#comments'))
            ->with('desk-comment', $status === CommentStatus::Approved ? 'approved' : 'pending');
    }

    private function guardTiming(mixed $token): void
    {
        try {
            $issued = is_string($token) ? (int) Crypt::decryptString($token) : 0;
        } catch (DecryptException) {
            $issued = 0;
        }

        $elapsed = now()->getTimestamp() - $issued;
        if ($issued <= 0 || $elapsed < self::MIN_SECONDS || $elapsed > 86400) {
            throw ValidationException::withMessages([
                'body' => $issued > 0 && $elapsed < self::MIN_SECONDS ? __('desk-editor::blog.comments.too_fast') : __('desk-editor::blog.comments.expired'),
            ])->redirectTo(url()->previous().'#comment-form');
        }
    }

    /**
     * Resolve the reply target; replies beyond max_depth attach to the deepest allowed ancestor.
     */
    private function parent(DeskArticle $article, ?int $id): ?DeskComment
    {
        if (! $id) {
            return null;
        }

        $parent = DeskComment::query()->where('article_id', $article->getKey())->approved()->find($id);
        if (! $parent instanceof DeskComment) {
            return null;
        }

        $maxDepth = max(1, (int) config('desk-editor.blog.comments.max_depth', 3));
        while ($parent->depth + 1 >= $maxDepth && $parent->parent_id) {
            $next = $parent->parent;
            if (! $next instanceof DeskComment) {
                break;
            }
            $parent = $next;
        }

        return $parent->depth + 1 >= $maxDepth ? null : $parent;
    }
}
