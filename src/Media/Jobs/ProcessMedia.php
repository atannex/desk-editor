<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Media\Jobs;

use Atannex\DeskEditor\Media\Models\DeskMedia;
use Atannex\DeskEditor\Media\Processing\MediaProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Probes a video/audio upload, extracts its poster and transcodes it.
 * Failures are recorded on the media item (status "failed"), not retried.
 */
class ProcessMedia implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public DeskMedia $media)
    {
        $this->timeout = (int) config('desk-editor.media.ffmpeg.timeout', 3600) + 60;
    }

    public function handle(MediaProcessor $processor): void
    {
        $processor->process($this->media);
    }

    public function failed(?Throwable $e): void
    {
        $this->media->refresh()->addError($e?->getMessage() ?? 'Processing failed.');
        $this->media->forceFill(['status' => DeskMedia::STATUS_FAILED])->save();
    }
}
