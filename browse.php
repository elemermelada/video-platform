<?php

require_once("lib.php");

renderHead("Filters");
renderBar("browse.php");

$counts = metaCounts();

$columns = array(
    "author" => array("Authors", $counts['authors']),
    "tag" => array("Tags", $counts['tags']),
);

echo '<div class="columns">';

foreach ($columns as $param => $column) {
    echo '<section><h2>' . $column[0] . '</h2>';

    foreach ($column[1] as $name => $count) {
        echo '<a href="index.php?' . $param . '=' . urlencode((string) $name) . '">'
            . escapeHtml((string) $name) . '</a> <span class="count">' . $count . '</span><br>';
    }

    echo '</section>';
}

//videos with no metadata sidecar yet (used to live in check.php)

$missing = videosMissingMeta();

echo '<section><h2>Missing metadata</h2>';

if ($missing == array()) {
    echo '<span class="count">none</span><br>';
}

foreach ($missing as $vid) {
    echo '<a href="edit.php?vid=' . urlencode($vid) . '">' . escapeHtml($vid) . "</a><br>";
}

echo '</section></div>';

renderFoot();
