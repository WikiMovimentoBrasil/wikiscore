<?php
echo("<pre>");

//Conecta ao banco de dados
require "connect.php";

//Coleta planilha com usuarios inscritos
$outreach_params = [
	"course" 	=> $contest['outreach_name']
];
$csv = file_get_contents('https://outreachdashboard.wmflabs.org/course_students_csv?'.http_build_query($outreach_params)); 

//Converte csv em uma array
$lines = str_getcsv($csv, "\n");
foreach($lines as &$row) $row = str_getcsv($row, ","); 
array_shift($lines);

//Atualiza tabela de usuários e de edições
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

