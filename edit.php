<?php

require_once("lib.php");

//where we came from: the grid, with its page & filters

$back = grid_url($_GET["ret"]);

if ($_POST["data"]!="") {

	file_put_contents("data/" . $_GET["vid"] . ".data", $_POST["data"]);

	//back to the same spot in the grid

	header("Location: " . $back);
	exit;

}

	nav_header("edit.php", $back);

	echo '
<center>
<video controls src="' . $_GET["vid"] . '" style="height:50%;"></video>
<p>
<form action="" method="POST">
<input style="font-size:2em;width:50%;" type="text" name="data" value="' . file_get_contents("data/" . $_GET["vid"] . ".data") . '" />
<input type="submit">
</form>
<p>
<a href="' . htmlspecialchars($back) . '">&larr; Back to the grid</a>

';

?>
