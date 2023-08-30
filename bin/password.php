<?php
//Protetor de login
require_once "protect.php";

//Formulário submetido
if (isset($_POST['oldpass'])) {

    //Verifica senha anterior
    require_once "credentials-lib.php";
    if ($USR->verify($_SESSION['user']['user_email'], $_POST['oldpass'])) {

        //Troca senha
        $USR->save($_SESSION['user']['user_email'], $_POST['newpass'], $_SESSION['user']['user_id']);

        //Gera resultado
        $status = §('recover-success');

    } else {

        //Gera erro
        $status = §('password-wrongpass');
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
                                <strong><?=§('password-username')?></strong>
                            </label>
                            <input
                            class="w3-input w3-border w3-margin-bottom"
                            type="text"
                            maxlength="255"
                            name="username"
                            value="<?=$_SESSION['user']['user_name']?>"
                            disabled>

                            <label>
                                <strong><?=§('password-oldpassword')?></strong>
                            </label>
                            <input
                            class="w3-input w3-border w3-margin-bottom"
                            type="password"
                            maxlength="255"
                            name="oldpass"
                            required>

                            <label>
                                <strong><?=§('recover-newpassword')?></strong>
                            </label>
                            <input
                            class="w3-input w3-border w3-margin-bottom"
                            type="password"
                            maxlength="255"
                            name="newpass"
                            required>

                            <button class="w3-button w3-block w3-<?=$contest['theme'];?> w3-section w3-padding" type="submit"><?=§('recover-send')?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php if ($status): ?>
            <script>alert('<?=$status?>');</script>
        <?php endif; ?>
    </body>
</html>

