<?php
//Protetor de login
require_once "protect.php";

//Coleta horário personalizado de consulta
if (isset($_POST['time_round'])) {
    $time_unix = strtotime($_POST['time_round']);
    $time_sql = date('Y-m-d\TH:i:s', $time_unix);
} else {
    $time_unix = time();
    $time_sql = '0';
}

//Processa redefinição de participantes
if (isset($_POST['user'])) {

    //Encerra script caso usuário não seja gestor
    if ($_SESSION['user']['user_status'] != 'G') {
        die("Ação não permitida. Não é gestor do concurso.");
    }

    //Processa query
    $reset_query = mysqli_prepare(
        $con,
        "DELETE FROM
            `{$contest['name_id']}__edits`
        WHERE
            `user` = ?"
    );
    mysqli_stmt_bind_param($reset_query, "s", $_POST['user']);
    mysqli_stmt_execute($reset_query);

    //Verifica se query ocorreu normalmente e solicita atualização do banco de dados
    if (mysqli_stmt_affected_rows($reset_query) != 0) {
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
        if (mysqli_stmt_affected_rows($refresh_query) != 0) {
            $output['success'] = true;
        }
    }
}

//Coleta lista de editores
$count_query = mysqli_query(
    $con,
    "SELECT
        `user_table`.`user`,
        IFNULL(`points`.`sum`, 0) AS `sum`,
        IFNULL(`points`.`total edits`, 0) AS `total edits`,
        IFNULL(`points`.`bytes points`, 0) AS `bytes points`,
        IFNULL(`points`.`total pictures`, 0) AS `total pictures`,
        IFNULL(`points`.`pictures points`, 0) AS `pictures points`,
        IFNULL(`points`.`total points`, 0) AS `total points`
    FROM
        (
            SELECT
                DISTINCT `{$contest['name_id']}__edits`.`user`
            FROM
                `{$contest['name_id']}__edits`
                INNER JOIN
                    `{$contest['name_id']}__users`
                ON `{$contest['name_id']}__users`.`user` = `{$contest['name_id']}__edits`.`user`
        ) AS `user_table`
        LEFT JOIN (
            SELECT
                t1.`user`,
                t1.`sum`,
                t1.`total edits`,
                t1.`bytes points`,
                t2.`total pictures`,
                t2.`pictures points`,
                (
                    t1.`bytes points` + t2.`pictures points`
                ) AS `total points`
            FROM
                (
                    SELECT
                        edits_ruled.`user`,
                        SUM(edits_ruled.`bytes`) AS `sum`,
                        SUM(edits_ruled.`valid_edits`) AS `total edits`,
                        FLOOR(
                            SUM(edits_ruled.`bytes`) / ${contest['bytes_per_points']}
                        ) AS `bytes points`
                    FROM
                        (
                            SELECT
                                `{$contest['name_id']}__edits`.`article`,
                                `{$contest['name_id']}__edits`.`user`,
                                CASE
                                    WHEN SUM(
                                        `{$contest['name_id']}__edits`.`bytes`
                                    ) > ${contest['max_bytes_per_article']}
                                    THEN ${contest['max_bytes_per_article']}
                                    ELSE SUM(`{$contest['name_id']}__edits`.`bytes`)
                                END AS `bytes`,
                                COUNT(`{$contest['name_id']}__edits`.`valid_edit`) AS `valid_edits`
                            FROM
                                `{$contest['name_id']}__edits`
                            WHERE
                                `{$contest['name_id']}__edits`.`valid_edit` IS NOT NULL AND
                                `{$contest['name_id']}__edits`.`timestamp` < (
                                    CASE
                                        WHEN '${time_sql}' = '0'
                                        THEN NOW()
                                        ELSE '${time_sql}'
                                    END
                                )
                            GROUP BY
                                `user`,
                                `article`
                            ORDER BY
                                NULL
                        ) AS edits_ruled
                    GROUP BY
                        edits_ruled.`user`
                    ORDER BY
                        NULL
                ) AS t1
                LEFT JOIN (
                    SELECT
                        `distinct`.`user`,
                        `distinct`.`article`,
                        SUM(`distinct`.`pictures`) AS `total pictures`,
                    CASE
                        WHEN ${contest['pictures_per_points']} = 0
                        THEN 0
                        ELSE FLOOR(SUM(`distinct`.`pictures`) / ${contest['pictures_per_points']})
                    END AS `pictures points`
                    FROM
                        (
                            SELECT
                                `{$contest['name_id']}__edits`.`user`,
                                `{$contest['name_id']}__edits`.`article`,
                                `{$contest['name_id']}__edits`.`pictures`,
                                `{$contest['name_id']}__edits`.`n`
                            FROM
                                `{$contest['name_id']}__edits`
                            WHERE
                                `{$contest['name_id']}__edits`.`pictures` IS NOT NULL AND
                                `{$contest['name_id']}__edits`.`timestamp` < (
                                    CASE WHEN '${time_sql}' = '0' THEN NOW() ELSE '${time_sql}' END
                                )
                            GROUP BY
                                CASE
                                    WHEN ${contest['pictures_mode']} = 0
                                    THEN `{$contest['name_id']}__edits`.`user`
                                END,
                                CASE
                                    WHEN ${contest['pictures_mode']} = 0
                                    THEN `{$contest['name_id']}__edits`.`article`
                                END,
                                CASE
                                    WHEN ${contest['pictures_mode']} = 0
                                    THEN `{$contest['name_id']}__edits`.`pictures`
                                    ELSE `{$contest['name_id']}__edits`.`n`
                                END
                        ) AS `distinct`
                    GROUP BY
                        `distinct`.`user`
                    ORDER BY
                        NULL
                ) AS t2 ON t1.`user` = t2.`user`
        ) AS `points` ON `user_table`.`user` = `points`.`user`
    ORDER BY
        `points`.`total points` DESC,
        `points`.`sum` DESC,
        `user_table`.`user` ASC
    ;"
);
if (mysqli_num_rows($count_query) == 0) { die("No users"); }


?>

<!DOCTYPE html>
<html lang="pt-br">
    <head>
        <title>Contador - <?=$contest['name'];?></title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="bin/w3.css">
        <link rel="stylesheet" type="text/css" href="bin/color.php?color=<?=@$contest['color'];?>">
    </head>
    <body>
        <header class="w3-container w3-<?=$contest['theme'];?>">
            <h1>Contador - <?=$contest['name'];?></h1>
        </header>
        <br>
        <div class="w3-container">
            <div class="w3-threequarter w3-section">
                <p class="w3-text-darkgrey w3-container">
                    Este cômputo se refere ao wikiconcurso "<?=$contest['name'];?>" e foi gerado no dia
                    <?=date('d/m/Y', $time_unix);?>, às <?=date('H:i:s', $time_unix);?> do horário UTC.
                    A ordem de classificação é realizada de acordo com o total de pontos, calculados com
                    a pontuação arredondada para baixo, sendo utilizado como critério de desempate o valor
                    da soma total de bytes adicionados e, caso ainda exista empate, por ordem alfabética.
                    Todos os usuários inscritos que editaram algum dos artigos da lista deste wikiconcurso
                    estão listados abaixo, mesmo não tendo edição válida alguma.
                </p>
            </div>
            <div class="w3-quarter w3-section">
                <form class="w3-container w3-card w3-padding" method="post">
                    <caption>
                        Caso queira obter um extrato da pontuação até determinado horário,
                        especifique no formulário abaixo.
                    </caption>
                    <input
                    class="w3-input w3-border"
                    type="datetime-local"
                    name="time_round"
                    step="1"
                    value="<?=date('Y-m-d\TH:i:s', $time_unix);?>"
                    >
                    <input class="w3-btn w3-block w3-<?=$contest['theme'];?>" type="submit">
                </form>
            </div>
            <table aria-label="Lista de participantes" class="w3-table-all w3-hoverable w3-card">
                <tr>
                    <th>Usuário</th>
                    <th>Soma de bytes</th>
                    <th>Total de edições</th>
                    <th>Pontos por bytes</th>
                    <th>Artigos com imagens</th>
                    <th>Pontos por imagens</th>
                    <th>Total de pontos</th>
                    <?php if ($_SESSION['user']["user_status"] == 'G'): ?>
                        <th>Redefinir</th>
                    <?php endif; ?>
                </tr>
                <?php while ($row = mysqli_fetch_assoc($count_query)): ?>
                    <tr>
                        <td><?=$row["user"]?></td>
                        <td><?=$row["sum"]?></td>
                        <td><?=$row["total edits"]?></td>
                        <td><?=$row["bytes points"]?></td>
                        <td><?=$row["total pictures"]?></td>
                        <td><?=$row["pictures points"]?></td>
                        <td><?=$row["total points"]?></td>
                        <?php if ($_SESSION['user']["user_status"] == 'G'): ?>
                            <td>
                                <form
                                method='post'
                                onSubmit='return confirm(
                                    "Todas as avaliações nas edições deste participante serão desfeitas. Deseja prosseguir?"
                                )'>
                                    <input type='hidden' name='user' value='<?=$row["user"]?>'>
                                    <input
                                    <?=($row["total edits"] == 0)?"disabled":""?>
                                    type='submit'
                                    class='w3-btn w3-<?=$contest["theme"]?>'
                                    value='Redefinir'>
                                </form>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endwhile; ?>
            </table>
        </div>
    </body>
    <?php if (isset($output['success'])): ?>
        <script>
            alert(
                'Edições redefinidas com sucesso! Uma nova atualização do banco de dados será realizada em breve.'
            );
            window.location.href = window.location.href;
        </script>
    <?php endif; ?>
</html>
