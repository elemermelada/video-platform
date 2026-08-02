<?php

declare(strict_types=1);

namespace VideoPlatform;

use DateTimeImmutable;
use JsonException;

/**
 * Video metadata: a rating, tag and author lists, and an optional date.
 *
 * The canonical on-disk form is JSON (`{"rate":3,"tags":[],"authors":[]}`).
 * `parse()` still reads the legacy `.data` format (`rate:tag:tag;author:author;`)
 * so `migrate.php` can convert existing libraries.
 *
 * The date is what the grid sorts by. It is stored rather than taken from the
 * file's mtime, which does not survive a copy, a move between disks or a
 * restore from backup. It is optional: sidecars written before the field
 * existed have none, and callers fall back to the mtime for those.
 */
final class Meta
{
    /**
     * ISO 8601, day precision — what `<input type="date">` sends and what
     * `edit.php` writes.
     */
    public const DATE_FORMAT = 'Y-m-d';

    /**
     * ISO 8601 with a time, kept for hand-edited sidecars that carry one.
     */
    public const DATE_TIME_FORMAT = 'Y-m-d\TH:i:s';

    /**
     * The date, normalised to one of the two formats above, or null.
     */
    public readonly ?string $date;

    /**
     * @param list<string> $tags
     * @param list<string> $authors
     * @param ?string      $date    any accepted spelling; anything else stores null
     */
    public function __construct(
        public readonly int $rate,
        public readonly array $tags,
        public readonly array $authors,
        ?string $date = null,
    ) {
        $this->date = self::normalizeDate($date);
    }

    public static function empty(): self
    {
        return new self(0, [], []);
    }

    /**
     * Today, in the form a sidecar stores: what the "Now" control fills in and
     * what a first save is dated.
     */
    public static function today(): string
    {
        return date(self::DATE_FORMAT);
    }

    /**
     * The same metadata under a different date, for backfilling.
     */
    public function withDate(?string $date): self
    {
        return new self($this->rate, $this->tags, $this->authors, $date);
    }

    /**
     * The date as a unix timestamp, for sorting; null when there is no date.
     */
    public function timestamp(): ?int
    {
        if ($this->date === null) {
            return null;
        }

        $stamp = strtotime($this->date);

        return $stamp === false ? null : $stamp;
    }

    /**
     * The date without any time, which is all `<input type="date">` accepts.
     */
    public function dateOnly(): string
    {
        return $this->date === null ? '' : substr($this->date, 0, 10);
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
        $date = $data['date'] ?? null;

        return new self(
            isset($data['rate']) && is_numeric($data['rate']) ? (int) $data['rate'] : 0,
            self::stringList($data['tags'] ?? []),
            self::stringList($data['authors'] ?? []),
            is_string($date) ? $date : null,
        );
    }

    /**
     * The date key is left out entirely when there is none, so a sidecar never
     * carries a null nobody can act on.
     *
     * @return array{rate: int, tags: list<string>, authors: list<string>, date?: string}
     */
    public function toArray(): array
    {
        $out = [
            'rate' => $this->rate,
            'tags' => $this->tags,
            'authors' => $this->authors,
        ];

        if ($this->date !== null) {
            $out['date'] = $this->date;
        }

        return $out;
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
     * A stored date is user data — from the form, or from a hand-edited file —
     * so only the spellings below are accepted and each is rewritten into the
     * canonical form. Anything else (a free-text date, a stray word) is no date
     * at all, and the caller falls back to the file's mtime.
     *
     * The `!` makes createFromFormat zero the fields the format does not set,
     * so a day-only date does not pick up the current time.
     */
    private static function normalizeDate(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $accepted = [
            '!' . self::DATE_FORMAT => self::DATE_FORMAT,
            '!Y-m-d\TH:i' => self::DATE_TIME_FORMAT,
            '!' . self::DATE_TIME_FORMAT => self::DATE_TIME_FORMAT,
            '!Y-m-d H:i' => self::DATE_TIME_FORMAT,
            '!Y-m-d H:i:s' => self::DATE_TIME_FORMAT,
        ];

        foreach ($accepted as $format => $canonical) {
            $parsed = DateTimeImmutable::createFromFormat($format, $value);

            //getLastErrors() is false only when the value matched exactly:
            //trailing data or an impossible day is a warning, not a failure

            if ($parsed !== false && DateTimeImmutable::getLastErrors() === false) {
                return $parsed->format($canonical);
            }
        }

        return null;
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
