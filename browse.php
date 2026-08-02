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
        echo '<a href="index.php?' . $param . '=' . urlencode((string) $name) . '">'
            . escapeHtml((string) $name) . ": " . $count . "</a><br>";
    }

    echo '</div>';
}

//videos with no metadata sidecar yet (used to live in check.php)

$missing = videosMissingMeta();

echo '<div style="vertical-align:top;display:inline-block;top:0;margin:15;">';
echo '<b>Missing metadata</b><br>';

if ($missing == array()) {
    echo "none<br>";
}

foreach ($missing as $vid) {
    echo '<a href="edit.php?vid=' . urlencode($vid) . '">' . escapeHtml($vid) . "</a><br>";
}

echo '</div>';
