<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Tests\Feature\Editor;

use Atannex\DeskEditor\Facades\DeskEditor;
use Atannex\DeskEditor\Support\ContentRenderer;
use Atannex\DeskEditor\Tests\TestCase;
use Illuminate\Support\Facades\Blade;

class ReaderContentTest extends TestCase
{
    public function test_pending_suggestions_and_comment_anchors_are_hidden_from_readers(): void
    {
        $html = '<p>The <ins data-suggestion="s-1">very </ins>quick <span data-comment="c-1">brown</span> fox jumps over the<del data-suggestion="s-2"> lazy</del> dog.</p>';

        $out = app(ContentRenderer::class)->render($html);

        $this->assertSame('<p>The quick brown fox jumps over the lazy dog.</p>', $out);
    }

    public function test_content_component_applies_the_renderer(): void
    {
        $html = Blade::render('<x-desk-editor::content :html="$html" />', ['html' => '<p>Hi <span data-comment="c-9">there</span></p>']);

        $this->assertStringContainsString('<p>Hi there</p>', $html);
        $this->assertStringNotContainsString('data-comment', $html);
    }

    public function test_new_blocks_survive_sanitisation(): void
    {
        $html = implode('', [
            '<div data-type="columns" data-layout="2-1" class="de-columns"><div data-type="column" class="de-column"><p>A</p></div><div data-type="column" class="de-column"><p>B</p></div></div>',
            '<figure data-type="gallery" data-columns="3" class="de-gallery"><figure class="de-gallery-item"><img src="/a.jpg" alt="a"><figcaption>Cap</figcaption></figure></figure>',
            '<p>Text<sup data-type="footnote" data-content="Source &amp; notes" class="de-footnote"></sup></p>',
            '<div data-type="button" data-align="center" class="de-cta-wrap"><a href="https://x.test" class="de-cta" data-variant="primary">Go</a></div>',
            '<div data-type="page-break" class="de-page-break"></div>',
            '<div data-type="diagram" data-code="graph TD; A--&gt;B" class="de-diagram"></div>',
            '<figure data-type="chart" data-chart=\'{"type":"bar","labels":["Q1"],"datasets":[{"label":"x","values":[1]}]}\' class="de-chart"><table class="de-chart-data"><tbody><tr><th>Q1</th><td>1</td></tr></tbody></table></figure>',
            '<a data-type="bookmark" class="de-bookmark" href="https://laravel.com"><span class="de-bookmark-body"><span class="de-bookmark-title">Laravel</span></span></a>',
            '<p data-clear="true">Below the float</p>',
            '<table><tbody><tr><td style="background-color: #fef3c7; vertical-align: middle">c</td></tr></tbody></table>',
            '<div data-embed="spotify" class="de-embed de-embed-fixed" style="height: 152px"><iframe src="https://open.spotify.com/embed/track/abc"></iframe></div>',
        ]);

        $clean = DeskEditor::sanitize($html);

        foreach ([
            'data-layout="2-1"', 'data-type="column"', 'data-columns="3"', '<figcaption>Cap</figcaption>', 'data-content="Source &amp; notes"',
            'data-variant="primary"', 'data-type="page-break"', 'data-code="graph TD; A--&gt;B"', 'data-chart=', 'de-bookmark-title',
            'data-clear="true"', 'background-color: #fef3c7', 'vertical-align: middle', 'open.spotify.com/embed/track/abc', 'height: 152px',
        ] as $expected) {
            $this->assertStringContainsString($expected, $clean, $expected);
        }
    }
}
