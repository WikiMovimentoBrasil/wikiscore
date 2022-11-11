<?php

//Formulário submetido
if (isset($_POST['email'])) {

	//Pedido sem token
	if (!isset($_POST['token'])) {

		//Gera token de redefinição de senha
		$token = bin2hex(random_bytes(18));

		//Gera código seriado com timestamp e token para gravação no banco de dados
		$user_data = serialize(
			array(
				'timestamp' => time(),
				'token' => $token
			)
		);

	    //Processa query
	    $update_query = mysqli_prepare(
	        $con,
	        "UPDATE
	            `{$contest['name_id']}__credentials`
	        SET
	            `user_data` = ?
	        WHERE
	            `user_email` = ?"
	    );
	    mysqli_stmt_bind_param($update_query, "ss", $user_data, $_POST['email']);
	    mysqli_stmt_execute($update_query);

	    //Verifica se houve alteração (se e-mail foi encontrado, principalmente)
	    if (mysqli_stmt_affected_rows($update_query) != 0) {
	    	
	    	//Cria corpo do e-mail
			$message = "Oi!\nUtilize o seguinte token para redefinir sua senha:\n{$token}\n\n\nAtenciosamente,\nWikiconcursos";
			$emailFile = fopen("php://temp", 'w+');
			$subject = "Wikiconcursos - Redefinição de senha";
			fwrite($emailFile, "Subject: " . $subject . "\n" . $message);
			rewind($emailFile);
			$fstat = fstat($emailFile);
			$size = $fstat['size'];

			//Envia e-mail
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, 'smtp://mail.tools.wmflabs.org:587');
			curl_setopt($ch, CURLOPT_MAIL_FROM, "tools.wikiconcursos@tools.wmflabs.org");
			curl_setopt($ch, CURLOPT_MAIL_RCPT, array($_POST['email']));
			curl_setopt($ch, CURLOPT_INFILE, $emailFile);
			curl_setopt($ch, CURLOPT_INFILESIZE, $size);
			curl_setopt($ch, CURLOPT_UPLOAD, true);
			curl_exec($ch);
			fclose($emailFile);
			curl_close($ch);

			//Gera resultado
			$status = 'E-mail enviado. Confira sua caixa de entrada e de spam!';
			$input['token'] = true;
			$input['password'] = true;

	    } else {

	    	//Gera erro
			$status = 'Avaliador não encontrado';
			$input['token'] = false;
			$input['password'] = false;
	    }

	//Pedido com token
	} else {

		//Processa query
		$verify_query = mysqli_prepare(
		    $con,
		    "SELECT
		    	`user_id`,
		        `user_data`
		    FROM
		        `{$contest['name_id']}__credentials`
		    WHERE
	            `user_email` = ? AND `user_data` IS NOT NULL"
		);
		mysqli_stmt_bind_param($verify_query, "s", $_POST['email']);
		mysqli_stmt_execute($verify_query);
		$verify_result = mysqli_stmt_get_result($verify_query);

		//Verifica se avaliador existe
		if (mysqli_num_rows($verify_result) != 0) {

			//Abre código seriado e coloca informações em uma array
			$verify_result = mysqli_fetch_assoc($verify_result);
			$user_id = $verify_result['user_id'];
			$verify_result = unserialize($verify_result['user_data']);

			//Verifica se token ainda é válido (prazo de 900 segundos)
			if ($verify_result['timestamp'] > (time() - 900)) {
				
				//Verifica se token é igual
				if ($verify_result['token'] == trim($_POST['token'])) {

					//Grava nova senha
					session_start();
				    require_once "credentials-lib.php";
				    $USR->save($_POST['email'], $_POST['password'], $user_id);

				    //Gera resultado
					$status = 'Senha alterada com sucesso!';
					$input['token'] = false;
					$input['password'] = false;
				} else {

					//Gera erro
					$status = 'Token inválido';
					$input['token'] = true;
					$input['password'] = false;
				}
			} else {

				//Gera erro
				$status = 'Token expirado. Solicite um novo token';
				$input['token'] = false;
				$input['password'] = false;
			}
		} else {

	    	//Gera erro
			$status = 'Avaliador não encontrado ou não solicitou redefinição de senha';
			$input['token'] = false;
			$input['password'] = false;
		}
	}
} else {

	//Foormulário inicial
	$status = false;
	$input['token'] = false;
	$input['password'] = false;
}

	

?>
<!DOCTYPE html>
<html lang="pt-br">
    <head>
        <title>Redefinir senha - <?=$contest['name'];?></title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="bin/w3.css">
        <link rel="stylesheet" type="text/css" href="bin/color.php?color=<?=@$contest['color'];?>">
    </head>
    <body>
        <header class="w3-container w3-<?=$contest['theme'];?>">
            <h1>Redefinir senha</h1>
        </header>
        <br>
        <div class="w3-row-padding w3-content" style="max-width:700px">
            <div class="w3-container w3-margin-top w3-card-4">
                <div class="w3-container">
                    <p>
                        Essa página lista os concursos cadastrados no sistema e permite a criação
                        de novos concursos.
                    </p>
                </div>
            </div>
            <div class="w3-margin-top w3-card-4">
                <div class="w3-container">
                    <form id="create" method="post">
                        <div class="w3-section">
                        	<label>
                                <strong>E-mail</strong>
                            </label>
                            <input
                            class="w3-input w3-border w3-margin-bottom"
                            type="email"
                            placeholder="example@example.com"
                            maxlength="255"
                            name="email"
                            value="<?=$_POST['email']??''?>"
                            required>

                        	<label>
                                <strong>Token</strong>
                            </label>
                            <input
                            class="w3-input w3-border w3-margin-bottom"
                            type="text"
                            placeholder="<?=($input['token'])?'Ex: a5b8139ccd22b314d20e53c3ad836242a88f':''?>"
                            maxlength="255"
                            name="token"
                            <?=($input['token'])?'required':'disabled'?>>

                        	<label>
                                <strong>Nova senha</strong>
                            </label>
                            <input
                            class="w3-input w3-border w3-margin-bottom"
                            type="password"
                            maxlength="255"
                            placeholder="<?=($input['password'])?'Insira uma nova senha':''?>"
                            name="password"
                            <?=($input['password'])?'required':'disabled'?>>

                            <button class="w3-button w3-block w3-<?=$contest['theme'];?> w3-section w3-padding" name="do_create" type="submit">Enviar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php
	    if ($status == 'Senha alterada com sucesso!') {
	        echo "<script>alert('{$status}');window.location.replace('index.php?contest={$contest['name_id']}');</script>";
	    } elseif ($status != false) {
	        echo "<script>alert('{$status}');</script>";
	    }
    ?>
    </body>
</html>
