<?php

declare(strict_types=1);

namespace VideoPlatform\Tests;

use VideoPlatform\Meta;
use VideoPlatform\MetaStore;

final class MetaStoreTest extends TempDirTestCase
{
    private MetaStore $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = new MetaStore($this->dir);
    }

    public function testSavesAndLoadsJsonSidecar(): void
    {
        $this->store->save('clip.mp4', new Meta(5, ['action'], ['nolan']));

        $this->assertFileExists($this->dir . DIRECTORY_SEPARATOR . 'clip.mp4.json');
        $this->assertTrue($this->store->has('clip.mp4'));

        $meta = $this->store->load('clip.mp4');

        $this->assertSame(5, $meta->rate);
        $this->assertSame(['action'], $meta->tags);
        $this->assertSame(['nolan'], $meta->authors);
    }

    public function testSaveOverwritesPreviousMetadata(): void
    {
        $this->store->save('clip.mp4', new Meta(1, ['old'], []));
        $this->store->save('clip.mp4', new Meta(2, ['new'], ['ath']));

        $meta = $this->store->load('clip.mp4');

        $this->assertSame(2, $meta->rate);
        $this->assertSame(['new'], $meta->tags);
        $this->assertSame(['ath'], $meta->authors);
    }

    public function testLoadingMissingMetadataYieldsEmptyMeta(): void
    {
        $this->assertFalse($this->store->has('nope.mp4'));

        $meta = $this->store->load('nope.mp4');

        $this->assertSame(0, $meta->rate);
        $this->assertSame([], $meta->tags);
        $this->assertSame([], $meta->authors);
    }

    public function testLoadingCorruptJsonYieldsEmptyMetaInsteadOfThrowing(): void
    {
        $this->write('broken.mp4.json', '{not json');

        $this->assertSame(0, $this->store->load('broken.mp4')->rate);
    }

    public function testIdsListsJsonAndLegacySidecarsSeparately(): void
    {
        $this->write('b.mp4.json', '{"rate":1,"tags":[],"authors":[]}');
        $this->write('a.mp4.json', '{"rate":1,"tags":[],"authors":[]}');
        $this->write('c.mp4.data', '1;;');

        $this->assertSame(['a.mp4', 'b.mp4'], $this->store->ids());
        $this->assertSame(['c.mp4'], $this->store->legacyIds());
    }

    public function testVideoIdCannotEscapeTheStoreDirectory(): void
    {
        $this->store->save('../escaped.mp4', new Meta(1, [], []));

        $this->assertFileExists($this->dir . DIRECTORY_SEPARATOR . 'escaped.mp4.json');
        $this->assertFileDoesNotExist(dirname($this->dir) . DIRECTORY_SEPARATOR . 'escaped.mp4.json');
    }

    public function testSaveCreatesTheDirectoryWhenMissing(): void
    {
        $nested = $this->dir . DIRECTORY_SEPARATOR . 'data';
        (new MetaStore($nested))->save('clip.mp4', new Meta(3, [], []));

        $this->assertFileExists($nested . DIRECTORY_SEPARATOR . 'clip.mp4.json');

        unlink($nested . DIRECTORY_SEPARATOR . 'clip.mp4.json');
        rmdir($nested);
    }
}
