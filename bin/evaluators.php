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
        die("Ação não permitida. Não é gestor do concurso.");
    }

    //Escapa nome de usuário submetido no formulário, ou encerra script caso nenhum nome tenha sido submetido
    if (
        !isset($_POST['user']) && (
            !isset($_POST['on']) ||
            !isset($_POST['off'])
        )
    ) {
        die("Nome de usuário não submetido no formulário.");
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
        <title>Avaliadores - <?=$contest['name'];?></title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="bin/w3.css">
        <link rel="stylesheet" type="text/css" href="bin/color.php?color=<?=@$contest['color'];?>">
    </head>
    <body>
        <header class="w3-container w3-<?=$contest['theme'];?>">
            <h1>Avaliadores - <?=$contest['name'];?></h1>
        </header>
        <br>
        <div class="w3-row-padding w3-content" style="max-width:700px">
            <div class="w3-container w3-margin-top w3-card-4">
                <div class="w3-container">
                    <p>
                        Essa página lista os gestores e os avaliadores deste wikiconcurso.
                        Ao gestor do concurso também são exibidas opções para habilitar ou desabilitar avaliadores.
                    </p>
                </div>
            </div>
            <div class="w3-margin-top w3-card">
                <header style="filter: hue-rotate(60deg);" class='w3-container w3-<?=$contest['theme'];?>'>
                    <h1>Gestores</h1>
                </header>
                <div class="w3-container">
                    <ul class="w3-ul">
                        <?php foreach ($output["evaluators"]["G"] ?? array() as $user => $data) {
                            echo '<li class="w3-bar">';
                                echo $icon;
                                echo '<div class="w3-bar-item">';
                                    echo "<span class='w3-large'>{$user}</span><br>";
                                    echo "<span>{$data['email']}</span><br>";
                                    echo "<span>{$data['evaluated']} avaliações efetuadas</span>";
                                echo '</div>';
                            echo '</li>';
                        }?>
                    </ul>
                </div>
            </div>
            <div class="w3-margin-top w3-card">
                <header style="filter: hue-rotate(120deg);" class='w3-container w3-<?=$contest['theme'];?>'>
                    <h1>Avaliadores</h1>
                </header>
                <div class="w3-container">
                    <ul class="w3-ul">
                        <?php foreach ($output["evaluators"]["A"] ?? array() as $user => $data) {
                            echo '<li class="w3-bar">';
                                echo $icon;
                                echo '<div class="w3-bar-item">';
                                    echo "<span class='w3-large'>{$user}</span><br>";
                                    echo "<span>{$data['email']}</span><br>";
                                    echo "<span>{$data['evaluated']} avaliações efetuadas</span>";
                                echo '</div>';
                                if ($_SESSION['user']["user_status"] == 'G') {
                                    echo '<form method="post">';
                                        echo "<input type='hidden' name='off' value='1'>";
                                        echo "<input type='hidden' name='user' value='{$user}'>";
                                        echo "<button
                                                type='submit'
                                                onclick=\"return confirm('Tem certeza?')\"
                                                class='w3-bar-item w3-right w3-button w3-section w3-red'
                                                >Desabilitar</button>";
                                    echo '</form>';
                                }
                            echo '</li>';
                        }?>
                    </ul>
                </div>
            </div>
            <div class="w3-margin-top w3-card">
                <header style="filter: hue-rotate(180deg);" class='w3-container w3-<?=$contest['theme'];?>'>
                    <h1>Desabilitados</h1>
                </header>
                <div class="w3-container">
                    <ul class="w3-ul">
                        <?php foreach ($output["evaluators"]["P"] ?? array() as $user => $data) {
                            echo '<li class="w3-bar">';
                                echo $icon;
                                echo '<div class="w3-bar-item">';
                                    echo "<span class='w3-large'>{$user}</span><br>";
                                    echo "<span>{$data['email']}</span><br>";
                                    echo "<span>{$data['evaluated']} avaliações efetuadas</span>";
                                echo '</div>';
                                if ($_SESSION['user']["user_status"] == 'G') {
                                    echo '<form method="post">';
                                        echo "<input type='hidden' name='on' value='1'>";
                                        echo "<input type='hidden' name='user' value='{$user}'>";
                                        echo "<button
                                                type='submit'
                                                onclick=\"return confirm('Tem certeza?')\"
                                                class='w3-bar-item w3-right w3-button w3-section w3-green'
                                                >Habilitar</button>";
                                    echo '</form>';
                                }
                            echo '</li>';
                        }?>
                    </ul>
                </div>
            </div>
        </div>
    </body>
    <?php
    if (isset($output['success'])) {
        if (is_null($output['success']['diff'])) {
            echo "<script>alert('Status do avaliador autalizado com sucesso!');";
            echo "window.location.href = window.location.href;</script>";
        } else {
            echo "<script>alert('Erro ao atualizar status do avaliador');</script>";
        }
    }
    ?>
</html>
