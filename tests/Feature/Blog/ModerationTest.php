<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Tests\Feature\Blog;

use Atannex\DeskEditor\Blog\Enums\CommentStatus;
use Atannex\DeskEditor\Blog\Models\DeskComment;
use Atannex\DeskEditor\Models\DeskArticle;
use Illuminate\Support\Facades\Gate;

class ModerationTest extends BlogTestCase
{
    private function comment(DeskArticle $article, CommentStatus $status = CommentStatus::Pending, string $body = 'A reader comment'): DeskComment
    {
        return DeskComment::create([
            'article_id' => $article->id,
            'guest_name' => 'Reader',
            'guest_email' => 'reader@example.com',
            'body' => $body,
            'status' => $status,
        ]);
    }

    public function test_guests_cannot_access_moderation(): void
    {
        $this->getJson('/desk/comments')->assertUnauthorized();
        $this->getJson('/desk/analytics')->assertUnauthorized();
    }

    public function test_moderate_comments_gate_is_enforced(): void
    {
        Gate::define('desk-articles.moderate-comments', fn () => false);
        $comment = $this->comment($this->article());

        $this->actingAs($this->user());
        $this->get('/desk/comments')->assertForbidden();
        $this->post('/desk/comments/'.$comment->id.'/approve')->assertForbidden();
        $this->assertSame(CommentStatus::Pending, $comment->fresh()->status);
    }

    public function test_index_lists_tabs_counts_and_search(): void
    {
        $article = $this->article(['title' => 'Moderated Story']);
        $this->comment($article, CommentStatus::Pending, 'Pending thoughts');
        $this->comment($article, CommentStatus::Approved, 'Approved thoughts');
        $this->comment($article, CommentStatus::Spam, 'Spammy thoughts');

        $this->actingAs($this->user());

        $this->get('/desk/comments')->assertOk()
            ->assertSee('Pending thoughts')->assertDontSee('Approved thoughts')
            ->assertSee('Moderated Story')
            ->assertSee('reader@example.com');
        $this->get('/desk/comments?status=approved')->assertOk()->assertSee('Approved thoughts')->assertDontSee('Pending thoughts');
        $this->get('/desk/comments?status=all&q=spammy')->assertOk()->assertSee('Spammy thoughts')->assertDontSee('Approved thoughts');
        $this->get('/desk/comments?status=trash')->assertOk()->assertSee(__('desk-editor::blog.moderation.empty_title'));
    }

    public function test_actions_update_status_and_comment_counts(): void
    {
        $article = $this->article();
        $comment = $this->comment($article);
        $this->actingAs($this->user());

        $this->post('/desk/comments/'.$comment->id.'/approve')->assertRedirect()->assertSessionHas('desk-status');
        $this->assertSame(CommentStatus::Approved, $comment->fresh()->status);
        $this->assertSame(1, $article->fresh()->comments_count);

        $this->post('/desk/comments/'.$comment->id.'/spam');
        $this->assertSame(CommentStatus::Spam, $comment->fresh()->status);
        $this->assertSame(0, $article->fresh()->comments_count);

        $this->post('/desk/comments/'.$comment->id.'/trash');
        $this->assertSame(CommentStatus::Trash, $comment->fresh()->status);

        $this->post('/desk/comments/'.$comment->id.'/restore');
        $this->assertSame(CommentStatus::Pending, $comment->fresh()->status);

        $this->delete('/desk/comments/'.$comment->id)->assertRedirect();
        $this->assertNull($comment->fresh());
    }

    public function test_staff_reply_approves_parent_and_is_public(): void
    {
        $article = $this->article();
        $comment = $this->comment($article, CommentStatus::Pending, 'Question from a reader');
        $this->actingAs($this->user());

        $this->post('/desk/comments/'.$comment->id.'/reply', ['body' => 'Thanks for asking!'])->assertRedirect();

        $reply = DeskComment::query()->where('is_staff', true)->firstOrFail();
        $this->assertSame($comment->id, $reply->parent_id);
        $this->assertSame(CommentStatus::Approved, $reply->status);
        $this->assertSame(CommentStatus::Approved, $comment->fresh()->status);
        $this->assertSame(2, $article->fresh()->comments_count);

        $this->post('/desk/comments/'.$comment->id.'/reply', ['body' => ''])->assertSessionHasErrors('body');

        auth()->logout();
        $this->get('/blog/'.$article->slug)->assertOk()->assertSee('Thanks for asking!')->assertSee(__('desk-editor::blog.comments.staff'));
    }

    public function test_bulk_actions(): void
    {
        $article = $this->article();
        $a = $this->comment($article);
        $b = $this->comment($article);
        $c = $this->comment($article);
        $this->actingAs($this->user());

        $this->post('/desk/comments/bulk', ['action' => 'approve', 'ids' => [$a->id, $b->id]])->assertRedirect();
        $this->assertSame(2, $article->fresh()->comments_count);
        $this->assertSame(CommentStatus::Pending, $c->fresh()->status);

        $this->post('/desk/comments/bulk', ['action' => 'delete', 'ids' => [$a->id, $c->id]]);
        $this->assertSame(1, DeskComment::count());
        $this->assertSame(1, $article->fresh()->comments_count);

        $this->post('/desk/comments/bulk', ['action' => 'explode', 'ids' => [$b->id]])->assertSessionHasErrors('action');
    }
}
