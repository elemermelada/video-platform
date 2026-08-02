<?php

$aths = array();
$aths2 = array();
$qa = array(); //number of vids per author
$tgs = array();
$tgs2 = array();
$qt = array(); //number of vids per tag

foreach (glob("data/*.data") as $vid) {
    $data = file_get_contents($vid);
    //get rate

    $rate = substr($data, 0, 1);
    $data = substr($data, 2);

    $ratext = "";

    for ($j = 0;$j < 5;$j++) {
        if ($rate == 0) {
            $ratext .= '<img style="width:0.75em;" src="thumbs/rating-off.png">';
        } else {
            $ratext .= '<img style="width:0.75em;" src="thumbs/rating-on.png">';
            $rate -= 1;
        }
    }

    //get tags

    $tags = substr($data, 0, strpos($data, ";")) . ":";

    while (strpos($tags, ":") != 0) {
        if (!in_array(substr($tags, 0, strpos($tags, ":")), $tgs)) {
            array_push($tgs, substr($tags, 0, strpos($tags, ":")));
            array_push($qt, 1);
        } else {
            $qt[array_search(substr($tags, 0, strpos($tags, ":")), $tgs)] += 1;
        }

        $tags = substr($tags, strpos($tags, ":") + 1);
    }

    $data = substr($data, strpos($data, ";") + 1);

    //get authors

    $authors = substr($data, 0, strpos($data, ";")) . ":";

    while (strpos($authors, ":") != 0) {
        if (!in_array(substr($authors, 0, strpos($authors, ":")), $aths)) {
            array_push($aths, substr($authors, 0, strpos($authors, ":")));
            array_push($qa, 1);
        } else {
            $qa[array_search(substr($authors, 0, strpos($authors, ":")), $aths)] += 1;
        }

        $authors = substr($authors, strpos($authors, ":") + 1);
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
