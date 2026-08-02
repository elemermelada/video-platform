<?php

declare(strict_types=1);

namespace VideoPlatform;

/**
 * Parser for the legacy `.data` sidecar format: `rate:tag:tag;author:author;`.
 */
final class Meta
{
    /**
     * @param list<string> $tags
     * @param list<string> $authors
     */
    public function __construct(
        public readonly int $rate,
        public readonly array $tags,
        public readonly array $authors,
    ) {
    }

    public static function parse(string $data): self
    {
        $data = trim($data);

        [$rate, $rest] = array_pad(explode(':', $data, 2), 2, '');
        [$tags, $authors] = array_pad(explode(';', (string) $rest, 3), 3, '');

        return new self(
            (int) $rate,
            self::split((string) $tags),
            self::split((string) $authors),
        );
    }

    /**
     * @return list<string>
     */
    private static function split(string $field): array
    {
        if ($field === '') {
            return [];
        }

        return array_values(array_filter(
            array_map(trim(...), explode(':', $field)),
            static fn (string $part): bool => $part !== '',
        ));
    }
}
