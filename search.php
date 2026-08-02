<style>

a {
	
	text-decoration: none;
	
}

a:hover {
	
	color:ee1111;
	
}

</style>

<body bgcolor="000000" style="color:ffffff;zoom:300%;">

<?php

error_reporting(0);

$alle = glob("*.*");
$sauthor = $_GET['author'];
$stag = $_GET['tag'];
$srate = $_GET['rate'];
$match = array();


foreach ($alle as $vid) {

    $data = file_get_contents("data/" . $vid . ".data");
    $rrate = substr($data, 0, 1);
    $data = substr($data, 2);

    $rtags = array();
    $tags = substr($data, 0, strpos($data, ";")) . ":";

    while (strpos($tags, ":") != 0) {

        array_push($rtags, substr($tags, 0, strpos($tags, ":")));
        $tags = substr($tags, strpos($tags, ":") + 1);

    }

    $data = substr($data, strpos($data, ";") + 1);

    $authors = substr($data, 0, strpos($data, ";")) . ":";
    $rauthors = array();

    while (strpos($authors, ":") != 0) {

        Array_Push($rauthors, substr($authors, 0, strpos($authors, ":")));

        $authors = substr($authors, strpos($authors, ":") + 1);

    }

    $correct = true;

    if (!(in_array($sauthor, $rauthors) or $sauthor == "")) {

        $correct = false;

    }
    if (!(in_array($stag, $rtags) or $stag == "")) {

        $correct = false;

    }
    if (!($srate == $rrate or $srate == "")) {

        $correct = false;

    }

    if ($correct) {

        array_push($match, $vid);

    }

}

//normal index

//p = page
//o = order
//s = sizeoflist
//l = elements in line
//u = sense

if ($_GET['u'] == 'a') {

    $sen = 'a';

} else {

    $sen = 'd';

}

$cols = $_GET['l'];

if ($cols == 0) {

    $cols = 4;

}

$siz = $_GET['s'];

if ($siz == 0) {

    $siz = 20;

}

echo '
<form action="search.php" method="GET">
<input name="s" type="number" placeholder="Size of list" value="' . $siz . '">
<input name="l" type="number" placeholder="Size of line" value="' . $cols . '">
<p>';
if ($sen == 'a') {

    echo '
<select style="height:1.4em;width:8em;" name="u" value="' . $sen . '">
	<option value="a" selected>Ascending</option>
	<option value="d">Descending</option>
</select>';

} else {

    echo '
<select style="height:1.4em;width:8em;" name="u" value="' . $sen . '">
	<option value="a">Ascending</option>
	<option value="d" selected>Descending</option>
</select>';

}
echo ' <input style="height:1.4em;width:11.3em;" name="tag" placeholder="Tag" value="' . $_GET['tag'] . '">
<br>';

if ($_GET["o"] == 1) {

    echo '
<select style="height:1.4em;width:8em;" name="o" value="' . $_GET["o"] . '">
	<option value="0">Name</option>
	<option value="1" selected>Date</option>
</select>';

} else {

    echo '
<select style="height:1.4em;width:8em;" name="o" value="' . $_GET["o"] . '">
	<option value="0" selected>Name</option>
	<option value="1">Date</option>
</select>';

}

echo '
<input style="height:1.4em;width:8em;" name="author" placeholder="Author" value="' . $_GET['author'] . '">
<input style="height:1.4em;width:3em;" name="rate" placeholder="Rating" type="number" value="' . $_GET['rate'] . '">
<p>
<input type="submit">
</form>';


echo '
<div style="color:ffffff;position:absolute;right:10;top:10;">
Page: 
<form action="search.php" method="GET" style="display:inline-block;">
	<input type="hidden" name="p" value="' . ($_GET["p"] - 1) . '">
	<input type="hidden" name="s" value="' . $siz . '">
	<input type="hidden" name="l" value="' . $cols . '">
	<input type="hidden" name="o" value="' . $_GET["o"] . '">
	<input type="hidden" name="u" value="' . $_GET["u"] . '">
	<input type="hidden" name="author" value="' . $_GET["author"] . '">
	<input type="hidden" name="rate" value="' . $_GET["rate"] . '">
	<input type="hidden" name="tag" value="' . $_GET["tag"] . '">
	<input type="submit" value="-">
</form>
' . $_GET['p'] . '
<form action="search.php" method="GET" style="display:inline-block;">
	<input type="hidden" name="p" value="' . ($_GET["p"] + 1) . '">
	<input type="hidden" name="s" value="' . $siz . '">
	<input type="hidden" name="l" value="' . $cols . '">
	<input type="hidden" name="o" value="' . $_GET["o"] . '">
	<input type="hidden" name="u" value="' . $_GET["u"] . '">
	<input type="hidden" name="author" value="' . $_GET["author"] . '">
	<input type="hidden" name="rate" value="' . $_GET["rate"] . '">
	<input type="hidden" name="tag" value="' . $_GET["tag"] . '">
	<input type="submit" value="+">
</form>
</div>
<hr>';

$glb = $match;

if ($_GET['o'] == 1) {

    usort($glb, function ($a, $b) {
        return filemtime($a) < filemtime($b);
    });

}

if ($sen == 'd') {

    $glb = array_reverse($glb);

}

$count = 0;

echo '<center><table style="table-layout: fixed;width:100%;"><tr>';

for ($i = $siz * $_GET['p']; $i < $siz * $_GET['p'] + $siz; $i++) {

    $vid = $glb[$i];

    //get rate

    $data = file_get_contents("data/" . $vid . ".data");
    $rate = substr($data, 0, 1);
    $data = substr($data, 2);

    $ratext = "";

    for ($j = 0;$j < 5;$j++) {

        if ($rate != "") {

            if ($rate == 0) {

                $ratext .= '<img style="width:0.75em;" src="thumbs/rating-off.png">';

            } else {

                $ratext .= '<img style="width:0.75em;" src="thumbs/rating-on.png">';
                $rate -= 1;

            }

        }

    }

    //get tags

    $tags = substr($data, 0, strpos($data, ";")) . ":";
    $tegxt = "";

    while (strpos($tags, ":") != 0) {

        $tegxt .= '<a href="search.php?tag=' . substr($tags, 0, strpos($tags, ":")) . '">' . substr($tags, 0, strpos($tags, ":")) . "</a>, ";

        $tags = substr($tags, strpos($tags, ":") + 1);


    }

    $tegxt = substr($tegxt, 0, strlen($tegxt) - 2);

    $data = substr($data, strpos($data, ";") + 1);

    //get authors

    $authors = substr($data, 0, strpos($data, ";")) . ":";
    $autxt = "";

    while (strpos($authors, ":") != 0) {

        $autxt .= '<a href="search.php?author=' . substr($authors, 0, strpos($authors, ":")) . '">' . substr($authors, 0, strpos($authors, ":")) . "</a>, ";

        $authors = substr($authors, strpos($authors, ":") + 1);


    }

    $autxt = substr($autxt, 0, strlen($autxt) - 2);

    //echo all

    echo '
	
	<td style="position:relative;border-style:solid;border-color:FFFFFF;border-size:1px;background-color:BBBBBB;text-align:center;padding:2px;">
		<center>
			<span>
				<a style="right:5px;position:absolute;" href="edit.php?vid=' . urlencode($vid) . '">✎</a>
				<a href="' . $vid . '">
					<img onerror="this.onerror=null;this.src=' . "'" . 'thumbs/err.png' . "'" . '" src="thumbs/' . $vid . '.png" style="width:90%;"/>
					<div style="word-wrap:break-word;overflow:hidden;text-overflow: ellipsis;display: -webkit-box;-webkit-line-clamp: 2;-webkit-box-orient: vertical;">' . $vid . '</div>
				</a>
			</span>
			<hr style="margin-top:2;margin-bottom:1;border:none;border-top:solid 1px white;">' . $ratext . '
			<font size="1">
				<div style="background-color:CCCCCC;color:000000;text-align:left;padding:1;margin-top:2px;">
					<b>Tags:</b> ' . $tegxt . '
					<br><b>Authors:</b> ' . $autxt . '
				</div>
			</font>
		</center>
	</td>
	
	';

    $count += 1;

    if ($count == $cols) {

        echo '</tr><tr>';
        $count = 0;

    }

}

echo '</tr></table></center>';

echo '
<hr>
<form action="search.php" method="GET" style="display:inline-block;">
<input name="tag" type="hidden" value="' . $_GET['tag'] . '">
<input name="author" type="hidden" value="' . $_GET['author'] . '">
<input name="rate" type="hidden" value="' . $_GET['rate'] . '">
<input name="s" type="number" placeholder="Size of list" value="' . $siz . '">
<input name="l" type="number" placeholder="Size of line" value="' . $cols . '">
<br>';
if ($sen == 'a') {

    echo '
<select name="u" value="' . $sen . '">
	<option value="a" selected>Ascending</option>
	<option value="d">Descending</option>
</select>';

} else {

    echo '
<select name="u" value="' . $sen . '">
	<option value="a">Ascending</option>
	<option value="d" selected>Descending</option>
</select>';

}
echo '<br>';

if ($_GET["o"] == 1) {

    echo '
<select name="o" value="' . $_GET["o"] . '">
	<option value="0">Name</option>
	<option value="1" selected>Date</option>
</select>
<br>
<input type="submit">

</form>';

} else {

    echo '
<select name="o" value="' . $_GET["o"] . '">
	<option value="0" selected>Name</option>
	<option value="1">Date</option>
</select>
<br>
<input type="submit">

</form>';

}

echo '
<div style="color:ffffff;position:absolute;right:10;display:inline-block;">
Page: 
<form action="search.php" method="GET" style="display:inline-block;">
	<input type="hidden" name="p" value="' . ($_GET["p"] - 1) . '">
	<input type="hidden" name="s" value="' . $siz . '">
	<input type="hidden" name="l" value="' . $cols . '">
	<input type="hidden" name="o" value="' . $_GET["o"] . '">
	<input type="hidden" name="u" value="' . $_GET["u"] . '">
	<input type="hidden" name="author" value="' . $_GET["author"] . '">
	<input type="hidden" name="rate" value="' . $_GET["rate"] . '">
	<input type="hidden" name="tag" value="' . $_GET["tag"] . '">
	<input type="submit" value="-">
</form>
' . $_GET['p'] . '
<form action="search.php" method="GET" style="display:inline-block;">
	<input type="hidden" name="p" value="' . ($_GET["p"] + 1) . '">
	<input type="hidden" name="s" value="' . $siz . '">
	<input type="hidden" name="l" value="' . $cols . '">
	<input type="hidden" name="o" value="' . $_GET["o"] . '">
	<input type="hidden" name="u" value="' . $_GET["u"] . '">
	<input type="hidden" name="author" value="' . $_GET["author"] . '">
	<input type="hidden" name="rate" value="' . $_GET["rate"] . '">
	<input type="hidden" name="tag" value="' . $_GET["tag"] . '">
	<input type="submit" value="+">
</form>
</div>';

?>