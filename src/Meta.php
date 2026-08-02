<?php

declare(strict_types=1);

namespace VideoPlatform;

use JsonException;

/**
 * Video metadata: a rating plus tag and author lists.
 *
 * The canonical on-disk form is JSON (`{"rate":3,"tags":[],"authors":[]}`).
 * `parse()` still reads the legacy `.data` format (`rate:tag:tag;author:author;`)
 * so `migrate.php` can convert existing libraries.
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

    public static function empty(): self
    {
        return new self(0, [], []);
    }

    /**
     * Parse the legacy `.data` format: `rate:tag:tag;author:author;`.
     */
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
     * @throws JsonException on malformed JSON
     */
    public static function fromJson(string $json): self
    {
        /** @var mixed $decoded */
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($decoded)) {
            throw new JsonException('Metadata JSON must decode to an object.');
        }

        return self::fromArray($decoded);
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            isset($data['rate']) && is_numeric($data['rate']) ? (int) $data['rate'] : 0,
            self::stringList($data['tags'] ?? []),
            self::stringList($data['authors'] ?? []),
        );
    }

    /**
     * @return array{rate: int, tags: list<string>, authors: list<string>}
     */
    public function toArray(): array
    {
        return [
            'rate' => $this->rate,
            'tags' => $this->tags,
            'authors' => $this->authors,
        ];
    }

    public function toJson(): string
    {
        return json_encode(
            $this->toArray(),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT,
        ) . "\n";
    }

    /**
     * @return list<string>
     */
    private static function split(string $field): array
    {
        if ($field === '') {
            return [];
        }

        return self::stringList(explode(':', $field));
    }

    /**
     * Normalise a mixed value into a list of non-empty trimmed strings.
     *
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (!is_array($value)) {
            return [];
        }

        $out = [];

        foreach ($value as $item) {
            if (!is_string($item) && !is_int($item) && !is_float($item)) {
                continue;
            }

            $item = trim((string) $item);

            if ($item !== '') {
                $out[] = $item;
            }
        }

        return $out;
    }
}
