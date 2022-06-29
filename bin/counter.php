<?php
//Protetor de login
require "protect.php";

//Conecta ao banco de dados
require "connect.php";

//Coleta lista de editores
$count_query = mysqli_query($con, 
"SELECT 
  `user_table`.`user`, 
  IFNULL(`points`.`sum`, 0) AS `sum`, 
  IFNULL(`points`.`total edits`, 0) AS `total edits`, 
  IFNULL(`points`.`bytes points`, 0) AS `bytes points`, 
  IFNULL(`points`.`total pictures`, 0) AS `total pictures`, 
  IFNULL(`points`.`pictures points`, 0) AS `pictures points`, 
  IFNULL(`points`.`total points`, 0) AS `total points` 
FROM 
  (
    SELECT 
      DISTINCT `edits`.`user` 
    FROM 
      `edits` 
      INNER JOIN `users` ON `users`.`user` = `edits`.`user`
  ) AS `user_table` 
  LEFT JOIN (
    SELECT 
      t1.`user`, 
      t1.`sum`, 
      t1.`total edits`, 
      t1.`bytes points`, 
      t2.`total pictures`, 
      t2.`pictures points`, 
      (
        t1.`bytes points` + t2.`pictures points`
      ) AS `total points` 
    FROM 
      (
        SELECT 
          edits_ruled.`user`, 
          SUM(edits_ruled.`bytes`) AS `sum`, 
          SUM(edits_ruled.`valid_edits`) AS `total edits`, 
          FLOOR(
            SUM(edits_ruled.`bytes`) / ${contest['bytes_per_points']}
          ) AS `bytes points` 
        FROM 
          (
            SELECT 
              edits.`article`, 
              edits.`user`, 
              CASE WHEN SUM(edits.`bytes`) > ${contest['mas_bytes_per_article']} THEN ${contest['mas_bytes_per_article']} ELSE SUM(edits.`bytes`) END AS `bytes`, 
              COUNT(edits.`valid_edit`) AS `valid_edits` 
            FROM 
              `edits` 
            WHERE 
              edits.`valid_edit` IS NOT NULL 
              AND edits.`bytes` > 0 
            GROUP BY 
              `user`, 
              `article` 
            ORDER BY 
              NULL
          ) AS edits_ruled 
        GROUP BY 
          edits_ruled.`user` 
        ORDER BY 
          NULL
      ) AS t1 
      LEFT JOIN (
        SELECT 
          `distinct`.`user`, 
          `distinct`.`article`, 
          SUM(`distinct`.`pictures`) AS `total pictures`, 
       CASE WHEN ${contest['pictures_per_points']} = 0 THEN 0 ELSE FLOOR(SUM(`distinct`.`pictures`) / ${contest['pictures_per_points']}) END AS `pictures points` 
        FROM 
          (
            SELECT 
              edits.`user`, 
              edits.`article`, 
              edits.`pictures`, 
              edits.`n` 
            FROM 
              `edits` 
            WHERE 
              edits.`pictures` IS NOT NULL 
            GROUP BY 
              CASE WHEN ${contest['count_pic_per_edit']} = 0 THEN edits.`user` END, 
              CASE WHEN ${contest['count_pic_per_edit']} = 0 THEN edits.`article` END, 
              CASE WHEN ${contest['count_pic_per_edit']} = 0 THEN edits.`pictures` ELSE edits.`n` END
          ) AS `distinct` 
        GROUP BY 
          `distinct`.`user` 
        ORDER BY 
          NULL
      ) AS t2 ON t1.`user` = t2.`user`
  ) AS `points` ON `user_table`.`user` = `points`.`user` 
ORDER BY 
  `points`.`total points` DESC, 
  `points`.`sum` DESC, 
  `user_table`.`user` ASC");
if (mysqli_num_rows($count_query) == 0) die("No users");
?>

<!DOCTYPE html>
<html lang="pt-BR">
	<head>
		<title>Contador - <?php echo($contest['name']);?></title>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<link rel="stylesheet" href="bin/w3.css">
	</head>
	<body>
		<div class="w3-<?php echo($contest['theme']);?> w3-padding-32 w3-margin-bottom w3-center">
			<h1 class="w3-jumbo"><?php echo $contest['name']; ?></h1>
		</div>
		<table class="w3-table-all w3-hoverable w3-card-4">
		<caption class="w3-text-grey">Este cômputo se refere ao <?php echo($contest['name']);?> e foi gerado no dia <?php echo(date('d/m/Y'));?>, às <?php echo(date('H:i:s'));?> do horário UTC. A ordem de classificação é realizada de acordo com o total de pontos, calculados com a pontuação arredondada para baixo, sendo utilizado como critério de desempate o valor da soma total de bytes adicionados e, caso ainda exista empate, por ordem alfabética. Todos os usuários inscritos que editaram algum dos artigos da lista deste wikiconcurso estão listados abaixo, mesmo não tendo edição válida alguma.<br><br></caption>
		<tr>
			<th>Usuário</th>
			<th>Soma de bytes</th>
			<th>Total de edições</th>
			<th>Pontos por bytes</th>
			<th>Artigos com imagens</th>
			<th>Pontos por imagens</th>
			<th>Total de pontos</th>
		</tr><?php

//Loop para exibição de cada linha
while ($row = mysqli_fetch_assoc($count_query)) {
	echo("<tr>\n");
	echo("<td>".$row["user"]."</td>\n");
	echo("<td>".$row["sum"]."</td>\n");
	echo("<td>".$row["total edits"]."</td>\n");
	echo("<td>".$row["bytes points"]."</td>\n");
	echo("<td>".$row["total pictures"]."</td>\n");
	echo("<td>".$row["pictures points"]."</td>\n");
	echo("<td>".$row["total points"]."</td>\n");
	echo("</tr>\n");
}
?>
		</table>
	</body>
</html>