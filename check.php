<?php

require_once("lib.php");

nav_header("check.php");

$vidz = glob("*.*");

foreach ($vidz as $vid) {
    if (!has_meta($vid)) {
        echo $vid . "<p>";
    }
}
