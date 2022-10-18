<?php
//Protetor de login
require "protect.php";

//Conecta ao banco de dados
require "connect.php";

//Processa informações caso formulário tenha sido submetido
if ($_POST) {

	//Escapa diff inserido no formulário
	if (!isset($_POST['diff'])) die("Diff não fornecido!");
	$post['diff'] = addslashes($_POST['diff']);

	//Processa validade da edição, de acordo com o avaliador
	if ($_POST['valid'] == 'sim') {
		$post['valid'] = 1;
	} else {
		$post['valid'] = 0;
	}

	//Verifica se/quantas imagem(es) foi(ram) inserida(s), de acordo com o avaliador
	if ($contest['pictures_mode'] == 2) {
		$post['pic'] = addslashes($_POST['pic']);
	} else {
		if ($_POST['pic'] == 'sim') {
			$post['pic'] = 1;
		} else {
			$post['pic'] = 0;
		}
	}
	
	//Processa observação inserida no formulário
	if (!isset($_POST['obs'])) {
		$post['obs'] = '';
	} else {
		$post['obs'] = addslashes($_POST['obs']);
	}

	//Busca número de bytes e nome do avaliador no banco de dados
	$query = mysqli_fetch_assoc(
		mysqli_query($con, "
			SELECT 
				`bytes`,
				`by`
			FROM 
				`{$contest['name_id']}__edits` 
			WHERE 
				`diff` = '{$post['diff']}'
			LIMIT 1
		;")
	);

	//Verifica se diff pertence ao avaliador atual ou o usuário atual é o gestor do concurso
	if ($query['by'] == $_SESSION['user']['user_name'] OR $_SESSION['user']['user_status'] == 'G') {

		//Processa alteração incluíndo número de bytes, caso informação tenha sido editada pelo reavaliador
		$time = date('Y-m-d H:i:s');
		if (isset($_POST['overwrite']) AND $query['bytes'] != $_POST['overwrite']) {

			$post['overwrite'] = addslashes($_POST['overwrite']);
			$obs = "Modif: por {$_SESSION['user']['user_name']} de {$query['bytes']} para {$post['overwrite']} em {$time} com justificativa \"{$post['obs']}\"\n";
			$sql_update = "
				UPDATE 
					`{$contest['name_id']}__edits` 
				SET 
					`bytes`	 		= '{$post['overwrite']}'
					`valid_edit`	= '{$post['valid']}',
					`pictures`		= '{$post['pic']}', 
					`obs` 			= CONCAT(IFNULL(`obs`, ''), '{$obs}')
				WHERE `diff` = '{$post['diff']}';
			";	
		
		//Processa alteração sem modificar número de bytes
		} else {

			$obs = "Modif: por {$_SESSION['user']['user_name']} em {$time} com justificativa \"{$post['obs']}\"\n";
			$sql_update = "
				UPDATE 
					`{$contest['name_id']}__edits` 
				SET 
					`valid_edit`	= '{$post['valid']}',
					`pictures`		= '{$post['pic']}', 
					`obs` 			= CONCAT(IFNULL(`obs`, ''), '{$obs}')
				WHERE `diff` = '{$post['diff']}';
			";
		}

		//Executa query e retorna o resultado para o reavaliador
		$update_query = mysqli_query($con, $sql_update);
		if (mysqli_affected_rows($con) != 0) {
			$output['success']['diff'] = addslashes($_POST['diff']);
		}

	//Caso o avaliador não seja autorizado, define diff do resultado como null
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
			`{$contest['name_id']}__edits` 
		WHERE 
			`diff`		= '".addslashes($_GET['diff'])."'
		LIMIT 1
	;");
	$output['revision'] = mysqli_fetch_assoc($revision_query);

	//Coleta informações da edição via API do MediaWiki, caso a diff esteja no banco de dados
	if (isset($output['revision']['diff'])) {
	    $compare_api_params = [
	        "action"    => "compare",
	        "prop"      => "title|diff",
	        "format"    => "json",
	        "fromrev"   => $_GET['diff'],
	        "torelative"=> "prev"
	    ];
	    $output['compare'] = json_decode(file_get_contents($contest['api_endpoint']."?".http_build_query($compare_api_params)), true)['compare'];
	} else {
		$output['compare'] = false;
	}
}

//Encerra conexão
mysqli_close($con);

//Exibe edição e formulário de avaliação
?>
<!DOCTYPE html>
<html>
    <title>Modificar - <?=$contest['name'];?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="bin/w3.css">
    <link rel="stylesheet" href="bin/diff.css">
    <body>
        <header class="w3-container w3-<?=$contest['theme'];?>">
            <h1>Modificar - <?=$contest['name'];?></h1>
        </header>
        <br>
		<div class="w3-row-padding w3-content" style="max-width:1400px">
			<div class="w3-container w3-quarter w3-margin-top">
				<form class="w3-container w3-card w3-margin-bottom" id="modify" method="get">
					<h2>Consultar avaliação</h2>
					<input type="hidden" name="contest" value=<?php echo($contest['name_id']); ?>>
					<input type="hidden" name="page" value="modify">
					<p>
						<input class="w3-input w3-border" type="number" name="diff" min="10000000" max="99999999" value=<?php echo('"'.@$_GET['diff'].'"'); ?> required>
						<label>Diff</label>
					</p>
					<p>
						<button class="w3-button w3-section w3-green w3-ripple" style="width:100%">Carregar edição</button>
					</p>
				</form>
				<form class="w3-container w3-light-grey w3-border w3-border-dark-grey w3-margin-bottom" id="modify" method="post" <?php if ($_SESSION['user']['user_name'] != @$output['revision']['by'] AND $_SESSION['user']['user_status'] != 'G') echo("style='display:none;'"); ?>>
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
						<p>Imagem?</p>
						<?php if ($contest['pictures_mode'] == 2) {
							echo('
								<input class="w3-input w3-border" type="number" id="pic" name="pic" value="0" min="0" max="9" required>
								<label for="pic">Quantidade</label><br><br>
							');
						} else {
							echo('
								<input class="w3-radio w3-section" type="radio" id="pic-sim" name="pic" value="sim" required>
								<label for="pic-sim">Sim</label><br>
								<input class="w3-radio w3-section" type="radio" id="pic-nao" name="pic" value="nao" required>
								<label for="pic-nao">Não</label><br><br>
							');
						}
						?>
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
				<div class="w3-container w3-light-grey w3-border w3-border-dark-grey w3-margin-bottom" <?php if(!isset($output['compare']['*'])) echo('style="display:none;"'); ?>>
					<h2>Dados da edição</h2>
					<ul class="w3-ul w3-margin-bottom">
						<li>Edição:<br><a href="<?=$contest['endpoint'];?>?diff=<?=@$output['revision']['diff'];?>" target="_blank"><?=@$output['revision']['diff'];?></a></li>
						<li>ID do artigo:<br><a href="<?=$contest['endpoint'];?>?curid=<?=@$output['revision']['article'];?>" target="_blank"><?=@$output['revision']['article'];?></a></li>
						<li>Horário da edição:<br><?php echo(@$output['revision']['timestamp']); ?></li>
						<li>Usuário:<br><?php echo(@$output['revision']['user']); ?></li>
						<li>Bytes:<br><?php echo(@$output['revision']['bytes']); ?></li>
						<li>Sumário:<br><?php echo(@$output['revision']['summary']); ?>&nbsp;</li>
						<li>Artigo novo:<br><?php if (@$output['revision']['new_page']) { echo("Sim"); } else { echo("Não"); } ?></li>
						<li>Edição válida:<br><?php if (@$output['revision']['valid_edit']) { echo("Sim"); } else { echo("Não"); } ?></li>
						<li>Usuário inscrito:<br><?php if (@$output['revision']['valid_user']) { echo("Sim"); } else { echo("Não"); } ?></li>
						<li>Imagem:<br><?php if ($contest['pictures_mode'] == 2) { echo(@$output['revision']['pictures']); } else { if ($output['revision']['pictures']) { echo("Sim"); } else { echo("Não"); }} ?></li>
						<li>Edição revertida:<br><?php if (@$output['revision']['reverted']) { echo("Sim"); } else { echo("Não"); } ?></li>
						<li>Avaliador:<br><?php echo(@$output['revision']['by']); ?></li>
						<li>Horário da avaliação:<br><?php echo(@$output['revision']['when']); ?></li>
						<li>Comentário do avaliador:<br><pre><?php echo(@$output['revision']['obs']); ?></pre>&nbsp;</li>
					</ul>
				</div>
			</div>
			<div class="w3-threequarter">
                <div <?php if(!isset($output['compare']['*'])) echo('style="display:none;"'); ?>>
                    <h3>Diferencial de edição</h3>
                    <table class="diff diff-contentalign-left diff-editfont-monospace" style="word-wrap: break-word;white-space: pre-wrap;word-break: break-word;">
                        <?php print_r(@$output['compare']['*']); ?>
                    </table>
                    <hr>
                </div>
                <?php
	                if (!$output['compare']) echo("<script>alert('Edição não encontrada no banco de dados!');</script>"); 
	                if (@array_key_exists('diff', $output['success'])) {
						if (is_null($output['success']['diff'])) { 
							echo("<script>alert('Você não pode modificar uma avaliação de terceiro!');</script>");
						} else {
							echo("<script>alert('Modificação realizada com sucesso!');</script>");
						}
					}
				?>
            </div>
		</div>
	</body>
</html>











