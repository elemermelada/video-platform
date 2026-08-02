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

navHeader("index.php");

$params = gridParams();

$matches = array();

foreach (videoFiles() as $vid) {
    $meta = loadMeta($vid);

    $keep = true;

    if (!(in_array($params['author'], $meta->authors) or $params['author'] == "")) {
        $keep = false;
    }
    if (!(in_array($params['tag'], $meta->tags) or $params['tag'] == "")) {
        $keep = false;
    }
    if (!($params['rate'] == $meta->rate or $params['rate'] == "")) {
        $keep = false;
    }

    if ($keep) {
        array_push($matches, $vid);
    }
}

//normal index

echo renderFilterForm($params);
echo renderPager($params, 'right:10;top:10;');
echo '<hr>';

if ($params['order'] == 1) {
    usort($matches, function ($a, $b) {
        return filemtime($b) <=> filemtime($a);
    });
}

if ($params['sense'] == 'd') {
    $matches = array_reverse($matches);
}

$page = array_slice($matches, $params['page'] * $params['size'], $params['size']);

//current page + filters, handed to edit.php so it can send us back here

$ret = urlencode(gridQuery($params));

echo '<center><table style="table-layout: fixed;width:100%;"><tr>';

$column = 0;

for ($i = 0; $i < $params['size']; $i++) {
    $vid = $page[$i] ?? "";
    $meta = loadMeta($vid);

    $rating = hasMeta($vid) ? renderRating($meta->rate) : "";
    $tags = renderLinkList($meta->tags, "tag");
    $authors = renderLinkList($meta->authors, "author");

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
			<hr style="margin-top:2;margin-bottom:1;border:none;border-top:solid 1px white;">' . $rating . '
			<font size="1">
				<div style="background-color:CCCCCC;color:000000;text-align:left;padding:1;margin-top:2px;">
					<b>Tags:</b> ' . $tags . '
					<br><b>Authors:</b> ' . $authors . '
				</div>
			</font>
		</center>
	</td>

	';

    $column += 1;

    if ($column == $params['cols']) {
        echo '</tr><tr>';
        $column = 0;
    }
}

echo '</tr></table></center>';

echo '<hr>';
echo renderFilterForm($params);
echo renderPager($params, 'right:10;display:inline-block;');
