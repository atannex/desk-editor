<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Media\Processing;

use Atannex\DeskEditor\Media\Models\DeskMedia;
use RuntimeException;
use Throwable;

/**
 * Gives processors a real local path for a stored file: the file itself on
 * local disks, a temporary copy for remote ones (S3 & co).
 */
final class LocalCopy
{
    /**
     * @template T
     *
     * @param  callable(string): T  $callback
     * @return T
     */
    public static function with(DeskMedia $media, callable $callback): mixed
    {
        $disk = $media->filesystem();

        try {
            $local = $disk->path($media->path);
        } catch (Throwable) {
            $local = null;
        }

        if (is_string($local) && is_file($local)) {
            return $callback($local);
        }

        $temp = self::tempFile(pathinfo($media->path, PATHINFO_EXTENSION));

        try {
            $stream = $disk->readStream($media->path);

            if (! is_resource($stream)) {
                throw new RuntimeException('Unable to read '.$media->path.' from disk '.$media->disk);
            }

            $target = fopen($temp, 'wb');

            if ($target === false) {
                throw new RuntimeException('Unable to open a temporary file.');
            }

            stream_copy_to_stream($stream, $target);
            fclose($target);
            fclose($stream);

            return $callback($temp);
        } finally {
            @unlink($temp);
        }
    }

    /**
     * A fresh, empty temporary file path with the given extension.
     */
    public static function tempFile(string $extension = ''): string
    {
        $base = tempnam(sys_get_temp_dir(), 'desk-media-');

        if ($base === false) {
            throw new RuntimeException('Unable to create a temporary file.');
        }

        if ($extension === '') {
            return $base;
        }

        @unlink($base);

        return $base.'.'.preg_replace('/[^a-z0-9]/i', '', $extension);
    }
}
