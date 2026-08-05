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
require_once __DIR__ . '/src/Thumbnailer.php';
require_once __DIR__ . '/src/VideoLibrary.php';

use VideoPlatform\Meta;
use VideoPlatform\MetaStore;
use VideoPlatform\Thumbnailer;
use VideoPlatform\VideoLibrary;

//everything that reaches the browser goes through here: filenames, tags and
//authors are user data, and the filter values come straight off the query string

function escapeHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

//view state: the params that describe the current grid (page + filters)
//
//p = page, s = size of list, l = elements in line, o = order, u = sense,
//q = free-text search over the filename

/**
 * @return array{page: int, size: int, cols: int, order: int, sense: string, query: string, tag: string, author: string, rate: string}
 */
function gridParams(): array
{
    $size = (int) ($_GET['s'] ?? 0);
    $cols = (int) ($_GET['l'] ?? 0);

    return array(
        'page' => max(0, (int) ($_GET['p'] ?? 0)),
        'size' => $size > 0 ? $size : 20,
        'cols' => $cols > 0 ? $cols : 4,
        //date, latest first, unless the form says otherwise: a library grows
        //at the end, so what was just added is what the grid should open on

        'order' => ($_GET['o'] ?? '1') == 1 ? 1 : 0,
        'sense' => ($_GET['u'] ?? '') === 'a' ? 'a' : 'd',
        'query' => (string) ($_GET['q'] ?? ''),
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
        'q' => (string) $params['query'],
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

    foreach (array('p', 's', 'l', 'o', 'u', 'q', 'author', 'rate', 'tag') as $name) {
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

//the one stylesheet: every page embeds it, so there is no asset to fetch and
//no second place where a colour is defined

function pageStyle(): string
{
    return '
:root {
	--bg: #111111;
	--card: #1e1e1e;
	--sunken: #191919;
	--border: #2f2f2f;
	--fg: #e6e6e6;
	--muted: #9a9a9a;
	--accent: #ee1111;
	--gap: 12px;
	--radius: 8px;
}

* { box-sizing: border-box; }

body {
	margin: 0;
	padding: var(--gap);
	background: var(--bg);
	color: var(--fg);
	font: 16px/1.45 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
}

a { color: var(--fg); text-decoration: none; }
a:hover { color: var(--accent); }

hr { border: none; border-top: solid 1px var(--border); margin: var(--gap) 0; }

input, select {
	background: var(--sunken);
	color: var(--fg);
	border: solid 1px var(--border);
	border-radius: 4px;
	padding: 4px 6px;
	font: inherit;
	font-size: 0.9rem;
}

/* the field styling above is for text boxes: a checkbox keeps its own look */

input[type="checkbox"] { padding: 0; border: none; accent-color: var(--accent); }

input[type="submit"] { cursor: pointer; background: var(--card); }
input[type="submit"]:hover { border-color: var(--accent); color: var(--accent); }

/* one sticky bar carries the nav, the filters and the pager */

.bar {
	position: sticky;
	top: 0;
	z-index: 1;
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: var(--gap);
	margin: calc(-1 * var(--gap)) calc(-1 * var(--gap)) var(--gap);
	padding: var(--gap);
	background: var(--card);
	border-bottom: solid 1px var(--border);
}

.bar .nav { font-weight: 600; }
.bar form { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin: 0; }
.bar .filters { flex: 1 1 auto; }
.bar .pager { display: flex; align-items: center; gap: 4px; }
.bar .pager .page { min-width: 2em; text-align: center; color: var(--muted); }

.field-size { width: 6em; }
.field-search { width: 12em; }
.field-rate { width: 5em; }
.field-text { width: 10em; }

/* the grid: auto-fill tracks, so a card can never stretch a whole row */

.grid {
	display: grid;
	grid-template-columns: repeat(
		auto-fill,
		minmax(max(200px, calc((100% - (var(--cols) - 1) * var(--gap)) / var(--cols))), 1fr)
	);
	gap: var(--gap);
	align-items: start;
}

.card {
	position: relative;
	background: var(--card);
	border: solid 1px var(--border);
	border-radius: var(--radius);
	overflow: hidden;
}

/* fixed 16:9 box: a vertical thumbnail is letterboxed, not made into a tall row */

.card .thumb {
	display: block;
	aspect-ratio: 16 / 9;
	background: #000000;
}

.card .thumb img { width: 100%; height: 100%; object-fit: contain; display: block; }

.card .body { padding: 8px; }

.card .name {
	display: -webkit-box;
	-webkit-line-clamp: 2;
	-webkit-box-orient: vertical;
	overflow: hidden;
	word-break: break-word;
	font-size: 0.9rem;
}

.card .edit {
	position: absolute;
	top: 4px;
	right: 6px;
	padding: 0 4px;
	border-radius: 4px;
	background: rgba(0, 0, 0, 0.55);
}

.rating { color: #e0b93a; letter-spacing: 1px; font-size: 0.9rem; }

.card .facets {
	margin-top: 6px;
	padding-top: 6px;
	border-top: solid 1px var(--border);
	color: var(--muted);
	font-size: 0.78rem;
}

.card .facets a { color: var(--muted); }
.card .facets a:hover { color: var(--accent); }

/* the tag/author index: a native <details>, folded away until asked for, so
   the grid stays the whole page on a phone */

.index {
	background: var(--card);
	border: solid 1px var(--border);
	border-radius: var(--radius);
	margin-bottom: var(--gap);
}

.index > summary {
	cursor: pointer;
	padding: 8px var(--gap);
	font-size: 0.9rem;
	font-weight: 600;
}

.index > summary:hover { color: var(--accent); }

/* auto-fit, not auto-fill: there are only ever three sections, and they should
   share the whole width instead of leaving empty tracks beside them */

.index .columns {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(min(100%, 14em), 1fr));
	gap: var(--gap);
	padding: 0 var(--gap) var(--gap);
	border-top: solid 1px var(--border);
}

.index h2 { font-size: 0.9rem; margin: var(--gap) 0 6px; }
.index .count { color: var(--muted); }

/* a long list scrolls inside its own column instead of pushing the grid down */

.index .list { max-height: 40vh; overflow-y: auto; font-size: 0.9rem; }

.player { display: block; width: 100%; max-height: 70vh; background: #000000; }

/* one form carries both the thumbnail panel and the metadata fields, so
   capturing a frame submits the half-filled fields along and the redraw can
   put them back. The panel is written after the fields and pulled above them
   here: the first submit button in the markup is the one Enter presses, and
   that has to be Save rather than the capture. */

.edit-form { display: flex; flex-direction: column; align-items: stretch; gap: var(--gap); margin: var(--gap) 0; }
.edit-form .fields { display: flex; flex-wrap: wrap; gap: 6px; }
.edit-form .tags-field { flex: 1 1 16em; }
.edit-form .check { display: flex; align-items: center; gap: 4px; font-size: 0.9rem; color: var(--muted); }

/* the thumbnail panel under the player: capture button, message, preview */

.thumb-panel { order: -1; display: flex; flex-direction: column; align-items: flex-start; gap: 6px; }
.thumb-panel p { margin: 0; }

.notice {
	padding: 6px 8px;
	border: solid 1px var(--border);
	border-radius: 4px;
	background: var(--card);
	color: var(--muted);
	font-size: 0.9rem;
}

.notice.bad { border-color: var(--accent); color: var(--accent); }

/* no thumbnail captured yet: the box is there for the script to fill, and
   until it does it should not push the fields down */

.thumb-preview:empty { display: none; }

.thumb-preview img {
	display: block;
	width: 320px;
	max-width: 100%;
	aspect-ratio: 16 / 9;
	object-fit: contain;
	background: #000000;
	border: solid 1px var(--border);
	border-radius: var(--radius);
}
';
}

/**
 * Document head + open body. Every page starts here, so the doctype, the
 * viewport and the stylesheet are declared in exactly one place.
 */
function renderHead(string $title): void
{
    echo '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>' . escapeHtml($title) . '</title>
<style>' . pageStyle() . '</style>
</head>
<body>
';
}

function renderFoot(): void
{
    echo '
</body>
</html>
';
}

//nav links shown on every page
//$current  = filename of the page we are on, so it is not linked
//$homeUrl  = where "Home" points (edit.php passes the grid it came from)

function navLinks(string $current = '', string $homeUrl = 'index.php'): string
{
    $links = array(
        'index.php' => array($homeUrl, 'Home'),
    );

    $parts = array();

    foreach ($links as $page => $link) {
        if ($page == $current) {
            array_push($parts, '<b>' . escapeHtml($link[1]) . '</b>');
        } else {
            array_push($parts, '<a href="' . escapeHtml($link[0]) . '">' . escapeHtml($link[1]) . '</a>');
        }
    }

    return '<div class="nav">' . implode(' &middot; ', $parts) . '</div>';
}

/**
 * The bar at the top of every page: nav, and on the grid the filters and the
 * pager as well. It replaces the old top/bottom copies of both forms.
 *
 * @param array{page: int, size: int, cols: int, order: int, sense: string, query: string, tag: string, author: string, rate: string}|null $params
 * @param array{tags: list<string>, authors: list<string>}|null                                                            $known
 */
function renderBar(
    string $current = '',
    string $homeUrl = 'index.php',
    ?array $params = null,
    ?array $known = null,
): void {
    echo '<div class="bar">' . navLinks($current, $homeUrl);

    if ($params !== null) {
        echo renderFilterForm($params, $known) . renderPager($params);
    }

    echo '</div>
';
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

//thumbnails: thumbs/<video>.png, captured from the player by edit.php
//
//ffmpeg is rarely on PATH on a Windows/XAMPP box, so the binary is taken from
//the VIDEO_PLATFORM_FFMPEG environment variable when it is set

function ffmpegBinary(): string
{
    //$_SERVER as well as getenv(): Apache's SetEnv only lands in one of them
    //depending on how PHP is wired in

    foreach (array(getenv('VIDEO_PLATFORM_FFMPEG'), $_SERVER['VIDEO_PLATFORM_FFMPEG'] ?? null) as $binary) {
        if (is_string($binary) && trim($binary) !== '') {
            return trim($binary);
        }
    }

    return 'ffmpeg';
}

function thumbnailer(): Thumbnailer
{
    static $thumbnailer = null;

    return $thumbnailer ??= new Thumbnailer(__DIR__ . '/thumbs', ffmpegBinary());
}

//the file name never changes when a thumbnail is recaptured, so the mtime
//rides along to keep the browser from showing the old frame

function thumbUrl(string $vid): string
{
    $url = 'thumbs/' . rawurlencode($vid) . '.png';

    $stamp = @filemtime(thumbnailer()->path($vid));

    return $stamp === false ? $url : $url . '?v=' . $stamp;
}

/**
 * When a video file was created, as a unix timestamp, or null when there is
 * nothing to read (no such file, or the stat failed).
 *
 * PHP has no portable creation time: filectime() *is* the creation time on
 * Windows, which is what this app runs on, while on unix it is the inode's last
 * change time. The mtime is the fallback either way, so a filesystem that
 * reports neither gives a null rather than a 1970 date.
 */
function videoCreated(string $vid): ?int
{
    $path = videoPath($vid);

    if ($path === '' || !is_file($path)) {
        return null;
    }

    foreach (array(@filectime($path), @filemtime($path)) as $stamp) {
        if (is_int($stamp) && $stamp > 0) {
            return $stamp;
        }
    }

    return null;
}

/**
 * The date a video's first sidecar is stamped with: when its file was created,
 * or today when the filesystem has nothing to say.
 */
function defaultVideoDate(string $vid): string
{
    $stamp = videoCreated($vid);

    return $stamp === null ? Meta::today() : date(Meta::DATE_FORMAT, $stamp);
}

/**
 * When a video is dated, for the grid's date sort: the date stored in its
 * metadata, falling back to the file itself for sidecars written before the
 * field existed (`php migrate.php --dates` stamps those).
 *
 * The fallback is the date videoCreated() reads, so an undated video sorts
 * where a first save from edit.php would put it. One with nothing readable
 * behind it sorts as the oldest rather than blowing up.
 *
 * @param Meta|null $meta the metadata if the caller has it loaded already
 */
function videoDate(string $vid, ?Meta $meta = null): int
{
    return ($meta ?? loadMeta($vid))->timestamp() ?? videoCreated($vid) ?? 0;
}

/**
 * The grid's matches in the order the view state asks for.
 *
 * The comparison is always ascending -- by name, or by date with the name
 * behind it so a batch sharing one date keeps a stable order -- and the sense
 * is applied on top of it. Sorting descending here as well would have the two
 * cancel out, which is how "Ascending" used to hand back the newest videos.
 *
 * @param list<string>                    $vids   in name order, as the library lists them
 * @param array{order: int, sense: string} $params the view state
 * @param array<string, int>              $dates  by video id; only read for the date order
 *
 * @return list<string>
 */
function sortVideos(array $vids, array $params, array $dates = array()): array
{
    if ($params['order'] == 1) {
        usort($vids, function (string $a, string $b) use ($dates) {
            return array($dates[$a] ?? 0, $a) <=> array($dates[$b] ?? 0, $b);
        });
    }

    if ($params['sense'] == 'd') {
        $vids = array_reverse($vids);
    }

    return array_values($vids);
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
 * Split a comma-separated filter field into the values it lists.
 *
 * @return list<string>
 */
function filterValues(string $field): array
{
    $values = array();

    foreach (explode(',', $field) as $value) {
        $value = trim($value);

        if ($value !== '') {
            array_push($values, $value);
        }
    }

    return $values;
}

/**
 * Does this video's filename match the search box?
 *
 * A case-insensitive substring match, kept apart from matchesFilters() because
 * it needs no metadata: a video with no sidecar yet is searchable all the same.
 * An empty (or all-space) field filters nothing, like the other filters.
 *
 * @param array{query: string, ...} $params
 */
function matchesName(string $vid, array $params): bool
{
    $needle = trim($params['query']);

    if ($needle === '') {
        return true;
    }

    return stripos($vid, $needle) !== false;
}

/**
 * Does this video pass the current filters?
 *
 * Several tags (or authors) narrow: a video has to carry all of them. An empty
 * field filters nothing, and the rating is a floor, not an exact match.
 *
 * @param array{tag: string, author: string, rate: string, ...} $params
 */
function matchesFilters(Meta $meta, array $params): bool
{
    foreach (filterValues($params['tag']) as $tag) {
        if (!in_array($tag, $meta->tags, true)) {
            return false;
        }
    }

    foreach (filterValues($params['author']) as $author) {
        if (!in_array($author, $meta->authors, true)) {
            return false;
        }
    }

    if (is_numeric($params['rate']) && $meta->rate < (int) $params['rate']) {
        return false;
    }

    return true;
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
 * Render a 0-5 rating as text stars, so it needs no image assets. Ratings from
 * a stored file are not trusted to be in range.
 */
function renderRating(int $rate): string
{
    $rate = max(0, min(5, $rate));

    $stars = str_repeat('&#9733;', $rate) . str_repeat('&#9734;', 5 - $rate);

    return '<span class="rating" title="' . $rate . '/5">' . $stars . '</span>';
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
function renderSelect(string $name, string $selected, array $options): string
{
    $out = '<select name="' . escapeHtml($name) . '">';

    foreach ($options as $value => $label) {
        $out .= '<option value="' . escapeHtml((string) $value) . '"'
            . ((string) $value === $selected ? ' selected' : '')
            . '>' . escapeHtml($label) . '</option>';
    }

    return $out . '</select>';
}

/**
 * The known values of a filter field, as a datalist the browser completes
 * from. See completionScript() for the comma-separated case.
 *
 * @param list<string> $values
 */
function renderDatalist(string $id, array $values): string
{
    $out = '<datalist id="' . escapeHtml($id) . '">';

    foreach ($values as $value) {
        $out .= '<option value="' . escapeHtml($value) . '">';
    }

    return $out . '</datalist>';
}

/**
 * A native datalist matches its options against the whole field, so once a
 * comma is typed nothing matches any more. This re-points the options at the
 * value being typed, keeping the ones already entered as a prefix.
 *
 * With scripting off the field still works: it just completes the first value
 * only, which is the plain native behaviour.
 */
function completionScript(): string
{
    return '<script>
for (const box of document.querySelectorAll("input[list]")) {
	const list = document.getElementById(box.getAttribute("list"));

	if (!list) {
		continue;
	}

	const values = [...list.options].map(function (option) {
		return option.value;
	});

	box.addEventListener("input", function () {
		const head = box.value.slice(0, box.value.lastIndexOf(",") + 1);

		//keep the spacing the typist used, or the option stops matching

		const pad = box.value.slice(head.length).startsWith(" ") ? " " : "";

		values.forEach(function (value, i) {
			list.options[i].value = head + pad + value;
		});
	});
}
</script>';
}

/**
 * The filter form. It lives in the sticky bar, once per page.
 *
 * @param array{page: int, size: int, cols: int, order: int, sense: string, query: string, tag: string, author: string, rate: string} $params
 * @param array{tags: list<string>, authors: list<string>}|null                                                        $known  values offered as autocomplete
 */
function renderFilterForm(array $params, ?array $known = null): string
{
    $order = renderSelect('o', (string) $params['order'], array('0' => 'Name', '1' => 'Date'));
    $sense = renderSelect('u', $params['sense'], array('a' => 'Ascending', 'd' => 'Descending'));

    $lists = '';
    $tagList = '';
    $authorList = '';

    if ($known !== null) {
        $lists = renderDatalist('tags', $known['tags'])
            . renderDatalist('authors', $known['authors'])
            . completionScript();
        $tagList = ' list="tags"';
        $authorList = ' list="authors"';
    }

    return '<form class="filters" action="index.php" method="GET">
<input class="field-size" name="s" type="number" min="1" placeholder="Per page" value="' . $params['size'] . '">
<input class="field-size" name="l" type="number" min="1" placeholder="Per row" value="' . $params['cols'] . '">
' . $order . $sense . '
<input class="field-search" name="q" type="search" placeholder="Search names" title="Part of a filename; case-insensitive" value="' . escapeHtml($params['query']) . '">
<input class="field-text" name="tag"' . $tagList . ' placeholder="Tags" title="Comma-separated; a video must carry all of them" value="' . escapeHtml($params['tag']) . '">
<input class="field-text" name="author"' . $authorList . ' placeholder="Authors" title="Comma-separated; a video must carry all of them" value="' . escapeHtml($params['author']) . '">
<input class="field-rate" name="rate" type="number" min="0" max="5" placeholder="Rating &ge;" title="Minimum rating" value="' . escapeHtml($params['rate']) . '">
<input type="submit" value="Filter">
' . $lists . '</form>';
}

/**
 * One pager button: the whole view state as hidden fields, with $page swapped in.
 *
 * @param array<string, mixed> $params
 */
function renderPagerButton(array $params, int $page, string $label): string
{
    $out = '<form action="index.php" method="GET">';

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
 * The pager, part of the sticky bar. Page 0 is the first page, so "-" never
 * walks off the front of the list.
 *
 * @param array{page: int, size: int, cols: int, order: int, sense: string, query: string, tag: string, author: string, rate: string} $params
 */
function renderPager(array $params): string
{
    //the label is escaped on the way out, so it is text, not an entity

    return '<div class="pager">'
        . renderPagerButton($params, max(0, $params['page'] - 1), 'Prev')
        . '<span class="page">' . $params['page'] . '</span>'
        . renderPagerButton($params, $params['page'] + 1, 'Next')
        . '</div>';
}

/**
 * One column of the index: each name links to the grid filtered by it, with
 * the number of videos that carry it.
 *
 * @param array<string, int> $counts name => number of videos
 * @param string             $param  the grid filter these names feed: "tag" or "author"
 */
function renderCountList(array $counts, string $param): string
{
    if ($counts === array()) {
        return '<span class="count">none</span>';
    }

    $out = '';

    foreach ($counts as $name => $count) {
        $out .= '<a href="index.php?' . $param . '=' . urlencode((string) $name) . '">'
            . escapeHtml((string) $name) . '</a> <span class="count">' . $count . '</span><br>';
    }

    return $out;
}

/**
 * The tag/author index, folded into the grid page as a native <details>: no
 * sidebar, and on a phone it costs one line until it is opened.
 *
 * @param array{tags: array<string, int>, authors: array<string, int>} $counts
 * @param list<string>                                                 $missing videos with no sidecar yet
 */
function renderIndexPanel(array $counts, array $missing): string
{
    $summary = count($counts['tags']) . ' tags &middot; ' . count($counts['authors']) . ' authors';

    if ($missing !== array()) {
        $summary .= ' &middot; ' . count($missing) . ' missing metadata';
    }

    $links = '';

    foreach ($missing as $vid) {
        $links .= '<a href="edit.php?vid=' . urlencode($vid) . '">' . escapeHtml($vid) . '</a><br>';
    }

    return '<details class="index">
<summary>Tags &amp; authors <span class="count">(' . $summary . ')</span></summary>
<div class="columns">
<section><h2>Authors</h2><div class="list">' . renderCountList($counts['authors'], 'author') . '</div></section>
<section><h2>Tags</h2><div class="list">' . renderCountList($counts['tags'], 'tag') . '</div></section>
<section><h2>Missing metadata</h2><div class="list">'
        . ($links === '' ? '<span class="count">none</span>' : $links) . '</div></section>
</div>
</details>
';
}

/**
 * The values the edit form's fields are drawn with.
 *
 * A request that carried the form wins over what is on disk, so redrawing the
 * page after a capture — or after one that failed — puts back exactly what was
 * typed, down to the spacing, instead of rolling the fields back to the
 * sidecar. Anything else (arriving on the page, coming back from a save) is
 * drawn from the stored metadata.
 *
 * @param array<string, mixed> $post
 * @param string               $date the value the picker opens on when the request carried no form:
 *                                   the stored date, or the one a first save would stamp
 *
 * @return array{rate: string, tags: string, authors: string, date: string, now: bool}
 */
function editFormFields(array $post, Meta $meta, string $date): array
{
    if (($post['form'] ?? '') !== 'edit') {
        return array(
            'rate' => (string) $meta->rate,
            'tags' => implode(', ', $meta->tags),
            'authors' => implode(', ', $meta->authors),
            'date' => $date,
            'now' => false,
        );
    }

    return array(
        'rate' => postedField($post, 'rate'),
        'tags' => postedField($post, 'tags'),
        'authors' => postedField($post, 'authors'),
        'date' => postedField($post, 'date'),
        'now' => isset($post['now']),
    );
}

/**
 * One posted field as a string. A field a caller sent as an array (or left
 * out) is no field at all rather than a warning or the word "Array".
 *
 * @param array<string, mixed> $post
 */
function postedField(array $post, string $name): string
{
    $value = $post[$name] ?? '';

    return is_scalar($value) ? (string) $value : '';
}

/**
 * One card in the grid. $meta is null for a video with no sidecar yet, which
 * is the one case where no rating is shown.
 *
 * @param string $ret the grid query edit.php should come back to
 */
function renderVideoCard(string $vid, ?Meta $meta, string $ret): string
{
    $editUrl = 'edit.php?vid=' . urlencode($vid) . '&amp;ret=' . urlencode($ret);
    $videoUrl = escapeHtml(videoUrl($vid));

    $out = '<article class="card">
<a class="edit" href="' . $editUrl . '" title="Edit">&#9998;</a>
<a class="thumb" href="' . $videoUrl . '">
<img src="' . escapeHtml(thumbUrl($vid)) . '" alt="" loading="lazy"'
        . ' onerror="this.onerror=null;this.src=&#039;thumbs/err.png&#039;">
</a>
<div class="body">
<a class="name" href="' . $videoUrl . '">' . escapeHtml($vid) . '</a>';

    if ($meta !== null) {
        $out .= '
<div>' . renderRating($meta->rate) . '</div>
<div class="facets"><b>Tags:</b> ' . renderLinkList($meta->tags, 'tag')
            . '<br><b>Authors:</b> ' . renderLinkList($meta->authors, 'author') . '</div>';
    }

    return $out . '
</div>
</article>';
}
