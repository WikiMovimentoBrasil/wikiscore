<?php

//Define credenciais do banco de dados
$ts_pw = posix_getpwuid(posix_getuid());
$ts_mycnf = parse_ini_file($ts_pw['dir'] . "/replica.my.cnf");
$db_user = $ts_mycnf['user'];
$db_pass = $ts_mycnf['password'];
$db_host = 'tools.db.svc.eqiad.wmflabs';
$database = $ts_mycnf['user']."__wikiconcursos";

//Conecta ao banco de dados
$con = mysqli_connect($db_host, $db_user, $db_pass, $database);
if (mysqli_connect_errno()) {
    echo "Failed to connect to MySQL: " . mysqli_connect_error();
    exit();
}
