<?php

//Protetor de login
require_once "protect.php";

//Verifica se a ultima atualização ocorreu em menos de 30 minutos
if ((time() - $contest['finished_update']) < 1800) {
    $early = true;
}

//Processa informações caso formulário tenha sido submetido
if (isset($_POST['update']) && !isset($early)) {
    $refresh_query = mysqli_prepare(
        $con,
        "UPDATE
            `manage__contests`
        SET
            `next_update` = NOW()
        WHERE
            `name_id` = ?"
    );
    mysqli_stmt_bind_param($refresh_query, "s", $contest['name_id']);
    mysqli_stmt_execute($refresh_query);
    if (mysqli_stmt_affected_rows($refresh_query) == 0) {
        die("<br>Erro ao solicitar atualização. Atualize a página para tentar novamente.");
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
        die("<br>Erro ao resolver inconsistência. Atualize a página para tentar novamente.");
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
        if (isset($pagetitle['missing'])) continue;
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
            if (isset($pagetitle['missing'])) continue;
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
        )['query']['categorymembers'];
        foreach ($list as $page) {
            $deletion[] = $page['title'];
        }
    }


    //Processa listagem
    $eliminar = array_intersect($deletion, $list_cat);
    asort($eliminar);
}

//Coleta lista de diffs inconsistentes
$inconsistency_query = mysqli_query(
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
    ORDER BY `timestamp` ASC;"
);

//Coleta lista de diffs revertidos e validados
$reverted_query = mysqli_query(
    $con,
    "SELECT
      `diff`,
      `timestamp`
    FROM
      `{$contest['name_id']}__edits`
    WHERE
      `valid_edit` = '1'
      AND `reverted` IS NOT NULL
    ORDER BY `timestamp` ASC;"
);

//Calcula contagem regressiva para atualização do banco de dados
$countdown = 'até 10 minutos';
if ($contest['next_update'] > time() && !isset($update)) {
    $countdown = gmdate('H \h\o\r\a\s \e i \m\i\n\u\t\o\s', $contest['next_update'] - time());
}
?>

<!DOCTYPE html>
<html lang="pt-br">
    <head>
        <title>Comparador - <?=$contest['name'];?></title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="bin/w3.css">
        <link rel="stylesheet" type="text/css" href="bin/color.php?color=<?=@$contest['color'];?>">
    </head>
    <body>
        <header class="w3-container w3-<?=$contest['theme'];?>">
            <h1>Comparador - <?=$contest['name'];?></h1>
        </header>
        <br>
        <div class="w3-container">
            <div class="w3-panel w3-pale-blue w3-display-container w3-border">
                <h3>Próxima atualização em <?=$countdown;?></h3>
                <form method="post">
                    <p>
                        <button
                        class="w3-button w3-small w3-blue"
                        type="submit"
                        name="update"
                        value="update"
                        style="display: <?=($countdown=='até 10 minutos'||isset($early))?'none':'block';?>"
                        >Antecipar atualização</button>
                        <span style="display: <?=isset($early)?'inline':'none';?>">
                            A última atualização ocorreu em menos de 30 minutos. Por favor, aguarde esse prazo
                            antes de solicitar uma nova atualização.
                        </span>
                    </p>
                </form>
            </div>
        </div>
        <br>
        <div class="w3-row-padding">
            <div class="w3-third w3-section">
                <div class="w3-card white">
                    <div class="w3-container w3-purple">
                        <h3>Não listados</h3>
                    </div>
                    <ul class="w3-ul w3-border-top">
                        <li>
                            Os artigos abaixos estão inseridos na categoria,
                            porém não estão inseridos na lista oficial.
                        </li>
                        <?php foreach ($adicionar as $title): ?>
                            <li>
                               <a target='_blank' href='<?=$contest['endpoint']?>?title=<?=urlencode($title)?>'>";
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
                        <h3>Descategorizados</h3>
                    </div>
                    <ul class="w3-ul w3-border-top">
                        <li>
                            Os artigos abaixos estão inseridos na lista oficial,
                            porém não estão inseridos na categoria.
                        </li>
                        <?php foreach ($remover as $title): ?>
                            <li>
                               <a target='_blank' href='<?=$contest['endpoint']?>?title=<?=urlencode($title)?>'>";
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
                        <h3>Passíveis de eliminação</h3>
                    </div>
                    <ul class="w3-ul w3-border-top">
                        <li>
                            Os artigos abaixos estão inseridos na categoria e estão marcados em
                            alguma modalidade de eliminação (rápida, semirrápida, por consenso
                            ou por candidatura).
                        </li>
                        <?php foreach ($eliminar ?? array() as $title): ?>
                            <li>
                                <a target='_blank' href='<?=$contest['endpoint']?>?title=<?=urlencode($title)?>'>
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
                        <h3>Sem Wikidata</h3>
                    </div>
                    <ul class="w3-ul w3-border-top">
                        <li>
                            Os artigos abaixos estão inseridos na categoria porém não possuem conexão no Wikidata.
                        </li>
                        <?php foreach ($list_wd ?? array() as $title): ?>
                            <li>
                                <a target='_blank' href='<?=$contest['endpoint']?>?title=<?=urlencode($title)?>'>
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
                        <h3>Inconsistências</h3>
                    </div>
                    <ul class="w3-ul w3-border-top">
                        <li>
                            As edições listadas abaixo pertecem a artigos que estavam listados
                            na categoria mas foram removidos. Caso estejam marcadas de vermelho,
                            a edição foi validada e conferiu pontos ao participante.
                        </li>
                        <?php while ($row = mysqli_fetch_assoc($inconsistency_query)): ?>
                            <li class='<?=($row['valid_edit'] == '1')?'w3-red':''?>'>
                                <button
                                class='w3-btn w3-padding-small w3-<?=$contest['theme']?>'
                                type='button'
                                onclick='window.open(
                                    "<?=$contest['endpoint']?>?diff=<?=urlencode($row['diff'])?>",
                                    "_blank"
                                )'>Ver edição <?=$row["diff"]?></button>
                                <form
                                style='display: inline'
                                method='post'
                                onSubmit='return confirm(
                                    "Essa edição será removida do banco de dados. Deseja prosseguir?"
                                )'>
                                    <input type='hidden' name='diff' value='<?=$row["diff"]?>'>
                                    <input type='submit' class='w3-btn w3-padding-small w3-red' value='Apagar'>
                                </form>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                </div>
            </div>
            <div class="w3-third w3-section">
                <div class="w3-card white">
                    <div class="w3-container w3-deep-orange">
                        <h3>Revertidos</h3>
                    </div>
                    <ul class="w3-ul w3-border-top">
                        <li>
                            As edições listadas abaixo foram revertidas após validação.
                        </li>
                        <?php while ($row = mysqli_fetch_assoc($reverted_query)): ?>
                            <li>
                                <button
                                class='w3-btn w3-padding-small w3-<?=$contest['theme']?>'
                                type='button'
                                onclick='window.open(
                                    "<?=$contest['endpoint']?>?diff=<?=$diff_encode?>",
                                    "_blank"
                                )'>Ver edição <?=$row["diff"]?></button>
                                <button
                                class='w3-btn w3-padding-small w3-purple'
                                type='button'
                                onclick='window.open(
                                    "index.php?contest=<?=$contest['name_id']?>&page=modify&diff=<?=urlencode($row['diff'])?>",
                                    "_blank"
                                );'>Reavaliar</button>";
                            </li>
                        <?php endwhile; ?>
                    </ul>
                </div>
            </div>
        </div>
    </body>
    <?php if (isset($fixed)): ?>
        <script>
            alert('Edições removida com sucesso!');
            window.location.href = window.location.href;
        </script>
    <?php endif; ?>
</html>
