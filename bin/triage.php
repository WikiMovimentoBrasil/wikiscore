<?php
//Protetor de login
require_once "protect.php";

//Conecta ao banco de dados
require_once "connect.php";

//Escapa variável para uso no SQL
if (isset($_SESSION['user']['user_name'])) $slashed_username = addslashes($_SESSION['user']['user_name']);

//Processa informações caso formulário tenha sido submetido
if ($_POST) {

    //Escapa variáveis para uso no SQL
    if (isset($_POST['diff'])) $post['diff'] = addslashes($_POST['diff']);
    if (isset($_POST['overwrite'])) $post['overwrite'] = addslashes($_POST['overwrite']);

    //Atualiza edição que o avaliador tenha pulado edição
    if (isset($_POST['skip']) and isset($_POST['diff'])) {
        mysqli_query($con, "
            UPDATE
                `{$contest['name_id']}__edits`
            SET
                `by` = 'skip-{$slashed_username}'
            WHERE
                `diff`='{$post['diff']}' AND `by` = 'hold-{$slashed_username}'
        ;");
        if (mysqli_affected_rows($con) == 0) die("<br>Erro ao pular edição. Atualize a página para tentar novamente.");

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
        if (isset($_POST['overwrite'])) {

            //Busca número de bytes no banco de dados
            $query = mysqli_fetch_assoc(
                mysqli_query($con, "
                    SELECT
                        `bytes`
                    FROM
                        `{$contest['name_id']}__edits`
                    WHERE
                        `diff` = '{$post['diff']}'
                    LIMIT 1
                ;")
            );

            //Verifica se há diferença. Caso sim, altera o número de bytes e adiciona comentário
            if ($query['bytes'] != $post['overwrite']) {
                mysqli_query($con, "
                    UPDATE
                        `{$contest['name_id']}__edits`
                    SET
                        `bytes`  = '{$post['overwrite']}'
                    WHERE
                        `diff` = '{$post['diff']}';
                ");
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

        //Monta query para atualizar banco de dados
        $when = date('Y-m-d H:i:s');
        $sql_update = "
            UPDATE
                `{$contest['name_id']}__edits`
            SET
                `valid_edit`    = '{$post['valid']}',
                `pictures`      = '{$post['pic']}',
                `by`            = '{$slashed_username}',
                `when`          = '{$when}',
                `obs`           = CONCAT(IFNULL(`obs`, ''), '{$post['obs']}')
            WHERE `diff`        = '{$post['diff']}';
        ";

        //Executa query e retorna o resultado para o avaliador
        $update_query = mysqli_query($con, $sql_update);
        if (mysqli_affected_rows($con) != 0) {

            $output['success']['diff'] = addslashes($_POST['diff']);
            $output['success']['valid'] = $post['valid'];
            $output['success']['pic'] = $post['pic'];

            //Destrava edições do usuário que porventura ainda estejam travadas
            mysqli_query($con, "
                UPDATE
                    `{$contest['name_id']}__edits`
                SET
                    `by` = NULL
                WHERE
                    `by` = 'hold-{$slashed_username}'
            ;");

        }
    }
}

//Define número mínimo de bytes necessários de acordo com com configuração do concurso
$bytes = 0;
if (isset($contest['minimum_bytes'])) $bytes = $contest['minimum_bytes'];

//Converte prazo de reversão para formato compatível com SQL
$revert_time = date('Y-m-d H:i:s', strtotime("-{$contest['revert_time']} hours"));

//Conta edições faltantes
$count_query = mysqli_query($con, "
    SELECT
        COUNT(*) AS `total_count`,
        IFNULL(SUM(CASE WHEN `timestamp` < '{$revert_time}' THEN 1 ELSE 0 END), 0) AS `count`
    FROM
        `{$contest['name_id']}__edits`
    WHERE
        `reverted` IS NULL AND
        `valid_edit` IS NULL AND
        `valid_user` IS NOT NULL AND
        `by` IS NULL AND
        CASE WHEN '{$bytes}' = '-1' THEN `bytes` IS NOT NULL ELSE `bytes` > {$bytes} END
;");
$count_query = mysqli_fetch_assoc($count_query);
$output['count'] = $count_query['count'];
$output['total_count'] = $count_query['total_count'] - $count_query['count'];

//Captura horário de última edição inserida no banco de dados
$lastedit_query = mysqli_query($con, "
    SELECT
        `timestamp` AS `lastedit`
    FROM
        `{$contest['name_id']}__edits`
    ORDER BY
        `timestamp` DESC
    LIMIT
        1
;");
$output['lastedit'] = strtotime(mysqli_fetch_assoc($lastedit_query)["lastedit"]);

//Coleta edição para avaliação
$revision_query = mysqli_query($con, "
    SELECT
        `diff`,
        `bytes`,
        `user`,
        `summary`,
        `article`,
        `timestamp`
    FROM
        `{$contest['name_id']}__edits`
    WHERE
        `reverted` IS NULL AND
        `valid_edit` IS NULL AND
        `valid_user` IS NOT NULL AND
        CASE WHEN '{$bytes}' = '-1' THEN `bytes` IS NOT NULL ELSE `bytes` > {$bytes} END AND
        `by` IS NULL AND
        `timestamp` < '{$revert_time}'
    ORDER BY
        `timestamp` ASC
    LIMIT 1
;");
$output['revision'] = mysqli_fetch_assoc($revision_query);

//Trava edição para evitar que dois avaliadores avaliem a mesma edição ao mesmo tempo
if ($output['revision'] != NULL) {
    mysqli_query($con, "
        UPDATE
            `{$contest['name_id']}__edits`
        SET
            `by` = 'hold-{$slashed_username}'
        WHERE
            `diff`='{$output['revision']['diff']}'
    ;");
    if (mysqli_affected_rows($con) == 0) die("<br>Erro ao travar edição. Atualize a página para tentar novamente.");

    //Coleta informações da edição via API do MediaWiki
    $compare_api_params = [
        "action"    => "compare",
        "prop"      => "title|diff",
        "format"    => "json",
        "fromrev"   => $output['revision']['diff'],
        "torelative"=> "prev"
    ];
    $output['compare'] = json_decode(file_get_contents($contest['api_endpoint']."?".http_build_query($compare_api_params)), true)['compare'];

    //Coleta histórico recente do artigo até o início do concurso
    $history_params = [
        "action"    => "query",
        "format"    => "json",
        "prop"      => "revisions",
        "pageids"   => $output['revision']['article'],
        "rvprop"    => "timestamp|user|size|ids",
        "rvlimit"   => "max",
        "rvend"     => date('Y-m-d\TH:i:s.000\Z', $contest['start_time'])
    ];
    $history = json_decode(file_get_contents($contest['api_endpoint']."?".http_build_query($history_params)), true)["query"]["pages"];

    //Verifica situação da primeira edição.
    //Se for a primeira edição, insere pseudo-edição anterior com tamanho zero
    //Se existir edição anterior, busca na API o tamanho da edição imediatamente anterior e insere pseudo-edição com tamanho correspondente
    $history = end($history)["revisions"];
    if (end($history)["parentid"] == 0) {
        $history[] = ["size" => "0"];
    } else {
        $lastdiff_params = [
            "action"        => "compare",
            "format"        => "json",
            "fromrev"       => end($history)["parentid"],
            "torelative"    => "prev",
            "prop"          => "size"
        ];
        $lastdiff = json_decode(file_get_contents($contest['api_endpoint']."?".http_build_query($lastdiff_params)), true)["compare"];
        $history[] = ["size" => $lastdiff["fromsize"]];
    }

    //Loop para retornar o código HTML do histórico da página
    foreach ($history as $i => $edit) {
        $delta = $history[$i]['size'] - $history[$i+1]['size'];
        if ($delta < 0) {
            $delta_color = "red";
        } elseif ($delta > 0) {
            $delta_color = "green";
        } else {
            $delta_color = "grey";
        }
        $timestamp = date('d/m/Y H:i:s (\U\T\C)', strtotime($edit['timestamp']));
        $output['history'][] = "
            <p class='w3-small'>
                <strong>{$edit['user']}</strong>
                <br>
                {$timestamp}
                <br>
                <span class='w3-text-{$delta_color}'>{$delta} bytes</span>
            </p>\n";
    }

    //Remove pseudo-edição
    array_pop($output['history']);
    $output['history'] = implode("", $output['history']);

}

//Encerra conexão
mysqli_close($con);

//Exibe edição e formulário de avaliação
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <title><?=$contest['name'];?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta charset="UTF-8">
    <link rel="stylesheet" href="bin/w3.css">
    <link rel="stylesheet" type="text/css" href="bin/color.php?color=<?=@$contest['color'];?>">
    <link rel="stylesheet" href="bin/diff.css">
    <link rel="stylesheet" href="https://tools-static.wmflabs.org/cdnjs/ajax/libs/font-awesome/6.2.0/css/all.css">
    <body>
        <div class="w3-<?=$contest['theme'];?> w3-padding-32 w3-margin-bottom w3-center">
            <h1 class="w3-jumbo"><?=$contest['name'];?></h1>
        </div>
        <div class="w3-row-padding w3-content" style="max-width:1400px">
            <div class="w3-quarter">
                <div 
                class="w3-container w3-light-grey w3-border w3-border-dark-grey w3-margin-bottom" 
                style="display:<?=(isset($output['success']['diff']))?'initial':'none';?>;"
                >
                    <h2>Última avaliação</h2>
                    <p>
                        Diff: <a href="<?=$contest['endpoint'];?>?diff=<?=@$output['success']['diff'];?>" target="_blank"><?=@$output['success']['diff'];?></a>
                    </p>
                    <p>
                        Edição válida: <?php if(@$output['success']['valid']){
                            echo '<i class="fa-regular w3-text-green fa-circle-check" aria-hidden="true"></i> Sim';
                        } else {
                            echo '<i class="fa-regular w3-text-red fa-circle-xmark" aria-hidden="true"></i> Não';
                        }?>
                    </p>
                    <p>
                        Com imagem: <?php if(@$output['success']['pic']){
                            echo '<i class="fa-regular w3-text-green fa-circle-check" aria-hidden="true"></i> Sim';
                        } else {
                            echo '<i class="fa-regular w3-text-red fa-circle-xmark" aria-hidden="true"></i> Não';
                        }?>
                    </p>
                    <p>
                        <button 
                        class="w3-button w3-border-purple w3-purple w3-border w3-block w3-small" 
                        type="button" 
                        onclick="window.open('index.php?contest=<?=$contest['name_id'];?>&page=modify&diff=<?=@$output['success']['diff'];?>', '_blank');"
                        >
                        <i class="fa-solid fa-eraser w3-medium" aria-hidden="true"></i> Corrigir</button>
                    </p>
                </div>
                <div class="w3-container w3-light-grey w3-border w3-border-dark-grey w3-margin-bottom">
                    <h2>Painel</h2>
                    <div class="w3-container">
                        <div style="<?php if(!isset($output['revision']['timestamp'])) echo 'display:none;';?>">
                            <h6 class="w3-center">Você está avaliando uma edição do dia</h6>
                            <h4 class="w3-center"><?=@substr($output['revision']['timestamp'], 0, 10);?></h4>
                        </div>
                            <div class="w3-row">
                                <div class="w3-half">
                                    <h6 class="w3-center">Edições<br>para avaliar</h6>
                                    <h1 class="w3-center"><?=$output['count'];?></h1>
                                </div>
                                <div class="w3-half">
                                    <h6 class="w3-center">Edições<br>em espera</h6>
                                    <h1 class="w3-center"><?=$output['total_count'];?></h1>
                                </div>
                            </div>
                        <br>
                    </div>

                    <div class="w3-container w3-margin-bottom">
                        <div class="w3-row">
                            <div class="w3-half">
                                <button class="w3-button w3-<?=$contest['theme'];?> w3-border w3-block w3-small" style="filter: hue-rotate(40deg);" type="button" onclick="window.open('index.php?contest=<?=$contest['name_id'];?>&page=counter', '_blank');">
                                    <i class="fa-solid fa-chart-line w3-xxlarge" aria-hidden="true"></i><br>Contador
                                </button>
                            </div>
                            <div class="w3-half">
                                <button class="w3-button w3-<?=$contest['theme'];?> w3-border w3-block w3-small" style="filter: hue-rotate(80deg);" type="button" onclick="window.open('index.php?contest=<?=$contest['name_id'];?>&page=modify', '_blank');">
                                    <i class="fa-solid fa-pen-to-square w3-xxlarge" aria-hidden="true"></i><br>Modificar
                                </button>
                            </div>
                        </div>
                        <div class="w3-row">
                            <div class="w3-half">
                                <button class="w3-button w3-<?=$contest['theme'];?> w3-border w3-block w3-small" style="filter: hue-rotate(120deg);" type="button" onclick="window.open('index.php?contest=<?=$contest['name_id'];?>&page=compare', '_blank');">
                                    <i class="fa-solid fa-code-compare w3-xxlarge" aria-hidden="true"></i><br>Comparador
                                </button>
                            </div>
                            <div class="w3-half">
                                <button class="w3-button w3-<?=$contest['theme'];?> w3-border w3-block w3-small" style="filter: hue-rotate(160deg);" type="button" onclick="window.open('index.php?contest=<?=$contest['name_id'];?>&page=edits', '_blank');">
                                    <i class="fa-solid fa-list-check w3-xxlarge" aria-hidden="true"></i><br>Avaliadas
                                </button>
                            </div>
                        </div>
                        <div class="w3-row">
                            <div class="w3-half">
                                <button class="w3-button w3-<?=$contest['theme'];?> w3-border w3-block w3-small" style="filter: hue-rotate(200deg);" type="button" onclick="window.open('index.php?contest=<?=$contest['name_id'];?>&page=backtrack', '_blank');">
                                    <i class="fa-solid fa-history w3-xxlarge" aria-hidden="true"></i><br>Retroceder
                                </button>
                            </div>
                            <div class="w3-half">
                                <button class="w3-button w3-<?=$contest['theme'];?> w3-border w3-block w3-small" style="filter: hue-rotate(240deg);" type="button" onclick="window.open('index.php?contest=<?=$contest['name_id'];?>&page=evaluators', '_blank');">
                                    <i class="fa-solid fa-users w3-xxlarge" aria-hidden="true"></i><br>Avaliadores
                                </button>
                            </div>
                        </div>
                        <div class="w3-row">
                            <div class="w3-half">
                                <form method="post">
                                    <input type="hidden" name="diff" value="<?=@$output['revision']['diff'];?>">
                                    <input type="hidden" name="skip" value="true">
                                    <button class="w3-button w3-<?=$contest['theme'];?> w3-border w3-block w3-small" style="filter: hue-rotate(280deg);" type="submit" value="Pular edição">
                                        <i class="fa-solid fa-forward w3-xxlarge" aria-hidden="true"></i><br>Pular
                                    </button>
                                </form>
                            </div>
                            <div class="w3-half">
                                <form method="post">
                                    <button class="w3-button w3-<?=$contest['theme'];?> w3-border w3-block w3-small" style="filter: hue-rotate(320deg);" type="submit" name="logout" value="Logout">
                                        <i class="fa-solid fa-door-open w3-xxlarge" aria-hidden="true"></i><br>Sair
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <div <?php if(!isset($output['revision']['timestamp'])) echo 'style="display:none;"';?>>
                    <div class="w3-container w3-light-grey w3-border w3-border-dark-grey w3-margin-bottom">
                        <h2>Avaliação</h2>
                        <form method="post">
                            <input type="hidden" name="diff" value="<?=@$output['revision']['diff'];?>">
                            <div class="w3-container w3-cell w3-half">
                                <p>Edição válida?</p>
                                <input class="w3-radio w3-section" type="radio" id="valid-sim" name="valid" value="sim" onclick="document.getElementById('obs').required = false" required>
                                <label for="valid-sim">Sim</label><br>
                                <input class="w3-radio w3-section" type="radio" id="valid-nao" name="valid" value="nao" onclick="document.getElementById('obs').required = true" required>
                                <label for="valid-nao">Não</label><br><br>
                            </div>
                            <div class="w3-container w3-cell w3-half">
                                <p>Imagem?</p>
                                <?php if ($contest['pictures_mode'] == 2) {
                                    echo '
                                        <input class="w3-input w3-border" type="number" id="pic" name="pic" value="0" min="0" max="9" required>
                                        <label for="pic">Quantidade</label><br><br>
                                    ';
                                } else {
                                    echo '
                                        <input class="w3-radio w3-section" type="radio" id="pic-sim" name="pic" value="sim" required>
                                        <label for="pic-sim">Sim</label><br>
                                        <input class="w3-radio w3-section" type="radio" id="pic-nao" name="pic" value="nao" required>
                                        <label for="pic-nao">Não</label><br><br>
                                    ';
                                }
                                ?>
                            </div>
                            <p>
                                <input class="w3-input w3-border" name="obs" id="obs" type="text" placeholder="Observação">
                                <br>
                                <input class="w3-button w3-border w3-block w3-red" name="overwrite" id="overwrite" type="button" value="Alterar bytes" onclick="
                                    document.getElementById('overwrite').removeAttribute('value');
                                    document.getElementById('overwrite').type = 'number';
                                    document.getElementById('overwrite').className = 'w3-input w3-border';
                                    document.getElementById('overwrite').value = '<?=@$output['revision']['bytes'];?>';
                                    document.getElementById('overwrite').removeAttribute('onclick');
                                    document.getElementById('overwrite').removeAttribute('id');
                                    document.getElementById('obs').required = true;
                                ">
                                <input class="w3-button w3-green w3-border-green w3-border w3-block w3-margin-top" type="submit" value="Salvar">
                            </p>
                        </form>
                    </div>
                    <div class="w3-container w3-light-grey w3-border w3-border-dark-grey w3-justify w3-margin-bottom">
                        <h2>Detalhes da edição</h2>
                        <p style="overflow-wrap: break-word;">
                            <strong>Usuário:</strong>
                            &nbsp;
                            <span style="font-weight:bolder;color:red;">
                                <?=@$output['revision']['user'];?>
                            </span>
                            <br>
                            <strong>Artigo:</strong> <?=@$output['compare']['totitle'];?>
                            <br>
                            <strong>Diferença:</strong> <?=@$output['revision']['bytes'];?> bytes
                            <br>
                            <strong>Horário:</strong> <?=@$output['revision']['timestamp'];?> (UTC)
                            <br>
                            <strong>Sumário:</strong> <?=@$output['revision']['summary'];?>
                            <br>
                            <strong>Diff:</strong>
                            <a href="<?=$contest['endpoint'];?>?diff=<?=@$output['revision']['diff'];?>" target="_blank"><?=@$output['revision']['diff'];?></a> - <a target="_blank" href="https://copyvios.toolforge.org/?lang=pt&amp;project=wikipedia&amp;action=search&amp;use_engine=1&amp;use_links=1&amp;turnitin=0&amp;oldid=<?=@$output['revision']['diff'];?>">Copyvio Detector</a>
                        </p>
                    </div>
                    <div class="w3-container w3-light-grey w3-border w3-border-dark-grey w3-justify w3-margin-bottom">
                        <h2>Histórico recente</h2>
                        <?=$output['history'];?>
                    </div>
                </div>
                <div class="w3-container w3-light-grey w3-border w3-border-dark-grey w3-justify w3-margin-bottom">
                    <h2>Informações gerais</h2>
                    <p class="w3-small">
                        <strong>Nome do wikiconcurso</strong>
                        <br>
                        <?=$contest['name'];?>
                    </p>
                    <p class="w3-small">
                        <strong>Nome do atual avaliador</strong>
                        <br>
                        <?=ucfirst($_SESSION['user']['user_name']);?>
                    </p>
                    <p class="w3-small">
                        <strong>Horário de início do wikiconcurso</strong>
                        <br>
                        <?=date('d/m/Y H:i:s (\U\T\C)', $contest['start_time']);?>
                    </p>
                    <p class="w3-small">
                        <strong>Horário de término do wikiconcurso</strong>
                        <br>
                        <?=date('d/m/Y H:i:s (\U\T\C)', $contest['end_time']);?>
                    </p>
                    <p class="w3-small">
                        <strong>Última atualização do banco de dados</strong>
                        <br>
                        <?=date('d/m/Y H:i:s (\U\T\C)', $output['lastedit']);?>
                    </p>
                    <p class="w3-small">
                        <strong>Delay no registro das edições</strong>
                        <br>
                        <?=$contest['revert_time'];?> horas
                    </p>
                </div>
            </div>
            <div class="w3-threequarter">
                <div <?php if(!isset($output['compare']['*'])) echo 'style="display:none;"'; ?>>
                    <h3>Diferencial de edição</h3>
                    <table class="diff diff-contentalign-left diff-editfont-monospace" style="word-wrap: break-word;white-space: pre-wrap;word-break: break-word;">
                        <?php print_r(@$output['compare']['*']); ?>
                    </table>
                    <hr>
                </div>
            </div>
        </div>
    </body>
</html>