<?php

/**
 * Shared helpers for the plain-PHP pages: navigation and metadata access.
 *
 * The metadata classes are loaded with bare requires (not composer's
 * autoloader) so the app keeps working on a host that has no `vendor/`.
 */

declare(strict_types=1);

require_once __DIR__ . '/src/Meta.php';
require_once __DIR__ . '/src/MetaStore.php';
require_once __DIR__ . '/src/Migrator.php';

use VideoPlatform\Meta;
use VideoPlatform\MetaStore;

//the params that describe the current grid view (page + filters)

function grid_query()
{
    $keep = array("p", "s", "l", "o", "u", "author", "tag", "rate");
    $q = array();

    foreach ($keep as $k) {
        if (isset($_GET[$k]) and $_GET[$k] !== "") {
            $q[$k] = $_GET[$k];
        }
    }

    return http_build_query($q);
}

//link back to the grid, keeping page & filters if we were given them

function grid_url($query = "")
{
    if ($query == "") {
        return "index.php";
    }

    return "index.php?" . $query;
}

//nav header shown on every page
//$current  = filename of the page we are on, so it is not linked
//$homeurl  = where "Home" points (edit.php passes the grid it came from)

function nav_header($current = "", $homeurl = "index.php")
{
    $links = array(
        "index.php" => array($homeurl, "Home"),
        "browse.php" => array("browse.php", "Filters"),
        "check.php" => array("check.php", "Missing metadata"),
    );

    $parts = array();

    foreach ($links as $page => $link) {
        if ($page == $current) {
            array_push($parts, "<b>" . $link[1] . "</b>");
        } else {
            array_push($parts, '<a href="' . htmlspecialchars($link[0]) . '">' . $link[1] . "</a>");
        }
    }

    echo '<div style="padding-bottom:4px;margin-bottom:6px;border-bottom:solid 1px #888888;">'
        . implode(" &middot; ", $parts)
        . '</div>';
}

//metadata access: data/<video>.json sidecars

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
