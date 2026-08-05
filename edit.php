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

//this page again, filters kept: what the form posts to

$self = 'edit.php?vid=' . urlencode($vid) . ($ret === '' ? '' : '&ret=' . urlencode($ret));

//how the capture went, for the panel under the player: a message, and whether
//the preview has a new frame to show

$error = '';
$captured = false;

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["thumb"])) {
    //a capture is not a save. The metadata fields ride along on this request —
    //they are in the same form — but nothing here writes data/<video>.json:
    //they are only read back below to redraw the form as the user left it

    $time = Thumbnailer::timestamp($_POST["time"] ?? "");

    if ($time === null) {
        $error = 'That is not a position in the video.';
    } else {
        try {
            thumbnailer()->capture(videoPath($vid), $vid, $time);

            $captured = true;
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }
    }

    //the page's own script captures with fetch(), so the browser never leaves
    //the page and there is no form state to lose in the first place. It wants
    //an answer, not a page: hand it one and stop here

    if (($_POST["ajax"] ?? "") === "1") {
        clearstatcache();

        header('Content-Type: application/json');

        echo json_encode(array(
            'ok' => $captured,
            'error' => $error,
            'thumb' => $captured ? thumbUrl($vid) : '',
        ), JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

        exit;
    }

    //without scripting the button is a plain submit and the answer is this page
    //redrawn from what was posted. It deliberately does not redirect the way a
    //save does: a redirect would drop the fields, which is the whole point. The
    //cost is that reloading the result re-posts, and captures the same frame a
    //second time
} elseif ($_SERVER["REQUEST_METHOD"] === "POST") {
    $date = (string) ($_POST["date"] ?? "");

    //"Now" overrides whatever the picker holds, and a first save falls back to
    //the video file's creation date (today if the filesystem has none), so
    //every sidecar written from here on carries a date

    if (isset($_POST["now"])) {
        $date = Meta::today();
    } elseif ($date === "" && !hasMeta($vid)) {
        $date = defaultVideoDate($vid);
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

//a video with no sidecar yet opens on the date its first save would take, so
//the picker shows what is about to be written instead of an empty box

$fields = editFormFields($_POST, $meta, hasMeta($vid) ? $meta->dateOnly() : defaultVideoDate($vid));

echo '
<video id="player" class="player" controls src="' . escapeHtml(videoUrl($vid)) . '"></video>
<h2>' . escapeHtml($vid) . '</h2>

<form id="edit-form" class="edit-form" action="' . escapeHtml($self) . '" method="POST">
<input type="hidden" name="form" value="edit">
<div class="fields">
<input class="field-rate" type="number" min="0" max="5" name="rate" value="' . escapeHtml($fields['rate']) . '">
<input class="tags-field" type="text" name="tags" placeholder="Tags (comma separated)" value="' . escapeHtml($fields['tags']) . '">
<input class="tags-field" type="text" name="authors" placeholder="Authors (comma separated)" value="' . escapeHtml($fields['authors']) . '">
<input class="field-date" type="date" name="date" title="Date the grid sorts by" value="' . escapeHtml($fields['date']) . '">
<label class="check" title="Save with today&#039;s date, whatever the picker holds"><input type="checkbox" name="now" value="1"'
    . ($fields['now'] ? ' checked' : '') . '> Now</label>
<input type="submit" value="Save">
</div>

<div class="thumb-panel">
';

//the capture button is only offered when ffmpeg is really there, so it cannot
//fail for a reason the page never mentioned

if (thumbnailer()->available()) {
    echo '<input type="hidden" name="time" value="0">
<input id="thumb-button" type="submit" name="thumb" value="Use current frame as thumbnail">
';
} else {
    echo '<p class="notice">No ffmpeg here, so frames cannot be captured. Set
VIDEO_PLATFORM_FFMPEG to the binary to turn this on.</p>
';
}

$notice = $error !== '' ? $error : ($captured ? 'Thumbnail captured.' : '');

echo '<p id="thumb-notice" class="notice' . ($error !== '' ? ' bad' : '') . '"'
    . ($notice === '' ? ' hidden' : '') . '>' . escapeHtml($notice) . '</p>
<div id="thumb-preview" class="thumb-preview">';

if (is_file(thumbnailer()->path($vid))) {
    echo '<img src="' . escapeHtml(thumbUrl($vid)) . '" alt="Current thumbnail">';
}

echo '</div>
</div>
</form>

<a href="' . escapeHtml($back) . '">&larr; Back to the grid</a>
';

if (thumbnailer()->available()) {
    echo '<script>
const player = document.getElementById("player");
const editForm = document.getElementById("edit-form");
const thumbButton = document.getElementById("thumb-button");
const thumbNotice = document.getElementById("thumb-notice");
const thumbPreview = document.getElementById("thumb-preview");

function sayThumb(message, bad) {
	thumbNotice.textContent = message;
	thumbNotice.className = bad ? "notice bad" : "notice";
	thumbNotice.hidden = false;
}

//capturing over fetch() leaves the page — and so every unsaved field — exactly
//as it is. With scripting off the same button plainly submits the form, which
//carries the fields along so the server can redraw them

thumbButton.addEventListener("click", function (event) {
	event.preventDefault();

	const body = new FormData();
	body.append("thumb", "1");
	body.append("time", player.currentTime || 0);
	body.append("ajax", "1");

	thumbButton.disabled = true;

	fetch(editForm.action, {method: "POST", body: body}).then(function (response) {
		return response.json();
	}).then(function (result) {
		sayThumb(result.ok ? "Thumbnail captured." : result.error, !result.ok);

		if (result.ok) {
			//same filename every time, so the preview is pointed at the fresh
			//stamp rather than left showing the frame the browser cached

			thumbPreview.innerHTML = "";

			const img = document.createElement("img");
			img.alt = "Current thumbnail";
			img.src = result.thumb;
			thumbPreview.appendChild(img);
		}
	}).catch(function () {
		sayThumb("The capture did not go through.", true);
	}).then(function () {
		thumbButton.disabled = false;
	});
});
</script>
';
}

renderFoot();
