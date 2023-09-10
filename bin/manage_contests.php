<?php
//Protetor de login
require_once "protect.php";

if (isset($_POST['do_create'])) {

    //Valida código interno submetido
    preg_match('/^[a-z_]{1,30}$/', $_POST['name_id'], $name_id);
    if (!isset($name_id['0'])) die(§('manage-invalidcode'));
    $name_id = $name_id['0'];

    //Verifica se tabelas existem
    $exist_statement =
        "SELECT
            COUNT(`table_name`) AS `count`
        FROM
            information_schema.tables
        WHERE
            `table_schema` = ? AND
            `table_name` LIKE ?";
    $exist_query = mysqli_prepare($con, $exist_statement);
    mysqli_stmt_bind_param(
        $exist_query,
        "ss",
        $database,
        $name_id_like
    );
    $name_id_like = $name_id.'%';
    mysqli_stmt_execute($exist_query);
    $exist_result = mysqli_fetch_assoc(mysqli_stmt_get_result($exist_query));
    if ($exist_result["count"] !== 0) die(§('manage-alreadyexist'));

    //Valida informações submetidas via formulário
    $_POST['start_time'] = date('Y-m-d\TH:i:s', strtotime($_POST['start_time']));
    $_POST['end_time']   = date('Y-m-d\TH:i:s', strtotime($_POST['end_time']));
    if ($_POST['source'] == "petscan") {
        $_POST['category_petscan'] == $_POST['sourceid'];
        $_POST['category_pageid'] = null;
    } else {
        $_POST['category_pageid'] == $_POST['sourceid'];
        $_POST['category_petscan'] = null;
    }
    if (empty($_POST['minimum_bytes'])) {
        $_POST['minimum_bytes'] = null;
    }
    if ($_POST['pictures_mode'] != '2') {
        $_POST['max_pic_per_article'] = null;
    }
    if ($_POST['theme'] != 'color') {
        $_POST['color'] = null;
    }

    //Prepara e executa query
    $create_statement =
        "INSERT INTO
            `manage__contests` (
                `name_id`,
                `start_time`,
                `end_time`,
                `name`,
                `revert_time`,
                `official_list_pageid`,
                `category_pageid`,
                `category_petscan`,
                `endpoint`,
                `api_endpoint`,
                `outreach_name`,
                `bytes_per_points`,
                `max_bytes_per_article`,
                `minimum_bytes`,
                `pictures_per_points`,
                `pictures_mode`,
                `max_pic_per_article`,
                `theme`,
                `color`
            )
        VALUES ( ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ? )";
    $create_query = mysqli_prepare($con, $create_statement);
    mysqli_stmt_bind_param(
        $create_query,
        "ssssiiiisssiiiiiiss",
        $_POST['name_id'],
        $_POST['start_time'],
        $_POST['end_time'],
        $_POST['name'],
        $_POST['revert_time'],
        $_POST['official_list_pageid'],
        $_POST['category_pageid'],
        $_POST['category_petscan'],
        $_POST['endpoint'],
        $_POST['api_endpoint'],
        $_POST['outreach_name'],
        $_POST['bytes_per_points'],
        $_POST['max_bytes_per_article'],
        $_POST['minimum_bytes'],
        $_POST['pictures_per_points'],
        $_POST['pictures_mode'],
        $_POST['max_pic_per_article'],
        $_POST['theme'],
        $_POST['color']
    );
    mysqli_stmt_execute($create_query);

    //Verifica se linha foi inserida com sucesso
    if (mysqli_stmt_affected_rows($create_query) != 1) {
        printf("Erro: %s.\n", mysqli_stmt_error($create_query));
        die();
    }

    //Cria novas tabelas
    mysqli_query(
        $con,
        "CREATE TABLE IF NOT EXISTS `{$name_id}__articles` (
            `key` int(4) unsigned NOT NULL AUTO_INCREMENT,
            `articleID` mediumint(9) unsigned NOT NULL,
            PRIMARY KEY (`key`),
            UNIQUE KEY `articleID` (`articleID`)
        ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
    );
    mysqli_query(
        $con,
        "CREATE TABLE IF NOT EXISTS `{$name_id}__credentials` (
            `user_id` int(11) NOT NULL AUTO_INCREMENT,
            `user_name` varchar(255) NOT NULL,
            `user_email` varchar(255) NOT NULL,
            `user_password` varchar(128) NOT NULL,
            `user_status` varchar(1) NOT NULL DEFAULT 'P',
            `user_data` text,
            PRIMARY KEY (`user_id`),
            UNIQUE KEY `user_email` (`user_email`),
            KEY `user_name` (`user_name`),
            KEY `user_status` (`user_status`)
        ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;"
    );
    mysqli_query(
        $con,
        "CREATE TABLE IF NOT EXISTS `{$name_id}__edits` (
            `n` int(6) unsigned NOT NULL AUTO_INCREMENT,
            `diff` int(9) unsigned NOT NULL,
            `article` mediumint(8) unsigned NOT NULL DEFAULT '0',
            `timestamp` timestamp NULL DEFAULT NULL,
            `user_id` int(11) NOT NULL,
            `bytes` int(11) DEFAULT NULL,
            `new_page` tinyint(1) unsigned DEFAULT NULL,
            `valid_edit` tinyint(1) unsigned DEFAULT NULL,
            `valid_user` tinyint(1) unsigned DEFAULT NULL,
            `pictures` tinyint(1) unsigned DEFAULT NULL,
            `reverted` tinyint(1) unsigned DEFAULT NULL,
            `by` tinytext COLLATE utf8mb4_unicode_ci,
            `when` timestamp NULL DEFAULT NULL,
            `obs` text NULL DEFAULT NULL,
            PRIMARY KEY (`n`),
            UNIQUE KEY `diff` (`diff`)
        ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
    );
    mysqli_query(
        $con,
        "CREATE TABLE IF NOT EXISTS `{$name_id}__users` (
            `n` int(4) unsigned NOT NULL AUTO_INCREMENT,
            `user` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
            `timestamp` timestamp NULL DEFAULT NULL,
            `global_id` int(11) NOT NULL,
            `local_id` int(11) DEFAULT NULL,
            `attached` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`n`),
            UNIQUE KEY `user` (`user`)
        ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
    );

    //Verifica novamente se tabelas existem
    mysqli_stmt_execute($exist_query);
    $exist_result = mysqli_fetch_assoc(mysqli_stmt_get_result($exist_query));
    if ($exist_result["count"] !== 4) {
        die(§('manage-creationerror'));
    }

    //Extrai nome do gestor via e-mail
    if (filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $email =  filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    } else {
        die(§('manage-wrongemail'));
    }
    $name = strstr($email, "@", true);
    $name = trim($name, "@");

    //Gera senha para gestor
    $password = bin2hex(random_bytes(14));
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $mananger_code = 'G';

    //Insere o gestor no banco de dados
    $mananger_statement =
        "INSERT INTO
            `{$name_id}__credentials` (`user_name`, `user_email`, `user_password`, `user_status`)
        VALUES
            (?,?,?,?)";
    $mananger_query = mysqli_prepare($con, $mananger_statement);
    mysqli_stmt_bind_param(
        $mananger_query,
        "ssss",
        $name,
        $email,
        $hash,
        $mananger_code
    );

    //Verifica se linha foi inserida com sucesso
    mysqli_stmt_execute($mananger_query);
    if (mysqli_stmt_affected_rows($mananger_query) != 1) {
        printf("Erro: %s.\n", mysqli_stmt_error($mananger_query));
        die();
    }

    //Cria corpo do e-mail para gestor
    $message = §('manage-email', $_POST['name'], $password);
    $emailFile = fopen("php://temp", 'w+');
    $subject = §('manage-subject');
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
    echo "<script>alert('".§('manage-created')."');window.location.href = window.location.href;</script>";

//Processo para reiniciar/apagar concurso
} elseif (isset($_POST['do_restart']) || isset($_POST['do_delete'])) {

    //Valida código interno submetido
    preg_match('/^[a-z_]{1,30}$/', $_POST['name_id'], $name_id);
    if (!isset($name_id['0'])) die(§('manage-invalidcode'));
    $name_id = $name_id['0'];

    //Valida se usuário pertence ao grupo relacionado ao concurso
    if (!isset($contests_array[$name_id])) die(§('manage-notfound'));
    if (
        $_SESSION['user']["user_group"] !== "ALL" && 
        $_SESSION['user']["user_group"] !== $contests_array[$name_id]['group']
    ) die(§('manage-unauthorized'));

    //Reinicia concurso
    if (isset($_POST['do_restart'])) {

        //Reinicia tabelas do concurso, mas mantem a tabela de credenciais        
        mysqli_query($con, "TRUNCATE TABLE `{$name_id}__edits`;");
        mysqli_query($con, "TRUNCATE TABLE `{$name_id}__users`;");
        mysqli_query($con, "TRUNCATE TABLE `{$name_id}__articles`;");

        //Retorna mensagem final
        echo "<script>alert('".§('manage-restarted')."');window.location.href = window.location.href;</script>";

    //Apaga concurso
    } elseif (isset($_POST['do_delete'])) {

        //Apaga tabelas do concurso
        mysqli_query($con, "DROP TABLE `{$name_id}__edits`, `{$name_id}__users`, `{$name_id}__articles`, `{$name_id}__credentials`;");

        //Apaga registro na tabela de concursos
        $delete_statement =
            "DELETE FROM
                `manage__contests`
            WHERE
                `name_id` = ?
            LIMIT 1";

        $delete_query = mysqli_prepare($con, $delete_statement);
        mysqli_stmt_bind_param(
            $delete_query,
            "s",
            $name_id
        );
        mysqli_stmt_execute($delete_query);

        //Retorna mensagem final
        echo "<script>alert('".§('manage-deleted')."');window.location.href = window.location.href;</script>";
    }
}

?>
<!DOCTYPE html>
<html lang="pt-br">
    <head>
        <title><?=§('manage-title')?></title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="bin/w3.css">
        <script type="text/javascript">
            document.addEventListener("DOMContentLoaded", function(){
                document.getElementById('theme').onchange = function () {
                    if (this.value == 'color') {
                        document.getElementById("hex").disabled = false;
                    } else {
                        document.getElementById("hex").value = '';
                        document.getElementById("hex").disabled = true;
                    }
                }
            });

            function sourceChange(id) {
                var form = document.getElementById(id);
                var sourceSelect = form.querySelector('#source');
                var label = form.querySelector('label[for="sourceid"]');
                var text = label.querySelector('strong');

                var selectedOption = sourceSelect.options[sourceSelect.selectedIndex].value;

                if (selectedOption === "category") {
                    text.textContent = "<?=§('manage-catid')?>";
                } else if (selectedOption === "petscan") {
                    text.textContent = "<?=§('manage-petscan')?>";
                }
            }
        </script>
    </head>
    <body>
        <header class="w3-container w3-deep-green">
            <h1><?=§('manage-title')?></h1>
        </header>
        <br>
        <div class="w3-row-padding w3-content" style="max-width:700px">
            <div class="w3-container w3-margin-top w3-card-4">
                <div class="w3-container">
                    <p><?=§('manage-about')?></p>
                </div>
            </div>
            <div class="w3-margin-top w3-card-4">
                <header class='w3-container w3-black'>
                    <h1><?=§('manage-newcontest')?></h1>
                </header>
                <div class="w3-container">
                    <form id="create" method="post">
                        <div class="w3-section">

                            <label for="contestname">
                                <strong><?=§('manage-contestname')?></strong>
                            </label>
                            <input
                            class="w3-input w3-border w3-margin-bottom"
                            id="contestname"
                            type="text"
                            placeholder="<?=§('manage-contestnameabout')?>"
                            maxlength="255"
                            name="name"
                            required>

                            <label for="internalcode">
                                <strong><?=§('manage-internalcode')?></strong>
                            </label>
                            <input
                            class="w3-input w3-border w3-margin-bottom"
                            id="internalcode"
                            type="text"
                            placeholder="<?=§('manage-internalcodeabout')?>"
                            maxlength="30"
                            pattern="[a-z0_]{1,30}"
                            name="name_id"
                            required>

                            <label for="starttime">
                                <strong><?=§('manage-starttime')?></strong>
                            </label>
                            <input
                            class="w3-input w3-border w3-margin-bottom"
                            id="starttime"
                            type="datetime-local"
                            name="start_time"
                            step="1"
                            required>

                            <label for="endtime">
                                <strong><?=§('manage-endtime')?></strong>
                            </label>
                            <input
                            class="w3-input w3-border w3-margin-bottom"
                            id="endtime"
                            type="datetime-local"
                            name="end_time"
                            step="1"
                            required>

                            <label for="endpoint">
                                <strong><?=§('manage-endpoint')?></strong>
                            </label>
                            <input
                            class="w3-input w3-border w3-margin-bottom"
                            id="endpoint"
                            type="url"
                            placeholder="https://.../w/index.php"
                            name="endpoint"
                            required>

                            <label for="api">
                                <strong><?=§('manage-api')?></strong>
                            </label>
                            <input
                            class="w3-input w3-border w3-margin-bottom"
                            id="api"
                            type="url"
                            placeholder="https://.../w/api.php"
                            name="api_endpoint"
                            required>

                            <div class="w3-row">
                                <div class="w3-half" style="padding-right: 8px;">

                                    <label for="reverttime">
                                        <strong><?=§('manage-reverttime')?></strong>
                                    </label>
                                    <input
                                    class="w3-input w3-border w3-margin-bottom"
                                    id="reverttime"
                                    type="number"
                                    min="0"
                                    max="99"
                                    value="24"
                                    name="revert_time"
                                    required>

                                    <label for="source">
                                        <strong><?=§('manage-source')?></strong>
                                    </label>
                                    <select
                                    id="source"
                                    name="source"
                                    class="w3-select w3-border w3-margin-bottom"
                                    onchange="sourceChange('create')"
                                    required>
                                        <option value="category" selected><?=§('manage-catid')?></option>
                                        <option value="petscan"><?=§('manage-petscan')?></option>
                                    </select>

                                </div>
                                <div class="w3-half" style="padding-left: 8px;">

                                    <label for="listid">
                                        <strong><?=§('manage-listid')?></strong>
                                    </label>
                                    <input
                                    class="w3-input w3-border w3-margin-bottom"
                                    id="listid"   
                                    type="number"
                                    maxlenght="10"
                                    name="official_list_pageid"
                                    required>

                                    <label for="sourceid">
                                        <strong><?=§('manage-catid')?></strong>
                                    </label>
                                    <input
                                    class="w3-input w3-border w3-margin-bottom"
                                    id="sourceid"   
                                    type="number"
                                    maxlenght="10"
                                    id="sourceid"
                                    name="sourceid"
                                    >

                                </div>
                            </div>

                            <label for="outreach">
                                <strong><?=§('manage-outreach')?></strong>
                            </label>
                            <input
                            class="w3-input w3-border w3-margin-bottom"
                            id="outreach"   
                            type="text"
                            placeholder="<?=§('manage-outreachplacehold')?>"
                            name="outreach_name"
                            required>

                            <div class="w3-row">
                                <div class="w3-half" style="padding-right: 8px;">

                                    <label for="bpp">
                                        <strong><?=§('manage-bpp')?></strong>
                                    </label>
                                    <input
                                    class="w3-input w3-border w3-margin-bottom"
                                    id="bpp"   
                                    type="number"
                                    min="1"
                                    max="999999999"
                                    name="bytes_per_points"
                                    required>

                                    <label for="maxbytes">
                                        <strong><?=§('manage-maxbytes')?></strong>
                                    </label>
                                    <input
                                    class="w3-input w3-border w3-margin-bottom"
                                    id="maxbytes"   
                                    type="number"
                                    min="0"
                                    max="999999999"
                                    name="max_bytes_per_article"
                                    required>

                                    <label for="minbytes">
                                        <strong><?=§('manage-minbytes')?></strong>
                                    </label>
                                    <input
                                    class="w3-input w3-border w3-margin-bottom"
                                    id="minbytes"   
                                    type="number"
                                    min="-1"
                                    max="999999999"
                                    name="minimum_bytes"
                                    >

                                </div>
                                <div class="w3-half" style="padding-left: 8px;">

                                    <label for="ipp">
                                        <strong><?=§('manage-ipp')?></strong>
                                    </label>
                                    <input
                                    class="w3-input w3-border w3-margin-bottom"
                                    id="ipp"   
                                    type="number"
                                    min="0"
                                    max="999999999"
                                    name="pictures_per_points"
                                    required>

                                    <label for="maximages">
                                        <strong><?=§('manage-maximages')?></strong>
                                    </label>
                                    <input
                                    class="w3-input w3-border w3-margin-bottom"
                                    id="maximages"   
                                    type="number"
                                    min="0"
                                    max="999999999"
                                    name="max_pic_per_article"
                                    required>

                                    <label for="imagemode">
                                        <strong><?=§('manage-imagemode')?></strong>
                                    </label>
                                    <select
                                    name="pictures_mode"
                                    class="w3-select w3-border w3-margin-bottom"
                                    id="imagemode"
                                    required>
                                        <option value="0"><?=§('manage-perarticle')?></option>
                                        <option value="1"><?=§('manage-peredit')?></option>
                                        <option value="2"><?=§('manage-perimage')?></option>
                                    </select>

                                </div>
                            </div>

                            <div class="w3-row">
                                <div class="w3-half" style="padding-right: 8px;">
                                    <label for="theme">
                                        <strong><?=§('manage-palette')?></strong>
                                    </label>
                                    <select
                                    name="theme"
                                    id="theme"
                                    class="w3-select w3-border w3-margin-bottom"
                                    required>
                                        <option value="red" class="w3-red">red</option>
                                        <option value="pink" class="w3-pink">pink</option>
                                        <option value="purple" class="w3-purple">purple</option>
                                        <option value="deep-purple" class="w3-deep-purple">deep-purple</option>
                                        <option value="indigo" class="w3-indigo">indigo</option>
                                        <option value="blue" class="w3-blue">blue</option>
                                        <option value="light-blue" class="w3-light-blue">light-blue</option>
                                        <option value="cyan" class="w3-cyan">cyan</option>
                                        <option value="aqua" class="w3-aqua">aqua</option>
                                        <option value="teal" class="w3-teal">teal</option>
                                        <option value="green" class="w3-green">green</option>
                                        <option value="light-green" class="w3-light-green">light-green</option>
                                        <option value="lime" class="w3-lime">lime</option>
                                        <option value="sand" class="w3-sand">sand</option>
                                        <option value="khaki" class="w3-khaki">khaki</option>
                                        <option value="yellow" class="w3-yellow">yellow</option>
                                        <option value="amber" class="w3-amber">amber</option>
                                        <option value="orange" class="w3-orange">orange</option>
                                        <option value="deep-orange" class="w3-deep-orange">deep-orange</option>
                                        <option value="blue-grey" class="w3-blue-grey">blue-grey</option>
                                        <option value="brown" class="w3-brown">brown</option>
                                        <option value="light-grey" class="w3-light-grey">light-grey</option>
                                        <option value="grey" class="w3-grey">grey</option>
                                        <option value="dark-grey" class="w3-dark-grey">dark-grey</option>
                                        <option value="black" class="w3-black">black</option>
                                        <option value="pale-red" class="w3-pale-red">pale-red</option>
                                        <option value="pale-yellow" class="w3-pale-yellow">pale-yellow</option>
                                        <option value="pale-green" class="w3-pale-green">pale-green</option>
                                        <option value="pale-blue" class="w3-pale-blue">pale-blue</option>
                                        <option value="color" class="w3-transparent"><?=§('manage-custom')?></option>
                                    </select>
                                </div>
                                <div class="w3-half" style="padding-left: 8px;">
                                    <label for="hex">
                                        <strong><?=§('manage-hexcolor')?></strong>
                                    </label>
                                    <input
                                    class="w3-input w3-border w3-margin-bottom"
                                    type="text"
                                    placeholder="A0B1C2"
                                    maxlength="99"
                                    pattern="[A-F0-9]{6}"
                                    name="color"
                                    id="hex"
                                    disabled>
                                </div>
                            </div>

                            <label for="managemail">
                                <strong><?=§('manage-managemail')?></strong>
                            </label>
                            <input
                            class="w3-input w3-border w3-margin-bottom"
                            id="managemail"
                            type="email"
                            placeholder="example@example.com"
                            name="email"
                            required>

                            <button
                            class="w3-button w3-block w3-deep-green w3-section w3-padding"
                            name="do_create"
                            type="submit"
                            ><?=§('manage-create')?></button>
                        </div>
                    </form>
                </div>
            </div>
            <?php foreach ($contests_array as $name_id => $contest_info): ?>
                <?php if (
                    $_SESSION['user']["user_group"] !== "ALL" &&           
                    $_SESSION['user']["user_group"] !== $contest_info['group']
                ) continue; ?>
                <div class="w3-margin-top w3-card">
                    <header
                    class='w3-container w3-<?=$contest_info['theme']?>'
                    style='color: #fff; background-color: #<?=($contest_info['color']??'fff')?>'
                    >
                        <h1><?=$contest_info['name']?></h1>
                    </header>
                    <div class="w3-container">
                        <ul class="w3-ul">
                            <li class="w3-bar">
                                <div class="w3-bar-item">
                                    <?=§('manage-startedtime', date(
                                        'Y/m/d H:i:s (\U\T\C)',
                                        $contest_info['start_time']
                                    ))?>
                                    <br>
                                    <?=§('manage-endedtime', date(
                                        'Y/m/d H:i:s (\U\T\C)',
                                        $contest_info['end_time']
                                    ))?>
                                </div>
                            </li>
                            <li class="w3-bar">
                                <div class="w3-bar-item">
                                    <?=§('manage-listedid')?> <a
                                    href='<?=$contest_info['endpoint']?>?curid=<?=$contest_info['official_list_pageid']?>'
                                    ><?=$contest_info['official_list_pageid']?></a>
                                    <br>
                                    <?php if (isset($contest_info['category_petscan'])): ?>
                                        <?=§('manage-petscan')?>: <a
                                        href='https://petscan.wmflabs.org/?psid=<?=$contest_info['category_petscan']?>'
                                        ><?=$contest_info['category_petscan']?></a>
                                    <?php else: ?>
                                        <?=§('manage-catedid')?> <a
                                        href='<?=$contest_info['endpoint']?>?curid=<?=$contest_info['category_pageid']?>'
                                        ><?=$contest_info['category_pageid']?></a>
                                    <?php endif; ?>
                                </div>
                            </li>
                            <li class="w3-bar">
                                <div class="w3-bar-item">
                                    <?=§('manage-endpoint')?>: <?=$contest_info['endpoint']?>
                                    <br>
                                    <?=§('manage-api')?>: <?=$contest_info['api_endpoint']?>
                                </div>
                            </li>
                            <li class="w3-bar">
                                <div class="w3-bar-item" style="word-break: break-word;">
                                    <?=§('manage-outreach')?>: <?=$contest_info['outreach_name']?>
                                </div>
                            </li>
                            <li class="w3-bar">
                                <div class="w3-bar-item">
                                    <?=§('manage-bpp')?>: <?=$contest_info['bytes_per_points']?>
                                    <br>
                                    <?=§('manage-maxbytes')?>: <?=$contest_info['max_bytes_per_article']?>
                                    <br>
                                    <?=§('manage-minbytes')?>: <?=$contest_info['minimum_bytes']??'0'?>
                                </div>
                            </li>
                            <li class="w3-bar">
                                <div class="w3-bar-item">
                                    <?=§('manage-ipp')?>: <?=$contest_info['pictures_per_points']?>
                                    <br>
                                    <?=§('manage-maximages')?>: <?=$contest_info['max_pic_per_article']??'0'?>
                                    <br>
                                    <?=§('manage-imagemode')?>: <?=$contest_info['pictures_mode']?>
                                </div>
                            </li>
                            <li class="w3-bar">
                                <div class="w3-bar-item">
                                    <?=§('manage-palette')?>:
                                    <div
                                    class='w3-<?=$contest_info['theme']?>'
                                    style='
                                        display: inline-block;
                                        height: 20px;
                                        width: 20px;
                                        margin-bottom: -4px;
                                        border: 1px solid black;
                                        clear: both;
                                        color: #fff;
                                        background-color: #<?=($contest_info['color']??'fff')?>
                                    '></div>
                                    <?=$contest_info['color']??$contest_info['theme']?>
                                    <br>
                                    <?=§('manage-reverttime')?>: <?=$contest_info['revert_time']?>
                                    <br>
                                    <?=§('manage-internalcode')?>: <?=$contest_info['name_id']?>
                                </div>
                            </li>
                            <li class="w3-bar">
                                <div class="w3-container">
                                    <form id="alter" method="post">
                                        <input type="hidden" name="name_id" value="<?=$contest_info['name_id']?>">
                                    </form>
                                    <button
                                    class="w3-button w3-orange"
                                    name="do_restart"
                                    form="alter"
                                    onclick="return confirm('<?=§('manage-confirmrestart')?>')"
                                    type="submit"><?=§('manage-restart')?></button>
                                    <button
                                    class="w3-button w3-red w3-right"
                                    name="do_delete"
                                    form="alter"
                                    onclick="return confirm('<?=§('manage-confirmdelete')?>')"
                                    type="submit"><?=§('manage-delete')?></button>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </body>
</html>
