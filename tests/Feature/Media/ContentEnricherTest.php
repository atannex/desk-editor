<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Tests\Feature\Media;

use Atannex\DeskEditor\Media\ContentEnricher;
use Atannex\DeskEditor\Media\Models\DeskMedia;
use Atannex\DeskEditor\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ContentEnricherTest extends TestCase
{
    use MediaTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function image(): DeskMedia
    {
        $media = $this->media(null, ['path' => 'desk-editor/e/photo.jpg', 'width' => 2000, 'height' => 1000, 'alt' => 'A photo']);
        $url = fn (int $w) => Storage::disk('public')->url("desk-editor/e/photo-{$w}.webp");
        $media->setMeta(['variants' => [
            ['width' => 960, 'height' => 480, 'path' => 'desk-editor/e/photo-960.webp', 'url' => $url(960)],
            ['width' => 480, 'height' => 240, 'path' => 'desk-editor/e/photo-480.webp', 'url' => $url(480)],
        ]])->save();

        return $media;
    }

    public function test_images_get_srcset_sizes_and_dimensions(): void
    {
        $media = $this->image();
        $html = '<p>Olá — “quotes” &amp; emoji 🎉</p><figure><img src="'.$media->url.'" alt="Custom"></figure><img src="https://elsewhere.test/x.png">';

        DB::enableQueryLog();
        $out = app(ContentEnricher::class)->enrich($html);
        $this->assertCount(1, array_filter(DB::getQueryLog(), fn ($q) => str_contains($q['query'], 'from "desk_media"')));

        $this->assertStringContainsString('photo-480.webp 480w, ', $out);
        $this->assertStringContainsString('photo-960.webp 960w, '.$media->url.' 2000w"', $out);
        $this->assertStringContainsString('sizes="'.ContentEnricher::SIZES.'"', $out);
        $this->assertStringContainsString('width="2000" height="1000"', $out);
        $this->assertStringContainsString('alt="Custom"', $out);
        $this->assertStringContainsString('<p>Olá — “quotes” &amp; emoji 🎉</p>', $out);
        $this->assertStringContainsString('<img src="https://elsewhere.test/x.png">', $out);
        $this->assertStringNotContainsString('<html', $out);
        $this->assertStringNotContainsString('<body', $out);
        $this->assertStringNotContainsString('desk-enricher-root', $out);
    }

    public function test_relative_urls_match_and_existing_attributes_are_kept(): void
    {
        $media = $this->image();
        $relative = (string) parse_url($media->url, PHP_URL_PATH);

        $out = app(ContentEnricher::class)->enrich('<img src="'.$relative.'" width="300" height="150" srcset="a.png 1x">');

        $this->assertStringContainsString('width="300" height="150"', $out);
        $this->assertStringContainsString('srcset="a.png 1x"', $out);
        $this->assertStringContainsString('alt="A photo"', $out);
    }

    public function test_videos_get_poster_and_transcoded_source(): void
    {
        $video = $this->media(null, ['kind' => 'video', 'mime' => 'video/webm', 'path' => 'desk-editor/e/clip.webm', 'width' => 640, 'height' => 360]);
        $video->setMeta(['poster_url' => 'https://cdn.test/clip-poster.jpg', 'transcoded_url' => 'https://cdn.test/clip-h264.mp4'])->save();

        $out = app(ContentEnricher::class)->enrich('<video src="'.$video->url.'" controls></video><video controls><source src="'.$video->url.'" type="video/webm"></video>');

        $this->assertSame(2, substr_count($out, 'poster="https://cdn.test/clip-poster.jpg"'));
        $this->assertStringContainsString('<video src="https://cdn.test/clip-h264.mp4"', $out);
        $this->assertStringContainsString('<source src="https://cdn.test/clip-h264.mp4" type="video/mp4">', $out);
    }

    public function test_untouched_content_is_returned_verbatim(): void
    {
        $this->image();
        $enricher = app(ContentEnricher::class);

        $this->assertSame('', $enricher->enrich(''));
        $this->assertSame('<p>No media <b>here</b></p>', $enricher->enrich('<p>No media <b>here</b></p>'));
        $this->assertSame('<p><img src="/x.png" ></p>', $enricher->enrich('<p><img src="/x.png" ></p>'));

        // Media still processing are not used.
        $pending = $this->media(null, ['path' => 'desk-editor/e/pending.png', 'status' => DeskMedia::STATUS_PROCESSING]);
        $this->assertSame('<img src="'.$pending->url.'">', $enricher->enrich('<img src="'.$pending->url.'">'));
    }
}
