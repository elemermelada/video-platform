<?php

/**
 * Shared metadata helpers for the plain-PHP pages.
 *
 * Loaded with bare requires (not composer's autoloader) so the app keeps
 * working on a host that has no `vendor/`.
 */

declare(strict_types=1);

require_once __DIR__ . '/src/Meta.php';
require_once __DIR__ . '/src/MetaStore.php';
require_once __DIR__ . '/src/Migrator.php';

use VideoPlatform\Meta;
use VideoPlatform\MetaStore;

function meta_store(): MetaStore
{
    static $store = null;

    return $store ??= new MetaStore(__DIR__ . '/data');
}

function load_meta(string $vid): Meta
{
    return meta_store()->load($vid);
}

function save_meta(string $vid, Meta $meta): void
{
    meta_store()->save($vid, $meta);
}

function has_meta(string $vid): bool
{
    return meta_store()->has($vid);
}

/**
 * Render a 0-5 rating as star images.
 */
function render_rating(int $rate): string
{
    $out = '';

    for ($i = 0; $i < 5; $i++) {
        $src = $i < $rate ? 'thumbs/rating-on.png' : 'thumbs/rating-off.png';
        $out .= '<img style="width:0.75em;" src="' . $src . '">';
    }

    return $out;
}
