<?php

//Coleta lista de concursos
require __DIR__.'/bin/data.php';

//Verifica se algum concurso existente foi definido. Caso contrario, retorna lista de concursos e encerra o script
if (isset($contests_array[@$_GET['contest']])) {
	$contest = $contests_array[$_GET['contest']];
} else {
	readfile("top.html");
	foreach ($contests_array as $contest) echo("<p><a href='index.php?contest=".$contest['name_id']."'>".$contest['name']."</a></p>\n");
	readfile("bottom.html");
	die();
}

//Define credenciais do banco de dados
$ts_pw = posix_getpwuid(posix_getuid());
$ts_mycnf = parse_ini_file($ts_pw['dir'] . "/replica.my.cnf");
$db_user = $ts_mycnf['user'];
$db_pass = $ts_mycnf['password'];
$db_host = 'tools.db.svc.eqiad.wmflabs';
$database = $ts_mycnf['user']."__".$contest['name_id'];

//Carrega página solicitada
if (!isset($_GET['page'])) {
	require __DIR__.'/bin/login.php';
} elseif ($_GET['page'] == 'triage') {
	require __DIR__.'/bin/triage.php';
} elseif ($_GET['page'] == 'counter') {
	require __DIR__.'/bin/counter.php';
} elseif ($_GET['page'] == 'compare') {
	require __DIR__.'/bin/compare.php';
} elseif ($_GET['page'] == 'load_edits') {
	require __DIR__.'/bin/load_edits.php';
} elseif ($_GET['page'] == 'load_reverts') {
	require __DIR__.'/bin/load_reverts.php';
} elseif ($_GET['page'] == 'load_users') {
	require __DIR__.'/bin/load_users.php';
} else {
	require __DIR__.'/bin/login.php';
}
