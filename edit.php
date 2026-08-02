<?php

require_once __DIR__ . '/lib.php';

use VideoPlatform\Meta;

$vid = $_GET["vid"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    save_meta($vid, Meta::fromArray([
        "rate" => $_POST["rate"],
        "tags" => $_POST["tags"],
        "authors" => $_POST["authors"],
    ]));
}

$meta = load_meta($vid);

echo '
<a href="lindex.php"><h1>HOME</h1></a>
<center>
<video controls src="' . $vid . '" style="height:50%;"></video>
<p>
<form action="" method="POST">
<input style="font-size:2em;width:4em;" type="number" min="0" max="5" name="rate" value="' . $meta->rate . '" />
<input style="font-size:2em;width:45%;" type="text" name="tags" placeholder="Tags (comma separated)" value="' . implode(', ', $meta->tags) . '" />
<input style="font-size:2em;width:45%;" type="text" name="authors" placeholder="Authors (comma separated)" value="' . implode(', ', $meta->authors) . '" />
<input type="submit">
</form>

';
