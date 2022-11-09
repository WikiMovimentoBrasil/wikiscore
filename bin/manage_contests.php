<?php
//Protetor de login
require_once "protect.php";

?>
<!DOCTYPE html>
<html lang="pt-br">
    <head>
        <title>Gerenciamento de concursos</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="bin/w3.css">
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
                                echo '</div>';
                            echo '</li>';
                        echo '</ul>';
                    echo '</div>';
                echo '</div>';
            } ?>
        </div>
    </body>
</html>