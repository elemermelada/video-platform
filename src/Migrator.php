<?php

declare(strict_types=1);

namespace VideoPlatform;

/**
 * One-off conversion of legacy `.data` sidecars into `.json`, and the one-off
 * date backfill that lets the mtime fallback in the date sort go away.
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
}
