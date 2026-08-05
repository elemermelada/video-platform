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

    //the date backfill: stamp sidecars that predate the date field

    public function testBackfillStampsDatelessSidecars(): void
    {
        $this->write('clip.mp4.json', '{"rate":3,"tags":["action"],"authors":["nolan"]}');

        $result = $this->migrator->backfillDates($this->stampOf(['clip.mp4' => '2024-05-06']));

        $this->assertSame(['clip.mp4'], $result['stamped']);
        $this->assertSame([], $result['skipped']);

        $meta = $this->store->load('clip.mp4');
        $this->assertSame('2024-05-06', $meta->date);

        //the rest of the metadata comes through untouched

        $this->assertSame(3, $meta->rate);
        $this->assertSame(['action'], $meta->tags);
        $this->assertSame(['nolan'], $meta->authors);
    }

    public function testBackfillNeverOverwritesTheStoredDate(): void
    {
        $this->write('clip.mp4.json', '{"rate":0,"tags":[],"authors":[],"date":"2020-01-02"}');

        $result = $this->migrator->backfillDates($this->stampOf(['clip.mp4' => '2024-05-06']));

        $this->assertSame([], $result['stamped']);
        $this->assertSame(['clip.mp4'], $result['skipped']);
        $this->assertSame('2020-01-02', $this->store->load('clip.mp4')->date);
    }

    public function testBackfillSkipsSidecarsWithNoTimestampToRead(): void
    {
        $this->write('clip.mp4.json', '{"rate":0,"tags":[],"authors":[]}');

        $result = $this->migrator->backfillDates(function (string $vid): ?int {
            return null;
        });

        $this->assertSame([], $result['stamped']);
        $this->assertSame(['clip.mp4'], $result['skipped']);
        $this->assertNull($this->store->load('clip.mp4')->date);
    }

    public function testBackfillDryRunWritesNothing(): void
    {
        $this->write('clip.mp4.json', '{"rate":0,"tags":[],"authors":[]}');

        $result = $this->migrator->backfillDates(
            $this->stampOf(['clip.mp4' => '2024-05-06']),
            dryRun: true,
        );

        $this->assertSame(['clip.mp4'], $result['stamped']);
        $this->assertNull($this->store->load('clip.mp4')->date);
    }

    public function testBackfillIsIdempotent(): void
    {
        $this->write('clip.mp4.json', '{"rate":0,"tags":[],"authors":[]}');

        $stamps = $this->stampOf(['clip.mp4' => '2024-05-06']);

        $this->migrator->backfillDates($stamps);
        $second = $this->migrator->backfillDates($stamps);

        $this->assertSame([], $second['stamped']);
        $this->assertSame(['clip.mp4'], $second['skipped']);
        $this->assertSame('2024-05-06', $this->store->load('clip.mp4')->date);
    }

    public function testBackfillLeavesLegacyOnlySidecarsToTheMigration(): void
    {
        //no `.json` yet, so there is nothing to stamp: migrate() first

        $this->write('clip.mp4.data', '3:action;nolan;');

        $result = $this->migrator->backfillDates($this->stampOf(['clip.mp4' => '2024-05-06']));

        $this->assertSame(['stamped' => [], 'skipped' => []], $result);
    }

    //the restamp: date a whole library from the filesystem, overwriting

    public function testRestampCreatesMissingSidecarsAndOverwritesStoredDates(): void
    {
        $this->write('dated.mp4.json', '{"rate":4,"tags":["keep"],"authors":["keep"],"date":"2020-01-02"}');

        $result = $this->migrator->restampDates(
            ['dated.mp4', 'bare.mkv'],
            $this->stampOf(['dated.mp4' => '2024-05-06', 'bare.mkv' => '2023-11-12']),
        );

        $this->assertSame(['dated.mp4', 'bare.mkv'], $result['stamped']);
        $this->assertSame(['bare.mkv'], $result['created']);
        $this->assertSame([], $result['skipped']);

        //the stored date goes, the rest of the metadata stays

        $dated = $this->store->load('dated.mp4');
        $this->assertSame('2024-05-06', $dated->date);
        $this->assertSame(4, $dated->rate);
        $this->assertSame(['keep'], $dated->tags);
        $this->assertSame(['keep'], $dated->authors);

        $bare = $this->store->load('bare.mkv');
        $this->assertSame('2023-11-12', $bare->date);
        $this->assertSame(0, $bare->rate);
        $this->assertSame([], $bare->tags);
    }

    public function testRestampSkipsVideosWithNoReadableTimestamp(): void
    {
        $this->write('clip.mp4.json', '{"rate":0,"tags":[],"authors":[],"date":"2020-01-02"}');

        $result = $this->migrator->restampDates(['clip.mp4'], function (string $vid): ?int {
            return null;
        });

        $this->assertSame([], $result['stamped']);
        $this->assertSame([], $result['created']);
        $this->assertSame(['clip.mp4'], $result['skipped']);

        //nothing readable behind it, so what was stored is left alone

        $this->assertSame('2020-01-02', $this->store->load('clip.mp4')->date);
    }

    public function testRestampDryRunWritesNothing(): void
    {
        $this->write('clip.mp4.json', '{"rate":0,"tags":[],"authors":[],"date":"2020-01-02"}');

        $result = $this->migrator->restampDates(
            ['clip.mp4', 'bare.mkv'],
            $this->stampOf(['clip.mp4' => '2024-05-06', 'bare.mkv' => '2024-05-06']),
            dryRun: true,
        );

        $this->assertSame(['clip.mp4', 'bare.mkv'], $result['stamped']);
        $this->assertSame(['bare.mkv'], $result['created']);

        $this->assertSame('2020-01-02', $this->store->load('clip.mp4')->date);
        $this->assertFileDoesNotExist($this->dir . DIRECTORY_SEPARATOR . 'bare.mkv.json');
    }

    public function testRestampLeavesSidecarsWithNoVideoAlone(): void
    {
        //the list is the library's videos: an orphan sidecar is not in it

        $this->write('gone.mp4.json', '{"rate":0,"tags":[],"authors":[],"date":"2020-01-02"}');

        $result = $this->migrator->restampDates([], $this->stampOf(['gone.mp4' => '2024-05-06']));

        $this->assertSame(['stamped' => [], 'created' => [], 'skipped' => []], $result);
        $this->assertSame('2020-01-02', $this->store->load('gone.mp4')->date);
    }

    public function testRestampIsIdempotent(): void
    {
        $stamps = $this->stampOf(['clip.mp4' => '2024-05-06']);

        $this->migrator->restampDates(['clip.mp4'], $stamps);
        $second = $this->migrator->restampDates(['clip.mp4'], $stamps);

        $this->assertSame(['clip.mp4'], $second['stamped']);
        $this->assertSame([], $second['created']);
        $this->assertSame('2024-05-06', $this->store->load('clip.mp4')->date);
    }

    /**
     * A timestamp resolver standing in for the filesystem: video id => date.
     *
     * @param array<string, string> $dates
     *
     * @return callable(string): ?int
     */
    private function stampOf(array $dates): callable
    {
        return function (string $vid) use ($dates): ?int {
            if (!isset($dates[$vid])) {
                return null;
            }

            $stamp = strtotime($dates[$vid]);

            return $stamp === false ? null : $stamp;
        };
    }
}
