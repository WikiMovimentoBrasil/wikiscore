<?php
set_time_limit(1790);

//Atualiza código
if (isset($_SERVER['HTTP_X_GITHUB_EVENT'])) { 
    $output = `bash git.sh`; 
    echo $output;
    exit();
}

var_dump($argv);
die();

//Conecta ao banco de dados
require_once __DIR__.'/bin/connect.php';

//Consulta lista de concursos
$contests_statement = '
    SELECT
        `name_id`
    FROM
        `manage__contests`
    WHERE
    
        -- Concurso já começou
        `start_time` < NOW()

        -- Não há registro de atualização iniciada (nunca houve ou foi apagado) ou
        -- A última atualização foi há mais de 10 minutos
        AND (                      
            `started_update` IS NULL OR
            `started_update` + INTERVAL 10 MINUTE < NOW()
        ) 
    
        -- Não há agendamento de próxima atualização (nunca houve ou foi apagado) ou
        AND (     
        `next_update` IS NULL 

            -- Concurso ainda não terminou, não está em atualização e o prazo de atualização foi atingido
            OR (
                `end_time` + INTERVAL 2 DAY > NOW() AND
                `started_update` < `finished_update` AND
                `next_update` < NOW()
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
        curl_setopt($ch, CURLOPT_URL, "https://wikiscore.toolforge.org/index.php?contest={$contest}&page={$script}");
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
