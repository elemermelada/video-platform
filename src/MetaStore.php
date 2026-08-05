<?php

declare(strict_types=1);

namespace VideoPlatform;

use JsonException;
use RuntimeException;

/**
 * Reads and writes `<dir>/<video>.json` metadata sidecars.
 */
final class MetaStore
{
    public const EXTENSION = '.json';
    public const LEGACY_EXTENSION = '.data';

    public function __construct(private readonly string $dir)
    {
    }

    public function path(string $vid): string
    {
        return $this->dir . DIRECTORY_SEPARATOR . self::key($vid) . self::EXTENSION;
    }

    public function legacyPath(string $vid): string
    {
        return $this->dir . DIRECTORY_SEPARATOR . self::key($vid) . self::LEGACY_EXTENSION;
    }

    public function has(string $vid): bool
    {
        return is_file($this->path($vid));
    }

    /**
     * Missing or unreadable metadata yields an empty Meta so callers can stay simple.
     */
    public function load(string $vid): Meta
    {
        $path = $this->path($vid);

        if (!is_file($path)) {
            return Meta::empty();
        }

        $raw = file_get_contents($path);

        if ($raw === false || trim($raw) === '') {
            return Meta::empty();
        }

        try {
            return Meta::fromJson($raw);
        } catch (JsonException) {
            return Meta::empty();
        }
    }

    public function save(string $vid, Meta $meta): void
    {
        if (!is_dir($this->dir) && !@mkdir($this->dir, 0o777, true) && !is_dir($this->dir)) {
            throw new RuntimeException("Cannot create metadata directory: {$this->dir}");
        }

        if (file_put_contents($this->path($vid), $meta->toJson()) === false) {
            throw new RuntimeException("Cannot write metadata for: {$vid}");
        }
    }

    /**
     * Video ids of every JSON sidecar in the store, in Names order.
     *
     * @return list<string>
     */
    public function ids(): array
    {
        return $this->idsWithExtension(self::EXTENSION);
    }

    /**
     * Video ids that still only exist in the legacy `.data` format.
     *
     * @return list<string>
     */
    public function legacyIds(): array
    {
        return $this->idsWithExtension(self::LEGACY_EXTENSION);
    }

    /**
     * @return list<string>
     */
    private function idsWithExtension(string $extension): array
    {
        $matches = glob($this->dir . DIRECTORY_SEPARATOR . '*' . $extension) ?: [];
        $ids = [];

        foreach ($matches as $match) {
            $ids[] = substr(basename($match), 0, -strlen($extension));
        }

        return Names::sort($ids);
    }

    /**
     * Strip any directory component so a video id can never escape the store.
     */
    private static function key(string $vid): string
    {
        return basename(str_replace('\\', '/', $vid));
    }
}
