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
foreach ($enrollments as $enrollment) {
    //$row_global_id = $enrollment['global_id']; 
    //$row_local_id = $enrollment['local_id']; 
    $row_user = $enrollment['username'];
    $row_timestamp = $enrollment['enrollment_timestamp'];
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
