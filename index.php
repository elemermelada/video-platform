<?php

require_once("lib.php");

$params = gridParams();

$matches = array();
$metas = array();

foreach (videoFiles() as $vid) {
    //the name filter first: it costs nothing, and it can rule a video out
    //before its sidecar is read

    if (!matchesName($vid, $params)) {
        continue;
    }

    $meta = loadMeta($vid);

    if (matchesFilters($meta, $params)) {
        array_push($matches, $vid);
        $metas[$vid] = $meta;
    }
}

$dates = array();

if ($params['order'] == 1) {
    //the date each video carries in its metadata, with its mtime as the
    //fallback: worked out once per video, not once per comparison

    foreach ($matches as $vid) {
        $dates[$vid] = videoDate($vid, $metas[$vid]);
    }
}

$matches = sortVideos($matches, $params, $dates);

$page = array_slice($matches, $params['page'] * $params['size'], $params['size']);

//current page + filters, handed to edit.php so it can send us back here

$ret = gridQuery($params);

//the tag/author index, built once: offered as autocomplete in the filters and
//listed with its counts in the panel under the bar

$counts = metaCounts();

renderHead("Videos");
renderBar("index.php", "index.php", $params, array(
    'tags' => array_map('strval', array_keys($counts['tags'])),
    'authors' => array_map('strval', array_keys($counts['authors'])),
));

echo renderIndexPanel($counts, videosMissingMeta());

//auto-fill tracks: a short page needs no padding cells, and a card can never
//stretch a row on its own

echo '<div class="grid" style="--cols:' . $params['cols'] . '">';

foreach ($page as $vid) {
    echo renderVideoCard($vid, hasMeta($vid) ? $metas[$vid] : null, $ret);
}

echo '</div>';

if ($page === array()) {
    echo '<p>No videos match these filters.';
}

renderFoot();
