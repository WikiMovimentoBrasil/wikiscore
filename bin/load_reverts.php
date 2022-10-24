<?php
echo "<pre>";

//Conecta ao banco de dados
require_once "connect.php";

//Coleta lista de edições
$edits_query = mysqli_query($con, "
	SELECT 
		`diff` 
	FROM 
		`{$contest['name_id']}__edits` 
	WHERE 
		`valid_user` IS NOT NULL AND 
		`reverted` IS NULL
	;");
if (mysqli_num_rows($edits_query) == 0) die("No edits");

//Loop para análise de cada edição
while ($row = mysqli_fetch_assoc($edits_query)) {

	//Coleta tags da revisão
	$revisions_api_params = [
		"action" 	=> "query",
		"format"	=> "json",
		"prop"		=> "revisions",
		"rvprop"	=> "sha1|tags",
		"revids"	=> $row["diff"]
	];
	$revisions_api = json_decode(file_get_contents($contest['api_endpoint']."?".http_build_query($revisions_api_params)), true)['query'];
	$revision = end($revisions_api['pages'])['revisions']['0'];

	//Marca edição caso tenha sido revertida ou eliminada
	if (
		in_array('mw-reverted',$revision['tags']) 
		OR isset($revisions_api['badrevids']) 
		OR isset($revision['sha1hidden'])
	) { 
		mysqli_query($con, "
			UPDATE 
				`{$contest['name_id']}__edits` 
			SET 
				`reverted` = '1' 
			WHERE 
				`diff` = '".$row["diff"]."'
			;");
		echo("Marcada edição ".$row["diff"]." como revertida.<br>");
	}
}

//Encerra conexão
mysqli_close($con);
echo "Concluido! (3/3)";