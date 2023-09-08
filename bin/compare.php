<?php

//Protetor de login
require_once "protect.php";

//Verifica se a ultima atualização ocorreu em menos de 30 minutos
if ((time() - $contest['finished_update']) < 1800 || $contest['next_update'] == null) {
    $early = true;
}

//Processa informações caso formulário tenha sido submetido
if (isset($_POST['update']) && !isset($early)) {
    $refresh_query = mysqli_prepare(
        $con,
        "UPDATE
            `manage__contests`
        SET
            `next_update` = NULL
        WHERE
            `name_id` = ?"
    );
    mysqli_stmt_bind_param($refresh_query, "s", $contest['name_id']);
    mysqli_stmt_execute($refresh_query);
    if (mysqli_stmt_affected_rows($refresh_query) == 0) {
        die("<br>".§('compare-error'));
    } else {
        $update = true;
    }
}

if (isset($_POST['diff'])) {
    $fix_query = mysqli_prepare(
        $con,
        "DELETE FROM
            `{$contest['name_id']}__edits`
        WHERE
            `article` NOT IN (
                SELECT
                    `articleID`
                FROM
                    `{$contest['name_id']}__articles`
            ) AND
            `diff` = ?"
    );
    mysqli_stmt_bind_param($fix_query, "i", $_POST['diff']);
    mysqli_stmt_execute($fix_query);
    if (mysqli_stmt_affected_rows($fix_query) == 0) {
        die("<br>".§('compare-inconsistency-error'));
    } else {
        $fixed = true;
    }
}


//Verifica se a lista oficial e a categoria foram definidas
if (isset($contest['official_list_pageid']) && isset($contest['category_pageid'])) {

    //Define arrays
    $list_cat = array();
    $list_official = array();

    //Coleta lista de artigos na página do concurso
    $list_api_params = [
        "action"        => "query",
        "format"        => "php",
        "generator"     => "links",
        "pageids"       => $contest['official_list_pageid'],
        "gplnamespace"  => "0",
        "gpllimit"      => "max"
    ];

    $list_api = unserialize(file_get_contents($contest['api_endpoint']."?".http_build_query($list_api_params)));
    $listmembers = $list_api['query']['pages'];
    foreach ($listmembers as $pagetitle) {
        if (isset($pagetitle['missing'])) { continue; }
        $list_official[] = $pagetitle['title'];
    }

    //Coleta segunda página da lista, caso exista
    while (isset($list_api['continue'])) {
        $list_api_params = [
            "action"        => "query",
            "format"        => "php",
            "generator"     => "links",
            "pageids"       => $contest['official_list_pageid'],
            "gplnamespace"  => "0",
            "gpllimit"      => "max",
            "gplcontinue"   => $list_api['continue']['gplcontinue']
        ];
        $list_api = unserialize(
            file_get_contents($contest['api_endpoint']."?".http_build_query($list_api_params))
        );
        $listmembers = $list_api['query']['pages'];
        foreach ($listmembers as $pagetitle) {
            if (isset($pagetitle['missing'])) { continue; }
            $list_official[] = $pagetitle['title'];
        }
    }

    //Coleta lista de artigos na categoria
    $categorymembers_api_params = [
        "action"        => "query",
        "format"        => "php",
        "prop"          => "pageprops",
        "generator"     => "categorymembers",
        "ppprop"        => "wikibase_item",
        "cmnamespace"   => "0",
        "gcmpageid"     => $contest['category_pageid'],
        "gcmprop"       => "title",
        "gcmlimit"      => "max"
    ];
    $categorymembers_api = unserialize(
        file_get_contents($contest['api_endpoint']."?".http_build_query($categorymembers_api_params))
    );
    foreach ($categorymembers_api['query']['pages'] as $pageid) {
        $list_cat[] = $pageid['title'];
        if (!isset($pageid['pageprops']['wikibase_item'])) {
            $list_wd[] = $pageid['title'];
        }
    }

    //Coleta segunda página da categoria, caso exista
    while (isset($categorymembers_api['continue'])) {
        $categorymembers_api_params = [
            "action"        => "query",
            "format"        => "php",
            "prop"          => "pageprops",
            "generator"     => "categorymembers",
            "ppprop"        => "wikibase_item",
            "cmnamespace"   => "0",
            "gcmpageid"     => $contest['category_pageid'],
            "gcmprop"       => "title",
            "gcmcontinue"   => $categorymembers_api['continue']['gcmcontinue']
        ];
        $categorymembers_api = unserialize(
            file_get_contents($contest['api_endpoint']."?".http_build_query($categorymembers_api_params))
        );
        foreach ($categorymembers_api['query']['pages'] as $pageid) {
            $list_cat[] = $pageid['title'];
            if (!isset($pageid['pageprops']['wikibase_item'])) {
                $list_wd[] = $pageid['title'];
            }
        }
    }

    //Processa listagens
    $adicionar = array_diff($list_cat, $list_official);
    asort($adicionar);
    $remover = array_diff($list_official, $list_cat);
    asort($remover);

} else {

    //Define variáveis como em branco, para evitar erro
    $adicionar = array();
    $remover = array();
}

//Seleciona categoria de páginas pendentes de eliminação
if ($contest['api_endpoint'] == 'https://pt.wikipedia.org/w/api.php') {
    $ec_curid   = '1001045';
    $other_dels = [ '3501865' , '2419924' , '5857294' ];

    //Coleta páginas em EC
    $ec_list_params = [
        "action"        => "query",
        "format"        => "php",
        "list"          => "categorymembers",
        "cmpageid"      => $ec_curid,
        "cmnamespace"   => "4",
        "cmlimit"       => "max",
        "cmprop"        => "title"
    ];
    $ec_list = unserialize(
        file_get_contents($contest['api_endpoint']."?".http_build_query($ec_list_params))
    )['query']['categorymembers'];
    foreach ($ec_list as $page) {
        $deletion[] = substr($page['title'], 34);
    }

    //Coleta outras páginas
    foreach ($other_dels as $del_curid) {
        $list_params = [
            "action"        => "query",
            "format"        => "php",
            "list"          => "categorymembers",
            "cmpageid"      => $del_curid,
            "cmnamespace"   => "0",
            "cmlimit"       => "max",
            "cmprop"        => "title"
        ];
        $list = unserialize(
            file_get_contents($contest['api_endpoint']."?".http_build_query($list_params))
        )['query']['categorymembers'] ?? [];
        foreach ($list as $page) {
            $deletion[] = $page['title'];
        }
    }


    //Processa listagem
    $eliminar = array_intersect($deletion, $list_cat);
    asort($eliminar);
}

//Coleta lista de diffs inconsistentes
$inconsistency_query = mysqli_prepare(
    $con,
    "SELECT
      `diff`,
      `valid_edit`,
      `timestamp`
    FROM
      `{$contest['name_id']}__edits`
    WHERE
      `article` NOT IN (
        SELECT
          `articleID`
        FROM
          `{$contest['name_id']}__articles`
      )
      AND `valid_user` = '1'
      AND `reverted` IS NULL
    ORDER BY `timestamp` ASC"
);
mysqli_stmt_execute($inconsistency_query);
$inconsistency_result = mysqli_stmt_get_result($inconsistency_query);

//Coleta lista de diffs revertidos e validados
$reverted_query = mysqli_prepare(
    $con,
    "SELECT
      `diff`,
      `timestamp`
    FROM
      `{$contest['name_id']}__edits`
    WHERE
      `valid_edit` = '1'
      AND `reverted` IS NOT NULL
    ORDER BY `timestamp` ASC"
);
mysqli_stmt_execute($reverted_query);
$reverted_result = mysqli_stmt_get_result($reverted_query);


//Calcula contagem regressiva para atualização do banco de dados
if ($contest['end_time'] + 172800 < time()) {
    $countdown = false;
} elseif ($contest['next_update'] > time() && !isset($update)) {
    $countdown = $contest['next_update'] - time();
} else {
    $countdown = 0;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
    <head>
        <title><?=§('compare')?> - <?=$contest['name'];?></title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="bin/w3.css">
        <link rel="stylesheet" type="text/css" href="bin/color.php?color=<?=@$contest['color'];?>">
        <script type="text/javascript">
            function formatTime(seconds) {
                const hours = Math.floor(seconds / 3600);
                const minutes = Math.floor((seconds % 3600) / 60);
                const secs = seconds % 60;

                return (
                    String(hours).padStart(2, '0') +
                    ':' +
                    String(minutes).padStart(2, '0') +
                    ':' +
                    String(secs).padStart(2, '0')
                );
            }

            function updateCountdown(targetTime) {
                const countdownDiv = document.getElementById('countdown');
                const currentTime = performance.now();
                const remainingTimeInSeconds = Math.max(0, Math.round((targetTime - currentTime) / 1000));

                if (remainingTimeInSeconds > 0) {
                    countdownDiv.textContent = formatTime(remainingTimeInSeconds);
                    requestAnimationFrame(() => updateCountdown(targetTime));
                } else {
                    countdownDiv.textContent = '<?=§('compare-soon')?>';
                }
            }

            function startCountdown(totalSeconds) {
                if (!totalSeconds || isNaN(totalSeconds) || totalSeconds < 0) {
                    return;
                }
                const targetTime = performance.now() + totalSeconds * 1000;
                updateCountdown(targetTime);
            }
        </script>
        <?php if (isset($update)) : ?>
            <script type="text/javascript">history.replaceState(null, document.title, location.href);</script>
        <?php endif; ?>
    </head>
    <body onload="startCountdown(<?=(is_numeric($countdown)?$countdown:'')?>)">
        <header class="w3-container w3-<?=$contest['theme'];?>">
            <h1><?=§('compare')?> - <?=$contest['name'];?></h1>
        </header>
        <br>
        <div class="w3-container">
            <?php if ($countdown === false) : ?>
                <div class="w3-panel w3-pale-red w3-display-container w3-border">
                    <?php if (isset($early) || isset($update)) : ?>
                        <h3><?=§('compare-next')?> <?=§('compare-soon')?></h3>
                    <?php else : ?>
                        <h3><?=§('compare-ended')?></h3>
                        <form method="post">
                            <p>
                                <button
                                class="w3-button w3-small w3-red"
                                type="submit"
                                name="update"
                                value="update"
                                ><?=§('compare-force')?></button>
                            </p>
                        </form>
                    <?php endif; ?>
                </div>
            <?php else : ?>
                <div class="w3-panel w3-pale-blue w3-display-container w3-border">
                    <h3><?=§('compare-next')?> <span id="countdown">...</span></h3>
                    <form method="post">
                        <p>
                            <button
                            class="w3-button w3-small w3-blue"
                            type="submit"
                            name="update"
                            value="update"
                            style="display: <?=($countdown===0||isset($early))?'none':'block';?>"
                            ><?=§('compare-anticipate')?></button>
                            <span style="display: <?=isset($early)?'inline':'none';?>">
                                <?=§('compare-early')?>
                            </span>
                        </p>
                    </form>
                </div>
            <?php endif; ?>
        </div>
        <br>
        <div class="w3-row-padding">
            <div class="w3-third w3-section">
                <div class="w3-card white">
                    <div class="w3-container w3-purple">
                        <h3><?=§('compare-unlisted')?></h3>
                    </div>
                    <ul class="w3-ul w3-border-top">
                        <li>
                            <?=§('compare-unlisted-about')?>
                        </li>
                        <?php foreach ($adicionar as $title): ?>
                            <li>
                               <a
                               rel="noopener"
                               target='_blank'
                               href='<?=$contest['endpoint']?>?title=<?=urlencode($title)?>'>
                                    <?=$title?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <div class="w3-third w3-section">
                <div class="w3-card white">
                    <div class="w3-container w3-indigo">
                        <h3><?=§('compare-uncated')?></h3>
                    </div>
                    <ul class="w3-ul w3-border-top">
                        <li>
                            <?=§('compare-uncated-about')?>
                        </li>
                        <?php foreach ($remover as $title): ?>
                            <li>
                               <a
                               rel="noopener"
                               target='_blank'
                               href='<?=$contest['endpoint']?>?title=<?=urlencode($title)?>'>
                                    <?=$title?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <div class="w3-third w3-section">
                <div class="w3-card white">
                    <div class="w3-container w3-red">
                        <h3><?=§('compare-deletion')?></h3>
                    </div>
                    <ul class="w3-ul w3-border-top">
                        <li>
                            <?=§('compare-deletion-about')?>
                        </li>
                        <?php foreach ($eliminar ?? array() as $title): ?>
                            <li>
                                <a
                                rel="noopener"
                                target='_blank'
                                href='<?=$contest['endpoint']?>?title=<?=urlencode($title)?>'>
                                    <?=$title?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
        <div class="w3-row-padding">
            <div class="w3-third w3-section">
                <div class="w3-card white">
                    <div class="w3-container w3-blue">
                        <h3><?=§('compare-nowikidata')?></h3>
                    </div>
                    <ul class="w3-ul w3-border-top">
                        <li>
                            <?=§('compare-nowikidata-about')?>
                        </li>
                        <?php foreach ($list_wd ?? array() as $title): ?>
                            <li>
                                <a
                                rel="noopener"
                                target='_blank'
                                href='<?=$contest['endpoint']?>?title=<?=urlencode($title)?>'>
                                    <?=$title?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <div class="w3-third w3-section">
                <div class="w3-card white">
                    <div class="w3-container w3-black">
                        <h3><?=§('compare-inconsistency')?></h3>
                    </div>
                    <ul class="w3-ul w3-border-top">
                        <li>
                            <?=§('compare-inconsistency-about')?>
                        </li>
                        <?php while ($row = mysqli_fetch_assoc($inconsistency_result)): ?>
                            <li class='<?=($row['valid_edit'] == '1')?'w3-red':''?>'>
                                <button
                                class='w3-btn w3-padding-small w3-<?=$contest['theme']?>'
                                type='button'
                                onclick='window.open(
                                    "<?=$contest['endpoint']?>?diff=<?=urlencode($row['diff'])?>",
                                    "_blank"
                                )'><?=§('compare-seediff')?><?=$row["diff"]?></button>
                                <form
                                style='display: inline'
                                method='post'
                                onSubmit='return confirm(
                                    "<?=§('compare-areyousure')?>"
                                )'>
                                    <input type='hidden' name='diff' value='<?=$row["diff"]?>'>
                                    <input type='submit' class='w3-btn w3-padding-small w3-red' value='<?=§('compare-delete')?>'>
                                </form>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                </div>
            </div>
            <div class="w3-third w3-section">
                <div class="w3-card white">
                    <div class="w3-container w3-deep-orange">
                        <h3><?=§('compare-rollback')?></h3>
                    </div>
                    <ul class="w3-ul w3-border-top">
                        <li>
                            <?=§('compare-rollback-about')?>
                        </li>
                        <?php while ($row = mysqli_fetch_assoc($reverted_result)): ?>
                            <li>
                                <button
                                class='w3-btn w3-padding-small w3-<?=$contest['theme']?>'
                                type='button'
                                onclick='window.open(
                                    "<?=$contest['endpoint']?>?diff=<?=$diff_encode?>",
                                    "_blank"
                                )'><?=§('compare-seediff')?><?=$row["diff"]?></button>
                                <button
                                class='w3-btn w3-padding-small w3-purple'
                                type='button'
                                onclick='window.open(
                                    "index.php?lang=<?=$lang?>&contest=<?=$contest['name_id']?>&page=modify&diff=<?=urlencode(
                                        $row['diff']
                                    )?>",
                                    "_blank"
                                );'><?=§('compare-reevaluate')?></button>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                </div>
            </div>
        </div>
    </body>
    <?php if (isset($fixed)): ?>
        <script>
            alert('<?=§('compare-success')?>');
            window.location.href = window.location.href;
        </script>
    <?php endif; ?>
</html>
