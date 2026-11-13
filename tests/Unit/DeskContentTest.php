<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Tests\Unit;

use Atannex\DeskEditor\Support\DeskContent;
use Atannex\DeskEditor\Tests\TestCase;

class DeskContentTest extends TestCase
{
    private function sample(): DeskContent
    {
        return DeskContent::make(
            '<h1 id="a">Getting started</h1><p>Hello <strong>brave</strong> new world.</p>'
            .'<h2>Install &amp; configure</h2><p>Ping <span data-type="mention" data-id="7" data-label="Ada">@Ada</span>.</p>'
            .'<figure data-type="image"><img src="/a.png" alt=""></figure>'
        );
    }

    public function test_text_words_and_characters(): void
    {
        $content = $this->sample();

        $this->assertStringContainsString("Getting started\nHello brave new world.", $content->text());
        $this->assertSame(11, $content->words());
        $this->assertSame(1, $content->readingTime());
        $this->assertGreaterThan(40, $content->characters());
    }

    public function test_outline_images_and_mentions(): void
    {
        $content = $this->sample();

        $this->assertSame([
            ['level' => 1, 'text' => 'Getting started', 'id' => 'a'],
            ['level' => 2, 'text' => 'Install & configure', 'id' => null],
        ], $content->outline());
        $this->assertSame(['/a.png'], $content->images());
        $this->assertSame([['id' => '7', 'label' => 'Ada']], $content->mentions());
    }

    public function test_excerpt_and_emptiness(): void
    {
        $this->assertSame('Getting started Hello…', $this->sample()->excerpt(22));
        $this->assertTrue(DeskContent::make('<p></p><p> </p>')->isEmpty());
        $this->assertFalse(DeskContent::make('<figure><img src="/x.png"></figure>')->isEmpty());
    }

    public function test_it_is_htmlable_and_json_serialisable(): void
    {
        $content = DeskContent::make('<p>x</p>');

        $this->assertSame('<p>x</p>', $content->toHtml());
        $this->assertSame('<p>x</p>', (string) $content);
        $this->assertSame('"<p>x<\/p>"', json_encode($content));
    }
}
