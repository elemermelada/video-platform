<?php

require_once("lib.php");

nav_header("check.php");

$vidz=glob("*.*");

foreach ($vidz as $vid) {
	
	if (!file_exists("data/" . $vid . ".data")) {
		echo $vid . "<p>";
	}
	
}

?>