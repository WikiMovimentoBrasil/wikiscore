<?php

//Conecta ao banco de dados
require_once __DIR__.'/bin/connect.php';

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
while ($row = mysqli_fetch_assoc($contests_result)) {
    $contests_array[$row['name_id']] = $row;
}

//Carrega traduções
require_once './bin/languages.php';
$acceptedLanguages = str_replace('.json', '', array_diff(scandir('translations'), array('..', '.')));
$userLang = filter_var($_GET["lang"] ?? "", FILTER_SANITIZE_STRING);
$browserLang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2) ?? '';
if (in_array($userLang, $acceptedLanguages)) {
    $lang = $userLang;
} elseif (in_array($browserLang, $acceptedLanguages)) {
    $lang = $browserLang;
} else {
    $lang = 'en';
}

$translationFile = './translations/' . $lang . '.json';
$trans = file_exists($translationFile) ? json_decode(file_get_contents($translationFile), true) : [];
$orig = json_decode(file_get_contents('./translations/en.json'), true);

//Função para exibição de traduções
function §($item, ...$args) {
    global $trans;
    global $orig;

    $translatedString = $trans[$item] ?? $orig[$item] ?? "??";
    
    // Replace placeholders ($1, $2, $3, etc.) with corresponding arguments
    for ($i = 1; $i <= count($args); $i++) {
        $placeholder = '$' . $i;
        $translatedString = str_replace($placeholder, $args[$i - 1], $translatedString);
    }

    return $translatedString;
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
            "maintenance",
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
    <title><?=§('main-title')?></title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="bin/w3.css">
    <style>
        @font-face {
            font-family:'LinLibertine';
            src: url("/font/LinLibertine_Re-4.7.3.otf");
        }
        #main-title {
            visibility: hidden;
        }
    </style>
</head>
<body>

<!-- Header -->
<header class="w3-container w3-deep-green w3-center" style="padding:16px 16px 128px;">
    <div style="display: flex; justify-content: flex-end;">
        <form method="get">
            <select name="lang" onchange="this.form.submit()" class="w3-select" style="max-width: 16em;">
                <option value="" disabled selected><?=§('language-select')?></option>
                <?php foreach ($acceptedLanguages as $optionLanguage): ?>
                    <option value='<?=$optionLanguage?>'><?=$langCodes[$optionLanguage]?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    <h1
    class="w3-margin" id="main-title"
    style="font-family: 'LinLibertine', sans-serif; font-size: calc(2em + 3.5vw);"
    ><?=§('main-title-w')?></h1>
    <script type="text/javascript">
        const heading = document.getElementById('main-title');
        const customFont = new FontFace('LinLibertine', 'url(/font/LinLibertine_Re-4.7.3.otf)');
        customFont.load().then(() => {
          document.fonts.add(customFont);
          heading.style.visibility = 'visible';
        });
    </script>
    <p class="w3-large"><?=§('subtitle')?></p>
    <button
    class="w3-button w3-black w3-padding-large w3-large w3-margin-top"
    onclick="document.getElementById('id01').style.display='block'"
    ><?=§('contest-enter')?></button>
    <br>
    <button
    class="w3-button w3-black w3-padding-large w3-large w3-margin-top"
    onclick="location.href='index.php?manage=true'"
    ><?=§('contest-manage')?></button>
</header>

<!-- Join -->
<div id="id01" class="w3-modal">
    <div class="w3-modal-content w3-card-4 w3-animate-top">
        <header class="w3-container w3-deep-green">
            <span onclick="document.getElementById('id01').style.display='none'"
            class="w3-button w3-display-topright">&times;</span>
            <h4><?=§('contest-select')?></h4>
        </header>
        <div class="w3-padding">
        <?php foreach ($contests_array as $name_id => $contest): ?>
            <p>
                <a href='index.php?lang=<?=$lang?>&contest=<?=$name_id?>'><?=$contest['name']?></a>
            </p>
        <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- First Grid -->
<div class="w3-row-padding w3-padding-64 w3-container">
    <div class="w3-content">
        <div class="w3-twothird">
            <h1><?=§('index-about-short')?></h1>
            <h5 class="w3-padding-32">
                <?=§('index-about-intro')?>
            </h5>
            <p class="w3-text-grey">
                <?=§('index-about-main')?>
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
            <h1><?=§('index-enroll-short')?></h1>
            <h5 class="w3-padding-32">
                <?=§('index-enroll-intro')?>
            </h5>
            <p class="w3-text-grey">
                <?=§('index-enroll-main')?>
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
