<?php

header("Content-type: text/css");

if (!isset($_GET['color'])) die();
if (empty($_GET['color'])) die();
$color = htmlspecialchars($_GET['color']);

?>

.w3-color,
.w3-hover-color:hover {
	color: #fff !important;
	background-color: #<?=$color;?> !important
}

.w3-text-color,
.w3-hover-text-color:hover {
	color: #<?=$color;?> !important
}

.w3-border-color,
.w3-hover-border-color:hover {
	border-color: #<?=$color;?> !important
}