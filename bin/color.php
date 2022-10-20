<?php

header("Content-type: text/css");

if (!isset($_GET['color'])) die();
if (empty($_GET['color'])) die();

?>

.w3-color,
.w3-hover-color:hover {
	color: #fff !important;
	background-color: #<?=$_GET['color'];?> !important
}

.w3-text-color,
.w3-hover-text-color:hover {
	color: #<?=$_GET['color'];?> !important
}

.w3-border-color,
.w3-hover-border-color:hover {
	border-color: #<?=$_GET['color'];?> !important
}