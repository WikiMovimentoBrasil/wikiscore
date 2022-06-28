<?php
//Protetor de login
require "protect.php";

//Conecta ao banco de dados
require "connect.php";

//Processa informações caso formulário tenha sido submetido
if ($_POST) {

	//Processa validade da edição, de acordo com o avaliador
	if ($_POST['valid'] == 'sim') {
		$post['valid'] = 1;
	} else {
		$post['valid'] = 0;
	}

	//Verifica se imagem foi inserida, de acordo com o avaliador
	if ($_POST['pic'] == 'sim') {
		$post['pic'] = 1;
	} else {
		$post['pic'] = 0;
	}
	
	//Processa observação inserida no formulário
	if (!isset($_POST['obs'])) {
		$post['obs'] = '';
	} else {
		$post['obs'] = addslashes($_POST['obs']);
	}

	//Busca número de bytes e nome do avaliador no banco de dados
	$look = mysqli_fetch_assoc(
		mysqli_query($con, "
			SELECT 
				`bytes`,
				`by`
			FROM 
				`edits` 
			WHERE 
				`diff` = '".addslashes($_POST['diff'])."'
			LIMIT 1
		;")
	);

	//Verifica se diff pertence ao avaliador atual
	if ($look['by'] == $_SESSION['user']['user_name']) {

		//Processa alteração incluíndo número de bytes, caso informação tenha sido editada pelo avaliador
		if (isset($_POST['overwrite']) AND $look['bytes'] != $_POST['overwrite']) {
			
			$post['obs'] .= " / bytes: ".$look['bytes']." -> ".addslashes($_POST['overwrite']);
			$sql_update = "
				UPDATE 
					`edits` 
				SET 
					`bytes`	 		= '".addslashes($_POST['overwrite'])."'
					`valid_edit`	= '".$post['valid']."',
					`pictures`		= '".$post['pic']."', 
					`obs` 			= CONCAT('Modificação em ".addslashes(date('Y-m-d H:i:s')).": ".$post['obs']."\n', `obs`)
				WHERE `diff` = '".addslashes($_POST['diff'])."';
			";	
		
		//Processa alteração sem modificar número de bytes
		} else {

			$sql_update = "
				UPDATE 
					`edits` 
				SET 
					`valid_edit`	= '".$post['valid']."',
					`pictures`		= '".$post['pic']."', 
					`obs` 			= CONCAT('Modificação em ".addslashes(date('Y-m-d H:i:s')).": ".$post['obs']."\n', `obs`)
				WHERE `diff` = '".addslashes($_POST['diff'])."';
			";
		}

		//Executa query e retorna o resultado para o avaliador
		$update_query = mysqli_query($con, $sql_update);
		if (mysqli_affected_rows($con) != 0) {
			$output['success']['diff'] = addslashes($_POST['diff']);
		}

	//Caso o nome do avaliador seja diferente, define diff do resultado como null
	} else {
		$output['success']['diff'] = NULL;
	}
}

//Carrega informações sobre o diff inserido no formulário
if (isset($_GET['diff'])) {

	//Coleta edição
	$revision_query = mysqli_query($con, "
		SELECT 
			*
		FROM 
			`edits` 
		WHERE 
			`diff`		= '".addslashes($_GET['diff'])."'
		LIMIT 1
	;");
	$output['revision'] = mysqli_fetch_assoc($revision_query);

}

//Encerra conexão
mysqli_close($con);

//Exibe edição e formulário de avaliação
?>
<!DOCTYPE html>
<html>
	<title><?php echo $contest['name']; ?></title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="stylesheet" href="./bin/w3.css">
	<body>
		<header class=<?php echo("\"w3-container w3-".$contest['theme']."\"");?>>
			<h1><?php echo $contest['name']; ?></h1>
		</header>
		<div class="w3-container w3-half w3-margin-top">
			<?php if (array_key_exists('diff', $output['success'])) {
				echo('<div class="w3-container w3-red"><p>');
				if (is_null($output['success']['diff'])) { 
					echo('Você não pode modificar uma avaliação de terceiro!');
				} else {
					echo('Modificação realizada com sucesso!');
				}
				echo('</p></div><hr>');
			} ?>
			<form class="w3-container w3-card-4" id="modify" method="get">
				<h2>Consultar avaliação</h2>
				<input type="hidden" name="contest" value=<?php echo($contest['name_id']); ?>>
				<input type="hidden" name="page" value="modify">
				<p>
					<input class="w3-input" type="number" name="diff" min="10000000" max="99999999" value=<?php echo('"'.@$_GET['diff'].'"'); ?> required>
					<label>Diff</label>
				</p>
				<p>
					<button class="w3-button w3-section w3-green w3-ripple" style="width:100%">Carregar edição</button>
				</p>
			</form>
			<hr>
			<form class="w3-container w3-light-grey" id="modify" method="post" <?php if ($_SESSION['user']['user_name'] != $output['revision']['by']) echo("style='display:none;'"); ?>>
				<h2>Reavaliação</h2>
				<input type="hidden" name="diff" value=<?php echo('"'.@$output['revision']['diff'].'"'); ?>>
				<div class="w3-container w3-cell w3-half">
					<p>Edição válida?</p>
					<input class="w3-radio w3-section" type="radio" id="valid-sim" name="valid" value="sim" onclick="document.getElementById('obs').required = false" required>
					<label for="valid-sim">Sim</label><br>
					<input class="w3-radio w3-section" type="radio" id="valid-nao" name="valid" value="nao" onclick="document.getElementById('obs').required = true" required>
					<label for="valid-nao">Não</label><br><br>
				</div>
				<div class="w3-container w3-cell w3-half">
					<p>Com imagem?</p>
					<input class="w3-radio w3-section" type="radio" id="pic-sim" name="pic" value="sim" required>
					<label for="pic-sim">Sim</label><br>
					<input class="w3-radio w3-section" type="radio" id="pic-nao" name="pic" value="nao" required>
					<label for="pic-nao">Não</label><br><br>
				</div>
				<p>
					<input class="w3-input w3-border" name="obs" id="obs" type="text" placeholder="Observação" required>
					<br>
					<input class="w3-button w3-border w3-block w3-red" name="overwrite" id="overwrite" type="button" value="Alterar bytes" onclick="
						document.getElementById('overwrite').removeAttribute('value');
						document.getElementById('overwrite').type = 'number';
						document.getElementById('overwrite').className = 'w3-input w3-border';
						document.getElementById('overwrite').value = '<?php echo(@$output['revision']['bytes']);?>';
						document.getElementById('overwrite').removeAttribute('onclick');
						document.getElementById('overwrite').removeAttribute('id');
						document.getElementById('obs').required = true;
					">
					<input class="w3-button w3-orange w3-border-orange w3-border w3-block w3-margin-top" type="submit" value="Modificar">
				</p>
			</form>
		</div>
		<div class="w3-container w3-half w3-margin-top" <?php if (!isset($output['revision'])) echo("style='display:none;'"); ?>>
			<div class="w3-container w3-light-grey">
				<h2>Dados da edição <?php echo(@$output['revision']['diff']); ?></h2>
				<ul class="w3-ul w3-margin-bottom">
					<li>CurID do artigo: <?php echo(@$output['revision']['article']); ?></li>
					<li>Horário da edição: <?php echo(@$output['revision']['timestamp']); ?></li>
					<li>Usuário: <?php echo(@$output['revision']['user']); ?></li>
					<li>Bytes: <?php echo(@$output['revision']['bytes']); ?></li>
					<li>Sumário: <?php echo(@$output['revision']['summary']); ?></li>
					<li>Artigo novo: <?php if ($output['revision']['new_page']) { echo("Sim"); } else { echo("Não"); } ?></li>
					<li>Edição válida: <?php if ($output['revision']['valid_edit']) { echo("Sim"); } else { echo("Não"); } ?></li>
					<li>Usuário inscrito: <?php if ($output['revision']['valid_user']) { echo("Sim"); } else { echo("Não"); } ?></li>
					<li>Imagem: <?php if ($output['revision']['pictures']) { echo("Sim"); } else { echo("Não"); } ?></li>
					<li>Edição revertida: <?php if ($output['revision']['reverted']) { echo("Sim"); } else { echo("Não"); } ?></li>
					<li>Avaliador: <?php echo(@$output['revision']['by']); ?></li>
					<li>Horário da avaliação: <?php echo(@$output['revision']['when']); ?></li>
					<li>Comentário do avaliador: <pre><?php echo(@$output['revision']['obs']); ?></pre></li>
				</ul>
			</div>
		</div>
	</body>
</html>











