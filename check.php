<?php

require_once __DIR__ . '/lib.php';

$vidz = glob("*.*");

foreach ($vidz as $vid) {
    if (!has_meta($vid)) {
        echo $vid . "<p>";
    }
}
