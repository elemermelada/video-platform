<?php

require_once("lib.php");

navHeader("browse.php");

$counts = metaCounts();

$columns = array(
    "author" => $counts['authors'],
    "tag" => $counts['tags'],
);

foreach ($columns as $param => $names) {
    echo '<div style="vertical-align:top;display:inline-block;top:0;margin:15;">';

    foreach ($names as $name => $count) {
        echo '<a href="index.php?' . $param . '=' . urlencode($name) . '">'
            . $name . ": " . $count . "</a><br>";
    }

    echo '</div>';
}
