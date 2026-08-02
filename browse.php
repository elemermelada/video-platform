<?php

require_once __DIR__ . '/lib.php';

$aths = array();
$aths2 = array();
$qa = array(); //number of vids per author
$tgs = array();
$tgs2 = array();
$qt = array(); //number of vids per tag

foreach (meta_store()->ids() as $vid) {
    $meta = load_meta($vid);

    //get rate

    $ratext = render_rating($meta->rate);

    //get tags

    foreach ($meta->tags as $tag) {
        if (!in_array($tag, $tgs)) {
            array_push($tgs, $tag);
            array_push($qt, 1);
        } else {
            $qt[array_search($tag, $tgs)] += 1;
        }
    }

    //get authors

    foreach ($meta->authors as $author) {
        if (!in_array($author, $aths)) {
            array_push($aths, $author);
            array_push($qa, 1);
        } else {
            $qa[array_search($author, $aths)] += 1;
        }
    }
}

$count = 0;
foreach ($aths as $a) {
    array_push($aths2, $a . ": " . $qa[$count]);
    $count += 1;
}

$count = 0;
foreach ($tgs as $t) {
    array_push($tgs2, $t . ": " . $qt[$count]);
    $count += 1;
}

sort($aths);
sort($aths2);
echo '<div style="vertical-align:top;display:inline-block;top:0;margin:15;">';
//echo '<pre>';
$count = 0;
foreach ($aths as $ath) {
    echo '<a href="search.php?author=' . $ath . '">' . $aths2[$count] . "</a><br>";
    $count += 1;
}
//echo '</pre>';
echo '</div>';

sort($tgs);
sort($tgs2);
echo '<div style="vertical-align:top;display:inline-block;top:0;margin:15;">';
//echo '<pre>';
$count = 0;
foreach ($tgs as $tg) {
    echo '<a href="search.php?tag=' . $tg . '">' . $tgs2[$count] . "</a><br>";
    $count += 1;
}
//echo '</pre>';
echo '</div>';
