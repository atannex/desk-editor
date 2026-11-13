<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Blog\Http\Controllers;

use Atannex\DeskEditor\Blog\Support\Blog;
use Atannex\DeskEditor\Blog\Support\FeedBuilder;
use Atannex\DeskEditor\Models\DeskArticle;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class FeedController extends Controller
{
    public function __construct(private readonly FeedBuilder $builder) {}

    public function rss(Request $request): Response
    {
        $locale = Blog::locale($request);

        return $this->xml($this->builder->rss($this->items($locale), $locale), 'application/rss+xml');
    }

    public function atom(Request $request): Response
    {
        $locale = Blog::locale($request);

        return $this->xml($this->builder->atom($this->items($locale), $locale), 'application/atom+xml');
    }

    public function sitemap(): Response
    {
        return $this->xml($this->builder->sitemap(), 'application/xml');
    }

    /**
     * @return iterable<DeskArticle>
     */
    private function items(?string $locale): iterable
    {
        return Blog::articles($locale)->limit(max(1, (int) config('desk-editor.blog.feed_items', 30)))->get();
    }

    private function xml(string $body, string $type): Response
    {
        return response($body, 200, [
            'Content-Type' => $type.'; charset=UTF-8',
            'Cache-Control' => 'public, max-age=600',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
