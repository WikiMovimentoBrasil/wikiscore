<?php
//Protetor de login
require_once "protect.php";

//Processa informações caso formulário tenha sido submetido
if ($_POST) {

    //Escapa diff inserido no formulário
    if (!isset($_POST['diff'])) { die("Diff não fornecido!"); }
    $post['diff'] = addslashes($_POST['diff']);

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

    //Processa observação inserida no formulário
    if (!isset($_POST['obs'])) {
        $post['obs'] = '';
    } else {
        $post['obs'] = addslashes($_POST['obs']);
    }

    //Busca número de bytes e nome do avaliador no banco de dados
    $evaluated_query = mysqli_prepare(
        $con,
        "SELECT
            `bytes`,
            `by`
        FROM
            `{$contest['name_id']}__edits`
        WHERE
            `diff` = ?
        LIMIT 1"
    );
    mysqli_stmt_bind_param($evaluated_query, "i", $_POST['diff']);
    mysqli_stmt_execute($evaluated_query);
    $query = mysqli_fetch_assoc(mysqli_stmt_get_result($evaluated_query));

    //Verifica se diff pertence ao avaliador atual ou o usuário atual é o gestor do concurso
    if ($query['by'] == $_SESSION['user']['user_name'] || $_SESSION['user']['user_status'] == 'G') {

        //Define timestamp
        $time = date('Y-m-d H:i:s');
        $bytes = $query['bytes'];

        //Verifica se houve mudança da quantidade de bytes e gera comentário de acordo
        $obs  = "Modif: por {$_SESSION['user']['user_name']} ";
        if (isset($_POST['overwrite']) && $query['bytes'] != $_POST['overwrite']) {
            $obs .= "de {$query['bytes']} para {$post['overwrite']} ";
            $bytes = $_POST['overwrite'];
        }
        $obs .= "em {$time} com justificativa \"{$post['obs']}\"\n";


        //Prepara query
        $update_statement = "
            UPDATE
                `{$contest['name_id']}__edits`
            SET
                `bytes`         = ?,
                `valid_edit`    = ?,
                `pictures`      = ?,
                `obs`           = CONCAT(IFNULL(`obs`, ''), ?)
            WHERE `diff` = ?";
        $update_query = mysqli_prepare($con, $update_statement);
        mysqli_stmt_bind_param(
            $update_query,
            "iiisi",
            $bytes,
            $post['valid'],
            $post['pic'],
            $obs,
            $_POST['diff']
        );

        //Executa query e retorna o resultado para o reavaliador
        mysqli_stmt_execute($update_query);
        if (mysqli_stmt_affected_rows($update_query) != 0) {
            $output['success']['diff'] = addslashes($_POST['diff']);
        }

    //Caso o avaliador não seja autorizado, define diff do resultado como null
    } else {
        $output['success']['diff'] = null;
    }
}

//Carrega informações sobre o diff inserido no formulário
if (isset($_GET['diff'])) {

    //Coleta edição
    $revision_query = mysqli_prepare(
        $con,
        "SELECT
            *
        FROM
            `{$contest['name_id']}__edits`
        WHERE
            `diff`      = ?
        LIMIT 1"
    );
    mysqli_stmt_bind_param($revision_query, "i", $_GET['diff']);
    mysqli_stmt_execute($revision_query);
    $revision_result = mysqli_stmt_get_result($revision_query);
    $output['revision'] = mysqli_fetch_assoc($revision_result);

    //Coleta informações da edição via API do MediaWiki, caso a diff esteja no banco de dados
    if (isset($output['revision']['diff'])) {
        $compare_api_params = [
            "action"    => "compare",
            "prop"      => "title|diff|comment|user",
            "format"    => "php",
            "fromrev"   => $_GET['diff'],
            "torelative"=> "prev"
        ];
        $output['compare'] = unserialize(
            file_get_contents($contest['api_endpoint']."?".http_build_query($compare_api_params))
        )['compare'];
    } else {
        $output['compare'] = false;
    }
}

//Encerra conexão
mysqli_close($con);

//Exibe edição e formulário de avaliação
?>
<!DOCTYPE html>
<html lang="pt-br">
    <head>
        <title><?=§('modify')?> - <?=$contest['name'];?></title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="bin/w3.css">
        <link rel="stylesheet" type="text/css" href="bin/color.php?color=<?=@$contest['color'];?>">
        <link rel="stylesheet" href="bin/diff.css">
    </head>
    <body>
        <?php require_once "sidebar.php"; ?>
        <div class="w3-row-padding w3-content w3-main" style="max-width:1400px;margin-top:43px;padding-top:16px;">
            <div class="w3-container w3-quarter w3-margin-top">
                <form class="w3-container w3-card w3-margin-bottom" id="modify" method="get">
                    <h2><?=§('modify-consult')?></h2>
                    <input type="hidden" name="contest" value=<?=$contest['name_id'];?>>
                    <input type="hidden" name="page" value="modify">
                    <p>
                        <input
                        class="w3-input w3-border"
                        type="number"
                        name="diff"
                        value="<?=htmlspecialchars(@$_GET['diff']);?>"
                        required
                        >
                        <label><?=§('modify-diff')?></label>
                    </p>
                    <p>
                        <button
                        class="w3-button w3-section w3-green w3-ripple"
                        style="width:100%"
                        ><?=§('modify-load')?></button>
                    </p>
                </form>
                <form
                class="w3-container w3-light-grey w3-border w3-border-dark-grey w3-margin-bottom"
                id="modify"
                method="post"
                style="display:<?php
                    if (
                        $_SESSION['user']['user_name']   == @$output['revision']['by'] ||
                        $_SESSION['user']['user_status'] == 'G'
                    ) {
                        echo 'block';
                    } else {
                        echo 'none';
                    }
                    ?>"
                >
                    <h2><?=§('modify-reavaluate')?></h2>
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
                        required
                        >
                        <label for="valid-sim"><?=§('yes')?></label><br>
                        <input
                        class="w3-radio w3-section"
                        type="radio"
                        id="valid-nao"
                        name="valid"
                        value="nao"
                        onclick="document.getElementById('obs').required = true" required
                        >
                        <label for="valid-nao"><?=§('no')?></label><br><br>
                    </div>
                    <div class="w3-container w3-cell w3-half">
                        <p><?=§('withimage')?></p>
                        <?php if ($contest['pictures_mode'] == 2): ?>
                            <input
                            class="w3-input w3-border"
                            type="number"
                            id="pic"
                            name="pic"
                            value="0"
                            min="0"
                            max="9"
                            required>
                            <label for="pic"><?=§('quantity')?></label><br><br>
                        <?php else: ?>
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
                        placeholder="Observação"
                        required
                        >
                        <br>
                        <input
                        class="w3-button w3-border w3-block w3-red"
                        name="overwrite"
                        id="overwrite"
                        type="button"
                        value="Alterar bytes"
                        onclick="
                            document.getElementById('overwrite').removeAttribute('value');
                            document.getElementById('overwrite').type = 'number';
                            document.getElementById('overwrite').className = 'w3-input w3-border';
                            document.getElementById('overwrite').value = '<?=@$output['revision']['bytes'];?>';
                            document.getElementById('overwrite').removeAttribute('onclick');
                            document.getElementById('overwrite').removeAttribute('id');
                            document.getElementById('obs').required = true;
                        ">
                        <input
                        class="w3-button w3-orange w3-border-orange w3-border w3-block w3-margin-top"
                        type="submit"
                        value="<?=§('modify')?>"
                        >
                    </p>
                </form>
                <div
                class="w3-container w3-light-grey w3-border w3-border-dark-grey w3-margin-bottom"
                style="display: <?=(isset($output['compare']['*']))?'block':'none';?>;"
                >
                    <h2><?=§('modify-diffstats')?></h2>
                    <ul class="w3-ul w3-margin-bottom">
                        <li><?=§('modify-label-edition')?><br>
                            <a
                            href="<?=$contest['endpoint'];?>?diff=<?=@$output['revision']['diff'];?>"
                            target="_blank"
                            rel="noopener"
                            >
                                <?=@$output['revision']['diff'];?>
                            </a>
                        </li>
                        <li><?=§('modify-label-curid')?><br>
                            <a
                            href="<?=$contest['endpoint'];?>?curid=<?=@$output['revision']['article'];?>"
                            target="_blank"
                            rel="noopener"
                            >
                                <?=@$output['revision']['article'];?>
                            </a>
                        </li>
                        <li><?=§('label-timestamp')?><br><?=(@$output['revision']['timestamp']);?></li>
                        <li><?=§('label-user')?><br><?=(@$output['compare']['touser']);?></li>
                        <li><?=§('modify-label-bytes')?><br><?=(@$output['revision']['bytes']);?></li>
                        <li><?=§('label-summary')?><br><?=(@$output['compare']['tocomment']);?>&nbsp;</li>
                        <li><?=§('modify-label-newpage')?><br><?=(@$output['revision']['new_page'])?"Sim":"Não";?></li>
                        <li><?=§('modify-label-valid')?><br><?=(@$output['revision']['valid_edit'])?"Sim":"Não";?></li>
                        <li><?=§('modify-label-enrolled')?><br><?=(@$output['revision']['valid_user'])?"Sim":"Não";?></li>
                        <li><?=§('modify-label-withimage')?><br><?php
                            if ($contest['pictures_mode'] == 2) {
                                echo @$output['revision']['pictures'];
                            } else {
                                echo ($output['revision']['pictures'])?§('yes'):§('no');
                            }
                        ?></li>
                        <li><?=§('modify-label-reverted')?><br><?=(@$output['revision']['reverted'])?§('yes'):§('no');?></li>
                        <li><?=§('modify-label-evaluator')?><br><?=@$output['revision']['by'];?></li>
                        <li><?=§('modify-label-evaltimestamp')?><br><?=@$output['revision']['when'];?></li>
                        <li><?=§('modify-label-comment')?><br><?=@$output['revision']['obs'];?>&nbsp;</li>
                    </ul>
                </div>
            </div>
            <div class="w3-threequarter">
                <div style="display:<?=(isset($output['compare']['*']))?'block':'none';?>">
                    <h3><?=§('modify-showdiff')?></h3>
                    <table
                    role="presentation"
                    aria-label="<?=§('modify-showdiff')?>"
                    class="diff diff-contentalign-left diff-editfont-monospace"
                    >
                        <?php print_r(@$output['compare']['*']); ?>
                    </table>
                    <hr>
                </div>
                <?php if (isset($output['compare']) && $output['compare'] === false): ?>
                    <script>alert('<?=§('modify-notfound')?>');</script>
                <?php endif; ?>
                <?php if (@array_key_exists('diff', $output['success'])): ?>
                    <?php if (is_null($output['success']['diff'])): ?>
                        <script>alert('<?=§('modify-denied')?>');</script>
                    <?php else: ?>
                        <script>alert('<?=§('modify-success')?>');</script>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </body>
</html>
