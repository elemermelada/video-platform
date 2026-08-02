<?php

$vidz = glob("*.*");

foreach ($vidz as $vid) {

    if (!file_exists("data/" . $vid . ".data")) {
        echo $vid . "<p>";
    }

}
