<?php
//Protetor de login
require_once "protect.php";

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
        `{$contest['name_id']}__edits`.`diff`, 
        `{$contest['name_id']}__edits`.`article`, 
        `{$contest['name_id']}__edits`.`timestamp`, 
        `{$contest['name_id']}__edits`.`n`, 
        IFNULL(
            `{$contest['name_id']}__users`.`user`, 
            CONCAT(
                'Special:Redirect/user/', `{$contest['name_id']}__edits`.`user_id`
            )
        ) AS `user`,
        `{$contest['name_id']}__edits`.`bytes`, 
        `{$contest['name_id']}__edits`.`new_page`, 
        `{$contest['name_id']}__edits`.`valid_edit`, 
        `{$contest['name_id']}__edits`.`valid_user`, 
        `{$contest['name_id']}__edits`.`pictures`, 
        `{$contest['name_id']}__edits`.`reverted`, 
        `{$contest['name_id']}__edits`.`by`, 
        `{$contest['name_id']}__edits`.`when`, 
        `{$contest['name_id']}__edits`.`obs` 
    FROM 
        `{$contest['name_id']}__edits` 
    LEFT JOIN 
        `{$contest['name_id']}__users` 
    ON 
        `{$contest['name_id']}__edits`.`user_id` = `{$contest['name_id']}__users`.`local_id`;
";
$edits_query = mysqli_prepare($con, $edits_statement);
mysqli_stmt_execute($edits_query);
$edits_result = mysqli_stmt_get_result($edits_query);
mysqli_stmt_close($edits_query);

//Verifica se existem edições cadastradas bo banco de dados
$rows = mysqli_num_rows($edits_result);
if ($rows == 0) { die("No edits"); }

$sep = '";"';

while ($query = mysqli_fetch_assoc($edits_result)) {
    $csv .= '"'.
            $query["diff"].$sep.
            $query["article"].$sep.
            $query["timestamp"].$sep.
            $query["user"].$sep.
            $query["bytes"].$sep.
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
