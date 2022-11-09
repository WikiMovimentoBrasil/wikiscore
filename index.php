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

//Coleta lista de concursos
$contests_statement = '
    SELECT
        `name_id`,
        UNIX_TIMESTAMP(`start_time`) AS `start_time`,
        UNIX_TIMESTAMP(`end_time`) AS `end_time`,
        `name`,
        `revert_time`,
        `official_list_pageid`,
        `category_pageid`,
        `category_petscan`,
        `endpoint`,
        `api_endpoint`,
        `outreach_name`,
        `bytes_per_points`,
        `max_bytes_per_article`,
        `minimum_bytes`,
        `pictures_per_points`,
        `pictures_mode`,
        `max_pic_per_article`,
        `theme`,
        `color`
    FROM
        `contests`
    ORDER BY
        `start_time` DESC
';
$contests_query = mysqli_prepare($con, $contests_statement);
mysqli_stmt_execute($contests_query);
$contests_result = mysqli_stmt_get_result($contests_query);
while ($row = mysqli_fetch_assoc($contests_result)) {
    $contests_array[$row['name_id']] = $row;
}

//Verifica se página de gerenciamento foi chamada
if (isset($_GET['manage'])) {
    $contest['name_id'] = 'manage';
    if (isset($_GET['page']) && $_GET['page'] == 'contests') {
        require_once __DIR__.'/bin/manage_contests.php';
    } else {
        require_once __DIR__.'/bin/manage_login.php';
    }
    exit();
}

//Verifica se algum concurso existente foi definido. Caso contrario, retorna lista de concursos e encerra o script
if (isset($contests_array[@$_GET['contest']])) {
    $contest = $contests_array[$_GET['contest']];
} else {
    readfile("top.html");
    foreach ($contests_array as $name_id => $contest) {
        echo "<p><a href='index.php?contest=".$name_id."'>".$contest['name']."</a></p>\n";
    }
    readfile("bottom.html");
    exit();
}

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
