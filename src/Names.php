<?php

declare(strict_types=1);

namespace VideoPlatform;

/**
 * How the app orders and compares the names a person types: video filenames,
 * tags and authors.
 *
 * Case is a spelling accident, not a category. Sorting by raw bytes herds every
 * capital above every lowercase letter, which splits a list into two alphabets
 * to scan; matching by raw bytes makes "Action" a different tag from "action",
 * so a filter silently drops half the library.
 *
 * ASCII case is all of it: strcasecmp and strtolower fold a-z and leave the
 * rest alone, so an accented name still compares by its bytes. That is as far
 * as this goes without the intl extension, which the app does not require.
 */
final class Names
{
    /**
     * Order two names the way a reader scans a list: "Action" next to "action".
     * Case only separates names that are otherwise identical, so the order is
     * still total and two spellings never swap about — Aa, Bb, Cc.
     */
    public static function compare(string $a, string $b): int
    {
        return strcasecmp($a, $b) ?: strcmp($a, $b);
    }

    /**
     * Two spellings of the same name.
     */
    public static function same(string $a, string $b): bool
    {
        return strcasecmp($a, $b) === 0;
    }

    /**
     * Is this name in the list, however either side is capitalised?
     *
     * @param list<string> $names
     */
    public static function contains(array $names, string $needle): bool
    {
        foreach ($names as $name) {
            if (self::same($name, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The key every spelling of a name shares, for counting or grouping.
     */
    public static function key(string $name): string
    {
        return strtolower($name);
    }

    /**
     * A list of names in the reading order above.
     *
     * @param list<string> $names
     *
     * @return list<string>
     */
    public static function sort(array $names): array
    {
        usort($names, self::compare(...));

        return $names;
    }
}
