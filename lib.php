<?php

/**
 * Shared helpers for the plain-PHP pages: navigation, view state, rendering
 * and metadata access.
 *
 * The metadata classes are loaded with bare requires (not composer's
 * autoloader) so the app keeps working on a host that has no `vendor/`.
 */

declare(strict_types=1);

require_once __DIR__ . '/src/Meta.php';
require_once __DIR__ . '/src/MetaStore.php';
require_once __DIR__ . '/src/Migrator.php';
require_once __DIR__ . '/src/VideoLibrary.php';

use VideoPlatform\Meta;
use VideoPlatform\MetaStore;
use VideoPlatform\VideoLibrary;

//everything that reaches the browser goes through here: filenames, tags and
//authors are user data, and the filter values come straight off the query string

function escapeHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

//view state: the params that describe the current grid (page + filters)
//
//p = page, s = size of list, l = elements in line, o = order, u = sense

/**
 * @return array{page: int, size: int, cols: int, order: int, sense: string, tag: string, author: string, rate: string}
 */
function gridParams(): array
{
    $size = (int) ($_GET['s'] ?? 0);
    $cols = (int) ($_GET['l'] ?? 0);

    return array(
        'page' => max(0, (int) ($_GET['p'] ?? 0)),
        'size' => $size > 0 ? $size : 20,
        'cols' => $cols > 0 ? $cols : 4,
        'order' => ($_GET['o'] ?? '') == 1 ? 1 : 0,
        'sense' => ($_GET['u'] ?? '') === 'a' ? 'a' : 'd',
        'tag' => (string) ($_GET['tag'] ?? ''),
        'author' => (string) ($_GET['author'] ?? ''),
        'rate' => (string) ($_GET['rate'] ?? ''),
    );
}

/**
 * The view state as query-string fields, in the order the forms carry them.
 *
 * @param array<string, mixed> $params
 *
 * @return array<string, string>
 */
function gridFields(array $params): array
{
    return array(
        'p' => (string) $params['page'],
        's' => (string) $params['size'],
        'l' => (string) $params['cols'],
        'o' => (string) $params['order'],
        'u' => (string) $params['sense'],
        'author' => (string) $params['author'],
        'rate' => (string) $params['rate'],
        'tag' => (string) $params['tag'],
    );
}

/**
 * The current grid view as a query string, dropping empty filters.
 *
 * @param array<string, mixed>|null $params
 */
function gridQuery(?array $params = null): string
{
    $fields = gridFields($params ?? gridParams());

    if ($fields['p'] === '0') {
        unset($fields['p']);
    }

    return http_build_query(array_filter($fields, function ($value) {
        return $value !== '';
    }));
}

/**
 * Rebuild a query string handed to us by a caller (edit.php's "ret") from the
 * grid fields alone, so nothing else can ride along into a URL or a header.
 */
function sanitizeGridQuery(string $query): string
{
    $parsed = array();
    parse_str($query, $parsed);

    $fields = array();

    foreach (array('p', 's', 'l', 'o', 'u', 'author', 'rate', 'tag') as $name) {
        $value = $parsed[$name] ?? null;

        if (is_scalar($value) && (string) $value !== '') {
            $fields[$name] = (string) $value;
        }
    }

    return http_build_query($fields);
}

//link back to the grid, keeping page & filters if we were given them

function gridUrl(string $query = ''): string
{
    $query = sanitizeGridQuery($query);

    if ($query === '') {
        return 'index.php';
    }

    return 'index.php?' . $query;
}

//nav header shown on every page
//$current  = filename of the page we are on, so it is not linked
//$homeUrl  = where "Home" points (edit.php passes the grid it came from)

function navHeader(string $current = '', string $homeUrl = 'index.php'): void
{
    $links = array(
        'index.php' => array($homeUrl, 'Home'),
        'browse.php' => array('browse.php', 'Filters'),
    );

    $parts = array();

    foreach ($links as $page => $link) {
        if ($page == $current) {
            array_push($parts, '<b>' . $link[1] . '</b>');
        } else {
            array_push($parts, '<a href="' . htmlspecialchars($link[0]) . '">' . $link[1] . '</a>');
        }
    }

    echo '<div style="padding-bottom:4px;margin-bottom:6px;border-bottom:solid 1px #888888;">'
        . implode(' &middot; ', $parts)
        . '</div>';
}

//metadata access: data/<video>.json sidecars

function metaStore(): MetaStore
{
    static $store = null;

    return $store ??= new MetaStore(__DIR__ . '/data');
}

function loadMeta(string $vid): Meta
{
    return metaStore()->load($vid);
}

function saveMeta(string $vid, Meta $meta): void
{
    metaStore()->save($vid, $meta);
}

function hasMeta(string $vid): bool
{
    return metaStore()->has($vid);
}

//the video library: its own directory, so the app's own files are not videos

function videoLibrary(): VideoLibrary
{
    static $library = null;

    return $library ??= new VideoLibrary(__DIR__ . '/videos', 'videos/');
}

/**
 * Every file the library treats as a video.
 *
 * @return list<string>
 */
function videoFiles(): array
{
    return videoLibrary()->files();
}

function videoExists(string $vid): bool
{
    return videoLibrary()->has($vid);
}

function videoUrl(string $vid): string
{
    return videoLibrary()->url($vid);
}

function videoPath(string $vid): string
{
    return videoLibrary()->path($vid);
}

function thumbUrl(string $vid): string
{
    return 'thumbs/' . rawurlencode($vid) . '.png';
}

/**
 * Videos with no metadata sidecar yet.
 *
 * @return list<string>
 */
function videosMissingMeta(): array
{
    return array_values(array_filter(videoFiles(), function (string $vid) {
        return !hasMeta($vid);
    }));
}

/**
 * How many videos carry each tag and each author, in one pass over the store.
 *
 * @return array{tags: array<string, int>, authors: array<string, int>}
 */
function metaCounts(): array
{
    $counts = array('tags' => array(), 'authors' => array());

    foreach (metaStore()->ids() as $vid) {
        $meta = loadMeta($vid);

        $values = array('tags' => $meta->tags, 'authors' => $meta->authors);

        foreach ($values as $field => $names) {
            foreach ($names as $name) {
                $counts[$field][$name] = ($counts[$field][$name] ?? 0) + 1;
            }
        }
    }

    ksort($counts['tags']);
    ksort($counts['authors']);

    return $counts;
}

/**
 * Render a 0-5 rating as star images.
 */
function renderRating(int $rate): string
{
    $out = '';

    for ($i = 0; $i < 5; $i++) {
        $src = $i < $rate ? 'thumbs/rating-on.png' : 'thumbs/rating-off.png';
        $out .= '<img style="width:0.75em;" src="' . $src . '">';
    }

    return $out;
}

/**
 * Comma-separated links that re-filter the grid by tag or by author.
 *
 * @param list<string> $values
 * @param string       $param  the grid filter these values feed: "tag" or "author"
 */
function renderLinkList(array $values, string $param): string
{
    $links = array();

    foreach ($values as $value) {
        array_push(
            $links,
            '<a href="index.php?' . $param . '=' . urlencode($value) . '">' . escapeHtml($value) . '</a>',
        );
    }

    return implode(', ', $links);
}

/**
 * @param array<string|int, string> $options value => label
 */
function renderSelect(string $name, string $selected, array $options, string $style = ''): string
{
    $out = '<select';

    if ($style !== '') {
        $out .= ' style="' . $style . '"';
    }

    $out .= ' name="' . escapeHtml($name) . '">';

    foreach ($options as $value => $label) {
        $out .= '<option value="' . escapeHtml((string) $value) . '"'
            . ((string) $value === $selected ? ' selected' : '')
            . '>' . escapeHtml($label) . '</option>';
    }

    return $out . '</select>';
}

/**
 * The filter form, shown above and below the grid.
 *
 * @param array{page: int, size: int, cols: int, order: int, sense: string, tag: string, author: string, rate: string} $params
 */
function renderFilterForm(array $params): string
{
    $sense = renderSelect(
        'u',
        $params['sense'],
        array('a' => 'Ascending', 'd' => 'Descending'),
        'height:1.4em;width:8em;',
    );

    $order = renderSelect(
        'o',
        (string) $params['order'],
        array('0' => 'Name', '1' => 'Date'),
        'height:1.4em;width:8em;',
    );

    return '
<form action="index.php" method="GET">
<input name="s" type="number" placeholder="Size of list" value="' . $params['size'] . '">
<input name="l" type="number" placeholder="Size of line" value="' . $params['cols'] . '">
<p>' . $sense . '
<input style="height:1.4em;width:11.3em;" name="tag" placeholder="Tag" value="' . escapeHtml($params['tag']) . '">
<br>' . $order . '
<input style="height:1.4em;width:8em;" name="author" placeholder="Author" value="' . escapeHtml($params['author']) . '">
<input style="height:1.4em;width:3em;" name="rate" placeholder="Rating" type="number" value="' . escapeHtml($params['rate']) . '">
<p>
<input type="submit">
</form>';
}

/**
 * One pager button: the whole view state as hidden fields, with $page swapped in.
 *
 * @param array<string, mixed> $params
 */
function renderPagerButton(array $params, int $page, string $label): string
{
    $out = '<form action="index.php" method="GET" style="display:inline-block;">';

    $fields = gridFields(array_merge($params, array('page' => $page)));

    foreach ($fields as $name => $value) {
        $out .= '
	<input type="hidden" name="' . escapeHtml($name) . '" value="' . escapeHtml($value) . '">';
    }

    return $out . '
	<input type="submit" value="' . escapeHtml($label) . '">
</form>';
}

/**
 * The pager, shown above and below the grid. Page 0 is the first page, so "-"
 * never walks off the front of the list.
 *
 * @param array{page: int, size: int, cols: int, order: int, sense: string, tag: string, author: string, rate: string} $params
 * @param string                                                                                                       $style   placement of the pager box
 */
function renderPager(array $params, string $style): string
{
    return '
<div style="color:ffffff;position:absolute;' . $style . '">
Page: ' . renderPagerButton($params, max(0, $params['page'] - 1), '-')
        . '
' . $params['page'] . '
' . renderPagerButton($params, $params['page'] + 1, '+') . '
</div>';
}
