<?php

//Coleta lista de concursos
require_once __DIR__.'/bin/data.php';

//Verifica se algum concurso existente foi definido. Caso contrario, retorna lista de concursos e encerra o script
if (isset($contests_array[@$_GET['contest']])) {
	$contest = $contests_array[$_GET['contest']];
	$contest['name_id'] = $_GET['contest'];
} else {
	readfile("top.html");
	foreach ($contests_array as $name_id => $contest) echo("<p><a href='index.php?contest=".$name_id."'>".$contest['name']."</a></p>\n");
	readfile("bottom.html");
	die();
}

//Define credenciais do banco de dados
$ts_pw = posix_getpwuid(posix_getuid());
$ts_mycnf = parse_ini_file($ts_pw['dir'] . "/replica.my.cnf");
$db_user = $ts_mycnf['user'];
$db_pass = $ts_mycnf['password'];
$db_host = 'tools.db.svc.eqiad.wmflabs';
$database = $ts_mycnf['user']."__wikiconcursos";

//Lista páginas disponíveis para uso
$accepted_pages = array(
	"login",
	"triage",
	"counter",
	"compare",
	"edits",
	"modify",
	"backtrack",
	"evaluators",
	"graph",
	"load_edits",
	"load_reverts",
	"load_users"
);

//Carrega página solicitada ou redireciona para página de login
if (isset($_GET['page'])) {
	if (in_array($_GET['page'], $accepted_pages)) {
		require_once __DIR__.'/bin/'.$_GET['page'].'.php';
	} else {
		require_once __DIR__.'/bin/login.php';
	}
} else {
	require_once __DIR__.'/bin/login.php';
}