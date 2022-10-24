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
$edits_query = mysqli_query($con, "SELECT * FROM `{$contest['name_id']}__edits` WHERE `valid_edit` IS NOT NULL;");
if (mysqli_num_rows($edits_query) == 0) die("No edits");

while ($query = mysqli_fetch_assoc($edits_query)) {
    $csv .= "\"".
            $query["diff"]."\";\"".
            $query["article"]."\";\"".
            $query["timestamp"]."\";\"".
            $query["user"]."\";\"".
            $query["bytes"]."\";\"".
            str_replace('"', '""', $query["summary"])."\";\"".
            $query["new_page"]."\";\"".
            $query["valid_edit"]."\";\"".
            $query["valid_user"]."\";\"".
            $query["pictures"]."\";\"".
            $query["reverted"]."\";\"".
            $query["by"]."\";\"".
            $query["when"]."\";\"".
            str_replace('"', '""', $query["obs"])."\"\r\n";
}

echo(mb_convert_encoding($csv, 'CP1252', 'UTF-8'));