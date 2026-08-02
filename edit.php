<?php

require_once("lib.php");

use VideoPlatform\Meta;
use VideoPlatform\Thumbnailer;

//where we came from: the grid, with its page & filters

$ret = sanitizeGridQuery((string) ($_GET["ret"] ?? ""));
$back = gridUrl($ret);

$vid = (string) ($_GET["vid"] ?? "");

//only ever edit a video the library actually holds: the id ends up in a file
//path, so an unchecked one would let a caller write outside data/

if (!videoExists($vid)) {
    http_response_code(404);
    renderHead("Not found");
    renderBar("edit.php", $back);
    echo '<p>No such video.<p><a href="' . escapeHtml($back) . '">&larr; Back to the grid</a>';
    renderFoot();
    exit;
}

//this page again, filters kept: where a capture lands, so reloading the result
//does not run ffmpeg a second time

$self = 'edit.php?vid=' . urlencode($vid) . ($ret === '' ? '' : '&ret=' . urlencode($ret));

//set when a capture failed: the page is then rendered rather than redirected
//to, so the reason survives

$error = '';

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "thumb") {
    $time = Thumbnailer::timestamp($_POST["time"] ?? "");

    if ($time === null) {
        $error = 'That is not a position in the video.';
    } else {
        try {
            thumbnailer()->capture(videoPath($vid), $vid, $time);

            header("Location: " . $self . "&thumb=ok");
            exit;
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }
    }
} elseif ($_SERVER["REQUEST_METHOD"] === "POST") {
    $date = (string) ($_POST["date"] ?? "");

    //"Now" overrides whatever the picker holds, and a first save is dated today
    //so every sidecar written from here on carries a date

    if (isset($_POST["now"]) || ($date === "" && !hasMeta($vid))) {
        $date = Meta::today();
    }

    saveMeta($vid, Meta::fromArray([
        "rate" => $_POST["rate"] ?? "",
        "tags" => $_POST["tags"] ?? "",
        "authors" => $_POST["authors"] ?? "",
        "date" => $date,
    ]));

    //back to the same spot in the grid

    header("Location: " . $back);
    exit;
}

renderHead($vid);
renderBar("edit.php", $back);

$meta = loadMeta($vid);

echo '
<video id="player" class="player" controls src="' . escapeHtml(videoUrl($vid)) . '"></video>
<h2>' . escapeHtml($vid) . '</h2>
';

//the capture button is only offered when ffmpeg is really there, so it cannot
//fail for a reason the page never mentioned

if (thumbnailer()->available()) {
    echo '<form id="thumb-form" class="thumb-form" action="' . escapeHtml($self) . '" method="POST">
<input type="hidden" name="action" value="thumb">
<input type="hidden" name="time" value="0">
<input type="submit" value="Use current frame as thumbnail">
</form>
<script>
const player = document.getElementById("player");
const thumbForm = document.getElementById("thumb-form");

//with scripting off the hidden field keeps its 0 and the first frame is taken

thumbForm.addEventListener("submit", function () {
	thumbForm.elements.time.value = player.currentTime;
});
</script>
';
} else {
    echo '<p class="notice">No ffmpeg here, so frames cannot be captured. Set
VIDEO_PLATFORM_FFMPEG to the binary to turn this on.</p>
';
}

if ($error !== '') {
    echo '<p class="notice bad">' . escapeHtml($error) . '</p>
';
} elseif (($_GET["thumb"] ?? "") === "ok") {
    echo '<p class="notice">Thumbnail captured.</p>
';
}

if (is_file(thumbnailer()->path($vid))) {
    echo '<div class="thumb-preview"><img src="' . escapeHtml(thumbUrl($vid)) . '" alt="Current thumbnail"></div>
';
}

echo '
<form class="edit-form" action="' . escapeHtml($self) . '" method="POST">
<input type="hidden" name="action" value="save">
<input class="field-rate" type="number" min="0" max="5" name="rate" value="' . $meta->rate . '">
<input class="tags-field" type="text" name="tags" placeholder="Tags (comma separated)" value="' . escapeHtml(implode(', ', $meta->tags)) . '">
<input class="tags-field" type="text" name="authors" placeholder="Authors (comma separated)" value="' . escapeHtml(implode(', ', $meta->authors)) . '">
<input class="field-date" type="date" name="date" title="Date the grid sorts by" value="' . escapeHtml($meta->dateOnly()) . '">
<label class="check" title="Save with today&#039;s date, whatever the picker holds"><input type="checkbox" name="now" value="1"> Now</label>
<input type="submit" value="Save">
</form>
<a href="' . escapeHtml($back) . '">&larr; Back to the grid</a>
';

renderFoot();
