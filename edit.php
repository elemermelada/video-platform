<?php

require_once("lib.php");

use VideoPlatform\Meta;

//where we came from: the grid, with its page & filters

$back = grid_url($_GET["ret"]);

$vid = $_GET["vid"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    save_meta($vid, Meta::fromArray([
        "rate" => $_POST["rate"],
        "tags" => $_POST["tags"],
        "authors" => $_POST["authors"],
    ]));

    //back to the same spot in the grid

    header("Location: " . $back);
    exit;
}

nav_header("edit.php", $back);

$meta = load_meta($vid);

echo '
<center>
<video controls src="' . $vid . '" style="height:50%;"></video>
<p>
<form action="" method="POST">
<input style="font-size:2em;width:4em;" type="number" min="0" max="5" name="rate" value="' . $meta->rate . '" />
<input style="font-size:2em;width:45%;" type="text" name="tags" placeholder="Tags (comma separated)" value="' . implode(', ', $meta->tags) . '" />
<input style="font-size:2em;width:45%;" type="text" name="authors" placeholder="Authors (comma separated)" value="' . implode(', ', $meta->authors) . '" />
<input type="submit">
</form>
<p>
<a href="' . htmlspecialchars($back) . '">&larr; Back to the grid</a>

';
