<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Tests\Unit;

use Atannex\DeskEditor\Facades\DeskEditor;
use Atannex\DeskEditor\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class SanitizerTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function attacks(): array
    {
        return [
            'script tag' => ['<p>Hi</p><script>alert(1)</script>', '<script'],
            'event handler' => ['<img src="/a.png" onerror="alert(1)">', 'onerror'],
            'javascript link' => ['<a href="javascript:alert(1)">x</a>', 'javascript:'],
            'foreign iframe' => ['<div data-embed="youtube" class="de-embed"><iframe src="https://evil.test/x"></iframe></div>', 'evil.test'],
            'css url' => ['<p style="background: url(https://evil.test/x.png)">x</p>', 'url('],
            'css position' => ['<span style="position: fixed; color: red">x</span>', 'position'],
            'arbitrary class' => ['<p class="fixed inset-0 z-50">x</p>', 'inset-0'],
            'style tag' => ['<style>body{display:none}</style><p>x</p>', '<style'],
            'svg payload' => ['<svg><script>alert(1)</script></svg>', 'alert'],
            'form' => ['<form action="https://evil.test"><input name="x"></form>', '<form'],
            'bad data attribute' => ['<div data-callout="<b>x</b>">x</div>', 'data-callout'],
        ];
    }

    #[DataProvider('attacks')]
    public function test_it_strips_dangerous_markup(string $html, string $forbidden): void
    {
        $this->assertStringNotContainsString($forbidden, DeskEditor::sanitize($html));
    }

    public function test_it_keeps_editor_markup(): void
    {
        $html = implode('', [
            '<h2 id="intro" data-toc-id="intro">Intro</h2>',
            '<p style="text-align: center;"><strong>Bold</strong> <span style="color: #dc2626;">red</span> <mark data-color="#fef08a" style="background-color: #fef08a;">hi</mark></p>',
            '<ul data-type="taskList"><li data-type="taskItem" data-checked="true"><label><span></span></label><div><p>Done</p></div></li></ul>',
            '<div data-callout="warning" class="de-callout"><p>Careful</p></div>',
            '<figure data-type="image" data-align="left" class="de-figure"><img src="/storage/a.png" alt="A" width="320"><figcaption>Caption</figcaption></figure>',
            '<figure data-type="video" data-align="center" class="de-media de-media-video"><video src="/storage/v.mp4" controls="true" preload="metadata"></video></figure>',
            '<div data-embed="youtube" class="de-embed"><iframe src="https://www.youtube-nocookie.com/embed/abc"></iframe></div>',
            '<span data-type="inline-math" data-latex="e^{i\pi}"></span>',
            '<pre><code class="language-php">echo 1;</code></pre>',
            '<a href="/files/report.pdf" class="de-attachment" data-attachment="PDF" data-name="report.pdf" data-size="1.2 MB">report.pdf</a>',
        ]);

        $clean = DeskEditor::sanitize($html);

        foreach ([
            'data-toc-id="intro"', 'text-align: center', 'color: #dc2626', 'data-checked="true"', 'data-callout="warning"',
            'data-align="left"', '<figcaption>Caption</figcaption>', '<video src="/storage/v.mp4"', 'youtube-nocookie.com/embed/abc',
            'sandbox=', 'data-latex="e^{i\pi}"', 'class="language-php"', 'data-attachment="PDF"',
        ] as $expected) {
            $this->assertStringContainsString($expected, $clean);
        }
    }

    public function test_empty_input_returns_empty_string(): void
    {
        $this->assertSame('', DeskEditor::sanitize(null));
        $this->assertSame('', DeskEditor::sanitize("  \n "));
    }
}
