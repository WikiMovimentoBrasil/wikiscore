<?php
echo "<pre>";

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
$csv_lines = str_getcsv($csv, "\n");
foreach ($csv_lines as &$row) {
    $row = str_getcsv($row, ",");
}
unset($row);

//Separa a linha de cabeçalho da array
$csv_head = array_shift($csv_lines);

//Conta a quantidade de colunas
$csv_num_rows = count($csv_head);

//Consolida informações em uma array própria
$enrollments = array();
foreach ($csv_lines as $csv_line) {

    //Caso existam linhas com menos colunas que o cabeçalho
    if (count($csv_line) < $csv_num_rows) {
        die("Erro! Uma das linhas possui menos colunas que o cabeçalho.");
    }

    //Caso existam linhas com mais colunas que o cabeçalho
    //Ocorre essencialmente com usernames contendo vírgulas que foram divididas em colunas diferentes
    //Nesse caso, ele concatena as primeiras colunas até alcançar a quantidade correta de colunas
    if (count($csv_line) > $csv_num_rows) {
        $csv_line_extra_columns = 1 + count($csv_line) - $csv_num_rows;
        $csv_line_first_column = array_splice($csv_line, 0, $csv_line_extra_columns);
        $csv_line_first_column = implode(',', $csv_line_first_column);
        array_unshift($csv_line, $csv_line_first_column);
    }

    //Combina cabeçalho e linha na array própria
    $enrollments[] = array_combine($csv_head, $csv_line);
}

//Prepara lista de CentralUser IDs
foreach ($enrollments as $enrollment) {
    $global_ids[] = $enrollment['global_id'];
}
$bindClause = implode(',', array_fill(0, count($global_ids), '?'));
$bindString = str_repeat('s', count($global_ids) + 1);

//Coleta ID da wiki
$wiki_id_params = [
    "action"        => "query",
    "format"        => "php",
    "meta"          => "siteinfo"
];
$wiki_id = file_get_contents(
    $contest['api_endpoint'] . "?" . http_build_query($wiki_id_params)
);
$wiki_id = unserialize($wiki_id)["query"]["general"]["wikiid"];

//Conecta ao banco de dados do CentralAuth
$con_centralauth = mysqli_connect('centralauth.analytics.db.svc.wikimedia.cloud', $db_user, $db_pass, 'centralauth_p');
if (mysqli_connect_errno()) {
    echo "Failed to connect to MySQL: " . mysqli_connect_error();
    exit();
}

//Prepara consulta SQL
$centralauth_query = mysqli_prepare(
    $con_centralauth,
    "SELECT
        `lu_name`, `lu_local_id`, `lu_global_id`
    FROM
        `localuser`
    WHERE
        `lu_wiki` = ?
        AND `lu_global_id` IN (${bindClause})"
);

//Executa consulta e coleta os resultados
mysqli_stmt_bind_param($centralauth_query, $bindString, $wiki_id, ...$global_ids);
mysqli_stmt_execute($centralauth_query);
$centralauth_result = mysqli_stmt_get_result($centralauth_query);
mysqli_stmt_close($centralauth_query);

while ($lu = mysqli_fetch_assoc($centralauth_result)) {
    $centralauth_users[$lu["lu_global_id"]] = [
        "lu_name"       =>  $lu['lu_name'],
        "lu_local_id"   =>  $lu['lu_local_id']
    ];
}



//Limpa tabela de usuários
mysqli_query($con, "TRUNCATE `{$contest['name_id']}__users`;");

//Prepara query de inserção de usuários
$adduser_statement = "
    INSERT INTO
        `{$contest['name_id']}__users` (`user`, `timestamp`, `global_id`, `local_id`)
    VALUES
        (?, ?, ?, ?)
";
$adduser_query = mysqli_prepare($con, $adduser_statement);
mysqli_stmt_bind_param($adduser_query, "ssii", $row_user, $row_timestamp, $row_global_id, $row_local_id);

//Prepara query de validação de edições
$validedit_statement = "
    UPDATE
        `{$contest['name_id']}__edits`
    SET
        `valid_user`='1'
    WHERE
        `user_id` = ? AND `timestamp` >= ?
";
$validedit_query = mysqli_prepare($con, $validedit_statement);
mysqli_stmt_bind_param($validedit_query, "ss", $row_local_id, $row_timestamp);

//Loop de execução das queries
foreach ($enrollments as $enrollment) {
    $row_global_id = $enrollment['global_id'];
    $row_timestamp = strftime('%Y-%m-%d %H:%M:%S', strtotime($enrollment['enrollment_timestamp']));
    $row_local_id = $centralauth_users[$enrollment['global_id']]['lu_local_id'] ?? null;
    $row_user = $centralauth_users[$enrollment['global_id']]['lu_name'] ?? $enrollment['username'] ?? null;

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

//Destrava edições que porventura ainda estejam travadas
mysqli_query(
    $con,
    "UPDATE
        `{$contest['name_id']}__edits`
    SET
        `by` = NULL,
        `when` = NULL
    WHERE
        `by` LIKE 'hold-%' OR
        `by` LIKE 'skip-%';"
);

//Encerra conexão
mysqli_close($con);
echo "<br>Concluido! (2/3)<br>";
echo "<a href='index.php?contest=".$contest['name_id']."&page=load_reverts'>Próxima etapa, clique aqui.</a>";
