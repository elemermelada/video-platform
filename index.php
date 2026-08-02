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

require_once("lib.php");

nav_header("index.php");

$alle = glob("*.*");
$sauthor = $_GET['author'];
$stag = $_GET['tag'];
$srate = $_GET['rate'];
$match = array();

foreach ($alle as $vid) {
    $meta = load_meta($vid);

    $correct = true;

    if (!(in_array($sauthor, $meta->authors) or $sauthor == "")) {
        $correct = false;
    }
    if (!(in_array($stag, $meta->tags) or $stag == "")) {
        $correct = false;
    }
    if (!($srate == $meta->rate or $srate == "")) {
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
<form action="index.php" method="GET">
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
<form action="index.php" method="GET" style="display:inline-block;">
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
<form action="index.php" method="GET" style="display:inline-block;">
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

//current page + filters, handed to edit.php so it can send us back here

$ret = urlencode(grid_query());

echo '<center><table style="table-layout: fixed;width:100%;"><tr>';

for ($i = $siz * $_GET['p']; $i < $siz * $_GET['p'] + $siz; $i++) {
    $vid = $glb[$i] ?? "";
    $meta = load_meta($vid);

    //get rate

    $ratext = has_meta($vid) ? render_rating($meta->rate) : "";

    //get tags

    $tegxt = "";

    foreach ($meta->tags as $tag) {
        $tegxt .= '<a href="index.php?tag=' . urlencode($tag) . '">' . $tag . "</a>, ";
    }

    $tegxt = substr($tegxt, 0, strlen($tegxt) - 2);

    //get authors

    $autxt = "";

    foreach ($meta->authors as $author) {
        $autxt .= '<a href="index.php?author=' . urlencode($author) . '">' . $author . "</a>, ";
    }

    $autxt = substr($autxt, 0, strlen($autxt) - 2);

    //echo all

    echo '
	
	<td style="position:relative;border-style:solid;border-color:FFFFFF;border-size:1px;background-color:BBBBBB;text-align:center;padding:2px;">
		<center>
			<span>
				<a style="right:5px;position:absolute;" href="edit.php?vid=' . urlencode($vid) . '&amp;ret=' . $ret . '">✎</a>
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
<form action="index.php" method="GET" style="display:inline-block;">
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
<form action="index.php" method="GET" style="display:inline-block;">
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
<form action="index.php" method="GET" style="display:inline-block;">
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