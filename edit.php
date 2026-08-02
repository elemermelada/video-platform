<?php

require_once("lib.php");

use VideoPlatform\Meta;

//where we came from: the grid, with its page & filters

$back = gridUrl((string) ($_GET["ret"] ?? ""));

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

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    saveMeta($vid, Meta::fromArray([
        "rate" => $_POST["rate"] ?? "",
        "tags" => $_POST["tags"] ?? "",
        "authors" => $_POST["authors"] ?? "",
    ]));

    //back to the same spot in the grid

    header("Location: " . $back);
    exit;
}

renderHead($vid);
renderBar("edit.php", $back);

$meta = loadMeta($vid);

echo '
<video class="player" controls src="' . escapeHtml(videoUrl($vid)) . '"></video>
<h2>' . escapeHtml($vid) . '</h2>
<form class="edit-form" action="" method="POST">
<input class="field-rate" type="number" min="0" max="5" name="rate" value="' . $meta->rate . '">
<input class="tags-field" type="text" name="tags" placeholder="Tags (comma separated)" value="' . escapeHtml(implode(', ', $meta->tags)) . '">
<input class="tags-field" type="text" name="authors" placeholder="Authors (comma separated)" value="' . escapeHtml(implode(', ', $meta->authors)) . '">
<input type="submit" value="Save">
</form>
<a href="' . escapeHtml($back) . '">&larr; Back to the grid</a>
';

renderFoot();
