<?php

declare(strict_types=1);

namespace VideoPlatform;

/**
 * One-off conversion of legacy `.data` sidecars into `.json`, the one-off date
 * backfill that lets the mtime fallback in the date sort go away, and the
 * clean-up of sidecars poisoned by a bad batch of metadata.
 */
final class Migrator
{
    public function __construct(private readonly MetaStore $store)
    {
    }

    /**
     * @param bool $deleteLegacy remove the `.data` file once its `.json` exists
     * @param bool $dryRun       report what would happen without touching disk
     *
     * @return array{converted: list<string>, skipped: list<string>, deleted: list<string>}
     */
    public function migrate(bool $deleteLegacy = true, bool $dryRun = false): array
    {
        $converted = [];
        $skipped = [];
        $deleted = [];

        foreach ($this->store->legacyIds() as $vid) {
            $legacyPath = $this->store->legacyPath($vid);

            if ($this->store->has($vid)) {
                // Already migrated — never clobber the JSON that is now authoritative.
                $skipped[] = $vid;
            } else {
                $raw = file_get_contents($legacyPath);

                if ($raw === false) {
                    $skipped[] = $vid;

                    continue;
                }

                if (!$dryRun) {
                    $this->store->save($vid, Meta::parse($raw));
                }

                $converted[] = $vid;
            }

            if ($deleteLegacy) {
                if (!$dryRun) {
                    unlink($legacyPath);
                }

                $deleted[] = $vid;
            }
        }

        return ['converted' => $converted, 'skipped' => $skipped, 'deleted' => $deleted];
    }

    /**
     * Stamp every dateless sidecar with a date worked out from the filesystem,
     * so the grid stops needing the mtime fallback for libraries that predate
     * the `date` field.
     *
     * The timestamp is resolved by the caller: the store knows nothing about
     * where the video files live.
     *
     * @param callable(string): ?int $timestamp video id => unix time, or null when there is nothing to read
     * @param bool                   $dryRun    report what would happen without touching disk
     *
     * @return array{stamped: list<string>, skipped: list<string>}
     */
    public function backfillDates(callable $timestamp, bool $dryRun = false): array
    {
        $stamped = [];
        $skipped = [];

        foreach ($this->store->ids() as $vid) {
            $meta = $this->store->load($vid);

            if ($meta->date !== null) {
                // Already dated — the stored date is authoritative, never an mtime.
                $skipped[] = $vid;

                continue;
            }

            $stamp = $timestamp($vid);

            if ($stamp === null) {
                $skipped[] = $vid;

                continue;
            }

            if (!$dryRun) {
                $this->store->save($vid, $meta->withDate(date(Meta::DATE_FORMAT, $stamp)));
            }

            $stamped[] = $vid;
        }

        return ['stamped' => $stamped, 'skipped' => $skipped];
    }

    /**
     * Date a whole library from the filesystem: every video listed gets a
     * sidecar (created empty if it had none) and a date, and the date that was
     * stored before is replaced.
     *
     * This is the destructive counterpart to backfillDates(), for a library
     * whose stored dates are known to be wrong. The caller is expected to have
     * warned before calling it -- nothing here asks.
     *
     * The list is driven by videos rather than by sidecars, so a video with no
     * metadata yet gets one; a sidecar with no video behind it is not touched,
     * because there is no file to read a date from.
     *
     * @param list<string>           $vids      the videos to date, as the library lists them
     * @param callable(string): ?int $timestamp video id => unix time, or null when there is nothing to read
     * @param bool                   $dryRun    report what would happen without touching disk
     *
     * @return array{stamped: list<string>, created: list<string>, skipped: list<string>}
     */
    public function restampDates(array $vids, callable $timestamp, bool $dryRun = false): array
    {
        $stamped = [];
        $created = [];
        $skipped = [];

        foreach ($vids as $vid) {
            $stamp = $timestamp($vid);

            if ($stamp === null) {
                // Nothing readable behind it: leave whatever is stored alone.
                $skipped[] = $vid;

                continue;
            }

            //load() hands back an empty Meta when there is no sidecar, so the
            //create and the overwrite are the same write

            $isNew = !$this->store->has($vid);
            $meta = $this->store->load($vid);

            if (!$dryRun) {
                $this->store->save($vid, $meta->withDate(date(Meta::DATE_FORMAT, $stamp)));
            }

            if ($isNew) {
                $created[] = $vid;
            }

            $stamped[] = $vid;
        }

        return ['stamped' => $stamped, 'created' => $created, 'skipped' => $skipped];
    }

    /**
     * Clear the sidecars poisoned by one bad batch of metadata: every sidecar
     * carrying exactly the tags and authors given (and exactly the rating, when
     * one is given) has its tags and authors emptied.
     *
     * The match is exact on purpose, so it cannot touch a sidecar somebody has
     * since worked on: a sidecar with all the listed tags *and one more* is not
     * the same sidecar the bad batch wrote, and is left alone. Order and
     * repetition do not matter -- the lists are compared as sets -- but the
     * values are compared as the grid's filters compare them, case and all.
     *
     * The rating is only cleared when one was given to match on; there is no way
     * to tell a rating the bad batch wrote from one somebody meant otherwise.
     * The date is always kept: it says when the video arrived, not what the bad
     * batch thought of it.
     *
     * @param list<string> $tags    the exact tags a poisoned sidecar carries
     * @param list<string> $authors the exact authors a poisoned sidecar carries
     * @param ?int         $rate    the exact rating, or null to match any rating and keep it
     * @param bool         $dryRun  report what would happen without touching disk
     *
     * @return array{cleared: list<string>, skipped: list<string>}
     */
    public function clearPoisoned(array $tags, array $authors, ?int $rate = null, bool $dryRun = false): array
    {
        $cleared = [];
        $skipped = [];

        foreach ($this->store->ids() as $vid) {
            $meta = $this->store->load($vid);

            $matches = self::sameValues($meta->tags, $tags)
                && self::sameValues($meta->authors, $authors)
                && ($rate === null || $meta->rate === $rate);

            if (!$matches) {
                $skipped[] = $vid;

                continue;
            }

            if (!$dryRun) {
                $this->store->save($vid, new Meta($rate === null ? $meta->rate : 0, [], [], $meta->date));
            }

            $cleared[] = $vid;
        }

        return ['cleared' => $cleared, 'skipped' => $skipped];
    }

    /**
     * Do two lists hold the same values, ignoring order and repetition?
     *
     * @param list<string> $left
     * @param list<string> $right
     */
    private static function sameValues(array $left, array $right): bool
    {
        $left = array_unique($left);
        $right = array_unique($right);

        //most sidecars are not the ones being looked for, and the count rules
        //most of those out without sorting anything

        if (count($left) !== count($right)) {
            return false;
        }

        sort($left);
        sort($right);

        return array_values($left) === array_values($right);
    }
}
