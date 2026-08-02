<?php

require_once("lib.php");

navHeader("check.php");

$vidz = glob("*.*");

foreach ($vidz as $vid) {
    if (!hasMeta($vid)) {
        echo $vid . "<p>";
    }
}
