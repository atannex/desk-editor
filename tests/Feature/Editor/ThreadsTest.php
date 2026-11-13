<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Tests\Feature\Editor;

use Atannex\DeskEditor\Tests\TestCase;

class ThreadsTest extends TestCase
{
    public function test_full_comment_thread_lifecycle(): void
    {
        $ada = $this->user();
        $bob = $this->user();

        $thread = $this->actingAs($ada)->postJson('/desk-editor/threads', [
            'document' => 'article:7',
            'anchor' => 'c-abc123',
            'quote' => 'the quick brown fox',
            'body' => 'Can we tighten this sentence?',
        ])->assertCreated()
            ->assertJsonPath('data.anchor', 'c-abc123')
            ->assertJsonPath('data.messages.0.body', 'Can we tighten this sentence?')
            ->assertJsonPath('data.messages.0.author', 'Ada Lovelace')
            ->json('data');

        $this->actingAs($bob)->postJson("/desk-editor/threads/{$thread['id']}/messages", ['body' => 'Done — shortened it.'])
            ->assertOk()->assertJsonCount(2, 'data.messages');

        $this->actingAs($bob)->getJson('/desk-editor/threads?document=article:7')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.can_delete', false);
        $this->actingAs($ada)->getJson('/desk-editor/threads?document=article:8')->assertJsonCount(0, 'data');

        $this->actingAs($bob)->patchJson("/desk-editor/threads/{$thread['id']}", ['resolved' => true])
            ->assertJsonPath('data.resolved', true)->assertJsonPath('data.resolved_by', 'Ada Lovelace');
        $this->actingAs($ada)->patchJson("/desk-editor/threads/{$thread['id']}", ['resolved' => false])
            ->assertJsonPath('data.resolved', false);

        $this->actingAs($bob)->deleteJson("/desk-editor/threads/{$thread['id']}")->assertForbidden();
        $this->actingAs($ada)->deleteJson("/desk-editor/threads/{$thread['id']}")->assertNoContent();
        $this->assertDatabaseCount('desk_thread_messages', 0);
    }

    public function test_anchors_must_be_unique_and_safe(): void
    {
        $user = $this->user();
        $payload = ['document' => 'post:1', 'anchor' => 'c-1', 'body' => 'x'];

        $this->actingAs($user)->postJson('/desk-editor/threads', $payload)->assertCreated();
        $this->actingAs($user)->postJson('/desk-editor/threads', $payload)->assertUnprocessable();
        $this->actingAs($user)->postJson('/desk-editor/threads', [...$payload, 'anchor' => '"><script>'])->assertUnprocessable();
    }

    public function test_guests_cannot_use_threads(): void
    {
        $this->postJson('/desk-editor/threads', ['document' => 'post:1', 'anchor' => 'c-2', 'body' => 'x'])->assertUnauthorized();
        $this->getJson('/desk-editor/threads?document=post:1')->assertUnauthorized();
    }
}
