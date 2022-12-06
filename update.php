<?php

//Conecta ao banco de dados
require_once __DIR__.'/bin/connect.php';

//Consulta lista de concursos
$contests_statement = '
    SELECT
        `name_id`
    FROM
        `manage__contests`
    WHERE
        `start_time` < NOW() AND                            #Concurso já começou e
        `end_time` + INTERVAL 2 DAY > NOW() AND (           #Ainda não terminou e
            `started_update` IS NULL OR (                       #Ou nunca foi atualizado
                `started_update` < `finished_update` AND            #Ou não está em atualização e
                `next_update` < NOW()                               #O prazo de atualização foi atingido
            )
        )
';
$contests_query = mysqli_prepare($con, $contests_statement);
mysqli_stmt_execute($contests_query);
$contests_result = mysqli_stmt_get_result($contests_query);
while ($row = mysqli_fetch_assoc($contests_result)) {
    $contests_array[] = $row['name_id'];
}
if (!isset($contests_array)) die("Sem atualizações previstas.\n");

//Define comandos a ser executados para cada concurso
$steps = ["load_edits", "load_users", "load_reverts"];

//Define queries
$start_query = mysqli_prepare(
    $con,
    "UPDATE
        `manage__contests`
    SET
        `started_update` = NOW()
    WHERE
        `name_id` = ?"
);
$finish_query = mysqli_prepare(
    $con,
    "UPDATE
        `manage__contests`
    SET
        `finished_update` = NOW(),
        `next_update` = INTERVAL 1 DAY + NOW()
    WHERE
        `name_id` = ?"
);
mysqli_stmt_bind_param($start_query, 's', $contest);
mysqli_stmt_bind_param($finish_query, 's', $contest);

//Loop de concursos
foreach ($contests_array as $contest) {

    //Grava horário de início
    mysqli_stmt_execute($start_query);

    //Loop de scripts
    foreach ($steps as $script) {

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://wikiconcursos.toolforge.org/index.php?contest={$contest}&page={$script}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt($ch, CURLOPT_USERAGENT, 'WikiCronJob/1.0');

        $result = curl_exec($ch);
        if (curl_errno($ch)) $result = curl_error($ch);
        curl_close($ch);

        print(time()."{$contest}\t{$script}\n");
    }

    //Grava horário de finalização
    mysqli_stmt_execute($finish_query);
}
?>
