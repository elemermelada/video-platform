<?php

/**
 * One-off migration: convert `data/<video>.data` sidecars to `data/<video>.json`,
 * and optionally date sidecars from the filesystem.
 *
 * Usage:
 *   php migrate.php [--dry-run] [--keep] [--dates | --restamp [--yes]]
 *
 *   --dry-run  report what would change, write nothing
 *   --keep     leave the legacy `.data` files in place (they are deleted by default)
 *   --dates    give every sidecar with no date one, taken from the video's mtime
 *              (the sidecar's own mtime if the video is gone), so index.php's
 *              date sort no longer has to fall back to the filesystem
 *   --restamp  the destructive version of --dates: give every video in the
 *              library a sidecar and date all of them from the file's creation
 *              time, REPLACING the dates already stored. It warns and asks
 *              before writing; --yes answers the question for a scripted run.
 */

declare(strict_types=1);

require_once __DIR__ . '/lib.php';

use VideoPlatform\Meta;
use VideoPlatform\MetaStore;
use VideoPlatform\Migrator;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("migrate.php is a CLI script.\n");
}

$dryRun = in_array('--dry-run', $argv, true);
$keep = in_array('--keep', $argv, true);
$dates = in_array('--dates', $argv, true);
$restamp = in_array('--restamp', $argv, true);
$assumeYes = in_array('--yes', $argv, true);

//--restamp does everything --dates does and more, so asking for both is a
//mistake worth stopping on rather than quietly picking one

if ($dates && $restamp) {
    exit("--dates and --restamp do the same job; --restamp overwrites, --dates does not. Pick one.\n");
}

$migrator = new Migrator(metaStore());

$result = $migrator->migrate(deleteLegacy: !$keep, dryRun: $dryRun);

$prefix = $dryRun ? '[dry-run] ' : '';

foreach ($result['converted'] as $vid) {
    echo $prefix . 'converted ' . $vid . MetaStore::LEGACY_EXTENSION . ' -> ' . $vid . MetaStore::EXTENSION . "\n";
}

foreach ($result['skipped'] as $vid) {
    echo $prefix . 'skipped   ' . $vid . " (json already exists)\n";
}

foreach ($result['deleted'] as $vid) {
    echo $prefix . 'deleted   ' . $vid . MetaStore::LEGACY_EXTENSION . "\n";
}

printf(
    "%s%d converted, %d skipped, %d legacy file(s) deleted.\n",
    $prefix,
    count($result['converted']),
    count($result['skipped']),
    count($result['deleted']),
);

if ($restamp) {
    restampLibrary($migrator, $dryRun, $assumeYes, $prefix);

    exit;
}

if (!$dates) {
    exit;
}

//run after the conversion, so sidecars that were legacy a moment ago are dated
//too. The video's own mtime is the best guess at when it arrived; a sidecar
//with no video left behind it falls back to its own.

$mtime = function (string $vid): ?int {
    foreach (array(videoPath($vid), metaStore()->path($vid)) as $path) {
        if ($path === '' || !is_file($path)) {
            continue;
        }

        $stamp = filemtime($path);

        if ($stamp !== false) {
            return $stamp;
        }
    }

    return null;
};

$backfill = $migrator->backfillDates($mtime, dryRun: $dryRun);

foreach ($backfill['stamped'] as $vid) {
    echo $prefix . 'dated     ' . $vid . MetaStore::EXTENSION . "\n";
}

printf(
    "%s%d dated, %d left alone (already dated, or nothing to read a date from).\n",
    $prefix,
    count($backfill['stamped']),
    count($backfill['skipped']),
);

/**
 * --restamp: the whole library dated from the video files, stored dates and
 * all. It is the one thing this script does that throws metadata away, so it
 * says so and waits for the word "yes" before writing anything.
 */
function restampLibrary(Migrator $migrator, bool $dryRun, bool $assumeYes, string $prefix): void
{
    $vids = videoFiles();

    if ($vids === array()) {
        echo "No videos in the library; nothing to restamp.\n";

        return;
    }

    if (!$dryRun && !confirmRestamp(count($vids), $assumeYes)) {
        echo "Aborted; nothing was written.\n";

        return;
    }

    //stat every video once: the run reports the date it wrote, so the resolver
    //reads from this map rather than going back to the filesystem per line

    $stamps = array();

    foreach ($vids as $vid) {
        $stamps[$vid] = videoCreated($vid);
    }

    $result = $migrator->restampDates(
        $vids,
        function (string $vid) use ($stamps): ?int {
            return $stamps[$vid] ?? null;
        },
        dryRun: $dryRun,
    );

    $created = array_flip($result['created']);

    foreach ($result['stamped'] as $vid) {
        echo $prefix . str_pad(isset($created[$vid]) ? 'created' : 'restamped', 9) . ' '
            . $vid . MetaStore::EXTENSION . ' -> ' . date(Meta::DATE_FORMAT, (int) $stamps[$vid]) . "\n";
    }

    foreach ($result['skipped'] as $vid) {
        echo $prefix . 'skipped   ' . $vid . " (no readable creation time)\n";
    }

    printf(
        "%s%d dated (%d sidecar(s) created), %d skipped.\n",
        $prefix,
        count($result['stamped']),
        count($result['created']),
        count($result['skipped']),
    );
}

/**
 * The warning, and the question behind it. The prompt goes to STDERR so a run
 * whose output is being piped somewhere still shows it on the terminal.
 */
function confirmRestamp(int $videos, bool $assumeYes): bool
{
    fwrite(STDERR, "
!! WARNING -- this rewrites metadata !!

  " . $videos . " video(s) will be dated from their file creation time.

  * every video without a sidecar gets a new data/<video>.json
  * every video WITH a sidecar has its stored date OVERWRITTEN, including
    dates set by hand in edit.php -- they cannot be recovered afterwards
  * ratings, tags and authors are left exactly as they are

  Back up data/ first, or run `php migrate.php --restamp --dry-run` for the list.

");

    if ($assumeYes) {
        return true;
    }

    fwrite(STDERR, 'Type "yes" to continue: ');

    $answer = fgets(STDIN);

    fwrite(STDERR, "\n");

    return $answer !== false && strtolower(trim($answer)) === 'yes';
}
