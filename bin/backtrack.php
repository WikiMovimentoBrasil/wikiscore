<?php
//Protetor de login
require_once "protect.php";

//Coleta lista de edições para retroceder inscrição
$backtrack_statement = "
    SELECT
        `{$contest['name_id']}__edits`.`diff`,
        `{$contest['name_id']}__edits`.`bytes`,
        `{$contest['name_id']}__edits`.`timestamp` AS `edit_timestamp`,
        `{$contest['name_id']}__users`.`user`,
        `{$contest['name_id']}__users`.`timestamp` AS `enrollment_timestamp`
    FROM
        `{$contest['name_id']}__edits`
        INNER JOIN
            `{$contest['name_id']}__users`
        ON
            `{$contest['name_id']}__edits`.`user_id` = `{$contest['name_id']}__users`.`local_id`
    WHERE
        `{$contest['name_id']}__edits`.`valid_user` IS NULL AND
        `{$contest['name_id']}__edits`.`reverted` IS NULL
    ORDER BY
        `user`,
        `edit_timestamp`
";
$backtrack_query = mysqli_prepare($con, $backtrack_statement);
mysqli_stmt_execute($backtrack_query);
$backtrack_result = mysqli_stmt_get_result($backtrack_query);
mysqli_stmt_close($backtrack_query);

//Insere edições em uma array
while ($edit = mysqli_fetch_assoc($backtrack_result)) {
    $output["backtrack"][$edit["user"]]["enrollment_timestamp"] = $edit['enrollment_timestamp'];
    $output["backtrack"][$edit["user"]]["diffs"][] = [
        "diff" => $edit['diff'],
        "bytes" => $edit['bytes'],
        'timestamp' => $edit['edit_timestamp']
    ];
}

//Processa informações caso formulário tenha sido submetido
if (isset($_POST['diff'])) {

    //Monta query para atualizar banco de dados
    $now = date('Y-m-d H:i:s');
    $update_statement = "
        UPDATE
            `{$contest['name_id']}__edits`
        SET
            `valid_user`    = '1',
            `obs`           = CONCAT(
                IFNULL(`obs`, ''),
                'Backtrack: ',
                ?,
                ' em ',
                ?,
                '\n'
            )
        WHERE
            `diff`        = ? AND
            `valid_user`  IS NULL
    ";
    $update_query = mysqli_prepare($con, $update_statement);
    mysqli_stmt_bind_param(
        $update_query,
        "ssi",
        $_SESSION['user']['user_name'],
        $now,
        $_POST['diff']
    );

    //Executa query
    mysqli_stmt_execute($update_query);
    if (mysqli_stmt_affected_rows($update_query) != 0) {
        $output['success']['diff'] = addslashes($_POST['diff']);
    }
    mysqli_stmt_close($update_query);
    mysqli_close($con);
}

//Exibe página
?>
<!DOCTYPE html>
<html lang="pt-br">
    <head>
        <title>Retroceder - <?=$contest['name'];?></title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="bin/w3.css">
        <link rel="stylesheet" type="text/css" href="bin/color.php?color=<?=@$contest['color'];?>">
    </head>
    <body>
        <header class="w3-container w3-<?=$contest['theme'];?>">
            <h1>Retroceder - <?=$contest['name'];?></h1>
        </header>
        <br>
        <div class="w3-row-padding w3-content" style="max-width:700px">
            <div class="w3-container w3-margin-top w3-card-4">
                <div class="w3-container">
                    <p>
                        Essa página lista as edições que foram feitas por usuários participantes no âmbito do
                        wikiconcurso mas foram realizadas antes da efetivação da inscrição no Outreach Dashboard.
                        Se necessário, clique no botão para aceitar a edição.
                        Após a aceitação, a edição estará disponível na fila de avaliação.
                    </p>
                </div>
            </div>
            <?php foreach ($output["backtrack"] ?? array() as $user => $case): ?>
                <div class="w3-margin-top w3-card">
                    <header class='w3-container w3-<?=$contest['theme']?>'><h1><?=$user?></h1></header>
                    <div class="w3-container">
                        <ul class="w3-ul">
                            <?php foreach ($case["diffs"] as $diff): ?>
                                <li class="w3-bar">
                                    <div class="w3-bar-item">
                                        <span class="w3-large">
                                            <a
                                            href='<?=$contest['endpoint']?>?diff=<?=$diff['diff']?>'
                                            target='_blank'
                                            rel="noopener"
                                            ><?=$diff['diff']?></a>
                                        </span>
                                        <br>
                                        <span>Edição em <?=$diff['timestamp']?> com <?=$diff['bytes']?> bytes</span>
                                    </div>
                                    <form method="post">
                                        <input type='hidden' name='diff' value='<?=$diff['diff']?>'>
                                        <button
                                        type='submit'
                                        onclick="return confirm('Tem certeza?')"
                                        class='w3-bar-item w3-right w3-button w3-section w3-green'
                                        >Aceitar edição</button>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <footer class='w3-container w3-<?=$contest['theme']?>' style='filter: hue-rotate(180deg);'>
                        <h5>Participante se inscreveu em <strong><?=$case['enrollment_timestamp']?></strong></h5>
                    </footer>
                </div>
            <?php endforeach; ?>
        </div>
    </body>
    <?php if (@array_key_exists('diff', $output['success'])): ?>
        <?php if (is_null($output['success']['diff'])): ?>
            <script>alert('Erro ao aceitar edição');</script>
        <?php else: ?>
            <script>
                alert('Edição aceita com sucesso!');
                window.location.href = window.location.href;
            </script>
        <?php endif; ?>
    <?php endif; ?>
</html>
