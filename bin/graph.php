<?php

//Função para query de cálculo de pontuação (quase a mesma query do counter.php)
function querypoints($time_sql)
{
    global $contest;
    global $con;

    $time_sql .= ' 23:59:59';

    $query = "SELECT `user_table`.`user`, IFNULL(`points`.`total points`, 0) AS `total points` FROM ( SELECT DISTINCT `{$contest['name_id']}__edits`.`user_id`, `{$contest['name_id']}__users`.`user` FROM `{$contest['name_id']}__edits` INNER JOIN `{$contest['name_id']}__users` ON `{$contest['name_id']}__users`.`local_id` = `{$contest['name_id']}__edits`.`user_id` ) AS `user_table` LEFT JOIN ( SELECT t1.`user_id`, t1.`sum`, t1.`total edits`, t1.`bytes points`, t2.`total pictures`, t2.`pictures points`, ( t1.`bytes points` + t2.`pictures points` ) AS `total points` FROM ( SELECT edits_ruled.`user_id`, SUM(edits_ruled.`bytes`) AS `sum`, SUM(edits_ruled.`valid_edits`) AS `total edits`, FLOOR( SUM(edits_ruled.`bytes`) / ${contest['bytes_per_points']} ) AS `bytes points` FROM ( SELECT `{$contest['name_id']}__edits`.`article`, `{$contest['name_id']}__edits`.`user_id`, CASE WHEN SUM( `{$contest['name_id']}__edits`.`bytes` ) > ${contest['max_bytes_per_article']} THEN ${contest['max_bytes_per_article']} ELSE SUM(`{$contest['name_id']}__edits`.`bytes`) END AS `bytes`, COUNT(`{$contest['name_id']}__edits`.`valid_edit`) AS `valid_edits` FROM `{$contest['name_id']}__edits` WHERE `{$contest['name_id']}__edits`.`valid_edit` = '1' AND `{$contest['name_id']}__edits`.`timestamp` < ( CASE WHEN '${time_sql}' = '0' THEN NOW() ELSE '${time_sql}' END ) GROUP BY `user_id`, `article` ORDER BY NULL ) AS edits_ruled GROUP BY edits_ruled.`user_id` ORDER BY NULL ) AS t1 LEFT JOIN ( SELECT `distinct`.`user_id`, `distinct`.`article`, SUM(`distinct`.`pictures`) AS `total pictures`, CASE WHEN ${contest['pictures_per_points']} = 0 THEN 0 ELSE FLOOR(SUM(`distinct`.`pictures`) / ${contest['pictures_per_points']}) END AS `pictures points` FROM ( SELECT `{$contest['name_id']}__edits`.`user_id`, `{$contest['name_id']}__edits`.`article`, `{$contest['name_id']}__edits`.`pictures`, `{$contest['name_id']}__edits`.`n` FROM `{$contest['name_id']}__edits` WHERE `{$contest['name_id']}__edits`.`pictures` IS NOT NULL AND `{$contest['name_id']}__edits`.`timestamp` < ( CASE WHEN '${time_sql}' = '0' THEN NOW() ELSE '${time_sql}' END ) GROUP BY CASE WHEN ${contest['pictures_mode']} = 0 THEN `{$contest['name_id']}__edits`.`user_id` END, CASE WHEN ${contest['pictures_mode']} = 0 THEN `{$contest['name_id']}__edits`.`article` END, CASE WHEN ${contest['pictures_mode']} = 0 THEN `{$contest['name_id']}__edits`.`pictures` ELSE `{$contest['name_id']}__edits`.`n` END ) AS `distinct` GROUP BY `distinct`.`user_id` ORDER BY NULL ) AS t2 ON t1.`user_id` = t2.`user_id` ) AS `points` ON `user_table`.`user_id` = `points`.`user_id` ORDER BY `points`.`total points` DESC, `points`.`sum` DESC, `user_table`.`user` ASC;";

    $run = mysqli_query($con, $query);

    while ($row = mysqli_fetch_assoc($run)) {
        $data[$row["user"]] = $row["total points"];
    }

    return $data ?? false;
}


//Calcula dia de início do wikiconcurso
$start_day = date('Y-m-d', $contest['start_time']);

//Caso o concurso ainda não tenha iniciado
if (time() < $contest['start_time']) {

    $datasets_graph = false;

} else {

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
}

?>

<!DOCTYPE html>
<html lang="pt-br">
    <head>
        <title><?=§('graph')?> - <?=$contest['name'];?></title>
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
            <h1><?=§('graph')?> - <?=$contest['name'];?></h1>
        </header>
        <br>
        <div class="w3-row-padding w3-content" style="max-width:700px">
            <div class="w3-container w3-margin-top w3-card-4">
                <div class="w3-container">
                    <p><?=§('graph-about')?></p>
                </div>
            </div>
            <div class="w3-container w3-section w3-card-4">
                <?php if ($datasets_graph !== false) : ?>
                    <canvas id="ranking"></canvas>
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
                                            text: "<?=§('graph-axis')?>"
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
                <?php else: ?>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" style="width:200px;margin:auto;display:block;">
                     <path 
                         d="M 8 2 C 4.6862915 2 2 4.6862915 2 8 C 2 11.313708 4.6862915 14 8 14 C 8.3415702 14 8.6740174 13.964985 9 13.910156 L 9 12.900391 C 8.6769296 12.965962 8.3424207 13 8 13 C 5.2385763 13 3 10.761424 3 8 C 3 5.2385763 5.2385763 3 8 3 C 10.761424 3 13 5.2385763 13 8 C 13 8.3424207 12.965962 8.6769296 12.900391 9 L 13.910156 9 C 13.964985 8.6740174 14 8.3415702 14 8 C 14 4.6862915 11.313708 2 8 2 z M 7 4 L 7 8 L 7 9 L 9 9 L 12 9 L 12 8 L 8 8 L 8 4 L 7 4 z "
                         style="fill:currentColor;fill-opacity:1;stroke:none;color:#4d4d4d;"/>
                     <path 
                        d="M 9.9899998,9.0000003 9,9.99 11.01,12 9,14.01 9.9899998,15 12,12.99 14.01,15 15,14.01 12.99,12 15,9.99 14.01,9.0000003 12,11.01 Z"  
                        style="fill:currentColor;fill-opacity:1;stroke:none;color:#da4453;"/>
                    </svg>
                <?php endif; ?>
            </div>
        </div>
    </body>
</html>
