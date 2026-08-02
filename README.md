# video-platform
Lightweight video listing platform with metadata

## Layout

    videos/   the video files themselves (mp4, webm, mkv, mov, ...)
    thumbs/   <video>.png thumbnails, plus err.png for the ones that are missing
    data/     <video>.json metadata sidecars, written by edit.php

Videos live in `videos/`, not next to the PHP files: only known video
extensions are listed, and a video id coming off the query string is checked
against that directory before it is used in a path.

## Pages

`index.php` is the grid, `browse.php` lists the tags and authors in use (plus
the videos with no metadata yet), and `edit.php` edits one video's metadata.

Every page emits the one embedded stylesheet in `pageStyle()` and a single
sticky bar: navigation, and on the grid the filters and the pager too. The grid
is a CSS grid of `auto-fill` tracks, so a card never stretches a row; `l` ("per
row") sets how many columns a wide window gets, and narrow windows fall back to
fewer. Thumbnails sit in a fixed 16:9 box and are letterboxed rather than
cropped, so a vertical video keeps its shape.

## Filtering

`tag` and `author` take a comma-separated list and narrow: a video has to carry
every value listed. Known tags and authors are offered as `<datalist>`
autocomplete in the filter form; a few lines of inline script re-point the
options at the value being typed, so the second and later values complete too.
With scripting off the first value still completes, natively.

`rate` is a floor, not an exact match — `rate=3` lists everything rated 3 or
better.
