<?php
//Protetor de login
require_once "protect.php";

//Função para validar informações submetidas via formulário
function processFormData()
{
    $_POST['start_time'] = date('Y-m-d\TH:i:s', strtotime($_POST['start_time']));
    $_POST['end_time']   = date('Y-m-d\TH:i:s', strtotime($_POST['end_time']));
    if ($_POST['source'] == "petscan") {
        $_POST['category_petscan'] = $_POST['sourceid'];
        $_POST['category_pageid'] = null;
    } else {
        $_POST['category_pageid'] = $_POST['sourceid'];
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
    if ($_SESSION['user']["user_group"] !== "ALL") {
        $_POST['group'] = $_SESSION['user']["user_group"];
    }
}

//Processa formulário
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
    processFormData();

    //Prepara e executa query
    $create_statement =
        "INSERT INTO
            `manage__contests` (
                `name_id`,
                `start_time`,
                `end_time`,
                `name`,
                `group`,
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
        VALUES ( ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ? )";
    $create_query = mysqli_prepare($con, $create_statement);
    mysqli_stmt_bind_param(
        $create_query,
        "ssssiiiisssiiiiiiss",
        $_POST['name_id'],
        $_POST['start_time'],
        $_POST['end_time'],
        $_POST['name'],
        $_POST['group'],
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
} elseif (isset($_POST['do_restart']) || isset($_POST['do_delete']) || isset($_POST['do_edit'])) {

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

    } elseif (isset($_POST['do_edit'])) {

        //Valida informações submetidas via formulário
        processFormData();

        //Prepara e executa query
        $update_statement =
            "UPDATE 
                `manage__contests`
            SET 
                `start_time` = ?,
                `end_time` = ?,
                `name` = ?,
                `group` = ?,
                `revert_time` = ?,
                `official_list_pageid` = ?,
                `category_pageid` = ?,
                `category_petscan` = ?,
                `endpoint` = ?,
                `api_endpoint` = ?,
                `outreach_name` = ?,
                `bytes_per_points` = ?,
                `max_bytes_per_article` = ?,
                `minimum_bytes` = ?,
                `pictures_per_points` = ?,
                `pictures_mode` = ?,
                `max_pic_per_article` = ?,
                `theme` = ?,
                `color` = ?
            WHERE 
                `name_id` = ?";
        $update_query = mysqli_prepare($con, $update_statement);
        mysqli_stmt_bind_param(
            $update_query,
            "ssssiiiisssiiiiiisss",
            $_POST['start_time'],
            $_POST['end_time'],
            $_POST['name'],
            $_POST['group'],
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
            $_POST['color'],
            $_POST['name_id']
        );
        mysqli_stmt_execute($update_query);

        //Verifica se linha foi inserida com sucesso
        if (mysqli_stmt_affected_rows($update_query) != 1) {
            printf("Erro: %s.\n", mysqli_stmt_error($update_query));
            die();
        } else {
            //Retorna mensagem final
            echo "<script>alert('".§('modify-success')."');window.location.href = window.location.href;</script>";
        }
    }
}

//Adiciona novo item na array para criar campo em branco para cadastro
$contests_array[]['name'] = null;

?>
<!DOCTYPE html>
<html lang="pt-br">
    <head>
        <title><?=§('manage-title')?></title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="bin/w3.css">
        <script type="text/javascript">
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

            function colorChange(id) {
                var form = document.getElementById(id);
                var sourceSelect = form.querySelector('#theme');
                var colorInput = form.querySelector('#hex');

                var selectedOption = sourceSelect.options[sourceSelect.selectedIndex].value;

                if (selectedOption === "color") {
                    colorInput.disabled = false;
                    colorInput.required = true;
                } else {
                    colorInput.value = '';
                    colorInput.disabled = true;
                    colorInput.required = false;
                }
            }

            function editChange(id) {
                var form = document.getElementById(id);
                var editButton = form.querySelector('#editor');
                var saveButton = form.querySelector('#saver');
                var group = form.querySelector('#group');
                var code = form.querySelector('#internalcode');
                var inputs = form.querySelectorAll('input');
                var selects = form.querySelectorAll('select');

                editButton.style.display = 'none';
                saveButton.style.display = 'block';

                for (var i = 0; i < inputs.length; i++) {
                    inputs[i].removeAttribute('disabled');
                }

                for (var j = 0; j < selects.length; j++) {
                    selects[j].removeAttribute('disabled');
                }

                if ('<?=$_SESSION['user']["user_group"]?>' !== 'ALL') {
                    group.readOnly = true;
                }

                code.readOnly = true;

                sourceChange(id);
                colorChange(id);
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
            <?php foreach ($contests_array as $name_id => $contest_info): ?>
                <?php if (
                    $_SESSION['user']["user_group"] !== "ALL" &&           
                    $_SESSION['user']["user_group"] !== $contest_info['group'] &&
                    $contest_info['name'] !== null
                ) continue; ?>
                <div class="w3-margin-top w3-card w3-section">
                    <header
                    class='w3-container w3-<?=$contest_info['theme']??'black'?>'
                    style='color: #fff; background-color: #<?=($contest_info['color']??'fff')?>'
                    >
                        <h1><?=$contest_info['name']??§('manage-newcontest')?></h1>
                    </header>
                    <div class="w3-container">
                        <form id="<?=($contest_info['name']==null)?'create':$contest_info['name_id']?>" method="post">
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
                                value="<?=$contest_info['name']?>"
                                <?=($contest_info['name']==null)?'required':'disabled'?>>

                                <div class="w3-row">
                                    <div class="w3-threequarter" style="padding-right: 8px;">
                                
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
                                        value="<?=$contest_info['name_id']?>"
                                        <?=($contest_info['name']==null)?'required':'disabled'?>>

                                    </div>
                                    <div class="w3-quarter" style="padding-right: 8px;">

                                        <label for="group">
                                            <strong><?=§('manage-group')?></strong>
                                        </label>
                                        <input
                                        class="w3-input w3-border w3-margin-bottom"
                                        id="group"
                                        type="text"
                                        placeholder="WMF"
                                        maxlength="30"
                                        name="group"
                                        value="<?=$contest_info['group']??(($_SESSION['user']["user_group"]!=='ALL')?$_SESSION['user']["user_group"]:'')?>"
                                        <?=($contest_info['group']==null&&$_SESSION['user']["user_group"]==='ALL')?'required':'disabled'?>>

                                    </div>
                                </div>

                                <label for="starttime">
                                    <strong><?=§('manage-starttime')?></strong>
                                </label>
                                <input
                                class="w3-input w3-border w3-margin-bottom"
                                id="starttime"
                                type="datetime-local"
                                name="start_time"
                                step="1"
                                value="<?=(isset($contest_info['start_time']))?date('Y-m-d\TH:i:s', $contest_info['start_time']):''?>"
                                <?=($contest_info['name']==null)?'required':'disabled'?>>

                                <label for="endtime">
                                    <strong><?=§('manage-endtime')?></strong>
                                </label>
                                <input
                                class="w3-input w3-border w3-margin-bottom"
                                id="endtime"
                                type="datetime-local"
                                name="end_time"
                                step="1"
                                required
                                value="<?=(isset($contest_info['end_time']))?date('Y-m-d\TH:i:s', $contest_info['end_time']):''?>"
                                <?=($contest_info['name']==null)?'required':'disabled'?>>

                                <label for="endpoint">
                                    <strong><?=§('manage-endpoint')?></strong>
                                </label>
                                <input
                                class="w3-input w3-border w3-margin-bottom"
                                id="endpoint"
                                type="url"
                                placeholder="https://.../w/index.php"
                                name="endpoint"
                                value="<?=$contest_info['endpoint']?>"
                                <?=($contest_info['name']==null)?'required':'disabled'?>>

                                <label for="api">
                                    <strong><?=§('manage-api')?></strong>
                                </label>
                                <input
                                class="w3-input w3-border w3-margin-bottom"
                                id="api"
                                type="url"
                                placeholder="https://.../w/api.php"
                                name="api_endpoint"
                                value="<?=$contest_info['api_endpoint']?>"
                                <?=($contest_info['name']==null)?'required':'disabled'?>>

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
                                        value="<?=$contest_info['revert_time']?>"
                                        <?=($contest_info['name']==null)?'required':'disabled'?>>

                                        <label for="source">
                                            <strong><?=§('manage-source')?></strong>
                                        </label>
                                        <select
                                        id="source"
                                        name="source"
                                        class="w3-select w3-border w3-margin-bottom"
                                        onchange="sourceChange('<?=($contest_info['name']==null)?'create':$contest_info['name_id']?>')"
                                        <?=($contest_info['name']==null)?'required':'disabled'?>>
                                        <?php if (isset($contest_info['category_petscan'])): ?>
                                            <option value="category"><?=§('manage-catid')?></option>
                                            <option value="petscan" selected><?=§('manage-petscan')?></option>
                                        <?php else: ?>
                                            <option value="category" selected><?=§('manage-catid')?></option>
                                            <option value="petscan"><?=§('manage-petscan')?></option>
                                        <?php endif; ?>
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
                                        value="<?=$contest_info['official_list_pageid']?>"
                                        <?=($contest_info['name']==null)?'required':'disabled'?>>

                                        <label for="sourceid">
                                            <strong><?=(isset($contest_info['category_petscan']))?§('manage-petscan'):§('manage-catid')?></strong>
                                        </label>
                                        <input
                                        class="w3-input w3-border w3-margin-bottom"
                                        id="sourceid"   
                                        type="number"
                                        maxlenght="10"
                                        id="sourceid"
                                        name="sourceid"
                                        value="<?=(isset($contest_info['category_petscan']))?$contest_info['category_petscan']:$contest_info['category_pageid']?>"
                                        <?=($contest_info['name']==null)?'required':'disabled'?>>

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
                                value="<?=$contest_info['outreach_name']?>"
                                <?=($contest_info['name']==null)?'required':'disabled'?>>

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
                                        value="<?=$contest_info['bytes_per_points']?>"
                                        <?=($contest_info['name']==null)?'required':'disabled'?>>

                                        <label for="maxbytes">
                                            <strong><?=§('manage-maxbytes')?></strong>
                                        </label>
                                        <input
                                        class="w3-input w3-border w3-margin-bottom"
                                        id="maxbytes"   
                                        type="number"
                                        min="0"
                                        max="999999999"
                                        placeholder="0"
                                        name="max_bytes_per_article"
                                        value="<?=$contest_info['max_bytes_per_article']??''?>"
                                        <?=($contest_info['name']==null)?'required':'disabled'?>>

                                        <label for="minbytes">
                                            <strong><?=§('manage-minbytes')?></strong>
                                        </label>
                                        <input
                                        class="w3-input w3-border w3-margin-bottom"
                                        id="minbytes"   
                                        type="number"
                                        min="-1"
                                        placeholder="0"
                                        max="999999999"
                                        name="minimum_bytes"
                                        value="<?=$contest_info['minimum_bytes']??''?>"
                                        <?=($contest_info['name']==null)?'required':'disabled'?>>

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
                                        value="<?=$contest_info['pictures_per_points']??''?>"
                                        <?=($contest_info['name']==null)?'required':'disabled'?>>

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
                                        placeholder="<?=§('triage-indef')?>"
                                        value="<?=$contest_info['max_pic_per_article']??''?>"
                                        <?=($contest_info['name']==null)?'required':'disabled'?>>

                                        <label for="imagemode">
                                            <strong><?=§('manage-imagemode')?></strong>
                                        </label>
                                        <select
                                        name="pictures_mode"
                                        class="w3-select w3-border w3-margin-bottom"
                                        id="imagemode"
                                        <?=($contest_info['name']==null)?'required':'disabled'?>>
                                            <option value="0" <?=($contest_info['pictures_mode']!=0)?:'selected'?>><?=§('manage-perarticle')?></option>
                                            <option value="1" <?=($contest_info['pictures_mode']!=1)?:'selected'?>><?=§('manage-peredit')?></option>
                                            <option value="2" <?=($contest_info['pictures_mode']!=2)?:'selected'?>><?=§('manage-perimage')?></option>
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
                                        onchange="colorChange('<?=($contest_info['name']==null)?'create':$contest_info['name_id']?>')"
                                        class="w3-select w3-border w3-margin-bottom"
                                        <?=($contest_info['name']==null)?'required':'disabled'?>>
                                            <option value="red" class="w3-red" <?=($contest_info['theme']!='red')?:'selected'?>>red</option>
                                            <option value="pink" class="w3-pink" <?=($contest_info['theme']!='pink')?:'selected'?>>pink</option>
                                            <option value="purple" class="w3-purple" <?=($contest_info['theme']!='purple')?:'selected'?>>purple</option>
                                            <option value="deep-purple" class="w3-deep-purple" <?=($contest_info['theme']!='deep-purple')?:'selected'?>>deep-purple</option>
                                            <option value="indigo" class="w3-indigo" <?=($contest_info['theme']!='indigo')?:'selected'?>>indigo</option>
                                            <option value="blue" class="w3-blue" <?=($contest_info['theme']!='blue')?:'selected'?>>blue</option>
                                            <option value="light-blue" class="w3-light-blue" <?=($contest_info['theme']!='light-blue')?:'selected'?>>light-blue</option>
                                            <option value="cyan" class="w3-cyan" <?=($contest_info['theme']!='cyan')?:'selected'?>>cyan</option>
                                            <option value="aqua" class="w3-aqua" <?=($contest_info['theme']!='aqua')?:'selected'?>>aqua</option>
                                            <option value="teal" class="w3-teal" <?=($contest_info['theme']!='teal')?:'selected'?>>teal</option>
                                            <option value="green" class="w3-green" <?=($contest_info['theme']!='green')?:'selected'?>>green</option>
                                            <option value="light-green" class="w3-light-green" <?=($contest_info['theme']!='light-green')?:'selected'?>>light-green</option>
                                            <option value="lime" class="w3-lime" <?=($contest_info['theme']!='lime')?:'selected'?>>lime</option>
                                            <option value="sand" class="w3-sand" <?=($contest_info['theme']!='sand')?:'selected'?>>sand</option>
                                            <option value="khaki" class="w3-khaki" <?=($contest_info['theme']!='khaki')?:'selected'?>>khaki</option>
                                            <option value="yellow" class="w3-yellow" <?=($contest_info['theme']!='yellow')?:'selected'?>>yellow</option>
                                            <option value="amber" class="w3-amber" <?=($contest_info['theme']!='amber')?:'selected'?>>amber</option>
                                            <option value="orange" class="w3-orange" <?=($contest_info['theme']!='orange')?:'selected'?>>orange</option>
                                            <option value="deep-orange" class="w3-deep-orange" <?=($contest_info['theme']!='deep-orange')?:'selected'?>>deep-orange</option>
                                            <option value="blue-grey" class="w3-blue-grey" <?=($contest_info['theme']!='blue-grey')?:'selected'?>>blue-grey</option>
                                            <option value="brown" class="w3-brown" <?=($contest_info['theme']!='brown')?:'selected'?>>brown</option>
                                            <option value="light-grey" class="w3-light-grey" <?=($contest_info['theme']!='light-grey')?:'selected'?>>light-grey</option>
                                            <option value="grey" class="w3-grey" <?=($contest_info['theme']!='grey')?:'selected'?>>grey</option>
                                            <option value="dark-grey" class="w3-dark-grey" <?=($contest_info['theme']!='dark-grey')?:'selected'?>>dark-grey</option>
                                            <option value="black" class="w3-black" <?=($contest_info['theme']!='black')?:'selected'?>>black</option>
                                            <option value="pale-red" class="w3-pale-red" <?=($contest_info['theme']!='pale-red')?:'selected'?>>pale-red</option>
                                            <option value="pale-yellow" class="w3-pale-yellow" <?=($contest_info['theme']!='pale-yellow')?:'selected'?>>pale-yellow</option>
                                            <option value="pale-green" class="w3-pale-green" <?=($contest_info['theme']!='pale-green')?:'selected'?>>pale-green</option>
                                            <option value="pale-blue" class="w3-pale-blue" <?=($contest_info['theme']!='pale-blue')?:'selected'?>>pale-blue</option>
                                            <option value="color" class="w3-transparent" <?=($contest_info['theme']!='custom')?:'selected'?>><?=§('manage-custom')?></option>
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
                                        value="<?=$contest_info['color']??''?>"
                                        <?=(isset($contest_info['color']))?'required':'disabled'?>>
                                    </div>
                                </div>

                                <?php if ($contest_info['name']==null): ?>
                                    <label for="managemail">
                                        <strong><?=§('manage-managemail')?></strong>
                                    </label>
                                    <input
                                    class="w3-input w3-border w3-margin-bottom"
                                    id="managemail"
                                    type="email"
                                    placeholder="example@example.com"
                                    name="email"
                                    value=""
                                    <?=($contest_info['name']==null)?'required':'disabled'?>>

                                    <button
                                    class="w3-button w3-block w3-deep-green w3-section w3-padding"
                                    name="do_create"
                                    type="submit"
                                    ><?=§('manage-create')?></button>
                                <?php else: ?>
                                    <div class="w3-row">
                                        <div class="w3-third">
                                            <button
                                            class="w3-button w3-orange w3-block w3-rightbar w3-border-light-grey"
                                            name="do_restart"
                                            onclick="return confirm('<?=§('manage-confirmrestart')?>')"
                                            type="submit"><?=§('manage-restart')?></button>
                                        </div>
                                        <div class="w3-third">
                                            <button
                                            class="w3-button w3-blue w3-block"
                                            style="display: block;"
                                            type="button"
                                            id="editor"
                                            onclick="editChange('<?=$contest_info['name_id']?>')"
                                            ><?=§('modify')?></button>
                                            <button
                                            class="w3-button w3-green w3-block"
                                            style="display: none;"
                                            name="do_edit"
                                            id="saver"
                                            type="submit"
                                            ><?=§('triage-save')?></button>
                                        </div>
                                        <div class="w3-third">
                                            <button
                                            class="w3-button w3-red w3-block w3-leftbar w3-border-light-grey"
                                            name="do_delete"
                                            onclick="return confirm('<?=§('manage-confirmdelete')?>')"
                                            type="submit"><?=§('manage-delete')?></button>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </body>
</html>
