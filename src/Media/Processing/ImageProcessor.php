<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Media\Processing;

use Atannex\DeskEditor\Media\Models\DeskMedia;
use GdImage;
use Imagick;
use RuntimeException;
use Throwable;

/**
 * Reads image dimensions and writes responsive variants next to the original
 * ({dir}/{basename}-{width}.{format}) with Imagick, falling back to GD.
 */
class ImageProcessor
{
    /** Refuse GD decoding above this many pixels to protect memory. */
    private const GD_MAX_PIXELS = 40_000_000;

    public function process(DeskMedia $media, string $localPath): void
    {
        $info = @getimagesize($localPath);

        if ($info === false) {
            $media->addError('Not a readable image.');

            return;
        }

        [$width, $height] = [(int) $info[0], (int) $info[1]];
        $orientation = $this->orientation($localPath, (string) $info['mime']);

        if ($orientation >= 5 && $orientation <= 8) {
            [$width, $height] = [$height, $width];
        }

        $media->width = $width ?: null;
        $media->height = $height ?: null;

        if ($this->isAnimated($localPath, (string) $info['mime'])) {
            $media->setMeta(['animated' => true]);

            return;
        }

        $widths = $this->targetWidths($width);
        $driver = $this->driver();

        if ($widths === [] || $driver === null) {
            return;
        }

        $format = $this->format();
        $quality = max(1, min(100, (int) config('desk-editor.media.variant_quality', 82)));
        $variants = [];

        try {
            $blobs = $driver === 'imagick'
                ? $this->resizeWithImagick($localPath, $widths, $format, $quality)
                : $this->resizeWithGd($localPath, (string) $info['mime'], $orientation, $widths, $format, $quality);

            $disk = $media->filesystem();
            $base = $this->basePath($media->path);
            $visibility = (string) config('desk-editor.uploads.visibility', 'public');

            foreach ($blobs as $targetWidth => [$blob, $targetHeight]) {
                $path = $base.'-'.$targetWidth.'.'.$this->extension($format);
                $disk->put($path, $blob, ['visibility' => $visibility]);
                $variants[] = ['width' => $targetWidth, 'height' => $targetHeight, 'path' => $path, 'url' => $disk->url($path)];
            }
        } catch (Throwable $e) {
            report($e);
            $media->addError('Variants: '.$e->getMessage());
        }

        $media->setMeta(['variants' => $variants ?: null]);
        $media->setMeta(['srcset' => $media->variantsSrcset()]);
    }

    /**
     * Configured widths strictly smaller than the original, ascending.
     *
     * @return list<int>
     */
    public function targetWidths(int $originalWidth): array
    {
        $widths = array_map('intval', (array) config('desk-editor.media.variants', [480, 960, 1600, 2400]));
        $widths = array_values(array_unique(array_filter($widths, fn (int $w) => $w > 0 && $w < $originalWidth)));
        sort($widths);

        return $widths;
    }

    /**
     * The usable driver for the configured format: "imagick", "gd" or null.
     */
    public function driver(): ?string
    {
        $preferred = strtolower((string) config('desk-editor.media.image_driver', 'auto'));
        $format = $this->format();

        $imagick = fn () => class_exists(Imagick::class) && Imagick::queryFormats(strtoupper($format === 'jpg' ? 'jpeg' : $format)) !== [];
        $gd = fn () => function_exists('imagecreatetruecolor') && function_exists($this->gdWriter($format));

        return match ($preferred) {
            'none' => null,
            'imagick' => $imagick() ? 'imagick' : null,
            'gd' => $gd() ? 'gd' : null,
            default => $imagick() ? 'imagick' : ($gd() ? 'gd' : null),
        };
    }

    private function format(): string
    {
        $format = strtolower((string) config('desk-editor.media.variant_format', 'webp'));

        return in_array($format, ['webp', 'avif', 'jpeg', 'jpg', 'png'], true) ? $format : 'webp';
    }

    private function extension(string $format): string
    {
        return $format === 'jpeg' ? 'jpg' : $format;
    }

    private function gdWriter(string $format): string
    {
        return match ($format) {
            'avif' => 'imageavif',
            'jpeg', 'jpg' => 'imagejpeg',
            'png' => 'imagepng',
            default => 'imagewebp',
        };
    }

    private function basePath(string $path): string
    {
        $info = pathinfo($path);
        $dir = ($info['dirname'] ?? '.') === '.' ? '' : $info['dirname'].'/';

        return $dir.$info['filename'];
    }

    /**
     * EXIF orientation (1–8) of a JPEG, 1 when unknown.
     */
    private function orientation(string $path, string $mime): int
    {
        if ($mime !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return 1;
        }

        $exif = @exif_read_data($path);

        return is_array($exif) && isset($exif['Orientation']) ? max(1, min(8, (int) $exif['Orientation'])) : 1;
    }

    private function isAnimated(string $path, string $mime): bool
    {
        if (! in_array($mime, ['image/gif', 'image/webp', 'image/png'], true)) {
            return false;
        }

        $contents = (string) @file_get_contents($path, false, null, 0, 8 * 1024 * 1024);

        return match ($mime) {
            'image/gif' => preg_match_all('/\x00\x21\xF9\x04.{4}\x00[\x2C\x21]/s', $contents) > 1,
            'image/webp' => str_contains(substr($contents, 0, 64), 'ANIM') || str_contains(substr($contents, 12, 32), 'VP8X') && (ord($contents[20] ?? "\0") & 0x02) === 0x02,
            'image/png' => str_contains(substr($contents, 0, 256), 'acTL'),
        };
    }

    /**
     * @param  list<int>  $widths
     * @return array<int, array{0: string, 1: int}>
     */
    private function resizeWithImagick(string $path, array $widths, string $format, int $quality): array
    {
        $image = new Imagick;
        $image->readImage($path);
        $image->setIteratorIndex(0);
        $this->imagickOrient($image);

        if ($image->getImageColorspace() === Imagick::COLORSPACE_CMYK) {
            $image->transformImageColorspace(Imagick::COLORSPACE_SRGB);
        }

        $sourceWidth = $image->getImageWidth();
        $sourceHeight = $image->getImageHeight();
        $out = [];

        foreach ($widths as $width) {
            if ($width >= $sourceWidth) {
                continue;
            }

            $height = max(1, (int) round($sourceHeight * $width / $sourceWidth));
            $variant = clone $image;
            $variant->resizeImage($width, $height, Imagick::FILTER_LANCZOS, 1);
            $variant->stripImage();
            $variant->setImageFormat($format === 'jpg' ? 'jpeg' : $format);
            $variant->setImageCompressionQuality($quality);
            $out[$width] = [$variant->getImageBlob(), $height];
            $variant->clear();
        }

        $image->clear();

        return $out;
    }

    private function imagickOrient(Imagick $image): void
    {
        switch ($image->getImageOrientation()) {
            case Imagick::ORIENTATION_TOPRIGHT: $image->flopImage();
                break;
            case Imagick::ORIENTATION_BOTTOMRIGHT: $image->rotateImage('#000', 180);
                break;
            case Imagick::ORIENTATION_BOTTOMLEFT: $image->flipImage();
                break;
            case Imagick::ORIENTATION_LEFTTOP: $image->rotateImage('#000', 90);
                $image->flopImage();
                break;
            case Imagick::ORIENTATION_RIGHTTOP: $image->rotateImage('#000', 90);
                break;
            case Imagick::ORIENTATION_RIGHTBOTTOM: $image->rotateImage('#000', -90);
                $image->flopImage();
                break;
            case Imagick::ORIENTATION_LEFTBOTTOM: $image->rotateImage('#000', -90);
                break;
        }

        $image->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);
    }

    /**
     * @param  list<int>  $widths
     * @return array<int, array{0: string, 1: int}>
     */
    private function resizeWithGd(string $path, string $mime, int $orientation, array $widths, string $format, int $quality): array
    {
        $size = @getimagesize($path);

        if ($size === false || $size[0] * $size[1] > self::GD_MAX_PIXELS) {
            throw new RuntimeException('Image too large for GD.');
        }

        $source = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/gif' => @imagecreatefromgif($path),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            'image/avif' => function_exists('imagecreatefromavif') ? @imagecreatefromavif($path) : false,
            default => false,
        };

        if (! $source instanceof GdImage) {
            throw new RuntimeException('GD cannot decode '.$mime.'.');
        }

        $source = $this->gdOrient($source, $orientation);
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $writer = $this->gdWriter($format);
        $out = [];

        foreach ($widths as $width) {
            if ($width >= $sourceWidth) {
                continue;
            }

            $height = max(1, (int) round($sourceHeight * $width / $sourceWidth));
            $target = imagecreatetruecolor($width, $height);
            $opaque = in_array($format, ['jpeg', 'jpg'], true);

            if ($opaque) {
                imagefill($target, 0, 0, (int) imagecolorallocate($target, 255, 255, 255));
            } else {
                imagealphablending($target, false);
                imagesavealpha($target, true);
                imagefill($target, 0, 0, (int) imagecolorallocatealpha($target, 0, 0, 0, 127));
            }

            imagecopyresampled($target, $source, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight);

            ob_start();
            match ($writer) {
                'imagepng' => imagepng($target, null, 9 - (int) round($quality / 100 * 9)),
                default => $writer($target, null, $quality),
            };
            $out[$width] = [(string) ob_get_clean(), $height];
            imagedestroy($target);
        }

        imagedestroy($source);

        return $out;
    }

    private function gdOrient(GdImage $image, int $orientation): GdImage
    {
        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($image, $orientation === 4 ? IMG_FLIP_VERTICAL : IMG_FLIP_HORIZONTAL);
        }

        $angle = match ($orientation) {
            3, 4 => 180,
            5, 8 => 90,  // imagerotate() turns counter-clockwise
            6, 7 => -90,
            default => 0,
        };

        if ($angle !== 0 && ($rotated = imagerotate($image, $angle, 0)) instanceof GdImage) {
            imagedestroy($image);

            return $rotated;
        }

        return $image;
    }
}
