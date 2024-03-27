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
if (isset($_POST['user_id'])) {

    //Encerra script caso usuário não seja gestor
    if ($_SESSION['user']['user_status'] != 'G') {
        die(§('counter-denied'));
    }

    //Processa query
    $reset_query = mysqli_prepare(
        $con,
        "DELETE FROM
            `{$contest['name_id']}__edits`
        WHERE
            `user_id` = ?"
    );
    mysqli_stmt_bind_param($reset_query, "s", $_POST['user_id']);
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
        `user_table`.`user_id`,
        IFNULL(`points`.`sum`, 0) AS `sum`,
        IFNULL(`points`.`total edits`, 0) AS `total edits`,
        IFNULL(`points`.`bytes points`, 0) AS `bytes points`,
        IFNULL(`points`.`total pictures`, 0) AS `total pictures`,
        IFNULL(`points`.`pictures points`, 0) AS `pictures points`,
        IFNULL(`points`.`total points`, 0) AS `total points`
    FROM
        (
            SELECT
                DISTINCT `{$contest['name_id']}__edits`.`user_id`,
                `{$contest['name_id']}__users`.`user`
            FROM
                `{$contest['name_id']}__edits`
                INNER JOIN
                    `{$contest['name_id']}__users`
                ON `{$contest['name_id']}__users`.`local_id` = `{$contest['name_id']}__edits`.`user_id`
        ) AS `user_table`
        LEFT JOIN (
            SELECT
                t1.`user_id`,
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
                        edits_ruled.`user_id`,
                        SUM(edits_ruled.`bytes`) AS `sum`,
                        SUM(edits_ruled.`valid_edits`) AS `total edits`,
                        FLOOR(
                            SUM(edits_ruled.`bytes`) / {$contest['bytes_per_points']}
                        ) AS `bytes points`
                    FROM
                        (
                            SELECT
                                `{$contest['name_id']}__edits`.`article`,
                                `{$contest['name_id']}__edits`.`user_id`,
                                CASE
                                    WHEN SUM(
                                        `{$contest['name_id']}__edits`.`bytes`
                                    ) > {$contest['max_bytes_per_article']}
                                    THEN {$contest['max_bytes_per_article']}
                                    ELSE SUM(`{$contest['name_id']}__edits`.`bytes`)
                                END AS `bytes`,
                                COUNT(`{$contest['name_id']}__edits`.`valid_edit`) AS `valid_edits`
                            FROM
                                `{$contest['name_id']}__edits`
                            WHERE
                                `{$contest['name_id']}__edits`.`valid_edit` = '1' AND
                                `{$contest['name_id']}__edits`.`timestamp` < (
                                    CASE
                                        WHEN '{$time_sql}' = '0'
                                        THEN NOW()
                                        ELSE '{$time_sql}'
                                    END
                                )
                            GROUP BY
                                `user_id`,
                                `article`
                            ORDER BY
                                NULL
                        ) AS edits_ruled
                    GROUP BY
                        edits_ruled.`user_id`
                    ORDER BY
                        NULL
                ) AS t1
                LEFT JOIN (
                    SELECT
                        `distinct`.`user_id`,
                        `distinct`.`article`,
                        SUM(`distinct`.`pictures`) AS `total pictures`,
                    CASE
                        WHEN {$contest['pictures_per_points']} = 0
                        THEN 0
                        ELSE FLOOR(SUM(`distinct`.`pictures`) / {$contest['pictures_per_points']})
                    END AS `pictures points`
                    FROM
                        (
                            SELECT
                                `{$contest['name_id']}__edits`.`user_id`,
                                `{$contest['name_id']}__edits`.`article`,
                                `{$contest['name_id']}__edits`.`pictures`,
                                `{$contest['name_id']}__edits`.`n`
                            FROM
                                `{$contest['name_id']}__edits`
                            WHERE
                                `{$contest['name_id']}__edits`.`pictures` IS NOT NULL AND
                                `{$contest['name_id']}__edits`.`timestamp` < (
                                    CASE WHEN '{$time_sql}' = '0' THEN NOW() ELSE '{$time_sql}' END
                                )
                            GROUP BY
                                CASE
                                    WHEN {$contest['pictures_mode']} = 0
                                    THEN `{$contest['name_id']}__edits`.`user_id`
                                END,
                                CASE
                                    WHEN {$contest['pictures_mode']} = 0
                                    THEN `{$contest['name_id']}__edits`.`article`
                                END,
                                CASE
                                    WHEN {$contest['pictures_mode']} = 0
                                    THEN `{$contest['name_id']}__edits`.`pictures`
                                    ELSE `{$contest['name_id']}__edits`.`n`
                                END
                        ) AS `distinct`
                    GROUP BY
                        `distinct`.`user_id`
                    ORDER BY
                        NULL
                ) AS t2 ON t1.`user_id` = t2.`user_id`
        ) AS `points` ON `user_table`.`user_id` = `points`.`user_id`
    ORDER BY
        `points`.`total points` DESC,
        `points`.`sum` DESC,
        `user_table`.`user` ASC
    ;"
);




?>

<!DOCTYPE html>
<html lang="pt-br">
    <head>
        <title><?=§('counter')?> - <?=$contest['name'];?></title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="bin/w3.css">
        <link rel="stylesheet" type="text/css" href="bin/color.php?color=<?=@$contest['color'];?>">
        <?php if (isset($output['success'])) : ?>
            <script type="text/javascript">history.replaceState(null, document.title, location.href);</script>
        <?php endif; ?>
    </head>
    <body>
        <?php require_once "sidebar.php"; ?>
        <div class="w3-container" style="margin-top:43px;padding-top:16px;">
            <div class="w3-threequarter w3-section">
                <p class="w3-text-darkgrey w3-container">
                    <?=§('counter-about',$contest['name'],date('Y/m/d',$time_unix),date('H:i:s',$time_unix))?>
                    <?=§('counter-description')?>
                </p>
            </div>
            <div class="w3-quarter w3-section">
                <form class="w3-container w3-card w3-padding" method="post">
                    <caption>
                        <?=§('counter-uptotime')?>
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
        </div>
        <?php if (time() > $contest['start_time']) : ?>
            <?php require_once "stats.php"; ?>
            <div class="w3-container">
                <table aria-label="Lista de participantes" class="w3-table-all w3-hoverable w3-card">
                    <tr>
                        <th><?=§('counter-user')?></th>
                        <th><?=§('counter-bytes')?></th>
                        <th><?=§('counter-edits')?></th>
                        <th><?=§('counter-ppb')?></th>
                        <th><?=§('counter-images')?></th>
                        <th><?=§('counter-ppi')?></th>
                        <th><?=§('counter-points')?></th>
                        <?php if ($_SESSION['user']["user_status"] == 'G'): ?>
                            <th><?=§('counter-redefine')?></th>
                        <?php endif; ?>
                    </tr>
                    <?php while ($row = mysqli_fetch_assoc($count_query) ?? []): ?>
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
                                        "<?=§('counter-confirm')?>"
                                    )'>
                                        <input type='hidden' name='user_id' value='<?=$row["user_id"]?>'>
                                        <input
                                        <?=($row["total edits"] == 0)?"disabled":""?>
                                        type='submit'
                                        class='w3-btn w3-<?=$contest["theme"]?>'
                                        value='<?=§('counter-redefine')?>'>
                                    </form>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endwhile; ?>
                </table>
            </div>
        <?php else: ?>
            <div class="w3-panel w3-orange w3-margin w3-display-container w3-border">
                <p>
                    <h3><?=§('triage-noedit')?></h3>
                </p>
            </div>
        <?php endif; ?>
    </body>
    <?php if (isset($output['success'])): ?>
        <script>
            alert(
                '<?=§('counter-success')?>'
            );
            window.location.href = window.location.href;
        </script>
    <?php endif; ?>
</html>
