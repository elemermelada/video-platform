<?php

declare(strict_types=1);

namespace VideoPlatform\Tests;

use VideoPlatform\MetaStore;
use VideoPlatform\Migrator;

final class MigratorTest extends TempDirTestCase
{
    private MetaStore $store;
    private Migrator $migrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = new MetaStore($this->dir);
        $this->migrator = new Migrator($this->store);
    }

    public function testConvertsLegacyDataFilesToJsonAndDeletesThem(): void
    {
        $this->write('clip.mp4.data', "3:action:scifi;nolan:kubrick;\n");
        $this->write('other.mkv.data', "0:;;\n");

        $result = $this->migrator->migrate();

        $this->assertSame(['clip.mp4', 'other.mkv'], $result['converted']);
        $this->assertSame([], $result['skipped']);
        $this->assertSame(['clip.mp4', 'other.mkv'], $result['deleted']);

        $this->assertFileDoesNotExist($this->dir . DIRECTORY_SEPARATOR . 'clip.mp4.data');
        $this->assertFileDoesNotExist($this->dir . DIRECTORY_SEPARATOR . 'other.mkv.data');

        $meta = $this->store->load('clip.mp4');
        $this->assertSame(3, $meta->rate);
        $this->assertSame(['action', 'scifi'], $meta->tags);
        $this->assertSame(['nolan', 'kubrick'], $meta->authors);

        $empty = $this->store->load('other.mkv');
        $this->assertSame(0, $empty->rate);
        $this->assertSame([], $empty->tags);
        $this->assertSame([], $empty->authors);
    }

    public function testKeepFlagLeavesLegacyFilesInPlace(): void
    {
        $this->write('clip.mp4.data', '2:doc;bbc;');

        $result = $this->migrator->migrate(deleteLegacy: false);

        $this->assertSame(['clip.mp4'], $result['converted']);
        $this->assertSame([], $result['deleted']);
        $this->assertFileExists($this->dir . DIRECTORY_SEPARATOR . 'clip.mp4.data');
        $this->assertSame(2, $this->store->load('clip.mp4')->rate);
    }

    public function testDryRunWritesNothing(): void
    {
        $this->write('clip.mp4.data', '2:doc;bbc;');

        $result = $this->migrator->migrate(dryRun: true);

        $this->assertSame(['clip.mp4'], $result['converted']);
        $this->assertSame(['clip.mp4'], $result['deleted']);
        $this->assertFileExists($this->dir . DIRECTORY_SEPARATOR . 'clip.mp4.data');
        $this->assertFileDoesNotExist($this->dir . DIRECTORY_SEPARATOR . 'clip.mp4.json');
    }

    public function testExistingJsonIsNeverOverwritten(): void
    {
        $this->write('clip.mp4.data', '1:legacy;legacy;');
        $this->write('clip.mp4.json', '{"rate":5,"tags":["kept"],"authors":["kept"]}');

        $result = $this->migrator->migrate();

        $this->assertSame([], $result['converted']);
        $this->assertSame(['clip.mp4'], $result['skipped']);
        $this->assertSame(['clip.mp4'], $result['deleted']);

        $meta = $this->store->load('clip.mp4');
        $this->assertSame(5, $meta->rate);
        $this->assertSame(['kept'], $meta->tags);
    }

    public function testMigrationIsIdempotent(): void
    {
        $this->write('clip.mp4.data', '3:action;nolan;');

        $this->migrator->migrate();
        $second = $this->migrator->migrate();

        $this->assertSame([], $second['converted']);
        $this->assertSame([], $second['skipped']);
        $this->assertSame([], $second['deleted']);
        $this->assertSame(['action'], $this->store->load('clip.mp4')->tags);
    }

    public function testNothingToMigrate(): void
    {
        $this->assertSame(
            ['converted' => [], 'skipped' => [], 'deleted' => []],
            $this->migrator->migrate(),
        );
    }
}
