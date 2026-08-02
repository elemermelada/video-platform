<?php

require_once("lib.php");

$params = gridParams();

$matches = array();

foreach (videoFiles() as $vid) {
    if (matchesFilters(loadMeta($vid), $params)) {
        array_push($matches, $vid);
    }
}

if ($params['order'] == 1) {
    usort($matches, function ($a, $b) {
        return filemtime(videoPath($b)) <=> filemtime(videoPath($a));
    });
}

if ($params['sense'] == 'd') {
    $matches = array_reverse($matches);
}

$page = array_slice($matches, $params['page'] * $params['size'], $params['size']);

//current page + filters, handed to edit.php so it can send us back here

$ret = gridQuery($params);

//the tag/author index, built once and offered as autocomplete in the filters

$counts = metaCounts();

renderHead("Videos");
renderBar("index.php", "index.php", $params, array(
    'tags' => array_map('strval', array_keys($counts['tags'])),
    'authors' => array_map('strval', array_keys($counts['authors'])),
));

//auto-fill tracks: a short page needs no padding cells, and a card can never
//stretch a row on its own

echo '<div class="grid" style="--cols:' . $params['cols'] . '">';

foreach ($page as $vid) {
    echo renderVideoCard($vid, hasMeta($vid) ? loadMeta($vid) : null, $ret);
}

echo '</div>';

if ($page === array()) {
    echo '<p>No videos match these filters.';
}

renderFoot();
