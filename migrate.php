<?php

/**
 * One-off migration: convert `data/<video>.data` sidecars to `data/<video>.json`,
 * and optionally stamp dateless sidecars from the filesystem.
 *
 * Usage:
 *   php migrate.php [--dry-run] [--keep] [--dates]
 *
 *   --dry-run  report what would change, write nothing
 *   --keep     leave the legacy `.data` files in place (they are deleted by default)
 *   --dates    give every sidecar with no date one, taken from the video's mtime
 *              (the sidecar's own mtime if the video is gone), so index.php's
 *              date sort no longer has to fall back to the filesystem
 */

declare(strict_types=1);

require_once __DIR__ . '/lib.php';

use VideoPlatform\MetaStore;
use VideoPlatform\Migrator;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("migrate.php is a CLI script.\n");
}

$dryRun = in_array('--dry-run', $argv, true);
$keep = in_array('--keep', $argv, true);
$dates = in_array('--dates', $argv, true);

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
