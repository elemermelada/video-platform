<?php

declare(strict_types=1);

namespace VideoPlatform\Tests;

use VideoPlatform\VideoLibrary;

final class VideoLibraryTest extends TempDirTestCase
{
    private VideoLibrary $library;

    protected function setUp(): void
    {
        parent::setUp();

        $this->library = new VideoLibrary($this->dir, 'videos/');
    }

    public function testListsOnlyVideoFiles(): void
    {
        $this->write('clip.mp4', '');
        $this->write('other.WEBM', '');
        $this->write('index.php', '');
        $this->write('README.md', '');
        $this->write('LICENSE', '');

        $this->assertSame(['clip.mp4', 'other.WEBM'], $this->library->files());
    }

    public function testListsNothingWhenTheLibraryDirectoryIsMissing(): void
    {
        $library = new VideoLibrary($this->dir . DIRECTORY_SEPARATOR . 'nope');

        $this->assertSame([], $library->files());
    }

    public function testHasOnlyAcceptsVideosTheLibraryHolds(): void
    {
        $this->write('clip.mp4', '');
        $this->write('notes.txt', '');

        $this->assertTrue($this->library->has('clip.mp4'));
        $this->assertFalse($this->library->has('missing.mp4'));
        $this->assertFalse($this->library->has('notes.txt'));
        $this->assertFalse($this->library->has(''));
    }

    /**
     * The id reaches file_put_contents() in edit.php, so a traversing one must
     * never be accepted — even when the file it points at exists.
     */
    public function testHasRejectsTraversalEvenWhenTheTargetExists(): void
    {
        mkdir($this->dir . DIRECTORY_SEPARATOR . 'sub');
        $this->write('clip.mp4', '');

        $library = new VideoLibrary($this->dir . DIRECTORY_SEPARATOR . 'sub');

        $this->assertFalse($library->has('../clip.mp4'));
        $this->assertFalse($library->has('..\\clip.mp4'));
    }

    public function testPathAndUrlAreEmptyForRejectedIds(): void
    {
        foreach (['', '../clip.mp4', 'sub/clip.mp4', 'clip.php', 'clip', '.mp4'] as $vid) {
            $this->assertSame('', $this->library->path($vid), $vid);
            $this->assertSame('', $this->library->url($vid), $vid);
        }
    }

    public function testUrlPrefixesAndEncodesTheName(): void
    {
        $this->assertSame(
            'videos/my%20clip%20%26%20co.mp4',
            $this->library->url('my clip & co.mp4'),
        );
    }

    public function testPathJoinsTheLibraryDirectory(): void
    {
        $this->assertSame(
            $this->dir . DIRECTORY_SEPARATOR . 'clip.mp4',
            $this->library->path('clip.mp4'),
        );
    }

    public function testIsVideoNameIgnoresCaseAndUnknownExtensions(): void
    {
        $this->assertTrue(VideoLibrary::isVideoName('a.MkV'));
        $this->assertTrue(VideoLibrary::isVideoName('a.mp4'));
        $this->assertFalse(VideoLibrary::isVideoName('a.mp3'));
        $this->assertFalse(VideoLibrary::isVideoName('a.md'));
        $this->assertFalse(VideoLibrary::isVideoName('mp4'));
    }
}
