<?php

//Coleta dados do concurso
require "data.php";

//Conecta ao banco de dados
$con = mysqli_connect($db_host, $db_user, $db_pass, $database);
if (mysqli_connect_errno()) {
	echo "Failed to connect to MySQL: " . mysqli_connect_error();
	exit();
}

?> 