<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Tests\Feature\Blog;

use Atannex\DeskEditor\Enums\ArticleStatus;

class FeedsTest extends BlogTestCase
{
    private function xml(string $content): \SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $this->assertNotFalse($xml, 'Invalid XML: '.json_encode(array_map(fn ($e) => $e->message, $errors)));

        return $xml;
    }

    public function test_rss_feed_is_valid_with_full_content(): void
    {
        $this->article(['title' => 'Feed & Friends', 'excerpt' => 'Tricky ]]> excerpt', 'body' => '<p>Body <img src="/img/a.png"></p>', 'category_id' => $this->category('News')->id], ['rss'], $this->user());
        $this->article(['title' => 'Hidden Draft', 'status' => ArticleStatus::Draft]);

        $response = $this->get('/blog/feed')->assertOk()->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8');
        $xml = $this->xml($response->getContent());

        $this->assertSame('The Desk Journal', (string) $xml->channel->title);
        $this->assertCount(1, $xml->channel->item);
        $this->assertSame('Feed & Friends', (string) $xml->channel->item[0]->title);
        $content = (string) $xml->channel->item[0]->children('content', true)->encoded;
        $this->assertSame('Tricky ]]> excerpt', (string) $xml->channel->item[0]->description);
        $this->assertStringContainsString(url('/img/a.png'), $content);
        $this->assertStringContainsString('<![CDATA[', $response->getContent());
        $this->assertStringNotContainsString('Hidden Draft', $response->getContent());
    }

    public function test_atom_feed_is_valid(): void
    {
        $this->article(['title' => 'Atom Entry']);

        $response = $this->get('/blog/atom')->assertOk()->assertHeader('Content-Type', 'application/atom+xml; charset=UTF-8');
        $xml = $this->xml($response->getContent());

        $this->assertSame('Atom Entry', (string) $xml->entry[0]->title);
        $this->assertNotEmpty((string) $xml->entry[0]->content);
    }

    public function test_sitemap_lists_articles_categories_and_tags(): void
    {
        $article = $this->article(['title' => 'Mapped', 'category_id' => $this->category('Design')->id], ['maps']);
        $this->article(['title' => 'Unmapped', 'status' => ArticleStatus::Draft]);

        $response = $this->get('/blog/sitemap.xml')->assertOk();
        $xml = $this->xml($response->getContent());
        $locs = array_map('strval', $xml->xpath('//*[local-name()="loc"]') ?: []);

        $this->assertContains(url('/blog'), $locs);
        $this->assertContains(url('/blog/'.$article->slug), $locs);
        $this->assertContains(url('/blog/category/design'), $locs);
        $this->assertContains(url('/blog/tag/maps'), $locs);
        $this->assertNotContains(url('/blog/unmapped'), $locs);
        $this->assertStringContainsString('<lastmod>', $response->getContent());
    }
}
