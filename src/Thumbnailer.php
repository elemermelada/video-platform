<?php

declare(strict_types=1);

namespace VideoPlatform;

use RuntimeException;

/**
 * Writes `<dir>/<video>.png` thumbnails by pulling a single frame out of a
 * video with ffmpeg.
 *
 * ffmpeg is not a requirement of the app: it is looked up when asked for, and
 * callers check available() so a box without it can hide the feature instead
 * of failing when the button is pressed.
 *
 * Everything that reaches the command line goes through escapeshellarg(), and
 * the timestamp is parsed by timestamp() first, so a caller cannot smuggle
 * anything past the video id check into a shell.
 */
final class Thumbnailer
{
    public const EXTENSION = '.png';

    /** @var (callable(string): array{int, string})|null */
    private $runner;

    private ?bool $available = null;

    /**
     * @param string                              $dir    directory the PNGs are written to
     * @param string                              $binary ffmpeg command: a bare name found on PATH, or a full path
     * @param (callable(string): array{int, string})|null $runner runs a command line, returning [exit status, output]
     */
    public function __construct(
        private readonly string $dir,
        private readonly string $binary = 'ffmpeg',
        ?callable $runner = null,
    ) {
        $this->runner = $runner;
    }

    /**
     * Where the thumbnail of a video lives. Any directory component of the id
     * is dropped, so it can never escape the thumbnail directory.
     */
    public function path(string $vid): string
    {
        return $this->dir . DIRECTORY_SEPARATOR . basename(str_replace('\\', '/', $vid)) . self::EXTENSION;
    }

    /**
     * Can we actually run ffmpeg? A binary given as a path has to exist; a bare
     * name is probed with `-version`, which is the only portable way to ask
     * PATH. The answer is worked out once per instance.
     */
    public function available(): bool
    {
        if ($this->available !== null) {
            return $this->available;
        }

        if (str_contains($this->binary, '/') || str_contains($this->binary, '\\')) {
            return $this->available = is_file($this->binary);
        }

        [$status] = $this->run(escapeshellarg($this->binary) . ' -version');

        return $this->available = $status === 0;
    }

    /**
     * The command line that grabs one frame. `-ss` before `-i` seeks instead of
     * decoding up to the timestamp, which is what keeps this quick on a long
     * file.
     */
    public function command(string $videoPath, float $time, string $target): string
    {
        return escapeshellarg($this->binary)
            . ' -y -loglevel error'
            . ' -ss ' . escapeshellarg(sprintf('%.3F', $time))
            . ' -i ' . escapeshellarg($videoPath)
            . ' -frames:v 1'
            . ' ' . escapeshellarg($target);
    }

    /**
     * Write the frame at $time of $videoPath as the thumbnail of $vid.
     *
     * @return string the file written
     *
     * @throws RuntimeException if the directory cannot be made or ffmpeg fails
     */
    public function capture(string $videoPath, string $vid, float $time): string
    {
        if (!is_dir($this->dir) && !@mkdir($this->dir, 0o777, true) && !is_dir($this->dir)) {
            throw new RuntimeException("Cannot create thumbnail directory: {$this->dir}");
        }

        $target = $this->path($vid);

        [$status, $output] = $this->run($this->command($videoPath, max(0.0, $time), $target));

        //a seek past the end exits 0 and writes nothing, so the file is the
        //only thing worth trusting

        if ($status !== 0 || !is_file($target)) {
            $detail = trim($output);

            throw new RuntimeException(
                'ffmpeg could not capture a frame' . ($detail === '' ? '' : ': ' . $detail),
            );
        }

        return $target;
    }

    /**
     * A timestamp as posted by the page: a number of seconds, clamped to the
     * start of the video. Anything else (empty, text, NaN, negative infinity)
     * is null — the caller reports it rather than guessing a frame.
     */
    public static function timestamp(mixed $value): ?float
    {
        if (!is_scalar($value) || !is_numeric($value)) {
            return null;
        }

        $time = (float) $value;

        if (!is_finite($time)) {
            return null;
        }

        return max(0.0, $time);
    }

    /**
     * @return array{int, string} exit status and combined output
     */
    private function run(string $command): array
    {
        if ($this->runner !== null) {
            return ($this->runner)($command);
        }

        $lines = [];
        $status = 1;

        @exec($command . ' 2>&1', $lines, $status);

        return [$status, implode("\n", $lines)];
    }
}
