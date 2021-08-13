<?php
echo("<pre>");

//Conecta ao banco de dados
require "connect.php";

//Coleta planilha com usuarios inscritos
$outreach_params = [
	"course" 	=> $contest['outreach_name']
];
$csv = file_get_contents('https://outreachdashboard.wmflabs.org/course_students_csv?'.http_build_query($outreach_params));
if(is_null($csv)) die("Não foi possível encontrar a lista de usuários no Outreach."); 

//Converte csv em uma array
$lines = str_getcsv($csv, "\n");
foreach($lines as &$row) $row = str_getcsv($row, ","); 
array_shift($lines);
unset($row);

//Atualiza tabela de usuários e de edições
mysqli_query($con, "TRUNCATE `users`;");
foreach ($lines as $row) {
	mysqli_query($con, "
		INSERT IGNORE INTO 
			`users` (`user`, `timestamp`) 
		VALUES 
			('".$row['0']."', '".$row['1']."')
	;");
	mysqli_query($con, "
		UPDATE 
			`edits` 
		SET 
			`valid_user`='1' 
		WHERE 
			`user` = '".$row['0']."' AND 
		`timestamp` >= '".$row['1']."'
	;");
}

//Coleta lista de usuários para verificar renomeações
$check_renamed = mysqli_query($con, "SELECT `user` FROM `users`;");

//Loop para verificar renomeações na API do meta
//Se encontrado, substititui nome na tabela `users` e edições em `edits`
//Adicionalmente, habilita edições que porventura ainda não haviam sido habilitadas
while ($user = mysqli_fetch_assoc($check_renamed)) {
	$check_renamed_api_params = [
		"action" 		=> "query",
		"format" 		=> "json",
		"list" 			=> "logevents",
		"leprop" 		=> "details|title",
		"leaction" 		=> "renameuser/renameuser",
		"letitle" 		=> "User:".$user['user'],
	];
	$check_renamed_api = json_decode(file_get_contents("https://meta.wikimedia.org/w/api.php?".http_build_query($check_renamed_api_params)), true)["query"]["logevents"];
	if(!empty($check_renamed_api)) {
		echo("<br>Usuário renomeado: ".$check_renamed_api['0']['params']['olduser']." -> ".$check_renamed_api['0']['params']['newuser']);
		mysqli_query($con, "
			UPDATE 
			  `edits` 
			SET 
			  `user` = '".$check_renamed_api['0']['params']['newuser']."' 
			WHERE 
			  `user` = '".$check_renamed_api['0']['params']['olduser']."';
		");
		mysqli_query($con, "
			UPDATE 
			  `users` 
			SET 
			  `user` = '".$check_renamed_api['0']['params']['newuser']."' 
			WHERE 
			  `user` = '".$check_renamed_api['0']['params']['olduser']."';
		");
		$missing_edits = mysqli_query($con, "
			SELECT 
			  `edits`.`n`
			FROM 
			  `edits` 
			  LEFT JOIN `users` ON `edits`.`user` = `users`.`user` 
			WHERE 
			  `edits`.`timestamp` > `users`.`timestamp` AND
			  `edits`.`valid_user` IS NULL AND
			  `edits`.`user` = '".$check_renamed_api['0']['params']['newuser']."';
		");
		while ($edition = mysqli_fetch_assoc($missing_edits)) {
			mysqli_query($con, "
				UPDATE 
					`edits` 
				SET 
					`valid_user`='1' 
				WHERE 
					`n` = '".$edition['n']."'
			;");
			echo("<br>Edição em n=".$edition['n']." habilitada.");
		}		
	}
}

//Destrava edições que porventura ainda estejam travadas
mysqli_query($con, "
	UPDATE 
		`edits` 
	SET 
		`by` = NULL 
	WHERE 
		`by` LIKE 'hold-%'
	;");

//Encerra conexão
mysqli_close($con);
echo("<br>Concluido! (2/3)<br><a href='index.php?contest=".$contest['name_id']."&page=load_reverts'>Próxima etapa, clique aqui.</a>");

