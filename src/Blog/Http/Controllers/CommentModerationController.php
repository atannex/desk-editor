<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Blog\Http\Controllers;

use Atannex\DeskEditor\Blog\Enums\CommentStatus;
use Atannex\DeskEditor\Blog\Models\DeskComment;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CommentModerationController extends Controller
{
    public const GATE = 'desk-articles.moderate-comments';

    public function index(Request $request): View
    {
        $this->authorizeModeration();

        $status = CommentStatus::tryFrom((string) $request->query('status', CommentStatus::Pending->value));
        $term = trim((string) $request->query('q', ''));

        $base = DeskComment::query()->search($term);
        $counts = (clone $base)->toBase()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');

        $comments = $base
            ->when($status, fn (Builder $q, CommentStatus $s) => $q->where('status', $s->value))
            ->with(['article', 'user', 'parent'])
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('desk-editor::comments.index', [
            'comments' => $comments,
            'counts' => $counts,
            'total' => (int) $counts->sum(),
            'status' => $status,
            'term' => $term,
        ]);
    }

    public function approve(DeskComment $comment): RedirectResponse
    {
        return $this->transition($comment, CommentStatus::Approved, 'approved');
    }

    public function spam(DeskComment $comment): RedirectResponse
    {
        return $this->transition($comment, CommentStatus::Spam, 'spammed');
    }

    public function trash(DeskComment $comment): RedirectResponse
    {
        return $this->transition($comment, CommentStatus::Trash, 'trashed');
    }

    public function restore(DeskComment $comment): RedirectResponse
    {
        return $this->transition($comment, CommentStatus::Pending, 'restored');
    }

    public function destroy(DeskComment $comment): RedirectResponse
    {
        $this->authorizeModeration();
        $articleId = $comment->article_id;
        $comment->delete();
        DeskComment::syncCount($articleId);

        return back()->with('desk-status', __('desk-editor::blog.moderation.flash.deleted'));
    }

    public function reply(Request $request, DeskComment $comment): RedirectResponse
    {
        $this->authorizeModeration();
        $data = $request->validate(['body' => ['required', 'string', 'min:1', 'max:5000']]);
        $user = $request->user();

        // Replying approves the comment being answered.
        if ($comment->status !== CommentStatus::Approved) {
            $comment->update(['status' => CommentStatus::Approved]);
        }

        $maxDepth = max(1, (int) config('desk-editor.blog.comments.max_depth', 3));
        $parent = $comment->depth + 1 >= $maxDepth ? $comment->parent : $comment;

        DeskComment::query()->create([
            'article_id' => $comment->article_id,
            'parent_id' => $parent?->getKey(),
            'depth' => $parent ? $parent->depth + 1 : 0,
            'user_type' => $user instanceof Model ? $user->getMorphClass() : null,
            'user_id' => $user instanceof Model ? $user->getKey() : null,
            'guest_name' => $user instanceof Model ? null : __('desk-editor::blog.comments.staff'),
            'body' => Str::of($data['body'])->replace("\r\n", "\n")->trim()->toString(),
            'status' => CommentStatus::Approved,
            'is_staff' => true,
        ]);

        DeskComment::syncCount($comment->article_id);

        return back()->with('desk-status', __('desk-editor::blog.moderation.flash.replied'));
    }

    public function bulk(Request $request): RedirectResponse
    {
        $this->authorizeModeration();
        $data = $request->validate([
            'action' => ['required', Rule::in(['approve', 'spam', 'trash', 'restore', 'delete'])],
            'ids' => ['required', 'array', 'max:500'],
            'ids.*' => ['integer'],
        ]);

        $query = DeskComment::query()->whereKey($data['ids']);
        $articleIds = (clone $query)->distinct()->pluck('article_id')->all();
        $count = (clone $query)->count();

        if ($data['action'] === 'delete') {
            $query->delete();
        } else {
            $status = match ($data['action']) {
                'approve' => CommentStatus::Approved,
                'spam' => CommentStatus::Spam,
                'trash' => CommentStatus::Trash,
                default => CommentStatus::Pending,
            };
            $query->update(['status' => $status->value, 'updated_at' => now()]);
        }

        foreach ($articleIds as $articleId) {
            DeskComment::syncCount((int) $articleId);
        }

        return back()->with('desk-status', trans_choice('desk-editor::blog.moderation.flash.bulk', $count, ['count' => $count]));
    }

    private function transition(DeskComment $comment, CommentStatus $status, string $flash): RedirectResponse
    {
        $this->authorizeModeration();
        $comment->update(['status' => $status]);
        DeskComment::syncCount($comment->article_id);

        return back()->with('desk-status', __('desk-editor::blog.moderation.flash.'.$flash));
    }

    private function authorizeModeration(): void
    {
        abort_unless(Gate::allows(self::GATE), 403);
    }
}
