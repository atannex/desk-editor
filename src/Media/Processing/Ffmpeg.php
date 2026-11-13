<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Media\Processing;

use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Thin, shell-free wrapper around the ffprobe / ffmpeg binaries. Every
 * command is passed as an argument array, so file names are never parsed
 * by a shell.
 */
class Ffmpeg
{
    /** @var array<string, string|false> */
    private static array $resolved = [];

    /**
     * Whether ffmpeg is enabled in config and both binaries can be found.
     */
    public function available(): bool
    {
        return (bool) config('desk-editor.media.ffmpeg.enabled', true)
            && class_exists(Process::class)
            && $this->binary('ffmpeg') !== null
            && $this->binary('ffprobe') !== null;
    }

    /**
     * Absolute path of a configured binary, or null when it is missing.
     *
     * @param  'ffmpeg'|'ffprobe'  $tool
     */
    public function binary(string $tool): ?string
    {
        $configured = (string) config('desk-editor.media.ffmpeg.'.($tool === 'ffmpeg' ? 'binary' : 'ffprobe'), $tool);

        if (! array_key_exists($configured, self::$resolved)) {
            self::$resolved[$configured] = match (true) {
                $configured === '' => false,
                str_contains($configured, DIRECTORY_SEPARATOR) => is_file($configured) && is_executable($configured) ? $configured : false,
                default => (new ExecutableFinder)->find($configured, null, ['/usr/bin', '/usr/local/bin', '/opt/homebrew/bin']) ?? false,
            };
        }

        return self::$resolved[$configured] ?: null;
    }

    /**
     * Forget resolved binary paths (after a config change).
     */
    public static function flush(): void
    {
        self::$resolved = [];
    }

    /**
     * Stream & container information as decoded ffprobe JSON.
     *
     * @return array{streams?: list<array<string, mixed>>, format?: array<string, mixed>}
     */
    public function probe(string $path): array
    {
        $output = $this->run([
            $this->require('ffprobe'), '-v', 'error', '-print_format', 'json', '-show_format', '-show_streams', '-i', $path,
        ], 60);

        $data = json_decode($output, true);

        if (! is_array($data)) {
            throw new RuntimeException('ffprobe returned unreadable output.');
        }

        /** @var array{streams?: list<array<string, mixed>>, format?: array<string, mixed>} $data */
        return $data;
    }

    /**
     * Save one frame at $seconds as a JPEG no wider than 1920px.
     */
    public function poster(string $input, string $output, float $seconds): void
    {
        $this->run([
            $this->require('ffmpeg'), '-nostdin', '-y', '-v', 'error',
            '-ss', number_format(max(0, $seconds), 3, '.', ''),
            '-i', $input,
            '-frames:v', '1',
            '-vf', "scale='min(1920,iw)':-2",
            '-q:v', '3',
            $output,
        ], 120);

        if (! is_file($output) || filesize($output) === 0) {
            throw new RuntimeException('ffmpeg produced no poster frame.');
        }
    }

    /**
     * Re-encode to a web-friendly H.264/AAC MP4 with the moov atom up front.
     */
    public function transcode(string $input, string $output, int $maxHeight, bool $hasAudio): void
    {
        $maxHeight = max(144, $maxHeight);
        $arguments = [
            $this->require('ffmpeg'), '-nostdin', '-y', '-v', 'error',
            '-i', $input,
            '-map', '0:v:0',
            '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '23', '-pix_fmt', 'yuv420p',
            '-vf', "scale=-2:'min({$maxHeight},ih)':flags=lanczos,scale=trunc(iw/2)*2:trunc(ih/2)*2",
        ];

        $arguments = $hasAudio
            ? [...$arguments, '-map', '0:a:0', '-c:a', 'aac', '-b:a', '128k', '-ac', '2']
            : [...$arguments, '-an'];

        $this->run([...$arguments, '-movflags', '+faststart', '-f', 'mp4', $output], (float) config('desk-editor.media.ffmpeg.timeout', 3600));

        if (! is_file($output) || filesize($output) === 0) {
            throw new RuntimeException('ffmpeg produced no output.');
        }
    }

    /**
     * @param  'ffmpeg'|'ffprobe'  $tool
     */
    private function require(string $tool): string
    {
        return $this->binary($tool) ?? throw new RuntimeException($tool.' is not available.');
    }

    /**
     * @param  list<string>  $command
     */
    private function run(array $command, float $timeout): string
    {
        $process = new Process($command, null, null, null, $timeout);
        $process->run();

        if (! $process->isSuccessful()) {
            $error = trim($process->getErrorOutput()) ?: 'exit code '.$process->getExitCode();

            throw new RuntimeException(basename($command[0]).' failed: '.mb_substr($error, 0, 300));
        }

        return $process->getOutput();
    }
}
