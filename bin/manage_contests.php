<?php
//Protetor de login
require_once "protect.php";

if ($_POST) {

    //Valida código interno submetido
    preg_match('/^[a-z_]{1,30}$/', $_POST['name_id'], $name_id);
    if (!isset($name_id['0'])) die("Erro: Código interno inválido!");
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
    if ($exist_result["count"] !== 0) die("Erro: Tabelas já existem!");

    //Insere linha com informações do concurso
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

    //Executa query
    $_POST['start_time'] = date('Y-m-d\TH:i:s', strtotime($_POST['start_time']));
    $_POST['end_time']   = date('Y-m-d\TH:i:s', strtotime($_POST['end_time']));
    if ($_POST['category_petscan'] == '0') {
        $_POST['category_pageid'] = null;
    }
    if ($_POST['minimum_bytes'] == '0') {
        $_POST['minimum_bytes'] = null;
    }
    if ($_POST['pictures_mode'] != '2') {
        $_POST['max_pic_per_article'] = null;
    }
    if ($_POST['theme'] != 'color') {
        $_POST['color'] = null;
    }

    //Verifica se linha foi inserida com sucesso
    mysqli_stmt_execute($create_query);
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
            `user` tinytext COLLATE utf8mb4_unicode_ci,
            `bytes` int(11) DEFAULT NULL,
            `summary` text COLLATE utf8mb4_unicode_ci,
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
            PRIMARY KEY (`n`),
            UNIQUE KEY `user` (`user`)
        ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
    );

    //Verifica novamente se tabelas existem
    mysqli_stmt_execute($exist_query);
    $exist_result = mysqli_fetch_assoc(mysqli_stmt_get_result($exist_query));
    if ($exist_result["count"] !== 4) {
        die("Erro: Erro na criação das tabelas!");
    }

    //Extrai nome do gestor via e-mail
    if (filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $email =  filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    } else {
        die("Erro: E-mail inválido!");
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
            (?,?,?)";
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
    $message  = "Oi!\n";
    $message .= "Um novo wikiconcurso ({$_POST['name']}) foi criado e seu e-mail foi cadastrado como gestor ou gestora.\n";
    $message .= "Para acessar, utilize seu e-mail e a seguinte senha: {$password}\n";
    $message .= "Caso queira, a senha pode ser alterada ao clicar em 'Esqueci a senha' na tela de login.\n";
    $message .= "Para mais detalhes, consulte nosso manual na wiki do GitHub.\n\n";
    $message .= "Atenciosamente,\nWikiconcursos";
    $emailFile = fopen("php://temp", 'w+');
    $subject = "Wikiconcursos - Novo concurso cadastrado";
    fwrite($emailFile, "Subject: " . $subject . "\n" . $message);
    rewind($emailFile);
    $fstat = fstat($emailFile);
    $size = $fstat['size'];

    //Envia e-mail ao gestor
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'smtp://mail.tools.wmflabs.org:587');
    curl_setopt($ch, CURLOPT_MAIL_FROM, "tools.wikiconcursos@tools.wmflabs.org");
    curl_setopt($ch, CURLOPT_MAIL_RCPT, array($email));
    curl_setopt($ch, CURLOPT_INFILE, $emailFile);
    curl_setopt($ch, CURLOPT_INFILESIZE, $size);
    curl_setopt($ch, CURLOPT_UPLOAD, true);
    curl_exec($ch);
    fclose($emailFile);
    curl_close($ch);

    //Retorna mensagem final
    echo "<script>alert('Concurso criado com sucesso!');window.location.href = window.location.href;</script>";
}

?>
<!DOCTYPE html>
<html lang="pt-br">
    <head>
        <title>Gerenciamento de concursos</title>
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
        </script>
    </head>
    <body>
        <header class="w3-container w3-deep-green">
            <h1>Gerenciamento de concursos</h1>
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
                <header class='w3-container w3-black'>
                    <h1>Criar novo wikiconcurso</h1>
                </header>
                <div class="w3-container">
                    <form id="create" method="post">
                        <div class="w3-section">

                            <label>
                                <strong>Nome do concurso</strong>
                            </label>
                            <input
                            class="w3-input w3-border w3-margin-bottom"
                            type="text"
                            placeholder="Insira o nome completo do concurso"
                            maxlength="255"
                            name="name"
                            required>

                            <label>
                                <strong>Código interno</strong>
                            </label>
                            <input
                            class="w3-input w3-border w3-margin-bottom"
                            type="text"
                            placeholder="Utilize somente letras minúsculas e underlines"
                            maxlength="30"
                            pattern="[a-z0_]{1,30}"
                            name="name_id"
                            required>

                            <label>
                                <strong>Horário de início</strong>
                            </label>
                            <input
                            class="w3-input w3-border w3-margin-bottom"
                            type="datetime-local"
                            name="start_time"
                            step="1"
                            required>

                            <label>
                                <strong>Horário de término</strong>
                            </label>
                            <input
                            class="w3-input w3-border w3-margin-bottom"
                            type="datetime-local"
                            name="end_time"
                            step="1"
                            required>

                            <label>
                                <strong>Endereço do endpoint</strong>
                            </label>
                            <input
                            class="w3-input w3-border w3-margin-bottom"
                            type="url"
                            placeholder="https://.../w/index.php"
                            name="endpoint"
                            required>

                            <label>
                                <strong>Endereço do API</strong>
                            </label>
                            <input
                            class="w3-input w3-border w3-margin-bottom"
                            type="url"
                            placeholder="https://.../w/api.php"
                            name="api_endpoint"
                            required>

                            <div class="w3-row">
                                <div class="w3-half" style="padding-right: 8px;">

                                    <label>
                                        <strong>Tempo de reversão em horas</strong>
                                    </label>
                                    <input
                                    class="w3-input w3-border w3-margin-bottom"
                                    type="number"
                                    min="0"
                                    max="99"
                                    value="24"
                                    name="revert_time"
                                    required>

                                    <label>
                                        <strong>PetScan ID dos artigos</strong>
                                    </label>
                                    <input
                                    class="w3-input w3-border w3-margin-bottom"
                                    type="number"
                                    maxlenght="10"
                                    name="category_petscan"
                                    id="category_petscan"
                                    onclick="document.getElementById('category_pageid').value = '';"
                                    >

                                </div>
                                <div class="w3-half" style="padding-left: 8px;">

                                    <label>
                                        <strong>ID da lista de artigos</strong>
                                    </label>
                                    <input
                                    class="w3-input w3-border w3-margin-bottom"
                                    type="number"
                                    maxlenght="10"
                                    name="official_list_pageid"
                                    required>

                                    <label>
                                        <strong>ID da categoria de artigos</strong>
                                    </label>
                                    <input
                                    class="w3-input w3-border w3-margin-bottom"
                                    type="number"
                                    maxlenght="10"
                                    id="category_pageid"
                                    name="category_pageid"
                                    onclick="document.getElementById('category_petscan').value = '';"
                                    >

                                </div>
                            </div>

                            <label>
                                <strong>Nome do concurso no Outreach Dashboard</strong>
                            </label>
                            <input
                            class="w3-input w3-border w3-margin-bottom"
                            type="text"
                            placeholder="Nome_da_campanha/Nome_do_programa"
                            name="outreach_name"
                            required>

                            <div class="w3-row">
                                <div class="w3-half" style="padding-right: 8px;">

                                    <label>
                                        <strong>Bytes por ponto</strong>
                                    </label>
                                    <input
                                    class="w3-input w3-border w3-margin-bottom"
                                    type="number"
                                    min="1"
                                    max="999999999"
                                    name="bytes_per_points"
                                    required>

                                    <label>
                                        <strong>Máximo de bytes por artigo-participante</strong>
                                    </label>
                                    <input
                                    class="w3-input w3-border w3-margin-bottom"
                                    type="number"
                                    min="1"
                                    max="999999999"
                                    name="max_bytes_per_article"
                                    required>

                                    <label>
                                        <strong>Mínimo de bytes por edição</strong>
                                    </label>
                                    <input
                                    class="w3-input w3-border w3-margin-bottom"
                                    type="number"
                                    min="-1"
                                    max="999999999"
                                    name="minimum_bytes"
                                    >

                                </div>
                                <div class="w3-half" style="padding-left: 8px;">

                                    <label>
                                        <strong>Imagens por ponto</strong>
                                    </label>
                                    <input
                                    class="w3-input w3-border w3-margin-bottom"
                                    type="number"
                                    min="0"
                                    max="999999999"
                                    name="pictures_per_points"
                                    required>

                                    <label>
                                        <strong>Máximo de imagens por artigo-participante</strong>
                                    </label>
                                    <input
                                    class="w3-input w3-border w3-margin-bottom"
                                    type="number"
                                    min="0"
                                    max="999999999"
                                    name="max_pic_per_article"
                                    required>

                                    <label>
                                        <strong>Modo de imagem</strong>
                                    </label>
                                    <select
                                    name="pictures_mode"
                                    class="w3-select w3-border w3-margin-bottom"
                                    required>
                                        <option value="0">Por artigo</option>
                                        <option value="1">Por edição</option>
                                        <option value="2">Por imagem</option>
                                    </select>

                                </div>
                            </div>

                            <div class="w3-row">
                                <div class="w3-half" style="padding-right: 8px;">
                                    <label>
                                        <strong>Tema</strong>
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
                                        <option value="color" class="w3-transparent">personalizado</option>
                                    </select>
                                </div>
                                <div class="w3-half" style="padding-left: 8px;">
                                    <label>
                                        <strong>Cor personalizada (hex)</strong>
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

                            <label>
                                <strong>E-mail do gestor</strong>
                            </label>
                            <input
                            class="w3-input w3-border w3-margin-bottom"
                            type="email"
                            placeholder="example@example.com"
                            name="email"
                            required>

                            <button
                            class="w3-button w3-block w3-deep-green w3-section w3-padding"
                            name="do_create"
                            type="submit"
                            >Cadastrar</button>
                        </div>
                    </form>
                </div>
            </div>

            <?php
            foreach ($contests_array as $name_id => $contest_info) {
                echo '<div class="w3-margin-top w3-card">';
                    echo "<header
                            class='w3-container w3-{$contest_info['theme']}'
                            style='color: #fff; background-color: #".($contest_info['color'] ?? 'fff')."'
                            >";
                        echo "<h1>{$contest_info['name']}</h1>";
                    echo "</header>";
                    echo '<div class="w3-container">';
                        echo '<ul class="w3-ul">';
                            echo '<li class="w3-bar">';
                                echo '<div class="w3-bar-item">';
                                    echo "Começa(ou) em {$contest_info['start_time']}: ";
                                    echo date('d/m/Y H:i:s (\U\T\C)', $contest_info['start_time']);
                                    echo '<br>';
                                    echo "Termina(ou) em {$contest_info['end_time']}: ";
                                    echo date('d/m/Y H:i:s (\U\T\C)', $contest_info['end_time']);
                                echo '</div>';
                            echo '</li>';
                            echo '<li class="w3-bar">';
                                echo '<div class="w3-bar-item">';
                                    echo "Lista de artigos em ID <a
                                    href='{$contest_info['endpoint']}?curid={$contest_info['official_list_pageid']}'
                                    >{$contest_info['official_list_pageid']}</a>";
                                    echo '<br>';
                                    if (isset($contest_info['category_petscan'])) {
                                        echo "Busca do PetScan ID em <a
                                        href='https://petscan.wmflabs.org/?psid={$contest_info['category_petscan']}'
                                        >{$contest_info['category_petscan']}</a>";
                                    } else {
                                        echo "Categoria de artigos em ID <a
                                        href='{$contest_info['endpoint']}?curid={$contest_info['category_pageid']}'
                                        >{$contest_info['category_pageid']}</a>";
                                    }
                                echo '</div>';
                            echo '</li>';
                            echo '<li class="w3-bar">';
                                echo '<div class="w3-bar-item">';
                                    echo "Endpoint principal: {$contest_info['endpoint']}";
                                    echo '<br>';
                                    echo "Endpoint da API: {$contest_info['api_endpoint']}";
                                echo '</div>';
                            echo '</li>';
                            echo '<li class="w3-bar">';
                                echo '<div class="w3-bar-item" style="word-break: break-word;">';
                                    echo "Outreach: {$contest_info['outreach_name']}";
                                echo '</div>';
                            echo '</li>';
                            echo '<li class="w3-bar">';
                                echo '<div class="w3-bar-item">';
                                    echo 'Bytes por ponto: ';
                                    echo $contest_info['bytes_per_points'];

                                    echo '<br>';

                                    echo 'Máximo de bytes por artigo-participante: ';
                                    echo $contest_info['max_bytes_per_article'];

                                    echo '<br>';

                                    echo 'Mínimo de bytes por edição: ';
                                    echo $contest_info['minimum_bytes'] ?? '0';
                                echo '</div>';
                            echo '</li>';
                            echo '<li class="w3-bar">';
                                echo '<div class="w3-bar-item">';
                                    echo 'Imagens por ponto: ';
                                    echo $contest_info['pictures_per_points'];

                                    echo '<br>';

                                    echo 'Máximo de imagens por artigo-participante: ';
                                    echo $contest_info['max_pic_per_article'] ?? '0';

                                    echo '<br>';

                                    echo 'Modo de imagem: ';
                                    echo $contest_info['pictures_mode'];
                                echo '</div>';
                            echo '</li>';
                            echo '<li class="w3-bar">';
                                echo '<div class="w3-bar-item">';
                                    echo 'Paleta de cor: ';
                                    echo "<div
                                        class='w3-{$contest_info['theme']}'
                                        style='
                                            display: inline-block;
                                            height: 20px;
                                            width: 20px;
                                            margin-bottom: -4px;
                                            border: 1px solid black;
                                            clear: both;
                                            color: #fff;
                                            background-color: #".($contest_info['color'] ?? 'fff')."
                                        '></div> ";
                                    echo $contest_info['color'] ?? $contest_info['theme'];

                                    echo '<br>';

                                    echo 'Tempo de reversão em horas: ';
                                    echo $contest_info['revert_time'];

                                    echo '<br>';

                                    echo 'Código interno: ';
                                    echo $contest_info['name_id'];
                                echo '</div>';
                            echo '</li>';
                        echo '</ul>';
                    echo '</div>';
                echo '</div>';
            } ?>
        </div>
    </body>
</html>
