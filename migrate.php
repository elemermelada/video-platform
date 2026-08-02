<?php

/**
 * One-off migration: convert `data/<video>.data` sidecars to `data/<video>.json`.
 *
 * Usage:
 *   php migrate.php [--dry-run] [--keep]
 *
 *   --dry-run  report what would change, write nothing
 *   --keep     leave the legacy `.data` files in place (they are deleted by default)
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

$result = (new Migrator(meta_store()))->migrate(deleteLegacy: !$keep, dryRun: $dryRun);

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
