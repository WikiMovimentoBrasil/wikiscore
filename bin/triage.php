<?php
//Protetor de login
require "protect.php";

//Conecta ao banco de dados
require "connect.php";

//Processa informações caso formulário tenha sido submetido
if ($_POST) {
	$post['valid'] = 0;
	$post['pic'] = 0;
	if ($_POST['valid'] == 'sim') $post['valid'] = 1;
	if ($_POST['pic'] == 'sim') $post['pic'] = 1;

	//Monta query
	$sql_update = "
		UPDATE 
			`edits` 
		SET 
			`valid_edit`='".$post['valid']."',
			`pictures`='".$post['pic']."', 
			`by` = '".addslashes($_SESSION['user']['user_name'])."', 
			`when` = '".addslashes(date('Y-m-d H:i:s'))."',
			`obs` =  '".addslashes($_POST['obs'])."'
		WHERE `diff`='".addslashes($_POST['diff'])."';";

	//Executa query
	$update_query = mysqli_query($con, $sql_update);
	if (mysqli_affected_rows($con) != 0) {

		$output['success']['diff'] = $_POST['diff'];
		$output['success']['valid'] = $post['valid'];
		$output['success']['pic'] = $post['pic'];

		//Destrava edições do usuário que porventura ainda estejam travadas
		$unhold_query = mysqli_query($con, "
			UPDATE 
				`edits` 
			SET 
				`by` = NULL 
			WHERE 
				`by` = 'hold-".$_SESSION['user']['user_name']."'
		;");

	}
}

//Conta edições faltantes
$count_query = mysqli_query($con, "
	SELECT 
		COUNT(*) AS `count` 
	FROM 
		`edits` 
	WHERE 
		`reverted` IS NULL AND 
		`valid_edit` IS NULL AND 
		`valid_user` IS NOT NULL AND 
		`by` IS NULL AND 
		`bytes` > 0 AND 
		`timestamp` < '".date('Y-m-d H:i:s',strtotime($contest['revert_time']))."'
;");
$output['count'] = mysqli_fetch_assoc($count_query)['count'];

//Coleta edição
$revision_query = mysqli_query($con, "
	SELECT 
		`diff`, 
		`bytes`, 
		`user`, 
		`summary`, 
		`article`, 
		`timestamp` 
	FROM 
		`edits` 
	WHERE 
		`reverted` IS NULL AND 
		`valid_edit` IS NULL AND 
		`valid_user` IS NOT NULL AND 
		`bytes` > 0 AND 
		`by` IS NULL AND 
		`timestamp` < '".date('Y-m-d H:i:s',strtotime($contest['revert_time']))."' 
	ORDER BY 
		`timestamp` DESC 
	LIMIT 1
;");
$output['revision'] = mysqli_fetch_assoc($revision_query);

//Trava edição para evitar que dois avaliadores avaliem a mesma edição ao mesmo tempo
if ($output['revision'] != NULL) {
	$hold_query = mysqli_query($con, "
		UPDATE 
			`edits` 
		SET 
			`by` = 'hold-".$_SESSION['user']['user_name']."' 
		WHERE 
			`diff`='".$output['revision']['diff']."'
	;");
	if (mysqli_affected_rows($con) == 0) die("<br>Erro ao travar edição. Atualize a página para tentar novamente.");

	//Coleta comparativo de edição
	$output['compare'] = json_decode(file_get_contents("https://pt.wikipedia.org/w/api.php?action=compare&prop=title%7Cdiff&format=json&fromrev=".$output['revision']['diff']."&torelative=prev"), true)['compare'];
}

//Encerra conexão
mysqli_close($con);


//Exibe revisão e formulário de avaliação
?>
<!DOCTYPE html>
<html lang="pt-BR">
	<title><?php echo $contest['name']; ?></title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta charset="UTF-8">
	<link rel="stylesheet" href="bin/w3.css">
	<link rel="stylesheet" href="bin/diff.css">
	<body>
		<div class="w3-<?php echo($contest['theme']);?> w3-padding-32 w3-margin-bottom w3-center">
			<h1 class="w3-jumbo"><?php echo $contest['name']; ?></h1>
		</div>
		<div class="w3-row-padding w3-content" style="max-width:1400px">
			<div class="w3-quarter">
				<div class="w3-container w3-light-grey">
					<div <?php if(isset($output['success']['diff'])) {
						echo('class="w3-half w3-container"');
					} else {
						echo('class="w3-container"');
					}?>>
						<div <?php if(!isset($output['revision']['timestamp'])) echo('style="display:none;"');?>>
							<h6 class="w3-center">Você está avaliando uma edição do dia</h6>
							<h4 class="w3-center"><?php echo(@substr($output['revision']['timestamp'], 0, 10));?></h4>
						</div>
						<h6 class="w3-center">Edições pendentes</h6>
						<h1 class="w3-center"><?php echo($output['count']);?></h1>
						<br>
						<form method="post">
							<input class="w3-button w3-red w3-border-red w3-border w3-block w3-margin-bottom w3-small" type="submit" name="logout" value="Logout">
						</form>
					</div>
					<div class="w3-half w3-container" <?php if(!isset($output['success']['diff'])) echo('style="display:none;"');?>>
						<h3>Última avaliação</h3>
						<p>
							Diff:<br>
							<a target="_blank" <?php echo('href="https://pt.wikipedia.org/w/index.php?diff='.@$output['success']['diff'].'"');?>><!--
							---><?php echo(@$output['success']['diff']);?><!--
						---></a>
						</p>
						<p>
							Edição válida:<br><?php echo(@$output['success']['valid']);?>
						</p>
						<p>
							Com imagem:<br><?php echo(@$output['success']['pic']);?>
						</p>
						<p>
							<button class="w3-button w3-border-purple w3-purple w3-border w3-block w3-small" type="button">Desfazer</button>
						</p>
					</div>
					<div class="w3-container w3-margin-bottom">
						<button class="w3-button w3-border-amber w3-<?php echo($contest['theme']);?> w3-border w3-block w3-small" type="button" onclick="window.open('index.php?contest=<?php echo($contest['name_id']);?>&page=counter', '_blank');">Contador</button>
						<button class="w3-button w3-border-blue w3-blue w3-border w3-block w3-small" type="button" onclick="window.open('index.php?contest=<?php echo($contest['name_id']);?>&page=compare', '_blank');">Comparador</button>
					</div>
				</div>
				<br>
				<div <?php if(!isset($output['revision']['timestamp'])) echo('style="display:none;"');?>>
					<div class="w3-container w3-light-grey">
						<h2>Avaliação</h2>
						<form method="post">
							<input type="hidden" name="diff" value=<?php echo('"'.@$output['revision']['diff'].'"'); ?>>
							<div class="w3-container w3-cell w3-half">
								<p>Edição válida?</p>
								<input class="w3-radio w3-section" type="radio" id="valid-sim" name="valid" value="sim" onclick="document.getElementById('obs').required = false">
								<label for="valid-sim">Sim</label><br>
								<input class="w3-radio w3-section" type="radio" id="valid-nao" name="valid" value="nao" onclick="document.getElementById('obs').required = true">
								<label for="valid-nao">Não</label><br><br>
							</div>
							<div class="w3-container w3-cell w3-half">
								<p>Com imagem?</p>
								<input class="w3-radio w3-section" type="radio" id="pic-sim" name="pic" value="sim">
								<label for="pic-sim">Sim</label><br>
								<input class="w3-radio w3-section" type="radio" id="pic-nao" name="pic" value="nao">
								<label for="pic-nao">Não</label><br><br>
							</div>
							<p>
								<input class="w3-input w3-border" name="obs" id="obs" type="text" placeholder="Observação">
								<input class="w3-button w3-green w3-border-green w3-border w3-block w3-margin-top w3-small" type="submit" value="Salvar">
							</p>
						</form>
					</div>
					<br>
					<div class="w3-container w3-light-grey w3-justify">
						<h2>Detalhes da edição</h2>
						<p style="overflow-wrap: break-word;">
							Diff: 
							<a target="_blank" <?php echo('href="https://pt.wikipedia.org/w/index.php?diff='.@$output['revision']['diff'].'"');?>><!--
							---><?php echo(@$output['revision']['diff']);?><!--
						---></a><!--
						---> - <!--
						---><a target="_blank" <?php echo('href="https://copyvios.toolforge.org/?lang=pt&amp;project=wikipedia&amp;action=search&amp;use_engine=1&amp;use_links=1&amp;turnitin=0&amp;oldid='.@$output['revision']['diff'].'"');?>><!--
							--->Copyvio Detector<!--
						---></a>
							<br><b>Usuário:</b> <?php echo(@$output['revision']['user']);?>
							<br><b>Artigo:</b> <?php echo(@$output['compare']['totitle']);?>
							<br><b>Diferença (bytes):</b> <?php echo(@$output['revision']['bytes']);?>
							<br><b>Horário:</b> <?php echo(@$output['revision']['timestamp']);?> (UTC)
							<br><b>Sumário:</b> <?php echo(@$output['revision']['summary']);?>
						</p>
					</div>
				</div>
				<br>
				<div class="w3-container w3-light-grey w3-justify">
					<h2>Dados do concurso</h2>
					<p>Nome: <?php echo $contest['name']; ?></p>
					<p class="w3-small">Usuário: <?php echo(ucfirst($_SESSION['user']['user_name']));?></p>
					<p class="w3-small">Início: <?php echo(date('d/m/Y H:i:s (\U\T\C)', $contest['start_time']))?></p>
					<p class="w3-small">Término: <?php echo(date('d/m/Y H:i:s (\U\T\C)', $contest['end_time']))?></p>
					<p class="w3-small">Delay no registro das edições: <?php echo(str_replace("hours", "horas", $contest['revert_time']))?></p>
				</div>
				<br>
			</div>
			<div class="w3-threequarter">
				<div <?php if(!isset($output['compare']['*'])) echo('style="display:none;"');?>>
					<h3>Diferencial de edição</h3>
					<table class="diff diff-contentalign-left diff-editfont-monospace" style="word-wrap: break-word;white-space: pre-wrap;word-break: break-word;">
						<?php print_r(@$output['compare']['*']);?>
					</table>
					<hr>
				</div>
			</div>
		</div>
	</body>
</html>