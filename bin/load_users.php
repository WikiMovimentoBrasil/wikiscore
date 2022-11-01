<?php
echo "<pre>";

//Conecta ao banco de dados
require_once "connect.php";

//Coleta planilha com usuarios inscritos
$outreach_params = [
    "course"    => $contest['outreach_name']
];
$csv = file_get_contents(
    'https://outreachdashboard.wmflabs.org/course_students_csv?'.http_build_query($outreach_params)
);
if (is_null($csv)) {
    die("Não foi possível encontrar a lista de usuários no Outreach.");
}

//Converte csv em uma array
$lines = str_getcsv($csv, "\n");
foreach ($lines as &$row) {
    $row = str_getcsv($row, ",");
}
array_shift($lines);
unset($row);

//Limpa tabela de usuários
mysqli_query($con, "TRUNCATE `{$contest['name_id']}__users`;");

//Prepara query de inserção de usuários
$adduser_statement = "
    INSERT INTO
        `{$contest['name_id']}__users` (`user`, `timestamp`)
    VALUES
        (?, ?)
";
$adduser_query = mysqli_prepare($con, $adduser_statement);
mysqli_stmt_bind_param($adduser_query, "ss", $row_user, $row_timestamp);

//Prepara query de validação de edições
$validedit_statement = "
    UPDATE
        `{$contest['name_id']}__edits`
    SET
        `valid_user`='1'
    WHERE
        `user` = ? AND `timestamp` >= ?
";
$validedit_query = mysqli_prepare($con, $validedit_statement);
mysqli_stmt_bind_param($validedit_query, "ss", $row_user, $row_timestamp);

//Loop de execução das queries
foreach ($lines as $row) {
    $row_user = $row['0'];
    $row_timestamp = $row['1'];
    mysqli_stmt_execute($adduser_query);
    mysqli_stmt_execute($validedit_query);

    if (mysqli_stmt_affected_rows($adduser_query) != 0) { 
        echo "\nInserido participante {$row_user}";
    }

    if (mysqli_stmt_affected_rows($validedit_query) != 0) { 
        echo "\n- Ativando ".mysqli_stmt_affected_rows($validedit_query)." edições";
    }
}

//Encerra queries
mysqli_stmt_close($adduser_query);
mysqli_stmt_close($validedit_query);

//Coleta lista de usuários para verificar renomeações
$check_renamed = mysqli_query($con, "SELECT `user` FROM `{$contest['name_id']}__users`;");

//Loop para verificar renomeações na API do meta
//Se encontrado, substititui nome na tabela `nameid__users` e edições em `nameid__edits`
//Adicionalmente, habilita edições que porventura ainda não haviam sido habilitadas
while ($user = mysqli_fetch_assoc($check_renamed)) {
    $check_renamed_api_params = [
        "action"        => "query",
        "format"        => "php",
        "list"          => "logevents",
        "leprop"        => "details|title",
        "leaction"      => "renameuser/renameuser",
        "ledir"         => "newer",
        "lestart"       => date('Y-m-d\TH:i:s.000\Z', $contest['start_time']),
        "letitle"       => "User:{$user['user']}",
    ];
    $check_renamed_api = file_get_contents(
        "https://meta.wikimedia.org/w/api.php?".http_build_query($check_renamed_api_params)
    );
    $check_renamed_api = unserialize($check_renamed_api)["query"]["logevents"];
    if (!empty($check_renamed_api)) {
        echo "<br>Usuário renomeado: ";
        echo "{$check_renamed_api['0']['params']['olduser']} -> {$check_renamed_api['0']['params']['newuser']}";
        mysqli_query(
            $con,
            "UPDATE
              `{$contest['name_id']}__edits`
            SET
              `user` = '{$check_renamed_api['0']['params']['newuser']}'
            WHERE
              `user` = '{$check_renamed_api['0']['params']['olduser']}';"
        );
        mysqli_query(
            $con,
            "UPDATE
              `{$contest['name_id']}__users`
            SET
              `user` = '{$check_renamed_api['0']['params']['newuser']}'
            WHERE
              `user` = '{$check_renamed_api['0']['params']['olduser']}';"
        );
        $missing_edits = mysqli_query(
            $con,
            "SELECT
              `{$contest['name_id']}__edits`.`n`
            FROM
              `{$contest['name_id']}__edits`
            LEFT JOIN
              `{$contest['name_id']}__users`
              ON `{$contest['name_id']}__edits`.`user` = `{$contest['name_id']}__users`.`user`
            WHERE
              `{$contest['name_id']}__edits`.`timestamp` > `{$contest['name_id']}__users`.`timestamp` AND
              `{$contest['name_id']}__edits`.`valid_user` IS NULL AND
              `{$contest['name_id']}__edits`.`user` = '{$check_renamed_api['0']['params']['newuser']}';"
        );
        while ($edition = mysqli_fetch_assoc($missing_edits)) {
            mysqli_query(
                $con,
                "UPDATE `{$contest['name_id']}__edits` SET `valid_user`='1' WHERE `n` = '{$edition['n']}';"
            );
            echo "<br>Edição em n={$edition['n']} habilitada.";
        }
    }
}

//Destrava edições que porventura ainda estejam travadas
mysqli_query(
    $con,
    "UPDATE `{$contest['name_id']}__edits` SET `by` = NULL, `when` = NULL WHERE `by` LIKE 'hold-%' OR `by` LIKE 'skip-%';"
);

//Encerra conexão
mysqli_close($con);
echo "<br>Concluido! (2/3)<br>";
echo "<a href='index.php?contest=".$contest['name_id']."&page=load_reverts'>Próxima etapa, clique aqui.</a>";
