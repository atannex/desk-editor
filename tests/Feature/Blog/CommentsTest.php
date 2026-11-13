<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Tests\Feature\Blog;

use Atannex\DeskEditor\Blog\Enums\CommentStatus;
use Atannex\DeskEditor\Blog\Models\DeskComment;
use Atannex\DeskEditor\Enums\ArticleStatus;

class CommentsTest extends BlogTestCase
{
    public function test_guest_comment_is_pending_under_manual_moderation(): void
    {
        $article = $this->article();

        $this->post('/blog/'.$article->slug.'/comments', $this->commentPayload())
            ->assertRedirect()
            ->assertSessionHas('desk-comment', 'pending');

        $comment = DeskComment::firstOrFail();
        $this->assertSame(CommentStatus::Pending, $comment->status);
        $this->assertSame('Grace Hopper', $comment->guest_name);
        $this->assertNotNull($comment->ip_hash);
        $this->assertSame(0, $article->fresh()->comments_count);

        // Pending comments are not public; the email never is.
        $this->get('/blog/'.$article->slug)->assertOk()->assertDontSee('What a lovely read.')->assertDontSee('grace@example.com');
    }

    public function test_awaiting_moderation_notice_is_shown_to_the_author(): void
    {
        $article = $this->article();

        $this->withSession(['desk-comment' => 'pending'])
            ->get('/blog/'.$article->slug)
            ->assertSee(__('desk-editor::blog.comments.pending'));
    }

    public function test_signed_in_readers_and_auto_moderation_are_approved(): void
    {
        $article = $this->article();

        $this->actingAs($this->user())
            ->post('/blog/'.$article->slug.'/comments', ['body' => 'Signed-in thoughts', '_desk_ts' => $this->formToken(5)])
            ->assertSessionHas('desk-comment', 'approved');

        config(['desk-editor.blog.comments.moderation' => 'auto']);
        auth()->logout();
        $this->post('/blog/'.$article->slug.'/comments', $this->commentPayload(['body' => 'Guest auto approved']));

        $this->assertSame(2, DeskComment::approved()->count());
        $this->assertSame(2, $article->fresh()->comments_count);

        $this->get('/blog/'.$article->slug)->assertOk()->assertSee('Signed-in thoughts')->assertSee('Guest auto approved')->assertSee('2 comments');
    }

    public function test_honeypot_submissions_are_discarded(): void
    {
        $article = $this->article();

        $this->post('/blog/'.$article->slug.'/comments', $this->commentPayload(['website' => 'http://spam.example']))
            ->assertRedirect();

        $this->assertSame(0, DeskComment::count());
    }

    public function test_too_fast_and_forged_submissions_are_rejected(): void
    {
        $article = $this->article();

        $this->post('/blog/'.$article->slug.'/comments', $this->commentPayload(['_desk_ts' => $this->formToken(0)]))
            ->assertSessionHasErrors('body');
        $this->post('/blog/'.$article->slug.'/comments', $this->commentPayload(['_desk_ts' => 'forged']))
            ->assertSessionHasErrors('body');
        $this->post('/blog/'.$article->slug.'/comments', $this->commentPayload(['_desk_ts' => null]))
            ->assertSessionHasErrors('body');

        $this->assertSame(0, DeskComment::count());
    }

    public function test_validation_requires_guest_name_and_email(): void
    {
        $article = $this->article();

        $this->post('/blog/'.$article->slug.'/comments', ['body' => 'Hi there', '_desk_ts' => $this->formToken(10)])
            ->assertSessionHasErrors(['name', 'email']);
        $this->post('/blog/'.$article->slug.'/comments', $this->commentPayload(['body' => str_repeat('a', 5001)]))
            ->assertSessionHasErrors('body');
    }

    public function test_link_heavy_comments_are_marked_spam(): void
    {
        $article = $this->article();

        $this->post('/blog/'.$article->slug.'/comments', $this->commentPayload([
            'body' => 'Buy http://a.example http://b.example http://c.example',
        ]));

        $this->assertSame(CommentStatus::Spam, DeskComment::firstOrFail()->status);
    }

    public function test_approved_comments_render_escaped_with_autolinks(): void
    {
        $article = $this->article();
        DeskComment::create([
            'article_id' => $article->id,
            'guest_name' => 'Linus',
            'guest_email' => 'linus@example.com',
            'body' => "<b>bold?</b>\nSee https://example.com/docs.",
            'status' => CommentStatus::Approved,
        ]);

        $this->get('/blog/'.$article->slug)
            ->assertOk()
            ->assertSee('&lt;b&gt;bold?&lt;/b&gt;<br>', false)
            ->assertSee('<a href="https://example.com/docs" rel="nofollow ugc noopener" target="_blank">https://example.com/docs</a>.', false)
            ->assertDontSee('linus@example.com')
            ->assertSee('>L</span>', false);
    }

    public function test_threaded_replies_respect_max_depth(): void
    {
        config(['desk-editor.blog.comments.max_depth' => 2, 'desk-editor.blog.comments.moderation' => 'auto']);
        $article = $this->article();

        $this->post('/blog/'.$article->slug.'/comments', $this->commentPayload(['body' => 'Root comment']));
        $root = DeskComment::firstOrFail();

        $this->post('/blog/'.$article->slug.'/comments', $this->commentPayload(['body' => 'First reply', 'parent_id' => $root->id]));
        $reply = DeskComment::query()->where('body', 'First reply')->firstOrFail();
        $this->assertSame($root->id, $reply->parent_id);
        $this->assertSame(1, $reply->depth);

        // Replying to the deepest level attaches to its parent instead.
        $this->post('/blog/'.$article->slug.'/comments', $this->commentPayload(['body' => 'Too deep', 'parent_id' => $reply->id]));
        $deep = DeskComment::query()->where('body', 'Too deep')->firstOrFail();
        $this->assertSame($root->id, $deep->parent_id);
        $this->assertSame(1, $deep->depth);

        $this->get('/blog/'.$article->slug)->assertOk()->assertSeeInOrder(['Root comment', 'First reply', 'Too deep']);
    }

    public function test_comments_closed_or_disabled(): void
    {
        $article = $this->article(['allow_comments' => false]);

        $this->post('/blog/'.$article->slug.'/comments', $this->commentPayload())->assertForbidden();
        $this->get('/blog/'.$article->slug)->assertOk()->assertSee(__('desk-editor::blog.comments.closed'))->assertDontSee('data-comment-form', false);

        $open = $this->article(['title' => 'Open']);
        config(['desk-editor.blog.comments.enabled' => false]);
        $this->post('/blog/'.$open->slug.'/comments', $this->commentPayload())->assertForbidden();
        $this->get('/blog/'.$open->slug)->assertOk()->assertDontSee('id="comments"', false);

        $this->assertSame(0, DeskComment::count());
    }

    public function test_guests_can_be_disallowed(): void
    {
        config(['desk-editor.blog.comments.guests' => false]);
        $article = $this->article();

        $this->post('/blog/'.$article->slug.'/comments', $this->commentPayload())->assertForbidden();
        $this->get('/blog/'.$article->slug)->assertOk()->assertSee(__('desk-editor::blog.comments.sign_in'));
    }

    public function test_comments_on_unpublished_articles_404(): void
    {
        $article = $this->article(['status' => ArticleStatus::Draft]);

        $this->post('/blog/'.$article->slug.'/comments', $this->commentPayload())->assertNotFound();
    }
}
