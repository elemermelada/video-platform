<?php

declare(strict_types=1);

namespace VideoPlatform;

/**
 * One-off conversion of legacy `.data` sidecars into `.json`.
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
}
