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

    //Encerra script para evitar carregamento da página inicial
    exit();
}

//Verifica se algum concurso foi definido
if (isset($_GET['contest'])) {

    //Verifica se o concurso existe
    if (isset($contests_array[@$_GET['contest']])) {

        //Insere dados do concurso em uma array
        $contest = $contests_array[$_GET['contest']];

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
            "password",
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

        //Encerra script para evitar carregamento da página inicial
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>WIKICONCURSOS</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="bin/w3.css">
    <style>
        @font-face {
            font-family:'LinLibertine';
            src: url("/font/LinLibertine_Re-4.7.3.otf");
    }
    </style>
</head>
<body>

<!-- Header -->
<header class="w3-container w3-deep-green w3-center" style="padding:128px 16px">
    <h1
    class="w3-margin w3-jumbo"
    style="font-family: 'LinLibertine', sans-serif;"
    >&#xE02F;IKICONCURSOS</h1>
    <p class="w3-xlarge">Contabilizador de pontos</p>
    <button
    class="w3-button w3-black w3-padding-large w3-large w3-margin-top"
    onclick="document.getElementById('id01').style.display='block'"
    >Entrar em um concurso</button>
    <br>
    <button
    class="w3-button w3-black w3-padding-large w3-large w3-margin-top"
    onclick="location.href='index.php?manage=true'"
    >Gerenciar concursos</button>
</header>

<!-- Modal -->
<div id="id01" class="w3-modal">
    <div class="w3-modal-content w3-card-4 w3-animate-top">
        <header class="w3-container w3-deep-green">
            <span onclick="document.getElementById('id01').style.display='none'"
            class="w3-button w3-display-topright">&times;</span>
            <h4>Selecione seu concurso</h4>
        </header>
        <div class="w3-padding">
        <?php
            foreach ($contests_array as $name_id => $contest) {
                echo "<p><a href='index.php?contest=".$name_id."'>".$contest['name']."</a></p>\n";
            }
        ?>
        </div>
    </div>
</div>

<!-- First Grid -->
<div class="w3-row-padding w3-padding-64 w3-container">
    <div class="w3-content">
        <div class="w3-twothird">
            <h1>O que é?</h1>
            <h5 class="w3-padding-32">
                WIKICONCURSOS é uma ferramenta criada para validar edições e contabilizar a pontuação de
                participantes dos wikiconcursos na Wikipédia lusófona.
            </h5>
            <p class="w3-text-grey">
                A ferramenta possui uma interface simples em língua portuguesa, muito embora possa ser traduzida para
                outros idiomas. Por meio dela, é possível fazer a validação ágil das edições nos artigos relacionados
                a um wikiconcurso qualquer. Diferentes avaliadores podem ter perfis diferentes, com registros de
                validação individualizados.
            </p>
        </div>
        <div class="w3-third w3-center">
            <img
            alt="Logo da Wikipédia"
            class="w3-padding-64"
            src="https://upload.wikimedia.org/wikipedia/commons/thumb/d/d3/Wikipedia_article_icon_BLACK.svg/226px-Wikipedia_article_icon_BLACK.svg.png"
            >
        </div>
    </div>
</div>

<!-- Second Grid -->
<div class="w3-row-padding w3-light-grey w3-padding-64 w3-container">
    <div class="w3-content">
        <div class="w3-third w3-center">
            <img
            alt="Logo de Editathons"
            class="w3-padding-64"
            src="https://upload.wikimedia.org/wikipedia/commons/thumb/e/ef/Editathons.svg/200px-Editathons.svg.png">
        </div>
        <div class="w3-twothird">
            <h1>Cadastrar novo wikiconcurso</h1>
            <h5 class="w3-padding-32">
                Para solicitar o cadastramento de um novo wikiconcurso, o contato inicial se dará via
                e-mail de contato do Wiki Movimento Brasil.
            </h5>
            <p class="w3-text-grey">
                Envie um e-mail para wikiconcurso@wmnobrasil.org e forneça as informações básicas sobre seu
                wikiconcurso, tais como previsão de datas de início e término, escopo, lista de artigos e sistema
                de pontuação. Solicitaremos informações adicionais posteriormente para os ajustes finais da ferramenta.
            </p>
        </div>
    </div>
</div>
<div class="w3-container w3-black w3-center w3-opacity w3-padding-64">
        <img
        alt="Logo do WMB"
        src="https://upload.wikimedia.org/wikipedia/commons/thumb/8/82/Wiki_Movimento_Brasil_-_logo_negativo.svg/125px-Wiki_Movimento_Brasil_-_logo_negativo.svg.png"
        >
</div>

<!-- Footer -->
<footer class="w3-container w3-padding-64 w3-center w3-opacity">
    <p>
        Powered by <a
        rel="noopener"
        href="https://www.w3schools.com/w3css/default.asp"
        target="_blank"
        >w3.css</a> and <a
        rel="noopener"
        href="https://wikitech.wikimedia.org/wiki/Portal:Toolforge"
        target="_blank">Toolforge</a>.<br>Source-code on <a
        rel="noopener"
        href="https://github.com/WikiMovimentoBrasil/wikiconcursos"
        >GitHub</a> under <a
        rel="noopener"
        href="https://github.com/WikiMovimentoBrasil/wikiconcursos/blob/main/LICENSE">GPL v3.0</a>.<br>Text license: <a
        rel="noopener"
        href="https://creativecommons.org/licenses/by-sa/4.0/deed"
        >CC-BY-SA 4.0 International</a>.
    </p>
</footer>

</body>
</html>
