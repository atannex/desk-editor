<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Tests\Feature\Articles;

use Atannex\DeskEditor\Articles\Support\Differ;
use Atannex\DeskEditor\Enums\ArticleStatus;
use Atannex\DeskEditor\Models\DeskArticle;
use Atannex\DeskEditor\Tests\TestCase;

class RevisionDiffTest extends TestCase
{
    public function test_word_diff(): void
    {
        $diff = (new Differ)->words('The quick brown fox jumps', 'The quick red fox leaps high');

        $this->assertSame(3, $diff['added']);
        $this->assertSame(2, $diff['removed']);
        $old = (new Differ)->render($diff['segments'], [Differ::EQUAL, Differ::DELETE]);
        $new = (new Differ)->render($diff['segments'], [Differ::EQUAL, Differ::INSERT]);
        $this->assertSame('The quick brown fox jumps', strip_tags($old));
        $this->assertSame('The quick red fox leaps high', strip_tags($new));
        $this->assertStringContainsString('>brown</del>', $old);
        $this->assertStringContainsString('>red</ins>', $new);
    }

    public function test_diff_is_minimal_and_lossless_on_random_input(): void
    {
        $differ = new Differ;
        $words = ['alpha', 'beta', 'gamma', 'delta', 'and', 'the', ','];
        mt_srand(42);
        for ($run = 0; $run < 60; $run++) {
            $a = $b = [];
            for ($i = 0, $n = mt_rand(0, 30); $i < $n; $i++) {
                $a[] = $words[mt_rand(0, 6)];
            }
            for ($i = 0, $n = mt_rand(0, 30); $i < $n; $i++) {
                $b[] = $words[mt_rand(0, 6)];
            }
            $ops = $differ->diff($a, $b);
            $this->assertSame($a, array_values(array_map(fn ($o) => $o[1], array_filter($ops, fn ($o) => $o[0] !== Differ::INSERT))));
            $this->assertSame($b, array_values(array_map(fn ($o) => $o[1], array_filter($ops, fn ($o) => $o[0] !== Differ::DELETE))));
            $edits = count(array_filter($ops, fn ($o) => $o[0] !== Differ::EQUAL));
            $this->assertSame(count($a) + count($b) - 2 * $this->lcs($a, $b), $edits);
        }
    }

    public function test_html_is_compared_block_by_block(): void
    {
        $result = (new Differ)->html(
            '<h2>Intro</h2><p>First paragraph stays.</p><p>Second is <strong>edited</strong> here.</p><ul><li>Gone</li></ul>',
            '<h2>Intro</h2><p>First paragraph stays.</p><p>Second was edited here.</p><p>Brand new block.</p>',
        );

        $types = array_column($result['rows'], 'type');
        $this->assertSame([Differ::EQUAL, Differ::EQUAL, Differ::CHANGE, Differ::CHANGE], $types);
        $this->assertSame('## Intro', $result['rows'][0]['inline']);
        $this->assertStringContainsString('<del', $result['rows'][2]['inline']);
        $this->assertStringContainsString('>was</ins>', $result['rows'][2]['inline']);
        $this->assertGreaterThan(0, $result['added']);
        $this->assertGreaterThan(0, $result['removed']);
    }

    public function test_diff_escapes_content(): void
    {
        $result = (new Differ)->text('<b>safe</b>', '<script>x</script> safe');

        $this->assertStringNotContainsString('<script>', $result['rows'][0]['inline']);
        $this->assertStringContainsString('&lt;', $result['rows'][0]['inline']);
        $this->assertStringContainsString('script', strip_tags($result['rows'][0]['inline']));
    }

    public function test_compare_page_against_current_and_between_revisions(): void
    {
        $this->actingAs($this->user());
        $article = DeskArticle::create(['title' => 'Original title', 'body' => '<p>The quick brown fox.</p>', 'status' => ArticleStatus::Draft]);
        $first = $article->revisions()->firstOrFail();
        $article->update(['title' => 'Better title', 'body' => '<p>The quick red fox.</p><p>Extra line.</p>']);
        $second = $article->revisions()->firstOrFail();

        $this->get(route('desk-editor.articles.revisions.compare', [$article, 'from' => $first->id, 'to' => 'current']))
            ->assertOk()
            ->assertSee('data-diff', false)
            ->assertSee('>brown</del>', false)
            ->assertSee('>red</ins>', false)
            ->assertSee('Extra line.')
            ->assertSee(__('desk-editor::articles.diff.split'))
            ->assertSee(route('desk-editor.articles.revisions.restore', [$article, $first->id]));

        $this->get(route('desk-editor.articles.revisions.compare', [$article, 'from' => $first->id, 'to' => $second->id, 'mode' => 'split']))
            ->assertOk()
            ->assertSee('data-mode="split"', false)
            ->assertSee('>Original</del>', false);

        // Defaults to "previous revision vs current".
        $this->get(route('desk-editor.articles.revisions.compare', $article))->assertOk()->assertSee('>Better</ins>', false);

        $this->get(route('desk-editor.articles.revisions.compare', [$article, 'from' => 99999]))->assertNotFound();
    }

    public function test_history_panel_links_to_compare(): void
    {
        $this->actingAs($this->user());
        $article = DeskArticle::create(['title' => 'One', 'status' => ArticleStatus::Draft]);
        $article->update(['title' => 'Two']);

        $this->get(route('desk-editor.articles.edit', $article))->assertOk()
            ->assertSee(route('desk-editor.articles.revisions.compare', $article));
    }

    private function lcs(array $a, array $b): int
    {
        $prev = array_fill(0, count($b) + 1, 0);
        foreach ($a as $x) {
            $row = [0];
            foreach ($b as $j => $y) {
                $row[$j + 1] = $x === $y ? $prev[$j] + 1 : max($prev[$j + 1], $row[$j]);
            }
            $prev = $row;
        }

        return $prev[count($b)];
    }
}
