<?php

if ($_POST["data"]!="") {
	
	file_put_contents("data/" . $_GET["vid"] . ".data", $_POST["data"]);
	echo $_POST["data"];
	
}

	echo '
<a href="lindex.php"><h1>HOME</h1></a>
<center>
<video controls src="' . $_GET["vid"] . '" style="height:50%;"></video>
<p>
<form action="" method="POST">
<input style="font-size:2em;width:50%;" type="text" name="data" value="' . file_get_contents("data/" . $_GET["vid"] . ".data") . '" />
<input type="submit">
</form>

';

?>