<?php
//Protetor de login
require_once "protect.php";

//Escapa variável para uso no SQL
if (isset($_SESSION['user']['user_name'])) $slashed_username = addslashes($_SESSION['user']['user_name']);
$utc_format = 'Y/m/d H:i:s (\U\T\C)';

//Processa informações caso formulário tenha sido submetido
if ($_POST) {

    //Escapa variáveis para uso no SQL
    if (isset($_POST['diff'])) $post['diff'] = addslashes($_POST['diff']);
    if (isset($_POST['overwrite'])) $post['overwrite'] = addslashes($_POST['overwrite']);

    //Atualiza edição que o avaliador tenha pulado edição
    if (isset($_POST['skip']) and isset($_POST['diff'])) {
        $jump_query = mysqli_prepare(
            $con,
            "UPDATE
                `{$contest['name_id']}__edits`
            SET
                `by` = CONCAT('skip-',?)
            WHERE
                `by` = CONCAT('hold-',?) AND
                `diff`= ?
            ");
        mysqli_stmt_bind_param(
            $jump_query,
            "ssi",
            $_SESSION['user']['user_name'],
            $_SESSION['user']['user_name'],
            $_POST['diff']
        );
        mysqli_stmt_execute($jump_query);
        if (mysqli_stmt_affected_rows($jump_query) == 0) {
            die("<br>Erro ao pular edição. Atualize a página para tentar novamente.");
        } else {
            $output['success']['skip'] = true;
        }

    //Libera edições puladas
    } elseif(isset($_POST['release'])) {
        $release_query = mysqli_prepare(
            $con,
            "UPDATE
                `{$contest['name_id']}__edits`
            SET
                `by` = NULL
            WHERE
                `by` = CONCAT('skip-',?)
            ");
        mysqli_stmt_bind_param(
            $release_query,
            "s",
            $_SESSION['user']['user_name']
        );
        mysqli_stmt_execute($release_query);
        if (mysqli_stmt_affected_rows($release_query) == 0) {
            die("<br>Erro ao liberar edição. Atualize a página para tentar novamente.");
        } else {
            $output['success']['release'] = true;
        }

    //Libera edições em avaliação (apenas gestores)
    } elseif(isset($_POST['unhold'])) {
        if ($_SESSION['user']['user_status'] != 'G') {
            die(§('evaluators-denied'));
        }

        $release_query = mysqli_prepare(
            $con,
            "UPDATE
                `{$contest['name_id']}__edits`
            SET
                `by` = NULL
            WHERE
                `by` LIKE 'skip-%' OR `by` LIKE 'hold-%'
            ");
        mysqli_stmt_bind_param(
            $release_query,
            "s",
            $_SESSION['user']['user_name']
        );
        mysqli_stmt_execute($release_query);
        if (mysqli_stmt_affected_rows($release_query) == 0) {
            die("<br>Erro ao liberar edição. Atualize a página para tentar novamente.");
        } else {
            $output['success']['release'] = true;
        }

    //Salva avaliação da edição
    } else {

        //Processa validade da edição, de acordo com o avaliador
        if ($_POST['valid'] == 'sim') {
            $post['valid'] = 1;
        } else {
            $post['valid'] = 0;
        }

        //Verifica se/quantas imagem(es) foi(ram) inserida(s), de acordo com o avaliador
        if ($contest['pictures_mode'] == 2) {
            $post['pic'] = addslashes($_POST['pic']);
        } else {
            if ($_POST['pic'] == 'sim') {
                $post['pic'] = 1;
            } else {
                $post['pic'] = 0;
            }
        }

        //Processa alteração do número de bytes, caso informação tenha sido editada pelo avaliador
        if (isset($_POST['overwrite']) && is_numeric($_POST['overwrite'])) {

            //Busca número de bytes no banco de dados
            $eval_query = mysqli_prepare(
                $con,
                "SELECT
                    `bytes`
                FROM
                    `{$contest['name_id']}__edits`
                WHERE
                    `diff` = ?
                LIMIT 1"
            );
            mysqli_stmt_bind_param($eval_query, "i", $_POST['diff']);
            mysqli_stmt_execute($eval_query);
            $query = mysqli_fetch_assoc(mysqli_stmt_get_result($eval_query));

            //Verifica se há diferença. Caso sim, altera o número de bytes e adiciona comentário
            if ($query['bytes'] != $post['overwrite']) {
                $overwrite_query = mysqli_prepare(
                    $con,
                    "UPDATE
                        `{$contest['name_id']}__edits`
                    SET
                        `bytes` = ?
                    WHERE
                        `diff` = ?"
                );
                mysqli_stmt_bind_param($overwrite_query, "ii", $post['overwrite'], $_POST['diff']);
                mysqli_stmt_execute($overwrite_query);
                $post['overwrited'] = TRUE;
            }
        }

        //Processa observação inserida no formulário
        if (!isset($_POST['obs']) OR $_POST['obs'] == '') {
            $post['obs'] = '';
        } elseif (isset($post['overwrited'])) {
            $obs = addslashes($_POST['obs']);
            $post['obs'] = "Aval: de {$query['bytes']} para {$post['overwrite']} com justificativa \"{$obs}\"\n";
        } else {
            $obs = addslashes($_POST['obs']);
            $post['obs'] = "Aval: \"{$obs}\"\n";
        }

        //Prepara query
        $when = date('Y-m-d H:i:s');
        $update_statement = "
            UPDATE
                `{$contest['name_id']}__edits`
            SET
                `valid_edit`    = ?,
                `pictures`      = ?,
                `by`            = ?,
                `when`          = ?,
                `obs`           = CONCAT(IFNULL(`obs`, ''), ?)
            WHERE `diff`        = ?";
        $update_query = mysqli_prepare($con, $update_statement);
        mysqli_stmt_bind_param(
            $update_query,
            "iisssi",
            $post['valid'],
            $post['pic'],
            $_SESSION['user']['user_name'],
            $when,
            $post['obs'],
            $_POST['diff']
        );

        //Executa query e retorna o resultado para o avaliador
        mysqli_stmt_execute($update_query);
        if (mysqli_stmt_affected_rows($update_query) != 0) {

            $output['success']['diff'] = htmlspecialchars($_POST['diff']);
            $output['success']['valid'] = $post['valid'];
            $output['success']['pic'] = $post['pic'];

        }
    }
}

//Define número mínimo de bytes necessários de acordo com com configuração do concurso
$bytes = 0;
if (isset($contest['minimum_bytes'])) {
    $bytes = $contest['minimum_bytes'];
}

//Converte prazo de reversão para formato compatível com SQL
$revert_time = date('Y-m-d H:i:s', strtotime("-{$contest['revert_time']} hours"));

//Coleta edição para avaliação
$revision_query = mysqli_prepare(
    $con,
    "SELECT
        `diff`,
        `bytes`,
        `article`,
        `timestamp`
    FROM
        `{$contest['name_id']}__edits`
    WHERE
        `reverted` IS null AND
        `valid_edit` IS null AND
        `valid_user` IS NOT null AND
        CASE
            WHEN ? = '-1'
            THEN `bytes` IS NOT null
            ELSE `bytes` > ?
        END AND
        `timestamp` < ? AND
        (
            `by` IS null OR
            `by` = CONCAT('hold-', ?)
        )
    ORDER BY
        `by` DESC,
        `timestamp` ASC
    LIMIT 1"
);
mysqli_stmt_bind_param($revision_query, "iiss", $bytes, $bytes, $revert_time, $_SESSION['user']['user_name']);
mysqli_stmt_execute($revision_query);
$output['revision'] = mysqli_fetch_assoc(mysqli_stmt_get_result($revision_query));

//Evita avaliação durante atualização do banco de dados
if ($contest['started_update'] > $contest['finished_update']) {
    $output['revision'] = null;
    $output['updating'] = true;
}

//Trava edição para evitar que dois avaliadores avaliem a mesma edição ao mesmo tempo
if ($output['revision'] != null) {
    $hold_query = mysqli_prepare(
        $con,
        "UPDATE
            `{$contest['name_id']}__edits`
        SET
            `by` = CONCAT('hold-', ?),
            `when` = NOW()
        WHERE
            `diff`= ?"
    );
    mysqli_stmt_bind_param($hold_query, "si", $_SESSION['user']['user_name'], $output['revision']['diff']);
    mysqli_stmt_execute($hold_query);
    if (mysqli_stmt_affected_rows($hold_query) == 0) {
        die("<br>Erro ao travar edição. Atualize a página para tentar novamente.");
    }

    //Coleta informações da edição via API do MediaWiki
    $compare_api_params = [
        "action"    => "compare",
        "prop"      => "title|diff|comment|user",
        "format"    => "php",
        "fromrev"   => $output['revision']['diff'],
        "torelative"=> "prev"
    ];
    $output['compare'] = unserialize(
        file_get_contents($contest['api_endpoint']."?".http_build_query($compare_api_params))
    )['compare'];

    $compare_api_params["difftype"] = "inline";
    
    $output['compare_mobile'] = unserialize(
        file_get_contents($contest['api_endpoint']."?".http_build_query($compare_api_params))
    )['compare'];

    //Coleta histórico recente do artigo até o início do concurso
    $history_params = [
        "action"    => "query",
        "format"    => "php",
        "prop"      => "revisions",
        "pageids"   => $output['revision']['article'],
        "rvprop"    => "timestamp|user|size|ids",
        "rvlimit"   => "max",
        "rvend"     => date('Y-m-d\TH:i:s.000\Z', $contest['start_time'])
    ];
    $history = unserialize(
        file_get_contents($contest['api_endpoint']."?".http_build_query($history_params))
    )["query"]["pages"];

    //Verifica situação da primeira edição.
    //Se for a primeira edição, insere pseudo-edição anterior com tamanho zero
    //Se existir edição anterior, busca na API o tamanho da edição
    //imediatamente anterior e insere pseudo-edição com tamanho correspondente
    $history = end($history)["revisions"];
    if (end($history)["parentid"] == 0) {
        $history[] = [
            "size"      => "0",
            "timestamp" => "1970-01-01T00:00:00",
            "user"      => "None",
            "revid"     => "0"
        ];
    } else {
        $lastdiff_params = [
            "action"        => "compare",
            "format"        => "php",
            "fromrev"       => end($history)["revid"],
            "torelative"    => "prev",
            "prop"          => "size"
        ];
        $lastdiff = unserialize(
            file_get_contents($contest['api_endpoint']."?".http_build_query($lastdiff_params))
        )["compare"];
        $history[] = [
            "size"      => $lastdiff["fromsize"],
            "timestamp" => "1970-01-01T00:00:00",
            "user"      => "None",
            "revid"     => "0"
        ];
    }

    //Loop para retornar o código HTML do histórico da página
    foreach ($history as $i => $edit) {

        //Calcula quantidade de bytes e formata timestamp
        $delta = $history[$i]['size'] - @$history[$i+1]['size'];
        $timestamp = date($utc_format, strtotime($edit['timestamp']));

        //Define cor para número de bytes
        $delta_color = "grey";
        if ($delta < 0) {
            $delta_color = "red";
        } elseif ($delta > 0) {
            $delta_color = "green";
        }

        //Insere estilo no parágrafo para destacar edição em avaliação
        $history_class = 'w3-small';
        if ($edit['revid'] == $output['revision']['diff']) {
            $history_class = 'w3-small w3-leftbar w3-border-grey w3-padding-small';
        }

        //Monta código da edição
        $output['history'][] = [
            "class"     => $history_class,
            "user"      => $edit['user'],
            "timestamp" => $timestamp,
            "color"     => $delta_color,
            "bytes"     => $delta
        ];
    }

    //Remove pseudo-edição
    array_pop($output['history']);

}

//Conta edições faltantes
$count_query = mysqli_prepare(
    $con,
    "SELECT
        IFNULL(SUM(CASE WHEN `by` IS null       THEN 1 ELSE 0 END), 0) AS `onqueue`,
        IFNULL(SUM(CASE WHEN `timestamp` > ?    THEN 1 ELSE 0 END), 0) AS `onwait`,
        IFNULL(SUM(CASE WHEN `by` LIKE 'skip-%' THEN 1 ELSE 0 END), 0) AS `onskip`,
        IFNULL(SUM(CASE WHEN `by` LIKE 'hold-%' THEN 1 ELSE 0 END), 0) AS `onhold`
    FROM
        `{$contest['name_id']}__edits`
    WHERE
        `reverted` IS null AND
        `valid_edit` IS null AND
        `valid_user` IS NOT null AND
        CASE
            WHEN ? = '-1'
            THEN `bytes` IS NOT null
            ELSE `bytes` > ?
        END"
);
mysqli_stmt_bind_param($count_query, "sii", $revert_time, $bytes, $bytes);
mysqli_stmt_execute($count_query);
$count_result = mysqli_fetch_assoc(mysqli_stmt_get_result($count_query));
$output['onwait'] = $count_result['onwait'];
$output['onskip'] = $count_result['onskip'];
$output['onhold'] = $count_result['onhold'];
$output['onqueue'] = $count_result['onqueue'] - $count_result['onwait'];

//Encerra conexão
mysqli_close($con);

//Exibe edição e formulário de avaliação
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <title><?=$contest['name'];?></title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta charset="UTF-8">
        <link rel="stylesheet" href="bin/w3.css">
        <link rel="stylesheet" type="text/css" href="bin/color.php?color=<?=@$contest['color'];?>">
        <link rel="stylesheet" href="bin/diff.css">
        <script type="text/javascript" src="bin/authorship.js"></script>
        <script type="text/javascript" src="bin/copyvios.js"></script>
        <script type="text/javascript">
            function handleOverwriteClick(outputRevisionBytes) {
                var overwriteElement = document.getElementById('overwrite');

                overwriteElement.removeAttribute('value');
                overwriteElement.type = 'number';
                overwriteElement.className = 'w3-input w3-border';
                overwriteElement.value = outputRevisionBytes;
                overwriteElement.removeAttribute('onclick');
                overwriteElement.removeAttribute('id');

                var obsElement = document.getElementById('obs');
                obsElement.required = true;
            }

            function movePosition(){
                var first = document.getElementById("first_column");
                var third = document.getElementById("third_column");
                var edits = document.getElementById("edits");
                
                var windowWidth = document.documentElement.clientWidth;
                if(windowWidth < 601){
                    third.insertBefore(edits, third.firstChild);
                } else {
                    first.appendChild(edits);
                }
            }


            var domReady = function(callback) {
                document.readyState === "interactive" || document.readyState === "complete" ? callback() : document.addEventListener("DOMContentLoaded", callback);
            };
            domReady(function() { 
                movePosition()
            });

            window.onresize = function(event) {
               movePosition()
            };
        </script>
        <?php if (isset($output['success']['diff']) || isset($output['success']['skip']) || isset($output['success']['release'])) : ?>
            <script type="text/javascript">history.replaceState(null, document.title, location.href);</script>
        <?php endif; ?>
    </head>
    <body onload="calculateAuthorship('<?=$output['revision']['diff']??'false'?>','<?=$contest['endpoint']?>')">
        <?php require_once "sidebar.php"; ?>
        <div class="w3-row-padding w3-content w3-main" style="max-width:unset;margin-top:43px;padding-top:16px;">
            <div id="first_column" class="w3-quarter">
                <?php if (isset($output['success']['diff'])) : ?>
                    <div
                    class="w3-container w3-light-grey w3-border w3-border-dark-grey w3-margin-bottom"
                    style="display: block"
                    >
                        <h2><?=§('triage-lasteval')?></h2>
                        <p>
                            <?=§('triage-diff')?>: <a
                                href="<?=$contest['endpoint'];?>?diff=<?=@$output['success']['diff'];?>"
                                rel="noopener"
                                target="_blank"><?=@$output['success']['diff'];?></a>
                        </p>
                        <p>
                            <?=§('triage-validedit')?>: <?php if (@$output['success']['valid']): ?><i
                            class="fa-regular w3-text-green fa-circle-check"
                            aria-hidden="true"
                            ></i> <?=§('yes')?><?php else: ?><i
                            class="fa-regular w3-text-red fa-circle-xmark"
                            aria-hidden="true"
                            ></i> <?=§('no')?><?php endif; ?>
                        </p>
                        <p>
                            <?=§('triage-withimage')?>: <?php if (@$output['success']['pic']): ?><i
                            class="fa-regular w3-text-green fa-circle-check"
                            aria-hidden="true"
                            ></i> <?=§('yes')?><?php else: ?><i
                            class="fa-regular w3-text-red fa-circle-xmark"
                            aria-hidden="true"
                            ></i> <?=§('no')?><?php endif; ?>
                        </p>
                        <p>
                            <button
                            class="w3-button w3-border-purple w3-purple w3-border w3-block w3-small"
                            type="button"
                            onclick="window.open(
                                'index.php?lang=<?=$lang?>&contest=<?=$contest['name_id'];?>&page=modify&diff=<?=@$output['success']['diff'];?>',
                                '_blank'
                            );"><i class="fa-solid fa-eraser w3-medium" aria-hidden="true"></i> <?=§('triage-fix')?></button>
                        </p>
                    </div>
                <?php endif; ?>
                <div class="w3-container w3-light-grey w3-border w3-border-dark-grey w3-margin-bottom" 
                style="display:<?=(isset($output['revision']['timestamp']))?'block':'none';?>">
                    <h2><?=§('triage-evaluation')?></h2>
                    <form method="post" id="evaluate">
                        <input type="hidden" name="diff" value="<?=@$output['revision']['diff'];?>">
                        <div class="w3-container w3-cell w3-col l6 m12 s6">
                            <p><?=§('isvalid')?></p>
                            <input
                            class="w3-radio w3-section"
                            type="radio"
                            id="valid-sim"
                            name="valid"
                            value="sim"
                            onclick="document.getElementById('obs').required = false"
                            required>
                            <label for="valid-sim"><?=§('yes')?></label><br>
                            <input
                            class="w3-radio w3-section"
                            type="radio"
                            id="valid-nao"
                            name="valid"
                            value="nao"
                            onclick="document.getElementById('obs').required = true"
                            required>
                            <label for="valid-nao"><?=§('no')?></label><br><br>
                        </div>
                        <div class="w3-container w3-cell w3-col l6 m12 s6">
                            <?php if ($contest['pictures_mode'] == 2): ?>
                                <p><?=§('withimage')?></p>
                                <input
                                class="w3-input w3-section"
                                type="number"
                                id="pic"
                                name="pic"
                                value="0"
                                min="0"
                                max="9"
                                required>
                                <label for="pic"><?=§('quantity')?></label><br>
                            <?php else: ?>
                                <p><?=§('withimage')?></p>
                                <input
                                class="w3-radio w3-section"
                                type="radio"
                                id="pic-sim"
                                name="pic"
                                value="sim"
                                required>
                                <label for="pic-sim"><?=§('yes')?></label><br>
                                <input
                                class="w3-radio w3-section"
                                type="radio"
                                id="pic-nao"
                                name="pic"
                                value="nao"
                                required>
                                <label for="pic-nao"><?=§('no')?></label><br><br>
                            <?php endif; ?>
                        </div>
                        <p>
                            <input
                            class="w3-input w3-border w3-leftbar w3-rightbar w3-border-light-grey"
                            name="obs"
                            id="obs"
                            type="text"
                            placeholder="<?=§('triage-observation')?>">
                            <br>
                            <input
                            class="w3-button w3-leftbar w3-rightbar w3-border-light-grey w3-block w3-red"
                            name="overwrite"
                            id="overwrite"
                            type="button"
                            value="<?=§('triage-alterbytes')?>"
                            onclick="handleOverwriteClick('<?=@$output['revision']['bytes'];?>')">
                        </p>
                    </form>
                    <div class="w3-row">
                        <div class="w3-section w3-col l6">
                            <input
                            form="evaluate"
                            class="w3-button w3-green w3-leftbar w3-rightbar w3-border-light-grey w3-block"
                            type="submit"
                            value="<?=§('triage-save')?>">
                        </div>
                        <div class="w3-section w3-col l6">
                            <form method="post">
                                <input type="hidden" name="diff" value="<?=@$output['revision']['diff'];?>">
                                <input type="hidden" name="skip" value="true">
                                <button
                                class="w3-button w3-purple w3-leftbar w3-rightbar w3-border-light-grey w3-block"
                                type="submit"
                                <?=(isset($output['revision']['diff']))?'':'disabled';?>
                                ><?=§('triage-jump')?></button>
                            </form>
                        </div>
                    </div>
                </div>
                <div id="edits" class="w3-container w3-light-grey w3-border w3-border-dark-grey w3-justify w3-margin-bottom">
                    <h2><?=§('triage-edits')?></h2>
                    <div class="w3-row">
                        <div class="w3-col l6 m12 s6">
                            <h6 class="w3-center"><?=§('triage-toeval')?></h6>
                            <h1 class="w3-center"><?=$output['onqueue'];?></h1>
                        </div>
                        <div class="w3-col l6 m12 s6">
                            <h6 class="w3-center"><?=§('triage-towait')?></h6>
                            <h1 class="w3-center"><?=$output['onwait'];?></h1>
                        </div>
                    </div>
                    <div class="w3-row">
                        <div class="w3-col l6 m12 s6">
                            <h6 class="w3-center"><?=§('triage-onhold')?></h6>
                            <h1 class="w3-center"><?=$output['onhold'];?></h1>
                        </div>
                        <div class="w3-col l6 m12 s6">
                            <h6 class="w3-center"><?=§('triage-onskip')?></h6>
                            <h1 class="w3-center"><?=$output['onskip'];?></h1>
                        </div>
                    </div>
                    <p>
                        <form method="post">
                            <input type="hidden" name="release" value="true">
                            <button
                            class="w3-button w3-purple w3-leftbar w3-rightbar w3-border-light-grey w3-block"
                            type="submit"
                            <?=($output['onskip']>0)?'':'disabled';?>
                            ><?=§('triage-release')?></button>
                        </form>
                    </p>
                    <?php if ($_SESSION['user']["user_status"] == 'G'): ?>
                        <p>
                            <form method="post"
                            onsubmit="return confirm('<?=§('evaluators-areyousure')?>');">
                                <input type="hidden" name="unhold" value="true">
                                <button
                                class="w3-button w3-red w3-leftbar w3-rightbar w3-border-light-grey w3-block"
                                type="submit"
                                <?=(($output['onhold'] + $output['onskip']) > 0)?'':'disabled';?>
                                ><?=§('triage-unhold')?></button>
                            </form>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            <div id="second_column" class="w3-half">
                <?php if (isset($output['updating'])): ?>
                    <div class="w3-panel w3-red w3-display-container w3-border">
                        <p>
                            <h3><?=§('triage-database')?></h3>
                            <?=§('triage-databaseabout')?>
                        </p>
                    </div>
                <?php elseif (!isset($output['compare']['*'])): ?>
                    <div class="w3-panel w3-orange w3-display-container w3-border">
                        <p>
                            <h3><?=§('triage-noedit')?></h3>
                        </p>
                    </div>
                <?php else: ?>
                    <div class="w3-container w3-justify w3-margin-bottom w3-row details">
                        <h3><?=§('triage-details')?></h3>
                        <div class="w3-col l6">
                            <strong><i class="fa-solid fa-user"></i><?=§('label-user')?></strong>
                            <span style="font-weight:bolder;color:red;"><?=@$output['compare']['touser'];?></span>
                            <br>
                            <strong><i class="fa-solid fa-font"></i><?=§('label-page')?></strong>
                            <?=@$output['compare']['totitle'];?>
                            <br>
                            <strong><i class="fa-solid fa-hand-point-up"></i><?=§('triage-authorship')?></strong>
                            <a onclick="calculateAuthorship('<?=$output['revision']['diff']?>','<?=$contest['endpoint']?>')"
                            href="#" id="a_authorship"><?=§('triage-verify')?></a>
                            <span id="span_authorship"></span>
                            <br>
                            <strong><i class="fa-regular fa-clock"></i><?=§('label-timestamp')?></strong>
                            <?=@$output['revision']['timestamp'];?> (UTC)
                        </div>
                        <div class="w3-col l6">
                            <strong><i class="fa-solid fa-arrow-up-9-1"></i><?=§('label-diff')?></strong>
                            <?=@$output['revision']['bytes'];?> bytes
                            <br>
                            <strong><i class="fa-solid fa-thumbtack"></i><?=§('triage-diff')?>:</strong>
                            <a href="<?=$contest['endpoint'];?>?diff=<?=@$output['revision']['diff'];?>"
                            target="_blank" rel="noopener"><?=@$output['revision']['diff'];?></a>
                            <br>
                            <strong><i class="fa-solid fa-triangle-exclamation"></i><?=§('triage-copyvio')?>:</strong>
                            <a onclick="calculateCopyvios('<?=$output['revision']['diff']?>','<?=$contest['endpoint']?>')"
                            href="#" id="a_copyvios"><?=§('triage-verify')?></a>
                            <span id="span_copyvios"></span>
                            <br>
                            <strong><i class="fa-solid fa-comment"></i><?=§('label-summary')?></strong>
                            <?=@$output['compare']['tocomment'];?>
                        </div>
                    </div>
                    <div class="w3-container">
                        <h3><?=§('triage-differential')?></h3>
                        <table
                        role="presentation"
                        aria-label="Diferencial de edição"
                        class="diff diff-desktop diff-contentalign-left diff-editfont-monospace w3-hide-small w3-hide-medium"
                        >
                            <colgroup>
                                <col style="width:2%">
                                <col style="width:48%">
                                <col style="width:2%;">
                                <col style="width:48%">
                            </colgroup>
                            <?=$output['compare']['*']?>
                        </table>
                        <table
                        role="presentation"
                        aria-label="Diferencial de edição"
                        class="diff diff-mobile diff-contentalign-left diff-editfont-monospace w3-hide-large"
                        >
                            <?=$output['compare_mobile']['*']?>
                        </table>
                        <hr>
                    </div>
                <?php endif; ?>
            </div>
            <div id="third_column" class="w3-quarter">
                <div class="w3-container w3-light-grey w3-border w3-border-dark-grey w3-justify w3-margin-bottom" 
                style="display:<?=(isset($output['revision']['timestamp']))?'block':'none';?>">
                    <h2><?=§('triage-recenthistory')?></h2>
                    <?php foreach ($output['history'] ?? [] as $oldid): ?>
                        <p class='<?=$oldid['class']?>'>
                            <strong><?=$oldid['user']?></strong>
                            <br>
                            <?=$oldid['timestamp']?>
                            <br>
                            <span class='w3-text-<?=$oldid['color']?>'><?=$oldid['bytes']?> bytes</span>
                        </p>
                    <?php endforeach; ?>
                </div>
                <div class="w3-container w3-light-grey w3-border w3-border-dark-grey w3-justify w3-margin-bottom">
                    <h2><?=§('triage-generalinfo')?></h2>
                    <p class="w3-small">
                        <strong><?=§('triage-contestname')?></strong>
                        <br>
                        <?=$contest['name'];?>
                    </p>
                    <p class="w3-small">
                        <strong><?=§('triage-loggedname')?></strong>
                        <br>
                        <?=ucfirst($_SESSION['user']['user_name']);?>
                    </p>
                    <p class="w3-small">
                        <strong><?=§('triage-conteststart')?></strong>
                        <br>
                        <?=date($utc_format, $contest['start_time']);?>
                    </p>
                    <p class="w3-small">
                        <strong><?=§('triage-contestend')?></strong>
                        <br>
                        <?=date($utc_format, $contest['end_time']);?>
                    </p>
                    <p class="w3-small">
                        <strong><?=§('triage-lastupdate')?></strong>
                        <br>
                        <?=date($utc_format, $contest['finished_update']);?>
                    </p>
                    <p class="w3-small">
                        <strong><?=§('triage-delay')?></strong>
                        <br>
                        <?=§('triage-hours', $contest['revert_time'])?>
                    </p>
                    <p class="w3-small">
                        <strong><?=§('triage-bpp')?></strong>
                        <br>
                        <?=§('triage-bytes', $contest['bytes_per_points'])?>
                    </p>
                    <p class="w3-small">
                        <strong><?=§('triage-maxbytes')?></strong>
                        <br>
                        <?=§('triage-bytes', $contest['max_bytes_per_article'])?>
                        /
                        <?=§('triage-points',($contest['max_bytes_per_article'] / $contest['bytes_per_points']))?>
                    </p>
                    <p class="w3-small">
                        <strong><?=§('triage-minbytes')?></strong>
                        <br>
                        <?php 
                            if (!$contest['minimum_bytes']) {
                                echo §('triage-indef');
                            } elseif ($contest['minimum_bytes'] == -1) {
                                echo §('triage-includingall');
                            } else {
                                echo §('triage-bytes', $contest['minimum_bytes']);
                            }
                        ?>
                    </p>
                    <p class="w3-small">
                        <strong><?=§('triage-ipp')?></strong>
                        <br>
                        <?= ($contest['pictures_per_points'] == 0)
                            ? §('triage-noimages')
                            : §('triage-images', $contest['pictures_per_points'])
                        ?>
                    </p>
                    <p class="w3-small" style="display:<?=($contest['pictures_per_points']!=0)?'block':'none';?>;">
                        <strong><?=§('triage-imagemode')?></strong>
                        <br>
                        <?php
                            if ($contest['pictures_per_points'] == 2) {
                                echo §('triage-byimage');
                            } elseif ($contest['pictures_per_points'] == 1) {
                                echo §('triage-byedition');
                            } else {
                                echo §('triage-bypage');
                            }
                        ?>
                    </p>
                    <p class="w3-small" style="display:<?=($contest['max_pic_per_article']!=0)?'block':'none';?>;">
                        <strong><?=§('triage-maximages')?></strong>
                        <br>
                        <?=$contest['max_pic_per_article']??§('triage-indef')?>
                    </p>
                </div>
            </div>
        </div>
    </body>
</html>