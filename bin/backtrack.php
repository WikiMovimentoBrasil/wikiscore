<?php
//Protetor de login
require "protect.php";

//Conecta ao banco de dados
require "connect.php";

//Coleta lista de edições para retroceder inscrição
$backtrack_query = mysqli_query($con, "
    SELECT 
      `{$contest['name_id']}__edits`.`diff`, 
      `{$contest['name_id']}__edits`.`bytes`,
      `{$contest['name_id']}__edits`.`timestamp` AS `edit_timestamp`, 
      `{$contest['name_id']}__users`.`user`, 
      `{$contest['name_id']}__users`.`timestamp` AS `enrollment_timestamp` 
    FROM 
      `{$contest['name_id']}__edits` 
      INNER JOIN `{$contest['name_id']}__users` ON `{$contest['name_id']}__edits`.`user` = `{$contest['name_id']}__users`.`user` 
    WHERE 
      `{$contest['name_id']}__edits`.valid_user IS NULL
    ORDER BY 
      `user`, 
      `edit_timestamp`
;");

//Insere edições em uma array
while ($edit = mysqli_fetch_assoc($backtrack_query)) {
    $output["backtrack"][$edit["user"]]["enrollment_timestamp"] = $edit['enrollment_timestamp'];
    $output["backtrack"][$edit["user"]]["diffs"][] = [ "diff" => $edit['diff'], "bytes" => $edit['bytes'], 'timestamp' => $edit['edit_timestamp']];
}

//Processa informações caso formulário tenha sido submetido
if (isset($_POST['diff'])) {
    
    //Monta query para atualizar banco de dados
    $slashed_diff = addslashes($_POST['diff']);
    $slashed_username = addslashes($_SESSION['user']['user_name']);
    $time = date('Y-m-d H:i:s');
    $sql_update = "
        UPDATE 
            `{$contest['name_id']}__edits` 
        SET 
            `valid_user`    = '1',
            `obs`           = CONCAT(IFNULL(`obs`, ''), 'Backtrack: {$slashed_username} em {$time}\n')
        WHERE 
            `diff`        = '{$slashed_diff}' AND 
            `valid_user`  IS NULL;
    ";

    //Executa query e retorna o resultado para o avaliador
    $update_query = mysqli_query($con, $sql_update);
    if (mysqli_affected_rows($con) != 0) {
        $output['success']['diff'] = addslashes($_POST['diff']);
    }
}

//Exibe página
?>
<!DOCTYPE html>
<html>
    <title>Retroceder - <?=$contest['name'];?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="bin/w3.css">
    <link rel="stylesheet" type="text/css" href="bin/color.php?color=<?=@$contest['color'];?>">
    <body>
        <header class="w3-container w3-<?=$contest['theme'];?>">
            <h1>Retroceder - <?=$contest['name'];?></h1>
        </header>
        <br>
        <div class="w3-row-padding w3-content" style="max-width:700px">
            <div class="w3-container w3-margin-top w3-card-4">
                <div class="w3-container">
                    <p>Essa página lista as edições que foram feitas por usuários participantes no âmbito do wikiconcurso mas foram realizadas antes da efetivação da inscrição no Outreach Dashboard. Se necessário, clique no botão para aceitar a edição. Após a aceitação, a edição estará disponível na fila de avaliação.</p>
                </div>
            </div>
            <?php 
            foreach ($output["backtrack"] as $user => $case) {
                echo('<div class="w3-margin-top w3-card">');
                    echo("<header class='w3-container w3-{$contest['theme']}'><h1>{$user}</h1></header>");
                    echo('<div class="w3-container">');
                        echo('<ul class="w3-ul">');

                        foreach ($case["diffs"] as $diff) {
                            echo('<li class="w3-bar">');
                                echo('<div class="w3-bar-item">');
                                    echo("<span class='w3-large'><a href='{$contest['endpoint']}?diff={$diff['diff']}' target='_blank'>{$diff['diff']}</a></span><br>");
                                    echo("<span>Edição  em {$diff['timestamp']} - {$diff['bytes']} bytes</span>");
                                echo('</div>');
                                echo('<form method="post">');
                                    echo("<input type='hidden' name='diff' value='{$diff['diff']}'>");
                                    echo("<button type='submit' onclick=\"return confirm('Tem certeza?')\" class='w3-bar-item w3-right w3-button w3-section w3-green'>Aceitar edição</button>");
                                echo('</form>');
                            echo('</li>');
                        }
                        echo('</ul>');
                    echo('</div>');
                    echo("<footer class='w3-container w3-{$contest['theme']}' style='filter: hue-rotate(180deg);'><h5>Participante se inscreveu em <b>{$case['enrollment_timestamp']}</b></h5></footer>");
                echo('</div>');
            }
            ?>
        </div>
    </body>
    <?php 
    if (@array_key_exists('diff', $output['success'])) {
        if (is_null($output['success']['diff'])) { 
            echo("<script>alert('Erro ao aceitar edição');</script>");
        } else {
            echo("<script>alert('Edição aceita com sucesso!');window.location.href = window.location.href;</script>");
        }
    }
    ?>
</html>





