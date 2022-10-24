<?php
echo "<pre>";

//Aumenta tempo limite de execução do script
set_time_limit(1200);

//Conecta ao banco de dados
require_once "connect.php";

//Verifica se PSID do PetScan foi fornecido ao invés de uma categoria comum
if (isset($contest['category_petscan'])) {
	
	//Recupera lista do PetScan
	$petscan_list = json_decode(
		file_get_contents(
			"https://petscan.wmflabs.org/?format=json&psid=".$contest['category_petscan']
		), true
	);
	$petscan_list = $petscan_list['*']['0']['a']['*'];

	//Insere lista em uma array
	$list = array();
	foreach ($petscan_list as $petscan_id) $list[] = $petscan_id['id'];

} else {

	//Coleta lista de artigos na categoria
	$categorymembers_api_params = [
		"action" 		=> "query",
		"format" 		=> "json",
		"list" 			=> "categorymembers",
		"cmnamespace" 	=> "0",
		"cmpageid" 		=> $contest['category_pageid'],
		"cmprop" 		=> "ids",
		"cmlimit"		=> "500"
	];
	$categorymembers_api = json_decode(file_get_contents($contest['api_endpoint']."?".http_build_query($categorymembers_api_params)), true);

	//Insere lista em uma array
	$list = array();
	foreach ($categorymembers_api['query']['categorymembers'] as $pageid) $list[] = $pageid['pageid'];

	//Coleta segunda página da lista, caso exista
	while (isset($categorymembers_api['continue'])) {
		$categorymembers_api_params = [
			"action" 		=> "query",
			"format" 		=> "json",
			"list" 			=> "categorymembers",
			"cmnamespace" 	=> "0",
			"cmpageid" 		=> $contest['category_pageid'],
			"cmprop" 		=> "ids",
			"cmlimit"		=> "500",
			"cmcontinue"	=> $categorymembers_api['continue']['cmcontinue']
		];
		$categorymembers_api = json_decode(file_get_contents($contest['api_endpoint']."?".http_build_query($categorymembers_api_params)), true);
		foreach ($categorymembers_api['query']['categorymembers'] as $pageid) $list[] = $pageid['pageid'];
	}
}

//Monta e executa query para atualização da tabela de artigos
$list = implode("'), ('", $list);
mysqli_query($con, "
	TRUNCATE 
		`{$contest['name_id']}__articles`
	;");
mysqli_query($con, "
	INSERT INTO 
		`{$contest['name_id']}__articles` (`articleID`) 
	VALUES 
		('{$list}')
	;");

//Coleta lista de artigos
$articles_query = mysqli_query($con, "SELECT * FROM `{$contest['name_id']}__articles`;");
if (mysqli_num_rows($articles_query) == 0) die("No articles");

//Coleta revisões já inseridas no banco de dados
$revision_list = array();
$revision_query = mysqli_query($con, "
	SELECT 
		`diff` 
	FROM 
		`{$contest['name_id']}__edits` 
	ORDER BY 
		`diff`
	;");
foreach (mysqli_fetch_all($revision_query, MYSQLI_ASSOC) as $diff) $revision_list[] = $diff['diff'];

//Loop para análise de cada artigo
while ($row = mysqli_fetch_assoc($articles_query)) {

	//Coleta revisões do artigo
	$revisions_api_params = [
		"action" 	=> "query",
		"format" 	=> "json",
		"prop" 		=> "revisions",
		"rvprop" 	=> "ids",
		"rvlimit" 	=> "50",
		"rvstart" 	=> date('Y-m-d\TH:i:s.000\Z', $contest['start_time']),
		"rvend" 	=> date('Y-m-d\TH:i:s.000\Z', $contest['end_time']),
		"rvdir" 	=> "newer",
		"pageids" 	=> $row["articleID"]
	];

    $revisions_api = end(json_decode(file_get_contents($contest['api_endpoint']."?".http_build_query($revisions_api_params)), true)['query']['pages']);

    //Verifica se artigo possui revisões dentro dos parâmetros escolhidos
    if (!isset($revisions_api['revisions'])) continue;

    //Loop para cada revisão do artigo
	foreach ($revisions_api['revisions'] as $revision) {

		//Verifica se revisão ainda não existe no banco de dados
		if (in_array($revision['revid'], $revision_list)) continue;

		//Coleta dados de diferenciais da revisão
		$compare_api_params = [
			"action"	=> "compare",
			"format" 	=> "json",
			"torelative"=> "prev",
			"prop" 		=> "diffsize|comment|size|title|user|timestamp",
			"fromrev" 	=> $revision['revid']
		];
		$compare_api = json_decode(file_get_contents($contest['api_endpoint']."?".http_build_query($compare_api_params)), true);

		//Verifica se edição foi ocultada. Caso sim, define valores da edição
		//como nulos, executa a query e continua para o próximo loop
		if (!isset($compare_api['compare'])) {
			mysqli_query($con, "
				INSERT IGNORE INTO 
					`{$contest['name_id']}__edits` (
						`diff`, 
						`article`
					) 
				VALUES 
					(
						'{$revision['revid']}', 
						'{$row["articleID"]}'
					)
			;");
			continue;
		} else {
			$compare_api = $compare_api['compare'];
		}

		//Verifica se página é nova. Caso sim, define valor inicial 
		//como zero para evitar erros e programa "UPDATE" após "INSERT"
		if (!isset($compare_api['fromsize'])) {
			$compare_api['fromsize'] = 0;
			$compare_api['new_page'] = TRUE;
		}

		//Prepara query de inserção da revisão no banco de dados
		$sql_compare = "
			INSERT IGNORE INTO 
				`{$contest['name_id']}__edits` (
					`diff`, 
					`article`, 
					`timestamp`, 
					`user`, 
					`bytes`, 
					`summary`
				) 
			VALUES 
				(
					'{$revision['revid']}', 
					'{$row["articleID"]}', 
					'{$compare_api['totimestamp']}', 
					'".addslashes($compare_api['touser'])."', 
					'".($compare_api['tosize'] - $compare_api['fromsize'])."', 
					'".addslashes($compare_api['tocomment'])."'
				)
			;";

		//Executa query
		mysqli_query($con, $sql_compare);
		if (mysqli_affected_rows($con) != 0) echo("<br> Inserida revisão ".$revision['revid']);

		//Marca edição como nova página
		if (isset($compare_api['new_page'])) {
			mysqli_query($con, "UPDATE `{$contest['name_id']}__edits` SET `new_page` = '1' WHERE `diff` = '".$revision['revid']."';");
			if (mysqli_affected_rows($con) != 0) echo("<br> Marcada como nova página a revisão ".$revision['revid']);
		} 
	}
}

//Encerra conexão
mysqli_close($con);
echo("<br>Concluido! (1/3)<br><a href='index.php?contest=".$contest['name_id']."&page=load_users'>Próxima etapa, clique aqui.</a>");