<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Tests\Feature\Editor;

use Atannex\DeskEditor\Support\Unfurl\Unfurler;
use Atannex\DeskEditor\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;

class UnfurlTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function unsafeUrls(): array
    {
        return [
            'loopback' => ['http://127.0.0.1/admin'],
            'private 10/8' => ['http://10.0.0.5/'],
            'private 192.168' => ['http://192.168.1.1/'],
            'link-local metadata' => ['http://169.254.169.254/latest/meta-data/'],
            'ipv6 loopback' => ['http://[::1]/'],
            'file scheme' => ['file:///etc/passwd'],
            'gopher' => ['gopher://example.com/'],
            'credentials' => ['http://user:pass@example.com/'],
            'odd port' => ['http://example.com:6379/'],
            'host resolving privately' => ['http://internal.example.test/'],
        ];
    }

    #[DataProvider('unsafeUrls')]
    public function test_it_refuses_non_public_targets(string $url): void
    {
        $unfurler = new Unfurler(fn (string $host) => $host === 'internal.example.test' ? ['10.1.2.3'] : ['93.184.216.34']);

        $this->assertFalse($unfurler->isPublicUrl($url));
        $this->assertNull($unfurler->unfurl($url));
    }

    public function test_it_parses_open_graph_metadata(): void
    {
        $html = '<html><head><title>Fallback</title><meta property="og:title" content="Desk &amp; Co"><meta property="og:description" content="Writing tools"><meta property="og:image" content="/cover.png"><meta property="og:site_name" content="Desk"><link rel="icon" href="/favicon.png"></head></html>';

        $data = (new Unfurler(fn () => ['93.184.216.34']))->parse($html, 'https://desk.example.com/post/1');

        $this->assertSame('Desk & Co', $data['title']);
        $this->assertSame('Writing tools', $data['description']);
        $this->assertSame('https://desk.example.com/cover.png', $data['image']);
        $this->assertSame('Desk', $data['site']);
        $this->assertSame('https://desk.example.com/favicon.png', $data['favicon']);
    }

    public function test_redirects_to_private_addresses_are_not_followed(): void
    {
        Http::fake([
            'public.example.com/*' => Http::response('', 302, ['Location' => 'http://169.254.169.254/']),
            '*' => Http::response('<title>secret</title>', 200, ['Content-Type' => 'text/html']),
        ]);

        $this->app->instance(Unfurler::class, new Unfurler(fn () => ['93.184.216.34']));

        $this->actingAs($this->user())->getJson('/desk-editor/unfurl?url='.urlencode('https://public.example.com/start'))->assertUnprocessable();
    }

    public function test_endpoint_requires_authentication(): void
    {
        $this->getJson('/desk-editor/unfurl?url=https://example.com')->assertUnauthorized();
    }
}
