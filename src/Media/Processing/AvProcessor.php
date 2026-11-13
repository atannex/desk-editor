<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Media\Processing;

use Atannex\DeskEditor\Media\Models\DeskMedia;

/**
 * Video & audio: reads dimensions/duration, extracts a poster frame and,
 * when configured, transcodes non-H.264 video to a faststart MP4.
 */
class AvProcessor
{
    public function __construct(private readonly Ffmpeg $ffmpeg) {}

    public function available(): bool
    {
        return $this->ffmpeg->available();
    }

    /**
     * Throws when probing fails; poster/transcode problems are recorded as errors.
     */
    public function process(DeskMedia $media, string $localPath): void
    {
        $probe = $this->ffmpeg->probe($localPath);
        $streams = $probe['streams'] ?? [];
        $video = $this->firstStream($streams, 'video');
        $audio = $this->firstStream($streams, 'audio');

        $duration = (float) ($probe['format']['duration'] ?? $video['duration'] ?? $audio['duration'] ?? 0);
        $media->duration = $duration > 0 ? round($duration, 3) : null;

        if ($media->kind !== 'video') {
            return;
        }

        if ($video === null) {
            $media->addError('No video stream found.');

            return;
        }

        [$width, $height] = [(int) ($video['width'] ?? 0), (int) ($video['height'] ?? 0)];

        if (abs($this->rotation($video)) % 180 === 90) {
            [$width, $height] = [$height, $width];
        }

        $media->width = $width ?: null;
        $media->height = $height ?: null;

        $disk = $media->filesystem();
        $base = $this->basePath($media->path);
        $visibility = (string) config('desk-editor.uploads.visibility', 'public');

        // Poster frame: ~1s in, or 10% in for very short clips.
        $poster = LocalCopy::tempFile('jpg');

        try {
            $this->ffmpeg->poster($localPath, $poster, $duration > 0 ? min(1.0, $duration * 0.1) : 0.0);
            $posterPath = $base.'-poster.jpg';
            $disk->put($posterPath, (string) file_get_contents($poster), ['visibility' => $visibility]);
            $posterUrl = $disk->url($posterPath);

            $media->setMeta([
                'poster_path' => $posterPath,
                'generated_poster_url' => $posterUrl,
                'poster_width' => $width ?: null,
                'poster_height' => $height ?: null,
            ]);

            if (! $media->metaValue('poster_media_id')) {
                $media->setMeta(['poster_url' => $posterUrl]);
            }
        } catch (\Throwable $e) {
            $media->addError('Poster: '.$e->getMessage());
        } finally {
            @unlink($poster);
        }

        if (! config('desk-editor.media.ffmpeg.transcode', true) || $this->isWebReady($media, $probe, $video)) {
            return;
        }

        $output = LocalCopy::tempFile('mp4');

        try {
            $this->ffmpeg->transcode($localPath, $output, (int) config('desk-editor.media.ffmpeg.max_height', 1080), $audio !== null);
            $transcodedPath = $base.'-h264.mp4';
            $stream = fopen($output, 'rb');
            $disk->put($transcodedPath, $stream ?: '', ['visibility' => $visibility]);

            if (is_resource($stream)) {
                fclose($stream);
            }

            $media->setMeta([
                'transcoded_path' => $transcodedPath,
                'transcoded_url' => $disk->url($transcodedPath),
                'transcoded_size' => (int) filesize($output),
            ]);
        } catch (\Throwable $e) {
            $media->addError('Transcode: '.$e->getMessage());
        } finally {
            @unlink($output);
        }
    }

    /**
     * An MP4 whose first video stream is H.264 plays everywhere already.
     *
     * @param  array<string, mixed>  $probe
     * @param  array<string, mixed>  $video
     */
    private function isWebReady(DeskMedia $media, array $probe, array $video): bool
    {
        $container = (string) ($probe['format']['format_name'] ?? '');

        return $media->mime === 'video/mp4'
            && str_contains($container, 'mp4')
            && ($video['codec_name'] ?? null) === 'h264';
    }

    /**
     * @param  list<array<string, mixed>>  $streams
     * @return array<string, mixed>|null
     */
    private function firstStream(array $streams, string $type): ?array
    {
        foreach ($streams as $stream) {
            if (($stream['codec_type'] ?? null) === $type && ! (($stream['disposition']['attached_pic'] ?? 0) === 1)) {
                return $stream;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $video
     */
    private function rotation(array $video): int
    {
        if (isset($video['tags']['rotate'])) {
            return (int) $video['tags']['rotate'];
        }

        foreach ((array) ($video['side_data_list'] ?? []) as $side) {
            if (is_array($side) && isset($side['rotation'])) {
                return (int) $side['rotation'];
            }
        }

        return 0;
    }

    private function basePath(string $path): string
    {
        $info = pathinfo($path);
        $dir = ($info['dirname'] ?? '.') === '.' ? '' : $info['dirname'].'/';

        return $dir.$info['filename'];
    }
}
