<?php

//Formulário submetido
$input['token'] = false;
$input['password'] = false;
if (isset($_POST['email'])) {

    //Sanitiza e-mail
    if (filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $email =  filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    } else {
        die(§('recover-notemail'));
    }

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
        mysqli_stmt_bind_param($update_query, "ss", $user_data, $email);
        mysqli_stmt_execute($update_query);

        //Verifica se houve alteração (se e-mail foi encontrado, principalmente)
        if (mysqli_stmt_affected_rows($update_query) != 0) {

            //Cria corpo do e-mail
            $message = §('recover-greeting');
            $message .= "\n";
            $message .= §('recover-instruction');
            $message .= "\n{$token}\n\n\n";
            $message .= §('recover-signature');
            $emailFile = fopen("php://temp", 'w+');
            $subject = §('recover-subject');
            fwrite($emailFile, "Subject: " . $subject . "\n" . $message);
            rewind($emailFile);
            $fstat = fstat($emailFile);
            $size = $fstat['size'];

            //Envia e-mail
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'smtp://mail.tools.wmflabs.org:587');
            curl_setopt($ch, CURLOPT_MAIL_FROM, get_current_user()."@tools.wmflabs.org");
            curl_setopt($ch, CURLOPT_MAIL_RCPT, array($email));
            curl_setopt($ch, CURLOPT_INFILE, $emailFile);
            curl_setopt($ch, CURLOPT_INFILESIZE, $size);
            curl_setopt($ch, CURLOPT_UPLOAD, true);
            curl_exec($ch);
            fclose($emailFile);
            curl_close($ch);

            //Gera resultado
            $status = §('recover-sent');
            $input['token'] = true;
            $input['password'] = true;

        } else {

            //Gera erro
            $status = §('recover-notfound');
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
        mysqli_stmt_bind_param($verify_query, "s", $email);
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
                    $USR->save($email, $_POST['password'], $user_id);

                    //Gera resultado
                    $status = §('recover-success');
                    $reload = true;
                } else {

                    //Gera erro
                    $status = §('recover-invalid');
                    $input['token'] = true;
                }
            } else {

                //Gera erro
                $status = §('recover-expired');
            }
        } else {

            //Gera erro
            $status = §('recover-notrequested');
        }
    }
} else {

    //Formulário inicial
    $status = false;
}



?>
<!DOCTYPE html>
<html lang="pt-br">
    <head>
        <title><?=§('recover-reset')?> - <?=$contest['name'];?></title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="bin/w3.css">
        <link rel="stylesheet" type="text/css" href="bin/color.php?color=<?=@$contest['color'];?>">
    </head>
    <body>
        <header class="w3-container w3-<?=$contest['theme'];?>">
            <h1><?=§('recover-reset')?></h1>
        </header>
        <br>
        <div class="w3-row-padding w3-content" style="max-width:700px">
            <div class="w3-container w3-margin-top w3-card-4">
                <div class="w3-container">
                    <p><?=§('recover-about')?></p>
                </div>
            </div>
            <div class="w3-margin-top w3-card-4">
                <div class="w3-container">
                    <form id="create" method="post">
                        <div class="w3-section">
                            <label>
                                <strong><?=§('recover-email')?></strong>
                            </label>
                            <input
                            class="w3-input w3-border w3-margin-bottom"
                            type="email"
                            placeholder="example@example.com"
                            maxlength="255"
                            name="email"
                            value="<?=$email??''?>"
                            required>

                            <label>
                                <strong><?=§('recover-token')?></strong>
                            </label>
                            <input
                            class="w3-input w3-border w3-margin-bottom"
                            type="text"
                            placeholder="<?=($input['token'])?'Ex: a5b8139ccd22b314d20e53c3ad836242a88f':''?>"
                            maxlength="255"
                            name="token"
                            <?=($input['token'])?'required':'disabled'?>>

                            <label>
                                <strong><?=§('recover-newpassword')?></strong>
                            </label>
                            <input
                            class="w3-input w3-border w3-margin-bottom"
                            type="password"
                            maxlength="255"
                            placeholder="<?=($input['password'])?§('recover-placeholder'):''?>"
                            name="password"
                            <?=($input['password'])?'required':'disabled'?>>

                            <button class="w3-button w3-block w3-<?=$contest['theme'];?> w3-section w3-padding" name="do_create" type="submit"><?=§('recover-send')?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php if ($status): ?>
            <script>alert('<?=$status?>');</script>
            <?php if (isset($reload)): ?>
                <script>
                    window.location.replace('index.php?lang=<?=$lang?>&contest=<?=$contest['name_id']?>');
                </script>
            <?php endif; ?>
        <?php endif; ?>
    </body>
</html>

