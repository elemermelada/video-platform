# video-platform
Lightweight video listing platform with metadata

## Layout

    videos/   the video files themselves (mp4, webm, mkv, mov, ...)
    thumbs/   <video>.png thumbnails, plus err.png and the rating icons
    data/     <video>.json metadata sidecars, written by edit.php

Videos live in `videos/`, not next to the PHP files: only known video
extensions are listed, and a video id coming off the query string is checked
against that directory before it is used in a path.
