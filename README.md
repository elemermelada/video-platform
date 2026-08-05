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
    migrate.php  CLI one-off: legacy .data sidecars -> .json, --dates to stamp
                 dateless sidecars from the filesystem, --restamp to re-date
                 the whole library (overwrites, so it asks first), --clear to
                 empty the sidecars a bad import poisoned
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
- **Filter form** (sticky bar): `Search names` is a case-insensitive substring
  match on the filename — it needs no metadata, so a video with no sidecar yet
  is findable too. `Tags` and `Authors` are comma-separated and
  conjunctive — a video must carry *all* listed values. `Rating ≥` is a
  floor, not an exact match. Known tags/authors autocomplete via
  `<datalist>` (a small inline script keeps completion working after the
  first comma; without JS the first value still completes).
- **Sort**: by name or date, ascending/descending; the grid opens on the date,
  latest first. The date is the one stored in the sidecar; videos whose sidecar
  has none (or that have no sidecar) fall back to the file's creation date —
  the same date a first save would stamp them with — and videos sharing a date
  keep name order. `Per page` and `Per row` control paging and
  grid density; narrow windows drop columns automatically.
- **Tags & authors** (the `<details>` under the bar, collapsed by default):
  every tag and author with its video count — each re-filters the grid — plus
  the list of videos that have no metadata yet (each links to its editor).
- **Edit** (`edit.php?vid=<filename>`): rating 0–5, comma-separated tags and
  authors, and the date the grid sorts by (a native datepicker, plus a `Now`
  checkbox that saves with today's date whatever the picker holds). A video
  with no sidecar yet opens on its file's creation date (today if the
  filesystem has none), which is what a first save takes. Saving writes `data/<filename>.json` and
  redirects back. The player also captures thumbnails — see below.

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
        "authors": ["someone"],
        "date": "2024-05-06"
    }

Files are written by `edit.php`; hand-editing is fine. Unknown keys are
ignored, missing keys default to empty, out-of-range ratings are clamped on
render.

`date` is optional and is what the date sort uses — stored rather than taken
from the filesystem, so it survives copies, moves between disks and restores
from backup. Accepted: `YYYY-MM-DD`, or with a time as `YYYY-MM-DDTHH:MM[:SS]`
(a space instead of the `T` is fine too). Anything else is read as "no date"
and that video falls back to its file's creation date. The editor works at day precision, so
saving a video whose sidecar carries a time drops it.

### Migrating a pre-JSON library

Older versions used `data/<video>.data` with the format
`rate:tag:tag;author:author;`. Convert with:

    php migrate.php --dry-run   # report only
    php migrate.php             # convert and delete .data files
    php migrate.php --keep      # convert, keep the .data files

Existing `.json` files are never overwritten.

### Backfilling dates

Sidecars written before the `date` field existed have none, and those videos
sort by mtime. Stamp them once so the fallback stops mattering:

    php migrate.php --dates --dry-run   # report only
    php migrate.php --dates             # stamp every dateless sidecar

The date comes from the video file's mtime (the sidecar's own mtime if the
video is gone), and a sidecar that already has a date is left alone.

### Re-dating a whole library

When the stored dates are wrong across the board, `--restamp` dates every video
in the library from its **file creation time**, giving a sidecar to the videos
that have none:

    php migrate.php --restamp --dry-run   # report only
    php migrate.php --restamp             # warns, then asks before writing
    php migrate.php --restamp --yes       # skip the prompt (non-interactive)

⚠️ Unlike `--dates`, this **overwrites the date already stored**,
including dates set by hand in the editor — they can't be recovered, so back up
`data/` first. Ratings, tags and authors are left untouched, and a sidecar with
no video behind it is not touched either (there's no file to read a date from).

PHP has no portable creation time: on Windows `filectime()` really is it, while
on unix it's the inode's last change time, with the mtime as the fallback on
both.

### Clearing poisoned sidecars

When a bad import has written the same wrong tags and authors across the
library, `--clear` empties them wherever they turn up **exactly** as that import
wrote them:

    php migrate.php --clear --tags=auto,import --authors=bot --dry-run
    php migrate.php --clear --tags=auto,import --authors=bot --rate=3
    php migrate.php --clear --tags= --authors= --rate=1 --yes

Both lists are required and comma-separated; either may be empty (`--tags=`),
which selects sidecars carrying none. A sidecar matches only if it has all the
listed tags **and no others**, and likewise for the authors — one that carries
an extra tag is one somebody has worked on since, and it is left alone. Order
and repetition don't matter; the values are compared case-sensitively, as the
grid's filters compare them.

`--rate` is optional and, unlike the grid's `Rating ≥`, it is an exact match:
give it and only sidecars with that rating match, and their rating is cleared to
0 as well. Leave it out and the rating is neither matched nor touched — there's
no telling a rating the import wrote from one somebody meant.

The stored date is always kept. Like `--restamp`, this throws metadata away, so
it lists what matched and asks before writing (`--yes` skips the prompt).

## Development

    composer install
    composer test      # phpunit
    composer lint      # php-cs-fixer --dry-run, phpcs, phpstan
    composer format    # php-cs-fixer fix
