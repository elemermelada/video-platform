<?php

declare(strict_types=1);

namespace VideoPlatform;

/**
 * The directory of video files the app lists.
 *
 * The library lives in its own directory (not the web root) so the application
 * files never show up as "videos", and so a video id coming off the query
 * string can be checked against a known list before it is used in a path.
 */
final class VideoLibrary
{
    /**
     * Extensions the library treats as video, lowercase and without the dot.
     *
     * @var list<string>
     */
    public const EXTENSIONS = [
        'avi',
        'flv',
        'm4v',
        'mkv',
        'mov',
        'mp4',
        'mpeg',
        'mpg',
        'ogv',
        'ts',
        'webm',
        'wmv',
    ];

    /**
     * @param string $dir       filesystem directory holding the video files
     * @param string $urlPrefix path the browser uses to reach that directory
     */
    public function __construct(
        private readonly string $dir,
        private readonly string $urlPrefix = '',
    ) {
    }

    /**
     * Every video file in the library, by name, sorted.
     *
     * @return list<string>
     */
    public function files(): array
    {
        $names = [];

        foreach (glob($this->dir . DIRECTORY_SEPARATOR . '*.*') ?: [] as $match) {
            $name = basename($match);

            if (self::isVideoName($name) && is_file($match)) {
                $names[] = $name;
            }
        }

        sort($names);

        return $names;
    }

    /**
     * Is this a video the library actually holds? Callers must ask before
     * putting a caller-supplied id into a path or a link.
     */
    public function has(string $vid): bool
    {
        return self::isValidId($vid) && is_file($this->dir . DIRECTORY_SEPARATOR . $vid);
    }

    /**
     * Filesystem path of a video, or '' for an id that is not a plain video
     * filename (`../`, a nested path, a non-video extension).
     */
    public function path(string $vid): string
    {
        if (!self::isValidId($vid)) {
            return '';
        }

        return $this->dir . DIRECTORY_SEPARATOR . $vid;
    }

    /**
     * URL of a video, or '' for an id the library rejects.
     */
    public function url(string $vid): string
    {
        if (!self::isValidId($vid)) {
            return '';
        }

        return $this->urlPrefix . rawurlencode($vid);
    }

    /**
     * A bare filename (no directory component) with a video extension.
     */
    public static function isValidId(string $vid): bool
    {
        if ($vid === '' || str_contains($vid, '/') || str_contains($vid, '\\')) {
            return false;
        }

        return self::isVideoName($vid);
    }

    public static function isVideoName(string $name): bool
    {
        $dot = strrpos($name, '.');

        if ($dot === false || $dot === 0) {
            return false;
        }

        return in_array(strtolower(substr($name, $dot + 1)), self::EXTENSIONS, true);
    }
}
