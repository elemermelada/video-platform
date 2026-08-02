<?php

declare(strict_types=1);

namespace VideoPlatform\Tests;

use RuntimeException;
use VideoPlatform\Thumbnailer;

final class ThumbnailerTest extends TempDirTestCase
{
    /**
     * Every command line the thumbnailer under test has run.
     *
     * @var list<string>
     */
    private array $commands = [];

    /**
     * A thumbnailer that records commands instead of running ffmpeg. $write is
     * the thumbnail ffmpeg is pretended to have produced, if any.
     */
    private function thumbnailer(int $status = 0, string $output = '', ?string $write = null, string $binary = 'ffmpeg'): Thumbnailer
    {
        return new Thumbnailer($this->dir, $binary, function (string $command) use ($status, $output, $write): array {
            $this->commands[] = $command;

            if ($write !== null) {
                file_put_contents($write, 'png');
            }

            return [$status, $output];
        });
    }

    public function testPathIsTheVideoNameWithPngAppended(): void
    {
        $this->assertSame(
            $this->dir . DIRECTORY_SEPARATOR . 'clip.mp4.png',
            $this->thumbnailer()->path('clip.mp4'),
        );
    }

    /**
     * The id is checked by VideoLibrary before it gets here, but the path is
     * written to, so a traversing one must not escape the directory anyway.
     */
    public function testPathDropsAnyDirectoryComponent(): void
    {
        $thumbnailer = $this->thumbnailer();

        $this->assertSame(
            $this->dir . DIRECTORY_SEPARATOR . 'clip.mp4.png',
            $thumbnailer->path('../clip.mp4'),
        );
        $this->assertSame(
            $this->dir . DIRECTORY_SEPARATOR . 'clip.mp4.png',
            $thumbnailer->path('..\\sub\\clip.mp4'),
        );
    }

    public function testCommandSeeksBeforeTheInputAndTakesOneFrame(): void
    {
        $command = $this->thumbnailer()->command('videos/clip.mp4', 12.5, 'thumbs/clip.mp4.png');

        $this->assertStringContainsString(' -ss ' . escapeshellarg('12.500') . ' -i ' . escapeshellarg('videos/clip.mp4'), $command);
        $this->assertStringContainsString('-frames:v 1', $command);
        $this->assertStringEndsWith(escapeshellarg('thumbs/clip.mp4.png'), $command);
    }

    /**
     * Names with spaces, quotes and semicolons all reach a shell, so nothing
     * may appear in the command line unquoted.
     */
    public function testCommandQuotesEveryArgument(): void
    {
        $command = $this->thumbnailer(binary: '/opt/ff mpeg')
            ->command('videos/a b; rm -rf x.mp4', 1.0, 'thumbs/out.png');

        $this->assertStringStartsWith(escapeshellarg('/opt/ff mpeg'), $command);
        $this->assertStringContainsString(escapeshellarg('videos/a b; rm -rf x.mp4'), $command);
        $this->assertStringNotContainsString('; rm -rf x.mp4 ', $command);
    }

    public function testCaptureWritesTheThumbnail(): void
    {
        $target = $this->dir . DIRECTORY_SEPARATOR . 'clip.mp4.png';

        $written = $this->thumbnailer(write: $target)->capture('videos/clip.mp4', 'clip.mp4', 3.0);

        $this->assertSame($target, $written);
        $this->assertFileExists($target);
        $this->assertCount(1, $this->commands);
    }

    public function testCaptureFailsWhenFfmpegFails(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Invalid data/');

        $this->thumbnailer(status: 1, output: "Invalid data found\n")->capture('videos/clip.mp4', 'clip.mp4', 3.0);
    }

    /**
     * Seeking past the end of a video exits 0 and writes nothing at all.
     */
    public function testCaptureFailsWhenNoFileIsProduced(): void
    {
        $this->expectException(RuntimeException::class);

        $this->thumbnailer()->capture('videos/clip.mp4', 'clip.mp4', 9999.0);
    }

    public function testCaptureNeverSeeksBeforeTheStart(): void
    {
        $target = $this->dir . DIRECTORY_SEPARATOR . 'clip.mp4.png';

        $this->thumbnailer(write: $target)->capture('videos/clip.mp4', 'clip.mp4', -5.0);

        $this->assertStringContainsString(' -ss ' . escapeshellarg('0.000') . ' ', $this->commands[0]);
    }

    public function testAvailableProbesBareBinaryOnce(): void
    {
        $thumbnailer = $this->thumbnailer();

        $this->assertTrue($thumbnailer->available());
        $this->assertTrue($thumbnailer->available());
        $this->assertCount(1, $this->commands);
        $this->assertStringContainsString('-version', $this->commands[0]);
    }

    public function testAvailableIsFalseWhenTheProbeFails(): void
    {
        $this->assertFalse($this->thumbnailer(status: 127)->available());
    }

    /**
     * A binary given as a path is not probed: the file either is there or not.
     */
    public function testAvailableChecksPathWithoutRunningIt(): void
    {
        $binary = $this->write('ffmpeg.exe', '');

        $this->assertTrue($this->thumbnailer(binary: $binary)->available());
        $this->assertFalse($this->thumbnailer(binary: $this->dir . DIRECTORY_SEPARATOR . 'nope.exe')->available());
        $this->assertSame([], $this->commands);
    }

    public function testTimestampAcceptsSecondsAndClampsToTheStart(): void
    {
        $this->assertSame(0.0, Thumbnailer::timestamp('0'));
        $this->assertSame(12.5, Thumbnailer::timestamp('12.5'));
        $this->assertSame(12.5, Thumbnailer::timestamp(12.5));
        $this->assertSame(0.0, Thumbnailer::timestamp('-3'));
    }

    public function testTimestampRejectsValuesThatAreNotNumbers(): void
    {
        foreach (['', ' ', 'abc', '1; rm -rf /', '1e', 'NaN', 'INF', [], null, true] as $value) {
            $this->assertNull(Thumbnailer::timestamp($value), var_export($value, true));
        }
    }
}
