<?php

/**
 * One-off migration: convert `data/<video>.data` sidecars to `data/<video>.json`,
 * and optionally date sidecars from the filesystem.
 *
 * Usage:
 *   php migrate.php [--dry-run] [--keep] [--dates | --restamp [--yes]]
 *   php migrate.php --clear --tags=<list> --authors=<list> [--rate=<n>] [--dry-run] [--yes]
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
 *   --clear    empty the tags and authors of every sidecar poisoned by one bad
 *              batch of metadata: those carrying exactly the --tags and exactly
 *              the --authors listed, and exactly --rate when it is given (which
 *              also zeroes the rating). Both lists are required and may be empty
 *              (`--tags=`), and both are comma-separated. It warns and asks
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
$clear = in_array('--clear', $argv, true);
$assumeYes = in_array('--yes', $argv, true);

//--restamp does everything --dates does and more, so asking for both is a
//mistake worth stopping on rather than quietly picking one

if ($dates && $restamp) {
    exit("--dates and --restamp do the same job; --restamp overwrites, --dates does not. Pick one.\n");
}

//--clear writes for its own reasons and touches every sidecar it matches;
//pairing it with a date pass hides one run's output inside the other's

if ($clear && ($dates || $restamp)) {
    exit("--clear does not combine with --dates or --restamp. Run them separately.\n");
}

//read the --clear selectors before anything is written, so a mistyped run stops
//before the legacy conversion below rather than halfway through the job

$clearTags = null;
$clearAuthors = null;
$clearRate = null;

if ($clear) {
    $clearTags = option($argv, '--tags');
    $clearAuthors = option($argv, '--authors');
    $rate = option($argv, '--rate');

    if ($clearTags === null || $clearAuthors === null) {
        exit("--clear needs both --tags= and --authors=; either may be empty, but leaving one out is not the same as asking for the empty list.\n");
    }

    if ($rate !== null && !preg_match('/^\d+$/', trim($rate))) {
        //not range-checked: a stored rating is not trusted to be 0-5, and a
        //poisoned sidecar carrying something else has to be selectable too

        exit("--rate takes a whole number.\n");
    }

    $clearRate = $rate === null ? null : (int) trim($rate);
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

if ($clear) {
    clearPoisoned(
        $migrator,
        filterValues((string) $clearTags),
        filterValues((string) $clearAuthors),
        $clearRate,
        $dryRun,
        $assumeYes,
        $prefix,
    );

    exit;
}

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
 * The value of a `--name=value` option, or null when it was not given at all.
 * An option given without a value (`--tags`) reads as the empty string: it is
 * how a list of nothing is asked for.
 *
 * @param list<string> $argv
 */
function option(array $argv, string $name): ?string
{
    foreach ($argv as $arg) {
        if ($arg === $name) {
            return '';
        }

        if (str_starts_with($arg, $name . '=')) {
            return substr($arg, strlen($name) + 1);
        }
    }

    return null;
}

/**
 * --clear: the tags and authors of one bad batch of metadata, emptied wherever
 * they turn up exactly as they were written. It throws metadata away, so like
 * --restamp it says what it is about to do and waits for the word "yes".
 *
 * @param list<string> $tags
 * @param list<string> $authors
 */
function clearPoisoned(
    Migrator $migrator,
    array $tags,
    array $authors,
    ?int $rate,
    bool $dryRun,
    bool $assumeYes,
    string $prefix,
): void {
    //the dry run of the same selectors, so the warning can name the sidecars it
    //is asking about rather than the pattern it will match them with

    $matched = $migrator->clearPoisoned($tags, $authors, $rate, dryRun: true)['cleared'];

    if ($matched === array()) {
        echo "No sidecar carries exactly that metadata; nothing to clear.\n";

        return;
    }

    if (!$dryRun && !confirmClear($matched, $tags, $authors, $rate, $assumeYes)) {
        echo "Aborted; nothing was written.\n";

        return;
    }

    $result = $migrator->clearPoisoned($tags, $authors, $rate, dryRun: $dryRun);

    foreach ($result['cleared'] as $vid) {
        echo $prefix . 'cleared   ' . $vid . MetaStore::EXTENSION . "\n";
    }

    printf(
        "%s%d cleared, %d left alone (metadata does not match exactly).\n",
        $prefix,
        count($result['cleared']),
        count($result['skipped']),
    );
}

/**
 * The warning for --clear, listing what matched so it can be read before the
 * write happens. Goes to STDERR, like the --restamp one, so a piped run still
 * shows it.
 *
 * @param list<string> $matched
 * @param list<string> $tags
 * @param list<string> $authors
 */
function confirmClear(array $matched, array $tags, array $authors, ?int $rate, bool $assumeYes): bool
{
    $none = '(none)';

    fwrite(STDERR, "
!! WARNING -- this throws metadata away !!

  " . count($matched) . " sidecar(s) carry exactly:

  * tags:    " . ($tags === array() ? $none : implode(', ', $tags)) . '
  * authors: ' . ($authors === array() ? $none : implode(', ', $authors)) . '
  * rating:  ' . ($rate === null ? 'any (and kept as it is)' : $rate . ' (cleared to 0)') . "

  " . implode("\n  ", $matched) . "

  Their tags and authors are emptied; the stored date is kept. This cannot be
  undone -- back up data/ first, or add --dry-run for the list alone.

");

    if ($assumeYes) {
        return true;
    }

    fwrite(STDERR, 'Type "yes" to continue: ');

    $answer = fgets(STDIN);

    fwrite(STDERR, "\n");

    return $answer !== false && strtolower(trim($answer)) === 'yes';
}

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
