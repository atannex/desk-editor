<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Tests\Feature\Editor;

use Atannex\DeskEditor\Tests\TestCase;

class DraftsTest extends TestCase
{
    public function test_drafts_are_private_per_user_and_round_trip(): void
    {
        $ada = $this->user();
        $bob = $this->user();

        $this->getJson('/desk-editor/drafts/article-1')->assertUnauthorized();

        $this->actingAs($ada)->getJson('/desk-editor/drafts/article-1')->assertOk()->assertJson(['draft' => null]);
        $this->actingAs($ada)->putJson('/desk-editor/drafts/article-1', ['content' => '<p>Work in progress</p>'])->assertOk()->assertJsonStructure(['saved_at']);
        $this->actingAs($ada)->putJson('/desk-editor/drafts/article-1', ['content' => '<p>Second save</p>'])->assertOk();

        $this->actingAs($ada)->getJson('/desk-editor/drafts/article-1')->assertJsonPath('draft.content', '<p>Second save</p>');
        $this->actingAs($bob)->getJson('/desk-editor/drafts/article-1')->assertJson(['draft' => null]);
        $this->assertDatabaseCount('desk_drafts', 1);

        $this->actingAs($ada)->deleteJson('/desk-editor/drafts/article-1')->assertNoContent();
        $this->assertDatabaseCount('desk_drafts', 0);
    }

    public function test_draft_keys_are_validated(): void
    {
        $this->actingAs($this->user())->putJson('/desk-editor/drafts/'.str_repeat('x', 200), ['content' => 'x'])->assertNotFound();
    }
}
