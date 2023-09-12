<?php
//Protetor de login
require_once "protect.php";

//Coleta lista de avaliadores
$evaluators_query = mysqli_prepare(
    $con,
    "SELECT
        `{$contest['name_id']}__credentials`.`user_name`,
        `{$contest['name_id']}__credentials`.`user_email`,
        `{$contest['name_id']}__credentials`.`user_status`,
        COUNT(`{$contest['name_id']}__edits`.`by`) AS `evaluated`
    FROM
        `{$contest['name_id']}__credentials`
        LEFT JOIN
            `{$contest['name_id']}__edits`
        ON `{$contest['name_id']}__edits`.`by` = `{$contest['name_id']}__credentials`.`user_name`
    GROUP BY
        `{$contest['name_id']}__credentials`.`user_name`"
);
mysqli_stmt_execute($evaluators_query);
$evaluators_result = mysqli_stmt_get_result($evaluators_query);
if (mysqli_num_rows($evaluators_result) == 0) {
    die("Sem avaliadores");
}

while ($row = mysqli_fetch_assoc($evaluators_result)) {
    $output["evaluators"][$row['user_status']][$row['user_name']] = [
        "email"     => $row['user_email'],
        "status"    => $row['user_status'],
        "evaluated" => $row['evaluated']
    ];
}


//Processa submissão de formulário
if ($_POST) {

    //Encerra script caso usuário não seja gestor
    if ($_SESSION['user']['user_status'] != 'G') {
        die(§('evaluators-denied'));
    }

    //Cria acesso para novo avaliador
    if (isset($_POST['email'])) {

        $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
        if (!$email) {
            echo §("recover-notemail");
            die();
        }

        $password = bin2hex(random_bytes(14));

        require_once "credentials-lib.php";
        $USR->save($email, $password);

        //Cria corpo do e-mail para avaliador
        $message  = "Oi!\n";
        $message .= "Seu e-mail foi cadastrado como avaliador do Wikiconcurso {$contest['name']}.\n";
        $message .= "Para acessar, utilize seu e-mail e a seguinte senha: {$password}\n";
        $message .= "Caso queira, a senha pode ser alterada ao clicar em 'Esqueci a senha' na tela de login.\n";
        $message .= "Para mais detalhes, consulte nosso manual na wiki do GitHub.\n\n";
        $message .= "Atenciosamente,\nWikiScore";
        $emailFile = fopen("php://temp", 'w+');
        $subject = "Wikiconcurso {$contest['name']} - Novo avaliador cadastrado";
        fwrite($emailFile, "Subject: " . $subject . "\n" . $message);
        rewind($emailFile);
        $fstat = fstat($emailFile);
        $size = $fstat['size'];

        //Envia e-mail ao gestor
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

        //Retorna mensagem final
        echo "<script>alert('".§('evaluators-added')."');window.location.href = window.location.href;</script>";
        exit();
    }

    //Escapa nome de usuário submetido no formulário, ou encerra script caso nenhum nome tenha sido submetido
    if (
        !isset($_POST['user']) && (
            !isset($_POST['on']) ||
            !isset($_POST['off'])||
            !isset($_POST['reset'])
        )
    ) {
        die(§('evaluators-missing'));
    }

    //Processa query
    $update_query = mysqli_prepare(
        $con,
        "UPDATE
            `{$contest['name_id']}__credentials`
        SET
            `user_status` = ?
        WHERE
            `user_status` = ?
            AND `user_name` = ?"
    );
    mysqli_stmt_bind_param($update_query, "sss", $after, $before, $_POST['user']);
    if (isset($_POST['off'])) {
        $before = 'A';
        $after  = 'P';
    } elseif (isset($_POST['on'])) {
        $before = 'P';
        $after  = 'A';
    }
    mysqli_stmt_execute($update_query);
    if (mysqli_stmt_affected_rows($update_query) != 0) { $output['success'] = true; }

    if (isset($_POST['reset'])) {
        $reset_query = mysqli_prepare(
            $con,
            "DELETE FROM
                `{$contest['name_id']}__edits`
            WHERE
                `by` = ?"
        );
        mysqli_stmt_bind_param($reset_query, "s", $_POST['user']);
        mysqli_stmt_execute($reset_query);
        if (mysqli_stmt_affected_rows($reset_query) != 0) {
            $refresh_query = mysqli_prepare(
                $con,
                "UPDATE
                    `manage__contests`
                SET
                    `next_update` = NOW()
                WHERE
                    `name_id` = ?"
            );
            mysqli_stmt_bind_param($refresh_query, "s", $contest['name_id']);
            mysqli_stmt_execute($refresh_query);
            if (mysqli_stmt_affected_rows($refresh_query) != 0) {
                $output['reseted'] = true;
            }
        }
    }
}

//Icone
$icon = '
<svg
    class="w3-bar-item"
    width="85"
    height="85"
    stroke-width="1.5"
    viewBox="0 0 24 24"
    fill="none"
    xmlns="http://www.w3.org/2000/svg"
>
<path
    d="M7 18V17C7 14.2386 9.23858 12 12 12V12C14.7614 12 17 14.2386 17 17V18"
    stroke="currentColor"
    stroke-linecap="round"
/>
<path
    d="M12 12C13.6569 12 15 10.6569 15 9C15 7.34315 13.6569 6 12 6C10.3431 6 9 7.34315 9 9C9 10.6569 10.3431 12 12 12Z"
    stroke="currentColor"
    stroke-linecap="round"
    stroke-linejoin="round"
/>
<circle
    cx="12"
    cy="12"
    r="10"
    stroke="currentColor"
    stroke-width="1.5"
/>
</svg>';

//Exibe página
?>
<!DOCTYPE html>
<html lang="pt-br">
    <head>
        <title><?=§('evaluators')?> - <?=$contest['name'];?></title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="bin/w3.css">
        <link rel="stylesheet" type="text/css" href="bin/color.php?color=<?=@$contest['color'];?>">
        <link rel="stylesheet" href="https://tools-static.wmflabs.org/cdnjs/ajax/libs/font-awesome/6.2.0/css/all.css">
    </head>
    <body>
        <?php require_once "sidebar.php"; ?>
        <div class="w3-row-padding w3-content w3-main" style="max-width:800px;margin-top:43px;padding-top:16px;">
            <div class="w3-container w3-margin-top w3-card-4">
                <div class="w3-container">
                    <p><?=§('evaluators-about')?></p>
                </div>
            </div>
            <?php if ($_SESSION['user']["user_status"] == 'G'): ?>
                <div class="w3-margin-top w3-card">
                    <header class='w3-container w3-<?=$contest['theme'];?>'>
                        <h1><?=§('evaluators-neweval')?></h1>
                    </header>
                    <div class="w3-container">
                        <ul class="w3-ul">
                            <li class="w3-bar">
                                <?=$icon?>
                                <form method="post">
                                    <input type="email" placeholder="<?=§('login-email')?>" name="email" 
                                    class="w3-input w3-border w3-bar-item w3-section"
                                    >
                                    <button type='submit' 
                                    class='w3-bar-item w3-right w3-button w3-section w3-<?=$contest['theme'];?>'
                                    >
                                        <?=§('evaluators-register')?>
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>
            <div class="w3-margin-top w3-card">
                <header style="filter: hue-rotate(60deg);" class='w3-container w3-<?=$contest['theme'];?>'>
                    <h1><?=§('evaluators-manager')?></h1>
                </header>
                <div class="w3-container">
                    <ul class="w3-ul">
                        <?php foreach ($output["evaluators"]["G"] ?? array() as $user => $data): ?>
                            <li class="w3-bar">
                                <?=$icon?>
                                <div class="w3-bar-item">
                                    <span class='w3-large'><?=$user?></span><br>
                                    <span><?=$data['email']?></span><br>
                                    <span><?=§('evaluators-stats',$data['evaluated'])?></span>
                                </div>
                                <?php if ($_SESSION['user']["user_status"] == 'G'): ?>
                                    <form method="post">
                                        <input type='hidden' name='reset' value='1'>
                                        <input type='hidden' name='user' value='<?=$user?>'>
                                        <button
                                        type='submit'
                                        onclick="return confirm('<?=§('evaluators-areyousure')?>')"
                                        class='w3-bar-item w3-right w3-button w3-margin w3-red'
                                        ><?=§('counter-redefine')?></button>
                                    </form>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <div class="w3-margin-top w3-card">
                <header style="filter: hue-rotate(120deg);" class='w3-container w3-<?=$contest['theme'];?>'>
                    <h1><?=§('evaluators')?></h1>
                </header>
                <div class="w3-container">
                    <ul class="w3-ul">
                        <?php foreach ($output["evaluators"]["A"] ?? array() as $user => $data): ?>
                            <li class="w3-bar">
                                <?=$icon?>
                                <div class="w3-bar-item">
                                    <span class='w3-large'><?=$user?></span><br>
                                    <span><?=$data['email']?></span><br>
                                    <span><?=§('evaluators-stats',$data['evaluated'])?></span>
                                </div>
                                <?php if ($_SESSION['user']["user_status"] == 'G'): ?>
                                    <form method="post">
                                        <input type='hidden' name='off' value='1'>
                                        <input type='hidden' name='user' value='<?=$user?>'>
                                        <button
                                        type='submit'
                                        onclick="return confirm('<?=§('evaluators-areyousure')?>')"
                                        class='w3-bar-item w3-right w3-button w3-section w3-orange'
                                        ><?=§('evaluators-disable')?></button>
                                    </form>
                                    <form method="post">
                                        <input type='hidden' name='reset' value='1'>
                                        <input type='hidden' name='user' value='<?=$user?>'>
                                        <button
                                        type='submit'
                                        onclick="return confirm('<?=§('evaluators-areyousure')?>')"
                                        class='w3-bar-item w3-right w3-button w3-margin w3-red'
                                        ><?=§('counter-redefine')?></button>
                                    </form>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <div class="w3-margin-top w3-card">
                <header style="filter: hue-rotate(180deg);" class='w3-container w3-<?=$contest['theme'];?>'>
                    <h1><?=§('evaluators-disabled')?></h1>
                </header>
                <div class="w3-container">
                    <ul class="w3-ul">
                        <?php foreach ($output["evaluators"]["P"] ?? array() as $user => $data): ?>
                            <li class="w3-bar">
                                <?=$icon?>
                                <div class="w3-bar-item">
                                    <span class='w3-large'><?=$user?></span><br>
                                    <span><?=$data['email']?></span><br>
                                    <span><?=§('evaluators-stats',$data['evaluated'])?></span>
                                </div>
                                <?php if ($_SESSION['user']["user_status"] == 'G'): ?>
                                    <form method="post">
                                        <input type='hidden' name='on' value='1'>
                                        <input type='hidden' name='user' value='<?=$user?>'>
                                        <button
                                        type='submit'
                                        onclick="return confirm('<?=§('evaluators-areyousure')?>')"
                                        class='w3-bar-item w3-right w3-button w3-section w3-green'
                                        ><?=§('evaluators-enable')?></button>
                                    </form>
                                    <form method="post">
                                        <input type='hidden' name='reset' value='1'>
                                        <input type='hidden' name='user' value='<?=$user?>'>
                                        <button
                                        type='submit'
                                        onclick="return confirm('<?=§('evaluators-areyousure')?>')"
                                        class='w3-bar-item w3-right w3-button w3-margin w3-red'
                                        ><?=§('counter-redefine')?></button>
                                    </form>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </body>
    <?php if (isset($output['reseted'])): ?>
            <script>
                alert('<?=§('counter-success')?>');
                window.location.href = window.location.href;
            </script>
    <?php elseif (isset($output['success'])): ?>
        <?php if (is_null($output['success']['diff'])): ?>
            <script>
                alert('<?=§('evaluators-success')?>');
                window.location.href = window.location.href;
            </script>
        <?php else: ?>
            <script>alert('<?=§('evaluators-error')?>');</script>
        <?php endif; ?>
    <?php endif; ?>
</html>
