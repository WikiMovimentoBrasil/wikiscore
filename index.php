<?php

//Conecta ao banco de dados
require_once __DIR__.'/bin/connect.php';

//Carrega traduções
require_once __DIR__.'/bin/languages.php';

//Coleta lista de concursos
$contests_statement = '
    SELECT
        `name_id`,
        UNIX_TIMESTAMP(`start_time`) AS `start_time`,
        UNIX_TIMESTAMP(`end_time`) AS `end_time`,
        `name`,
        `group`,
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
        `color`,
        UNIX_TIMESTAMP(`started_update`) AS `started_update`,
        UNIX_TIMESTAMP(`finished_update`) AS `finished_update`,
        UNIX_TIMESTAMP(`next_update`) AS `next_update`
    FROM
        `manage__contests`
    ORDER BY
        `start_time` DESC
';
$contests_query = mysqli_prepare($con, $contests_statement);
mysqli_stmt_execute($contests_query);
$contests_result = mysqli_stmt_get_result($contests_query);
$contests_array = [];
while ($row = mysqli_fetch_assoc($contests_result)) {
    $contests_array[$row['name_id']] = $row;
}
foreach ($contests_array as $contest) {
    $contests_chooser[$contest['group']][] = [ $contest["name_id"], $contest["name"] ];
}
$contests_groups = array_keys($contests_chooser);

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
            "recover",
            "password",
            "maintenance",
            "load_edits",
            "load_reverts",
            "load_users"
        );

        //Carrega página solicitada ou redireciona para página de login
        if (isset($_GET['page'])) {
            if (in_array($_GET['page'], $accepted_pages)) {
                $getPage = $_GET['page'];
                require_once __DIR__.'/bin/'.$getPage.'.php';
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

//Exibe revisão git atual no rodapé da página
$gitCommit  = "Commit: ";
$gitCommit .= shell_exec("git log -1 --pretty=format:'%h - %s (%ci)' --abbrev-commit");
$gitBranch  = "Branch: ";
$gitBranch .= shell_exec("git rev-parse --abbrev-ref HEAD");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title><?=§('main-title')?></title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="bin/w3.css">
    <link href='https://tools-static.wmflabs.org/fontcdn/css?family=Roboto' rel='stylesheet' type='text/css'>
</head>
<body style="font-family: 'Roboto', sans-serif;">

<!-- Header -->
<header class="w3-container w3-content w3-padding-32">
    <div style="display: flex; justify-content: flex-end;">
        <form method="get">
            <select name="lang" onchange="this.form.submit()" style="max-width: 16em; text-transform: uppercase;"
            class="w3-select w3-border w3-border-black w3-padding-small w3-round-xxlarge w3-small">
                <option value="" disabled selected><?=§('language-select')?></option>
                <?php foreach ($acceptedLanguages as $optionLanguage): ?>
                    <?php if ($optionLanguage == 'qqx') continue; ?>
                    <option value='<?=$optionLanguage?>'><?=Locale::getDisplayName($optionLanguage, $optionLanguage)?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    <img class="w3-section" alt="logo" src="images/Logo_Preto_Tagline.svg" style="width: 400px; max-width: 100%;">
    <br>
    <button
    class="w3-button w3-black w3-padding w3-margin w3-round-xxlarge"
    style="text-transform: uppercase;"
    onclick="document.getElementById('id01').style.display='block'"
    ><?=§('contest-enter')?></button>
    <button
    class="w3-button w3-black w3-padding w3-margin w3-round-xxlarge"
    style="text-transform: uppercase;"
    onclick="location.href='index.php?lang=<?=$lang?>&manage=true'"
    ><?=§('contest-manage')?></button>
</header>
<div class="w3-center" style="background-color: #8493a6;">
    <img src="images/Desenho_01.png" alt="drawing" style="width: 100%;max-width: 980px;">
</div>

<!-- Join -->
<div id="id01" class="w3-modal">
    <div class="w3-modal-content w3-card-4 w3-animate-top">
        <header class="w3-container w3-black">
            <span onclick="document.getElementById('id01').style.display='none'"
            class="w3-button w3-display-topright">&times;</span>
            <h4><?=§('contest-select')?></h4>
        </header>
        <div class="w3-padding">
            <div class="w3-bar w3-black">
                <?php foreach ($contests_groups as $group): ?>
                    <button 
                    class="w3-bar-item w3-button tablink <?=($group=='WMB')?'w3-red':''?>" 
                    onclick="openGroup(event,'<?=$group?>')"><?=$group?></button>
                <?php endforeach; ?>
            </div>
            <?php foreach ($contests_chooser as $group => $contest): ?>
                <div id="<?=$group?>" class="w3-container w3-border group" <?=($group=='WMB')?'':'style="display: none;"'?>>
                    <?php foreach ($contest as $contest_data): ?>
                        <p>
                            <a href='index.php?lang=<?=$lang?>&contest=<?=$contest_data["0"]?>'><?=$contest_data["1"]?></a>
                        </p>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<script>
function openGroup(evt, groupName) {
    var i, x, tablinks;
    x = document.getElementsByClassName("group");
    for (i = 0; i < x.length; i++) {
        x[i].style.display = "none";
    }
    tablinks = document.getElementsByClassName("tablink");
    for (i = 0; i < x.length; i++) {
        tablinks[i].className = tablinks[i].className.replace(" w3-red", "");
    }
    document.getElementById(groupName).style.display = "block";
    evt.currentTarget.className += " w3-red";
}
</script>

<!-- First Grid -->
<div class="w3-padding w3-padding-24 w3-container w3-border-top w3-border-black">
    <div class="w3-content">
        <div class="w3-half w3-jumbo"><?=§('index-about-short')?></div>
        <div class="w3-half w3-padding">
            <p><?=§('index-about-intro')?></p>
            <p style="color: #8493a6;"><?=§('index-about-main')?></p>
        </div>
    </div>
</div>

<!-- Second Grid -->
<div class="w3-padding w3-padding-24 w3-container w3-border-top w3-border-black">
    <div class="w3-content">
        <div class="w3-half w3-xlarge w3-margin-top">
            <?=§('index-enroll-short')?>
            <br>
            <img src="images/folder.svg" alt="folder" style="width: 30px;">
        </div>
        <div class="w3-half w3-padding">
            <p><?=§('index-enroll-intro')?></p>
            <p style="color: #8493a6;"><?=§('index-enroll-main')?></p>
        </div>
    </div>
</div>

<!-- Footer -->
<footer class="w3-container w3-padding w3-black">
    <div class="w3-row w3-content w3-section">
        <div class="w3-third">
            <a href="https://meta.wikimedia.org/wiki/Wiki_Movement_Brazil_User_Group">
                <img alt="Logo do WMB" class="w3-section" style="width: 80px;"
                src="https://upload.wikimedia.org/wikipedia/commons/thumb/8/82/Wiki_Movimento_Brasil_-_logo_negativo.svg/125px-Wiki_Movimento_Brasil_-_logo_negativo.svg.png">
            </a>
        </div>
        <div class="w3-third">
            <p class="w3-tiny">
                Powered by <a
                rel="noopener"
                href="https://www.w3schools.com/w3css/default.asp"
                target="_blank"
                >w3.css</a>, <a
                rel="noopener"
                href="https://translatewiki.net/wiki/Translating:WikiScore"
                target="_blank"
                >TranslateWiki</a> and <a
                rel="noopener"
                href="https://wikitech.wikimedia.org/wiki/Portal:Toolforge"
                target="_blank">Toolforge</a>.<br>Source-code on <a
                rel="noopener"
                href="https://github.com/WikiMovimentoBrasil/wikiscore"
                >GitHub</a> under <a
                rel="noopener"
                href="https://github.com/WikiMovimentoBrasil/wikiscore/blob/main/LICENSE">GPL v3.0</a>.<br>Text license: <a
                rel="noopener"
                href="https://creativecommons.org/licenses/by-sa/4.0/deed"
                >CC-BY-SA 4.0 International</a>.
            </p>
        </div>
        <div class="w3-third">
            <p class="w3-tiny">
                <?=htmlspecialchars($gitCommit)?>
                <br>
                <?=htmlspecialchars($gitBranch)?>
            </p>
        </div>
    </div>
</footer>

</body>
</html>
