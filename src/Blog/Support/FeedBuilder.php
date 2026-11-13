<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Blog\Support;

use Atannex\DeskEditor\Models\DeskArticle;
use Atannex\DeskEditor\Models\DeskCategory;
use Atannex\DeskEditor\Models\DeskTag;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds RSS 2.0, Atom 1.0 and sitemap XML documents for the blog.
 */
class FeedBuilder
{
    /**
     * @param  iterable<DeskArticle>  $articles
     */
    public function rss(iterable $articles, ?string $locale = null): string
    {
        $home = route('desk-editor.blog.home');
        $self = route('desk-editor.blog.feed', Blog::params([], $locale));
        $items = '';
        $latest = null;

        foreach ($articles as $article) {
            $latest ??= $article->published_at;
            $url = Blog::url($article);
            $items .= "\n    <item>"
                ."\n      <title>".$this->x($article->title).'</title>'
                ."\n      <link>".$this->x($url).'</link>'
                ."\n      <guid isPermaLink=\"true\">".$this->x($url).'</guid>'
                ."\n      <pubDate>".($article->published_at?->toRssString() ?? '').'</pubDate>'
                .(($author = Blog::authorName($article)) ? "\n      <dc:creator>".$this->cdata($author).'</dc:creator>' : '')
                .($article->category ? "\n      <category>".$this->x($article->category->name).'</category>' : '')
                .$article->tags->map(fn (DeskTag $t) => "\n      <category>".$this->x($t->name).'</category>')->implode('')
                ."\n      <description>".$this->cdata((string) ($article->excerpt ?: $article->subtitle)).'</description>'
                ."\n      <content:encoded>".$this->cdata($this->content($article)).'</content:encoded>'
                .($article->cover_image ? "\n      <enclosure url=\"".$this->x((string) Blog::absolute($article->cover_image)).'" type="'.$this->imageType($article->cover_image).'" length="0"/>' : '')
                ."\n    </item>";
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            ."\n".'<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:dc="http://purl.org/dc/elements/1.1/">'
            ."\n  <channel>"
            ."\n    <title>".$this->x(Blog::name()).'</title>'
            ."\n    <link>".$this->x($home).'</link>'
            ."\n    <description>".$this->x(Blog::description() ?: Blog::name()).'</description>'
            ."\n    <language>".$this->x($locale ?? Blog::defaultLocale()).'</language>'
            ."\n    <lastBuildDate>".($latest ?? now())->toRssString().'</lastBuildDate>'
            ."\n    <generator>Desk Editor</generator>"
            ."\n    ".'<atom:link href="'.$this->x($self).'" rel="self" type="application/rss+xml"/>'
            .$items
            ."\n  </channel>\n</rss>\n";
    }

    /**
     * @param  iterable<DeskArticle>  $articles
     */
    public function atom(iterable $articles, ?string $locale = null): string
    {
        $home = route('desk-editor.blog.home');
        $self = route('desk-editor.blog.atom', Blog::params([], $locale));
        $entries = '';
        $updated = null;

        foreach ($articles as $article) {
            $modified = $article->updated_at ?? $article->published_at ?? now();
            $updated = $updated === null || $modified->gt($updated) ? $modified : $updated;
            $url = Blog::url($article);
            $author = Blog::authorName($article) ?? Blog::name();
            $entries .= "\n  <entry>"
                ."\n    <title type=\"text\">".$this->x($article->title).'</title>'
                ."\n    ".'<link rel="alternate" type="text/html" href="'.$this->x($url).'"/>'
                ."\n    <id>".$this->x($url).'</id>'
                ."\n    <published>".($article->published_at ?? $modified)->toAtomString().'</published>'
                ."\n    <updated>".$modified->toAtomString().'</updated>'
                ."\n    <author><name>".$this->x($author).'</name></author>'
                .($article->category ? "\n    ".'<category term="'.$this->x($article->category->name).'"/>' : '')
                ."\n    <summary type=\"text\">".$this->x((string) ($article->excerpt ?: $article->subtitle)).'</summary>'
                ."\n    <content type=\"html\">".$this->cdata($this->content($article)).'</content>'
                ."\n  </entry>";
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            ."\n".'<feed xmlns="http://www.w3.org/2005/Atom" xml:lang="'.$this->x($locale ?? Blog::defaultLocale()).'">'
            ."\n  <title>".$this->x(Blog::name()).'</title>'
            .(Blog::description() ? "\n  <subtitle>".$this->x(Blog::description()).'</subtitle>' : '')
            ."\n  ".'<link rel="alternate" type="text/html" href="'.$this->x($home).'"/>'
            ."\n  ".'<link rel="self" type="application/atom+xml" href="'.$this->x($self).'"/>'
            ."\n  <id>".$this->x($home).'</id>'
            ."\n  <updated>".($updated ?? now())->toAtomString().'</updated>'
            ."\n  <generator>Desk Editor</generator>"
            .$entries
            ."\n</feed>\n";
    }

    public function sitemap(): string
    {
        $urls = [];
        $urls[] = [route('desk-editor.blog.home'), Blog::articles()->value('published_at'), 'daily', '1.0'];

        /** @var Collection<int, DeskArticle> $articles */
        $articles = Blog::model()::query()->published()->select(['id', 'slug', 'locale', 'updated_at', 'published_at'])->orderByDesc('published_at')->get();
        foreach ($articles as $article) {
            $urls[] = [Blog::url($article), $article->updated_at ?? $article->published_at, 'weekly', '0.8'];
        }

        $categories = DeskCategory::query()
            ->whereHas('articles', Blog::publishedConstraint())
            ->withMax(['articles as last_published' => Blog::publishedConstraint()], 'published_at')
            ->get();
        foreach ($categories as $category) {
            $urls[] = [route('desk-editor.blog.category', ['slug' => $category->slug]), $category->getAttribute('last_published'), 'weekly', '0.6'];
        }

        $tags = DeskTag::query()
            ->whereHas('articles', Blog::publishedConstraint())
            ->withMax(['articles as last_published' => Blog::publishedConstraint()], 'published_at')
            ->get();
        foreach ($tags as $tag) {
            $urls[] = [route('desk-editor.blog.tag', ['slug' => $tag->slug]), $tag->getAttribute('last_published'), 'weekly', '0.5'];
        }

        $body = '';
        foreach ($urls as [$loc, $lastmod, $freq, $priority]) {
            $date = $lastmod ? Carbon::parse($lastmod)->toAtomString() : null;
            $body .= "\n  <url>"
                ."\n    <loc>".$this->x($loc).'</loc>'
                .($date ? "\n    <lastmod>".$date.'</lastmod>' : '')
                ."\n    <changefreq>".$freq.'</changefreq>'
                ."\n    <priority>".$priority.'</priority>'
                ."\n  </url>";
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            ."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
            .$body
            ."\n</urlset>\n";
    }

    private function content(DeskArticle $article): string
    {
        $html = $article->body?->html() ?? '';
        if ($article->cover_image) {
            $html = '<p><img src="'.e((string) Blog::absolute($article->cover_image)).'" alt="'.e((string) $article->cover_alt).'"></p>'.$html;
        }

        // Make relative links and images absolute for feed readers.
        return (string) preg_replace_callback('/\s(src|href)="(\/[^"\/][^"]*)"/i', fn (array $m) => ' '.$m[1].'="'.url($m[2]).'"', $html);
    }

    private function x(string $value): string
    {
        $value = (string) preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $value);

        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function cdata(string $value): string
    {
        $value = (string) preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $value);

        return '<![CDATA['.str_replace(']]>', ']]]]><![CDATA[>', $value).']]>';
    }

    private function imageType(string $url): string
    {
        return match (strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'avif' => 'image/avif',
            'svg' => 'image/svg+xml',
            default => 'image/jpeg',
        };
    }
}
