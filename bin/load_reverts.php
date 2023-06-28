<?php
echo "<pre>";

//Coleta lista de edições
$edits_statement = "
    SELECT
        `diff`
    FROM
        `{$contest['name_id']}__edits`
    WHERE
        `valid_user` IS NOT NULL AND
        `reverted` IS NULL
";
$edits_query = mysqli_prepare($con, $edits_statement);
mysqli_stmt_execute($edits_query);
$edits_result = mysqli_stmt_get_result($edits_query);

//Verifica se existem edições cadastradas no banco de dados
$rows = mysqli_num_rows($edits_result);
if ($rows == 0) { die("No edits"); }

//Prepara query para atualizações no banco de dados
$update_statement = "
    UPDATE
        `{$contest['name_id']}__edits`
    SET
        `reverted` = '1'
    WHERE
        `diff` = ?
";
$update_query = mysqli_prepare($con, $update_statement);
mysqli_stmt_bind_param($update_query, "i", $diff);

//Loop para análise de cada edição
while ($row = mysqli_fetch_assoc($edits_result)) {

    //Coleta tags da revisão
    $revisions_api_params = [
        "action"    => "query",
        "format"    => "php",
        "prop"      => "revisions",
        "rvprop"    => "sha1|tags",
        "revids"    => $row["diff"]
    ];
    $revisions_api = file_get_contents($contest['api_endpoint']."?".http_build_query($revisions_api_params));
    $revisions_api = unserialize($revisions_api)['query'];
    $revision = end($revisions_api['pages'])['revisions']['0'];

    //Marca edição caso tenha sido revertida ou eliminada
    if (
        isset($revisions_api['badrevids'])
        || isset($revision['sha1hidden'])
        || in_array('mw-reverted', $revision['tags'])
    ) {
        $diff = $row["diff"];
        mysqli_stmt_execute($update_query);
        if (mysqli_stmt_affected_rows($update_query) != 0) {
            echo "Marcada edição {$row["diff"]} como revertida.<br>";
        }
    }
}

//Encerra conexão
mysqli_stmt_close($update_query);
mysqli_close($con);
echo "Concluido! (3/3)";
