# video-platform

Minimal self-hosted video library browser. Plain PHP, no runtime dependencies,
one embedded stylesheet — point Apache (or `php -S`) at the checkout and it
works. Videos are plain files on disk; metadata is a JSON sidecar per video.

## Requirements

PHP 8.4+ with a web server. `vendor/` is dev tooling only; the app runs
without it (the `src/` classes are loaded with bare requires).

## Setup

1. Serve the repo root, e.g.

       php -S 0.0.0.0:8080

   or an Apache vhost with the checkout as `DocumentRoot`.

2. Drop video files into `videos/`. Recognized extensions: avi, flv, m4v,
   mkv, mov, mp4, mpeg, mpg, ogv, ts, webm, wmv (see
   `VideoLibrary::EXTENSIONS`).

3. Optionally drop a `thumbs/<video filename>.png` thumbnail per video.
   Missing thumbnails fall back to `thumbs/err.png`. Thumbnails render in a
   fixed 16:9 box, letterboxed — vertical thumbs don't stretch the grid.
   Typical generation:

       ffmpeg -ss 10 -i "videos/foo.mp4" -frames:v 1 "thumbs/foo.mp4.png"

   Or let the editor capture one from the player: see [Thumbnails](#thumbnails).

There is no auth. It's built for a trusted LAN; put it behind basic auth or a
VPN if it's reachable from anywhere else.

## Layout

    index.php    the whole browser: grid, filters, tag/author index
    edit.php     per-video player + metadata editor (POST saves, then
                 redirects back to the grid page/filters you came from)
    migrate.php  CLI one-off: legacy .data sidecars -> .json
    lib.php      shared page helpers (procedural, escapes all output)
    src/         Meta, MetaStore, VideoLibrary, Migrator, Thumbnailer
                 (PSR-4, unit-tested)
    videos/      the video files (not the web root, so app files never list
                 as videos and query-string ids are validated against it)
    thumbs/      <video>.png thumbnails + err.png fallback
    data/        <video>.json metadata sidecars

## Using it

- **Grid** (`index.php`): each card links to the raw video file (native
  browser playback) and has a ✎ link to the editor. Tag/author links on a
  card re-filter the grid.
- **Filter form** (sticky bar): `Tags` and `Authors` are comma-separated and
  conjunctive — a video must carry *all* listed values. `Rating ≥` is a
  floor, not an exact match. Known tags/authors autocomplete via
  `<datalist>` (a small inline script keeps completion working after the
  first comma; without JS the first value still completes).
- **Sort**: by name or file mtime, ascending/descending. `Per page` and
  `Per row` control paging and grid density; narrow windows drop columns
  automatically.
- **Tags & authors** (the `<details>` under the bar, collapsed by default):
  every tag and author with its video count — each re-filters the grid — plus
  the list of videos that have no metadata yet (each links to its editor).
- **Edit** (`edit.php?vid=<filename>`): rating 0–5, comma-separated tags and
  authors. Saving writes `data/<filename>.json` and redirects back. The
  player also captures thumbnails — see below.

## Thumbnails

`edit.php` can turn the frame the player is showing into the thumbnail: scrub
to the frame you want and press **Use current frame as thumbnail**. It runs
ffmpeg, writes `thumbs/<video filename>.png` and shows the result under the
player. Capture is synchronous, so a large file can take a few seconds.
Without JavaScript the button still works and takes the first frame.

ffmpeg is optional. It's looked for on `PATH`, and the button is replaced by a
note when it isn't there. Where it isn't on `PATH` (XAMPP on Windows, mostly),
point `VIDEO_PLATFORM_FFMPEG` at the binary — for Apache, in `httpd.conf`:

    SetEnv VIDEO_PLATFORM_FFMPEG "C:/ffmpeg/bin/ffmpeg.exe"

or before `php -S`:

    VIDEO_PLATFORM_FFMPEG=/usr/local/bin/ffmpeg php -S 0.0.0.0:8080

## Metadata format

One JSON file per video at `data/<video filename>.json`:

    {
        "rate": 3,
        "tags": ["tutorial", "phpx"],
        "authors": ["someone"]
    }

Files are written by `edit.php`; hand-editing is fine. Unknown keys are
ignored, missing keys default to empty, out-of-range ratings are clamped on
render.

### Migrating a pre-JSON library

Older versions used `data/<video>.data` with the format
`rate:tag:tag;author:author;`. Convert with:

    php migrate.php --dry-run   # report only
    php migrate.php             # convert and delete .data files
    php migrate.php --keep      # convert, keep the .data files

Existing `.json` files are never overwritten.

## Development

    composer install
    composer test      # phpunit
    composer lint      # php-cs-fixer --dry-run, phpcs, phpstan
    composer format    # php-cs-fixer fix
