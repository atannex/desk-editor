<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Support\Unfurl;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Fetches Open Graph / meta information for link preview cards.
 *
 * Hardened against SSRF: only http(s), the host must resolve exclusively to
 * public IP addresses, redirects are followed manually and re-validated, and
 * responses are size- and time-limited.
 */
class Unfurler
{
    private const MAX_BYTES = 1_048_576;

    private const MAX_REDIRECTS = 3;

    /**
     * @var (callable(string): list<string>)|null
     */
    private $resolver;

    /**
     * @param  (callable(string): list<string>)|null  $resolver  host → IP list (overridable for tests)
     */
    public function __construct(?callable $resolver = null)
    {
        $this->resolver = $resolver;
    }

    /**
     * @return array{url: string, title: string, description: string|null, image: string|null, site: string, favicon: string|null}|null
     */
    public function unfurl(string $url): ?array
    {
        $url = trim($url);
        if (! $this->isPublicUrl($url)) {
            return null;
        }

        return Cache::remember('desk-editor:unfurl:'.sha1($url), now()->addDay(), fn () => $this->fetch($url));
    }

    public function isPublicUrl(string $url): bool
    {
        return $this->publicIp($url) !== null;
    }

    /**
     * The validated public IP the URL's host resolves to, or null when unsafe.
     */
    public function publicIp(string $url): ?string
    {
        $parts = parse_url($url);
        if (! is_array($parts) || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || empty($parts['host'])) {
            return null;
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }
        if (isset($parts['port']) && ! in_array((int) $parts['port'], [80, 443, 8080, 8443], true)) {
            return null;
        }

        $host = trim($parts['host'], '[]');
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $this->resolve($host);

        if ($ips === []) {
            return null;
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return null;
            }
        }

        return $ips[0];
    }

    /**
     * @return array{url: string, title: string, description: string|null, image: string|null, site: string, favicon: string|null}|null
     */
    private function fetch(string $url): ?array
    {
        try {
            $current = $url;
            for ($i = 0; $i <= self::MAX_REDIRECTS; $i++) {
                $ip = $this->publicIp($current);
                if ($ip === null) {
                    return null;
                }
                $parts = (array) parse_url($current);
                $port = (int) ($parts['port'] ?? (strtolower((string) ($parts['scheme'] ?? '')) === 'https' ? 443 : 80));
                $pin = str_contains($ip, ':') ? "[{$ip}]" : $ip;

                // Pin the connection to the validated address (defeats DNS rebinding).
                $response = Http::withOptions(['allow_redirects' => false, 'stream' => true, 'curl' => [CURLOPT_RESOLVE => [($parts['host'] ?? '').":{$port}:{$pin}"]]])
                    ->withHeaders(['User-Agent' => 'DeskEditorBot/1.0 (+link preview)', 'Accept' => 'text/html,application/xhtml+xml'])
                    ->connectTimeout(3)
                    ->timeout(6)
                    ->get($current);

                if ($response->redirect()) {
                    $location = $this->absolute((string) $response->header('Location'), $current);
                    if (! $location) {
                        return null;
                    }
                    $current = $location;

                    continue;
                }

                if (! $response->successful() || ! str_contains(strtolower($response->header('Content-Type')), 'html')) {
                    return null;
                }

                $body = $response->toPsrResponse()->getBody();
                $html = '';
                while (! $body->eof() && strlen($html) < self::MAX_BYTES) {
                    $html .= $body->read(65536);
                }

                return $this->parse(substr($html, 0, self::MAX_BYTES), $current);
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    /**
     * @return array{url: string, title: string, description: string|null, image: string|null, site: string, favicon: string|null}|null
     */
    public function parse(string $html, string $url): ?array
    {
        if (trim($html) === '') {
            return null;
        }

        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($dom);

        $meta = function (array $names) use ($xpath): ?string {
            foreach ($names as $name) {
                foreach (['property', 'name'] as $attr) {
                    $node = $xpath->query("//meta[@{$attr}='{$name}']/@content")->item(0);
                    if ($node && trim((string) $node->nodeValue) !== '') {
                        return trim((string) $node->nodeValue);
                    }
                }
            }

            return null;
        };

        $title = $meta(['og:title', 'twitter:title']) ?? trim((string) $xpath->query('//title')->item(0)?->textContent);
        if ($title === '') {
            return null;
        }

        $host = (string) parse_url($url, PHP_URL_HOST);
        $image = $meta(['og:image', 'og:image:url', 'twitter:image']);
        $icon = $xpath->query("//link[contains(@rel,'icon')]/@href")->item(0)?->nodeValue;

        return [
            'url' => $meta(['og:url']) && $this->isHttp((string) $meta(['og:url'])) ? (string) $meta(['og:url']) : $url,
            'title' => Str::limit(html_entity_decode($title, ENT_QUOTES | ENT_HTML5), 200),
            'description' => ($d = $meta(['og:description', 'twitter:description', 'description'])) ? Str::limit(html_entity_decode($d, ENT_QUOTES | ENT_HTML5), 300) : null,
            'image' => $image ? $this->absolute($image, $url) : null,
            'site' => Str::limit($meta(['og:site_name']) ?? preg_replace('/^www\./', '', $host) ?? $host, 80),
            'favicon' => $this->absolute($icon ? (string) $icon : '/favicon.ico', $url),
        ];
    }

    private function isHttp(string $url): bool
    {
        return (bool) preg_match('#^https?://#i', $url);
    }

    private function absolute(string $href, string $base): ?string
    {
        $href = trim($href);
        if ($href === '') {
            return null;
        }
        if ($this->isHttp($href)) {
            return $href;
        }
        $parts = parse_url($base);
        if (! is_array($parts) || empty($parts['host'])) {
            return null;
        }
        $origin = ($parts['scheme'] ?? 'https').'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
        if (str_starts_with($href, '//')) {
            return ($parts['scheme'] ?? 'https').':'.$href;
        }
        if (str_starts_with($href, '/')) {
            return $origin.$href;
        }
        $path = isset($parts['path']) ? preg_replace('#/[^/]*$#', '/', $parts['path']) : '/';

        return $origin.$path.$href;
    }

    /**
     * @return list<string>
     */
    private function resolve(string $host): array
    {
        if ($this->resolver) {
            return ($this->resolver)($host);
        }

        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
        $ips = [];
        foreach ($records as $record) {
            $ips[] = (string) ($record['ip'] ?? $record['ipv6'] ?? '');
        }

        return array_values(array_filter($ips));
    }
}
