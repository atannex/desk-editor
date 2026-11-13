<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Media\Processing;

use Atannex\DeskEditor\Media\Jobs\ProcessMedia;
use Atannex\DeskEditor\Media\Models\DeskMedia;
use Throwable;

/**
 * Entry point for media processing: images are handled inline (fast, and the
 * editor wants the srcset straight away); video/audio go through the
 * ProcessMedia job on the configured queue.
 */
class MediaProcessor
{
    public function __construct(
        private readonly ImageProcessor $images,
        private readonly AvProcessor $av,
    ) {}

    /**
     * Process a freshly stored upload. $localPath may point at the uploaded
     * temp file to avoid re-reading it from the disk.
     */
    public function handleUpload(DeskMedia $media, ?string $localPath = null): DeskMedia
    {
        if ($media->kind === 'image') {
            $this->processImage($media, $localPath);

            return $media;
        }

        if (in_array($media->kind, ['video', 'audio'], true) && $this->av->available()) {
            $media->forceFill(['status' => DeskMedia::STATUS_PROCESSING])->save();
            $this->dispatch($media);

            return $media->refresh();
        }

        return $media;
    }

    /**
     * Run (or re-run) processing for any media item synchronously.
     */
    public function process(DeskMedia $media): DeskMedia
    {
        if ($media->kind === 'image') {
            $this->processImage($media);

            return $media;
        }

        if (! in_array($media->kind, ['video', 'audio'], true) || ! $this->av->available()) {
            $media->forceFill(['status' => DeskMedia::STATUS_READY])->save();

            return $media;
        }

        $media->forceFill(['status' => DeskMedia::STATUS_PROCESSING])->setMeta(['errors' => null])->save();

        try {
            LocalCopy::with($media, fn (string $path) => $this->av->process($media, $path));
            $media->status = DeskMedia::STATUS_READY;
        } catch (Throwable $e) {
            report($e);
            $media->addError($e->getMessage());
            $media->status = DeskMedia::STATUS_FAILED;
        }

        $media->save();

        return $media;
    }

    /**
     * Queue the video/audio job on media.ffmpeg.queue, or run it now.
     *
     * "queue" may be a connection name, a queue name, "connection:queue" or
     * ['connection' => ..., 'queue' => ...].
     */
    public function dispatch(DeskMedia $media): void
    {
        $target = config('desk-editor.media.ffmpeg.queue');

        if ($target === null || $target === '' || $target === false) {
            ProcessMedia::dispatchSync($media);

            return;
        }

        [$connection, $queue] = $this->queueTarget($target);
        $job = new ProcessMedia($media);

        if ($connection !== null) {
            $job->onConnection($connection);
        }

        if ($queue !== null) {
            $job->onQueue($queue);
        }

        dispatch($job);
    }

    private function processImage(DeskMedia $media, ?string $localPath = null): void
    {
        try {
            $localPath !== null && is_file($localPath)
                ? $this->images->process($media, $localPath)
                : LocalCopy::with($media, fn (string $path) => $this->images->process($media, $path));
        } catch (Throwable $e) {
            report($e);
            $media->addError($e->getMessage());
        }

        // The original stays usable even when variants fail.
        $media->status = DeskMedia::STATUS_READY;
        $media->save();
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function queueTarget(mixed $target): array
    {
        if (is_array($target)) {
            return [
                isset($target['connection']) ? (string) $target['connection'] : null,
                isset($target['queue']) ? (string) $target['queue'] : null,
            ];
        }

        $target = (string) $target;

        if (str_contains($target, ':')) {
            [$connection, $queue] = explode(':', $target, 2);

            return [$connection ?: null, $queue ?: null];
        }

        return config("queue.connections.{$target}") !== null ? [$target, null] : [null, $target];
    }
}
