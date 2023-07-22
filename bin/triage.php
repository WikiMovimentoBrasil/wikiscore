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

//Conta edições faltantes
$count_query = mysqli_prepare(
    $con,
    "SELECT
        COUNT(*) AS `total_count`,
        IFNULL(SUM(CASE WHEN `timestamp` < ? THEN 1 ELSE 0 END), 0) AS `count`
    FROM
        `{$contest['name_id']}__edits`
    WHERE
        `reverted` IS null AND
        `valid_edit` IS null AND
        `valid_user` IS NOT null AND
        (
            `by` IS null OR
            `by` = CONCAT('hold-', ?)
        ) AND
        CASE
            WHEN ? = '-1'
            THEN `bytes` IS NOT null
            ELSE `bytes` > ?
        END"
);
mysqli_stmt_bind_param($count_query, "ssii", $revert_time, $_SESSION['user']['user_name'], $bytes, $bytes);
mysqli_stmt_execute($count_query);
$count_result = mysqli_fetch_assoc(mysqli_stmt_get_result($count_query));
$output['count'] = $count_result['count'];
$output['total_count'] = $count_result['total_count'] - $count_result['count'];

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
    $output['count'] = '-';
    $output['total_count'] = '-';
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
        <link rel="stylesheet" href="https://tools-static.wmflabs.org/cdnjs/ajax/libs/font-awesome/6.2.0/css/all.css">
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
        </script>
        <?php if (isset($output['success']['diff']) || isset($output['success']['skip'])) : ?>
            <script type="text/javascript">history.replaceState(null, document.title, location.href);</script>
        <?php endif; ?>
    </head>
    <body>
        <div class="w3-<?=$contest['theme'];?> w3-padding-32 w3-margin-bottom w3-center">
            <h1 class="w3-jumbo"><?=$contest['name'];?></h1>
        </div>
        <div class="w3-row-padding w3-content" style="max-width:1400px">
            <div class="w3-quarter">
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
                                'index.php?contest=<?=$contest['name_id'];?>&page=modify&diff=<?=@$output['success']['diff'];?>',
                                '_blank'
                            );"><i class="fa-solid fa-eraser w3-medium" aria-hidden="true"></i> <?=§('triage-fix')?></button>
                        </p>
                    </div>
                <?php endif; ?>
                <div class="w3-container w3-light-grey w3-border w3-border-dark-grey w3-margin-bottom">
                    <h2><?=§('triage-panel')?></h2>
                    <div class="w3-container">
                        <div style="display:<?=(isset($output['revision']['timestamp']))?'block':'none';?>;">
                            <h6 class="w3-center"><?=§('triage-editofday')?></h6>
                            <h4 class="w3-center"><?=@substr($output['revision']['timestamp'], 0, 10);?></h4>
                        </div>
                            <div class="w3-row">
                                <div class="w3-half">
                                    <h6 class="w3-center"><?=§('triage-toeval')?></h6>
                                    <h1 class="w3-center"><?=$output['count'];?></h1>
                                </div>
                                <div class="w3-half">
                                    <h6 class="w3-center"><?=§('triage-towait')?></h6>
                                    <h1 class="w3-center"><?=$output['total_count'];?></h1>
                                </div>
                            </div>
                        <br>
                    </div>

                    <div class="w3-container w3-margin-bottom">
                        <div class="w3-row">
                            <div class="w3-half">
                                <button
                                class="w3-button w3-<?=$contest['theme'];?> w3-border w3-block w3-small"
                                style="filter: hue-rotate(40deg);"
                                type="button"
                                onclick="window.open(
                                    'index.php?contest=<?=$contest['name_id'];?>&page=counter',
                                    '_blank'
                                );">
                                    <i class="fa-solid fa-chart-line w3-xxlarge" aria-hidden="true"></i><br><?=§('counter')?>
                                </button>
                            </div>
                            <div class="w3-half">
                                <button
                                class="w3-button w3-<?=$contest['theme'];?> w3-border w3-block w3-small"
                                style="filter: hue-rotate(80deg);"
                                type="button"
                                onclick="window.open(
                                    'index.php?contest=<?=$contest['name_id'];?>&page=modify',
                                    '_blank'
                                );">
                                    <i class="fa-solid fa-pen-to-square w3-xxlarge" aria-hidden="true"></i><br><?=§('modify')?>
                                </button>
                            </div>
                        </div>
                        <div class="w3-row">
                            <div class="w3-half">
                                <button
                                class="w3-button w3-<?=$contest['theme'];?> w3-border w3-block w3-small"
                                style="filter: hue-rotate(120deg);"
                                type="button"
                                onclick="window.open(
                                    'index.php?contest=<?=$contest['name_id'];?>&page=compare',
                                    '_blank'
                                );">
                                    <i class="fa-solid fa-code-compare w3-xxlarge" aria-hidden="true"></i><br><?=§('compare')?>
                                </button>
                            </div>
                            <div class="w3-half">
                                <button
                                class="w3-button w3-<?=$contest['theme'];?> w3-border w3-block w3-small"
                                style="filter: hue-rotate(160deg);"
                                type="button"
                                onclick="window.open(
                                    'index.php?contest=<?=$contest['name_id'];?>&page=edits',
                                    '_blank'
                                );">
                                    <i class="fa-solid fa-list-check w3-xxlarge" aria-hidden="true"></i><br><?=§('triage-evaluated')?>
                                </button>
                            </div>
                        </div>
                        <div class="w3-row">
                            <div class="w3-half">
                                <button
                                class="w3-button w3-<?=$contest['theme'];?> w3-border w3-block w3-small"
                                style="filter: hue-rotate(200deg);"
                                type="button"
                                onclick="window.open(
                                    'index.php?contest=<?=$contest['name_id'];?>&page=backtrack',
                                    '_blank'
                                );">
                                    <i class="fa-solid fa-history w3-xxlarge" aria-hidden="true"></i><br><?=§('backtrack')?>
                                </button>
                            </div>
                            <div class="w3-half">
                                <button
                                class="w3-button w3-<?=$contest['theme'];?> w3-border w3-block w3-small"
                                style="filter: hue-rotate(240deg);"
                                type="button"
                                onclick="window.open(
                                    'index.php?contest=<?=$contest['name_id'];?>&page=evaluators',
                                    '_blank'
                                );">
                                    <i class="fa-solid fa-users w3-xxlarge" aria-hidden="true"></i><br><?=§('evaluators')?>
                                </button>
                            </div>
                        </div>
                        <div class="w3-row">
                            <div class="w3-half">
                                <form method="post">
                                    <input type="hidden" name="diff" value="<?=@$output['revision']['diff'];?>">
                                    <input type="hidden" name="skip" value="true">
                                    <button
                                    class="w3-button w3-<?=$contest['theme'];?> w3-border w3-block w3-small"
                                    style="filter: hue-rotate(280deg);"
                                    type="submit"
                                    value="Pular edição"
                                    <?=(isset($output['revision']['diff']))?'':'disabled';?>
                                    >
                                        <i class="fa-solid fa-forward w3-xxlarge" aria-hidden="true"></i><br><?=§('triage-jump')?>
                                    </button>
                                </form>
                            </div>
                            <div class="w3-half">
                                <form method="post">
                                    <button
                                    class="w3-button w3-<?=$contest['theme'];?> w3-border w3-block w3-small"
                                    style="filter: hue-rotate(320deg);"
                                    type="submit"
                                    name="logout"
                                    value="Logout">
                                        <i class="fa-solid fa-door-open w3-xxlarge" aria-hidden="true"></i><br><?=§('triage-exit')?>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <div style="display:<?=(isset($output['revision']['timestamp']))?'block':'none';?>">
                    <div class="w3-container w3-light-grey w3-border w3-border-dark-grey w3-margin-bottom">
                        <h2><?=§('triage-evaluation')?></h2>
                        <form method="post">
                            <input type="hidden" name="diff" value="<?=@$output['revision']['diff'];?>">
                            <div class="w3-container w3-cell w3-half">
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
                            <div class="w3-container w3-cell w3-half">
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
                                class="w3-input w3-border"
                                name="obs"
                                id="obs"
                                type="text"
                                placeholder="<?=§('label-observation')?>">
                                <br>
                                <input
                                class="w3-button w3-border w3-block w3-red"
                                name="overwrite"
                                id="overwrite"
                                ype="button"
                                value="<?=§('label-alterbytes')?>"
                                onclick="handleOverwriteClick('<?=@$output['revision']['bytes'];?>')">
                                <input
                                class="w3-button w3-green w3-border-green w3-border w3-block w3-margin-top"
                                type="submit"
                                value="<?=§('label-save')?>">
                            </p>
                        </form>
                    </div>
                    <div class="w3-container w3-light-grey w3-border w3-border-dark-grey w3-justify w3-margin-bottom">
                        <h2><?=§('triage-details')?></h2>
                        <p style="overflow-wrap: break-word;">
                            <strong><?=§('withimage')?></strong>
                            &nbsp;
                            <span style="font-weight:bolder;color:red;">
                                <?=@$output['compare']['touser'];?>
                            </span>
                            <br>
                            <strong><?=§('label-page')?></strong> <?=@$output['compare']['totitle'];?>
                            <br>
                            <strong><?=§('label-diff')?></strong> <?=@$output['revision']['bytes'];?> bytes
                            <br>
                            <strong><?=§('label-timestamp')?></strong> <?=@$output['revision']['timestamp'];?> (UTC)
                            <br>
                            <strong><?=§('label-summary')?></strong> <?=@$output['compare']['tocomment'];?>
                            <br>
                            <strong><?=§('triage-diff')?>:</strong>
                            <a
                            href="<?=$contest['endpoint'];?>?diff=<?=@$output['revision']['diff'];?>"
                            target="_blank"
                            rel="noopener"
                            ><?=@$output['revision']['diff'];?></a> - <a
                            target="_blank"
                            rel="noopener"
                            href="https://copyvios.toolforge.org/?lang=pt&amp;project=wikipedia&amp;action=search&amp;use_engine=1&amp;use_links=1&amp;turnitin=0&amp;oldid=<?=@$output['revision']['diff'];?>"
                            ><?=§('triage-copyvio')?></a>
                        </p>
                    </div>
                    <div class="w3-container w3-light-grey w3-border w3-border-dark-grey w3-justify w3-margin-bottom">
                        <h2><?=§('triage-recenthistory')?></h2>
                        <?php foreach ($output['history'] as $oldid): ?>
                            <p class='<?=$oldid['class']?>'>
                                <strong><?=$oldid['user']?></strong>
                                <br>
                                <?=$oldid['timestamp']?>
                                <br>
                                <span class='w3-text-<?=$oldid['color']?>'><?=$oldid['bytes']?> bytes</span>
                            </p>
                        <?php endforeach; ?>
                    </div>
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
                        <?= ($contest['minimum_bytes'])
                            ? §('triage-bytes', $contest['minimum_bytes'])
                            : §('triage-indef')
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
                    <p class="w3-small">
                        <strong><?=§('triage-links')?></strong>
                        <br>
                        <a
                        href="<?=$contest['endpoint'];?>?curid=<?=$contest['official_list_pageid'];?>"
                        ><?=§('triage-list')?></a> - <a href="<?= ($contest['category_petscan'])
                            ? 'https://petscan.wmflabs.org/?psid=' . $contest['category_petscan']
                            : $contest['endpoint'] . '?curid=' . $contest['category_pageid']
                        ?>"><?=§('triage-cat')?></a>
                    </p>
                </div>
            </div>
            <div class="w3-threequarter">
                <?php if ($output['count'] == '-'): ?>
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
                    <div>
                        <h3><?=§('triage-differential')?></h3>
                        <table
                        role="presentation"
                        aria-label="Diferencial de edição"
                        class="diff diff-contentalign-left diff-editfont-monospace"
                        ><?=$output['compare']['*']?></table>
                        <hr>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </body>
</html>