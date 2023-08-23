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
            
            function w3_open() {
                var mySidebar = document.getElementById("mySidebar");
                var overlayBg = document.getElementById("myOverlay");
                if (mySidebar.style.display === 'block') {
                    mySidebar.style.display = 'none';
                    overlayBg.style.display = "none";
                } else {
                    mySidebar.style.display = 'block';
                    overlayBg.style.display = "block";
                }
            }
            function w3_close() {
                document.getElementById("mySidebar").style.display = "none";
                document.getElementById("myOverlay").style.display = "none";
            }
        </script>
        <?php if (isset($output['success']['diff']) || isset($output['success']['skip'])) : ?>
            <script type="text/javascript">history.replaceState(null, document.title, location.href);</script>
        <?php endif; ?>
    </head>
    <body>
        <div class="w3-<?=$contest['theme'];?> w3-large w3-bar w3-top" style="z-index:4">
            <button class="w3-bar-item w3-button w3-hide-large w3-hover-none w3-hover-text-light-grey" onclick="w3_open();"><i class="fa fa-bars"></i> &nbsp;</button>
            <span class="w3-bar-item w3-right"><?=$contest['name'];?></span>
        </div>
        <nav class="w3-sidebar w3-collapse w3-white w3-animate-left" style="z-index:3;width:230px;" id="mySidebar">
            <br>
            <div class="w3-container w3-row">
                <div class="w3-col s4">
                    <svg
                        class="w3-margin-right"
                        width="46"
                        height="46"
                        stroke-width="1.5"
                        viewBox="0 0 24 24"
                        fill="none"
                        xmlns="http://www.w3.org/2000/svg"
                    >
                        <path
                            d="M7 18V17C7 14.2386 9.23858 12 12 12V12C14.7614 12 17 14.2386 17 17V18"
                            stroke="currentColor"
                            stroke-linecap="round"
                        />
                        <path
                            d="M12 12C13.6569 12 15 10.6569 15 9C15 7.34315 13.6569 6 12 6C10.3431 6 9 7.34315 9 9C9 10.6569 10.3431 12 12 12Z"
                            stroke="currentColor"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                        <circle
                            cx="12"
                            cy="12"
                            r="10"
                            stroke="currentColor"
                            stroke-width="1.5"
                        />
                    </svg>
                </div>
                <div class="w3-col s8 w3-bar">
                    <span>Welcome, <strong><?=ucfirst($_SESSION['user']['user_name']);?></strong></span><br>
                    <a href="#" class="w3-bar-item w3-button" style="pointer-events: none;"><i class="fa fa-key"></i></a>
                    <a href="javascript:document.getElementById('logout').submit()" class="w3-bar-item w3-button"><i class="fa-solid fa-door-open"></i></a>
                    <form method="post" id="logout" style="display: none;">
                        <input type="hidden" name="logout" value="Logout">
                    </form>
                </div>
            </div>
            <hr>
            <div class="w3-container">
                <h5><?=§('triage-panel')?></h5>
            </div>
            <div class="w3-bar-block">
                <a href="#" rel="noopener" class="w3-bar-item w3-button w3-padding-16 w3-hide-large w3-dark-grey w3-hover-black" onclick="w3_close()" title="close menu"><i class="fa fa-remove fa-fw"></i>&nbsp; Close Menu</a>
                <a href="#" rel="noopener" class="w3-bar-item w3-button w3-padding w3-blue">
                    <i class="fa-solid fa-check-to-slot"></i>&nbsp; Triage
                </a>
                <a href="index.php?lang=<?=$lang?>&contest=<?=$contest['name_id'];?>&page=counter" 
                target="_blank" rel="noopener" class="w3-bar-item w3-button w3-padding">
                    <i class="fa-solid fa-chart-line"></i>&nbsp; <?=§('counter')?>
                </a>
                <a href="index.php?lang=<?=$lang?>&contest=<?=$contest['name_id'];?>&page=modify" 
                target="_blank" rel="noopener" class="w3-bar-item w3-button w3-padding">
                    <i class="fa-solid fa-pen-to-square"></i>&nbsp; <?=§('modify')?>
                </a>
                <a href="index.php?lang=<?=$lang?>&contest=<?=$contest['name_id'];?>&page=compare" 
                target="_blank" rel="noopener" class="w3-bar-item w3-button w3-padding">
                    <i class="fa-solid fa-code-compare"></i>&nbsp; <?=§('compare')?>
                </a>
                <a href="index.php?lang=<?=$lang?>&contest=<?=$contest['name_id'];?>&page=edits" 
                target="_blank" rel="noopener" class="w3-bar-item w3-button w3-padding">
                    <i class="fa-solid fa-list-check"></i>&nbsp; <?=§('triage-evaluated')?>
                </a>
                <a href="index.php?lang=<?=$lang?>&contest=<?=$contest['name_id'];?>&page=backtrack" 
                target="_blank" rel="noopener" class="w3-bar-item w3-button w3-padding">
                    <i class="fa-solid fa-history"></i>&nbsp; <?=§('backtrack')?>
                </a>
                <a href="index.php?lang=<?=$lang?>&contest=<?=$contest['name_id'];?>&page=evaluators" 
                target="_blank" rel="noopener" class="w3-bar-item w3-button w3-padding">
                    <i class="fa-solid fa-users"></i>&nbsp; <?=§('evaluators')?>
                </a>
                <br><br>
            </div>
        </nav>
        <div class="w3-overlay w3-hide-large w3-animate-opacity" onclick="w3_close()" style="cursor:pointer" title="close side menu" id="myOverlay"></div>
        <div class="w3-row-padding w3-content w3-main" style="max-width:1400px;margin-left:230px;margin-top:43px;padding-top:16px;">
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
                                'index.php?lang=<?=$lang?>&contest=<?=$contest['name_id'];?>&page=modify&diff=<?=@$output['success']['diff'];?>',
                                '_blank'
                            );"><i class="fa-solid fa-eraser w3-medium" aria-hidden="true"></i> <?=§('triage-fix')?></button>
                        </p>
                    </div>
                <?php endif; ?>
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
                                placeholder="<?=§('triage-observation')?>">
                                <br>
                                <input
                                class="w3-button w3-border w3-block w3-red"
                                name="overwrite"
                                id="overwrite"
                                ype="button"
                                value="<?=§('triage-alterbytes')?>"
                                onclick="handleOverwriteClick('<?=@$output['revision']['bytes'];?>')">
                                <input
                                class="w3-button w3-green w3-border-green w3-border w3-block w3-margin-top"
                                type="submit"
                                value="<?=§('triage-save')?>">
                            </p>
                        </form>
                        <p>
                            <form method="post">
                                <input type="hidden" name="diff" value="<?=@$output['revision']['diff'];?>">
                                <input type="hidden" name="skip" value="true">
                                <button
                                class="w3-button w3-purple w3-border w3-block"
                                type="submit"
                                value="Pular edição"
                                <?=(isset($output['revision']['diff']))?'':'disabled';?>
                                ><?=§('triage-jump')?></button>
                            </form>
                        </p>
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
                    <div class="w3-container w3-justify w3-margin-bottom">
                        <h3><?=§('triage-details')?></h3>
                        <div class="w3-half">
                            <p style="overflow-wrap: break-word;">
                                <strong><i class="fa-solid fa-user"></i>&nbsp; <?=§('label-user')?></strong>
                                &nbsp;
                                <span style="font-weight:bolder;color:red;">
                                    <?=@$output['compare']['touser'];?>
                                </span>
                                <br>
                                <strong><i class="fa-solid fa-font"></i>&nbsp; <?=§('label-page')?></strong> <?=@$output['compare']['totitle'];?>
                                <br>
                                <strong><i class="fa-solid fa-arrow-up-9-1"></i>&nbsp; <?=§('label-diff')?></strong> <?=@$output['revision']['bytes'];?> bytes
                                <br>
                                <strong><i class="fa-regular fa-clock"></i>&nbsp; <?=§('label-timestamp')?></strong> <?=@$output['revision']['timestamp'];?> (UTC)
                            </p>
                        </div>
                        <div class="w3-half">
                            <p style="overflow-wrap: break-word;">
                                <strong><i class="fa-solid fa-thumbtack"></i>&nbsp; <?=§('triage-diff')?>:</strong>
                                <a
                                href="<?=$contest['endpoint'];?>?diff=<?=@$output['revision']['diff'];?>"
                                target="_blank"
                                rel="noopener"
                                ><?=@$output['revision']['diff'];?></a> - <a
                                target="_blank"
                                rel="noopener"
                                href="https://copyvios.toolforge.org/?lang=pt&amp;project=wikipedia&amp;action=search&amp;use_engine=1&amp;use_links=1&amp;turnitin=0&amp;oldid=<?=@$output['revision']['diff'];?>"
                                ><?=§('triage-copyvio')?></a>
                                <br>
                                <strong><i class="fa-solid fa-comment"></i>&nbsp; <?=§('label-summary')?></strong> <?=@$output['compare']['tocomment'];?>
                            </p>
                        </div>
                    </div>
                    <div class="w3-container">
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