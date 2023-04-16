<?php

//Função para query de cálculo de pontuação
function querypoints($time_sql)
{
    global $contest;
    global $con;

    $time_sql .= ' 23:59:59';

    $query = "
    SELECT
        `user_table`.`user`,
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
                            SUM(edits_ruled.`bytes`) / ${contest['bytes_per_points']}
                        ) AS `bytes points`
                    FROM
                        (
                            SELECT
                                `{$contest['name_id']}__edits`.`article`,
                                `{$contest['name_id']}__edits`.`user_id`,
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
                                `{$contest['name_id']}__edits`.`valid_edit` = '1' AND
                                `{$contest['name_id']}__edits`.`timestamp` < (
                                    CASE
                                        WHEN '${time_sql}' = '0'
                                        THEN NOW()
                                        ELSE '${time_sql}'
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
                        WHEN ${contest['pictures_per_points']} = 0
                        THEN 0
                        ELSE FLOOR(SUM(`distinct`.`pictures`) / ${contest['pictures_per_points']})
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
                                    CASE WHEN '${time_sql}' = '0' THEN NOW() ELSE '${time_sql}' END
                                )
                            GROUP BY
                                CASE
                                    WHEN ${contest['pictures_mode']} = 0
                                    THEN `{$contest['name_id']}__edits`.`user_id`
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
                        `distinct`.`user_id`
                    ORDER BY
                        NULL
                ) AS t2 ON t1.`user_id` = t2.`user_id`
        ) AS `points` ON `user_table`.`user_id` = `points`.`user_id`
    ORDER BY
        `points`.`total points` DESC,
        `points`.`sum` DESC,
        `user_table`.`user` ASC
    ;
";

    $run = mysqli_query($con, $query);

    while ($row = mysqli_fetch_assoc($run)) {
        $data[$row["user"]] = $row["total points"];
    }

    return $data ?? false;
}


//Calcula dia de início do wikiconcurso
$start_day = date('Y-m-d', $contest['start_time']);

//Calcula dia de término do wikiconcurso, ou dia atual caso o wikiconcurso ainda esteja ocorrendo
if (time() > $contest['end_time']) {
    $end_time = date('Y-m-d', ($contest['end_time'] + 86400));
    $finished = true;
} else {
    $end_time = date('Y-m-d');
    $finished = false;
}

//Monta lista de dias em uma array
$days = array();
for ($i = -1; end($days) != $start_day; $i--) {
    $days[] = date('Y-m-d', strtotime("{$i} days", strtotime($end_time)));
}
$days = array_reverse($days);

//Separa último dia da lista
$last_day = array_pop($days);

//Coleta pontuação dos demais dias via query
foreach ($days as $time_sql) {
    $data_graph[$time_sql] = querypoints($time_sql);
}

//Coleta pontuação do último dia do último dia do wikiconcurso, caso ainda esteja ocorrendo
if ($finished) {
    $data_graph[$last_day] = querypoints($last_day);
}

//Coleta lista dos 9 primeiros colocados do último dia via query
$last_day = querypoints($last_day);
if (!$last_day) {
    die("Erro durante consulta. Há edições avaliadas?");
}
$last_day = array_keys($last_day);
$user_list = array_slice($last_day, 0, 9);

//Designa 9 cores para as linhas do gráfico
$colors = [
    "#fd7f6f",
    "#7eb0d5",
    "#b2e061",
    "#bd7ebe",
    "#ffb55a",
    "#ffee65",
    "#beb9db",
    "#fdcce5",
    "#8bd3c7"
];

//Loop para geração da série de pontos de cada usuário
foreach ($user_list as $user) {

    //Converte matriz Dia > Usuário > Pontos para Usuário > Dia > Pontos
    foreach ($data_graph as $day_points) {
        $user_points[] = $day_points[$user];
    }

    //Converte array de pontos do particante em uma string
    $user_points = implode(', ', $user_points);

    //Coleta a primera cor da lista e remove de sua array
    $color = array_shift($colors);

    //Gera dataset para uso no gráfico
    $datasets_graph[] = "
        {
            label: '{$user}',
            data: [ {$user_points} ],
            fill: false,
            borderColor: '{$color}',
            tension: 0.1
        }";

    //Apaga variável para uso no próximo loop
    unset($user_points);
}

//Converte array de datasets em uma única string
$datasets_graph = implode(',', $datasets_graph);

//Calcula número total de dias do wikiconcurso e monta eixo X dos gráficos
$elapsed_days = floor((time() - $contest['start_time']) / 60 / 60 / 24);
$total_days = ceil(($contest['end_time'] - $contest['start_time']) / 60 / 60 / 24) + 2;
$all_days = array();
for ($i=1; $i < $total_days; $i++) { array_push($all_days, $i); }
$all_days = implode(", ", $all_days);

?>

<!DOCTYPE html>
<html lang="pt-br">
    <head>
        <title>Gráfico de pontos - <?=$contest['name'];?></title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="bin/w3.css">
        <link rel="stylesheet" type="text/css" href="bin/color.php?color=<?=@$contest['color'];?>">
        <script
        src="https://tools-static.wmflabs.org/cdnjs/ajax/libs/Chart.js/3.9.1/chart.min.js"
        integrity="sha384-9MhbyIRcBVQiiC7FSd7T38oJNj2Zh+EfxS7/vjhBi4OOT78NlHSnzM31EZRWR1LZ"
        crossorigin="anonymous"></script>
    </head>
    <body>
        <header class="w3-container w3-<?=$contest['theme'];?>">
            <h1>Gráfico de pontos - <?=$contest['name'];?></h1>
        </header>
        <br>
        <div class="w3-row-padding w3-content" style="max-width:700px">
            <div class="w3-container w3-margin-top w3-card-4">
                <div class="w3-container">
                    <p>
                        O gráfico abaixo exibe a evolução das pontuações recebidas pelos 9 participantes melhores
                        classificados durante o wikiconcurso. Esse gráfico é gerado de forma automática e não
                        consiste em uma classificação final ou oficial. Observe que os organizadores podem
                        reavaliar edições anteriores e os pontos exibidos podem ser recalculados sem aviso
                        prévio. Em caso de discrepância, apenas a tabela inserida pelos organizadores do
                        wikiconcurso na sua página oficial deve ser considerada.</p>
                </div>
            </div>
            <div class="w3-container w3-section w3-card-4">
                <canvas id="ranking"></canvas>
            </div>
        <script type="text/javascript">
            const dias = [ <?=$all_days;?> ];

            const ranking = new Chart(
                document.getElementById('ranking'),
                {
                    type: 'line',
                    options: {
                        aspectRatio: 1.5,
                        plugins: {
                            title: {
                                display: true,
                                text: "Ranking dos 9 primeiros colocados por dia decorrido"
                            },
                            legend: {
                                display: false
                            }
                        }
                    },
                    data: {
                        labels: dias,
                        datasets: [ <?=$datasets_graph;?> ]
                    }
                }
            );
        </script>
    </body>
</html>
