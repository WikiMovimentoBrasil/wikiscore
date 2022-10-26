<?php
//Protetor de login
require_once "protect.php";

//Conecta ao banco de dados
require_once "connect.php";

//Cria header especial para exportar arquivo
header("Expires: 0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Content-Description: File Transfer");
header("Content-disposition: attachment; filename=".$contest['name_id'].time().".csv");
header("Cache-control: private");
header("Content-type: text/csv; charset=windows-1252");

//Cabeçalho do CSV
$csv =  "Diff da edição;".
        "CurID do artigo;".
        "Horário da edição;".
        "Usuário;".
        "Bytes;".
        "Sumário;".
        "Artigo novo;".
        "Edição válida;".
        "Usuário inscrito;".
        "Com imagem;".
        "Edição revertida;".
        "Avaliador;".
        "Horário da avaliação;".
        "Comentário do avaliador"."\r\n";

//Coleta lista de edições
$edits_statement = "
    SELECT
        *
    FROM
        `{$contest['name_id']}__edits`
    WHERE
        `valid_user` IS NOT NULL
;";
$edits_query = mysqli_prepare($con, $edits_statement);
mysqli_stmt_execute($edits_query);
$edits_result = mysqli_stmt_get_result($edits_query);
mysqli_stmt_close($edits_query);

//Verifica se existem edições cadastradas bo banco de dados
$rows = mysqli_num_rows($edits_result);
if ($rows == 0) die("No edits");

$sep = '";"';

while ($query = mysqli_fetch_assoc($edits_result)) {
    $csv .= '"'.
            $query["diff"].$sep.
            $query["article"].$sep.
            $query["timestamp"].$sep.
            $query["user"].$sep.
            $query["bytes"].$sep.
            str_replace('"', '""', $query["summary"]).$sep.
            $query["new_page"].$sep.
            $query["valid_edit"].$sep.
            $query["valid_user"].$sep.
            $query["pictures"].$sep.
            $query["reverted"].$sep.
            $query["by"].$sep.
            $query["when"].$sep.
            str_replace('"', '""', $query["obs"])."\"\r\n";
}

echo mb_convert_encoding($csv, 'CP1252', 'UTF-8');
