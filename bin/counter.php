<?php
//Protetor de login
require_once "protect.php";

//Conecta ao banco de dados
require_once "connect.php";

//Coleta horário personalizado de consulta
if (isset($_POST['time_round'])) { 
    $time_unix = strtotime($_POST['time_round']);
    $time_sql = date('Y-m-d\TH:i:s', $time_unix);
} else {
    $time_unix = time();
    $time_sql = '0';
}

//Coleta lista de editores
$count_query = mysqli_query($con, 
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
            INNER JOIN `{$contest['name_id']}__users` ON `{$contest['name_id']}__users`.`user` = `{$contest['name_id']}__edits`.`user`
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
                            CASE WHEN SUM(`{$contest['name_id']}__edits`.`bytes`) > ${contest['max_bytes_per_article']} THEN ${contest['max_bytes_per_article']} ELSE SUM(`{$contest['name_id']}__edits`.`bytes`) END AS `bytes`, 
                            COUNT(`{$contest['name_id']}__edits`.`valid_edit`) AS `valid_edits` 
                        FROM 
                            `{$contest['name_id']}__edits` 
                        WHERE 
                            `{$contest['name_id']}__edits`.`valid_edit` IS NOT NULL AND `{$contest['name_id']}__edits`.`timestamp` < ( CASE WHEN '${time_sql}' = '0' THEN NOW() ELSE '${time_sql}' END)
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
             CASE WHEN ${contest['pictures_per_points']} = 0 THEN 0 ELSE FLOOR(SUM(`distinct`.`pictures`) / ${contest['pictures_per_points']}) END AS `pictures points` 
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
                            `{$contest['name_id']}__edits`.`pictures` IS NOT NULL AND `{$contest['name_id']}__edits`.`timestamp` < ( CASE WHEN '${time_sql}' = '0' THEN NOW() ELSE '${time_sql}' END)
                        GROUP BY 
                            CASE WHEN ${contest['pictures_mode']} = 0 THEN `{$contest['name_id']}__edits`.`user` END, 
                            CASE WHEN ${contest['pictures_mode']} = 0 THEN `{$contest['name_id']}__edits`.`article` END, 
                            CASE WHEN ${contest['pictures_mode']} = 0 THEN `{$contest['name_id']}__edits`.`pictures` ELSE `{$contest['name_id']}__edits`.`n` END
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
    `user_table`.`user` ASC");
if (mysqli_num_rows($count_query) == 0) die("No users");


?>

<!DOCTYPE html>
<html lang="pt-br">
    <title>Contador - <?=$contest['name'];?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="bin/w3.css">
    <link rel="stylesheet" type="text/css" href="bin/color.php?color=<?=@$contest['color'];?>">
    <body>
        <header class="w3-container w3-<?=$contest['theme'];?>">
            <h1>Contador - <?=$contest['name'];?></h1>
        </header>
        <br>
        <div class="w3-container">
            <div class="w3-threequarter w3-section">
                <p class="w3-text-darkgrey w3-container">Este cômputo se refere ao wikiconcurso "<?=$contest['name'];?>" e foi gerado no dia <?=date('d/m/Y', $time_unix);?>, às 
                <?=date('H:i:s', $time_unix);?> do horário UTC. A ordem de classificação é realizada de acordo com o total de pontos, calculados com a pontuação arredondada para baixo, sendo utilizado como critério de desempate o valor da soma total de bytes adicionados e, caso ainda exista empate, por ordem alfabética. Todos os usuários inscritos que editaram algum dos artigos da lista deste wikiconcurso estão listados abaixo, mesmo não tendo edição válida alguma.</p>
            </div>
            <div class="w3-quarter w3-section">
                <form class="w3-container w3-card w3-padding" method="post">
                    <caption>Caso queira obter um extrato da pontuação até determinado horário, especifique no formulário abaixo.</caption>
                    <input class="w3-input w3-border" type="datetime-local" name="time_round" value="<?=date('Y-m-d\TH:i', $time_unix);?>">
                    <input class="w3-btn w3-block w3-<?=$contest['theme'];?>" type="submit">
                </form>
            </div>
            <table class="w3-table-all w3-hoverable w3-card">
                <tr>
                    <th>Usuário</th>
                    <th>Soma de bytes</th>
                    <th>Total de edições</th>
                    <th>Pontos por bytes</th>
                    <th>Artigos com imagens</th>
                    <th>Pontos por imagens</th>
                    <th>Total de pontos</th>
                </tr><?php

//Loop para exibição de cada linha
while ($row = mysqli_fetch_assoc($count_query)) {
    echo "<tr>\n";
    echo("<td>".$row["user"]."</td>\n");
    echo("<td>".$row["sum"]."</td>\n");
    echo("<td>".$row["total edits"]."</td>\n");
    echo("<td>".$row["bytes points"]."</td>\n");
    echo("<td>".$row["total pictures"]."</td>\n");
    echo("<td>".$row["pictures points"]."</td>\n");
    echo("<td>".$row["total points"]."</td>\n");
    echo "</tr>\n";
}
?>
            </table>
        </div>
    </body>
</html>
