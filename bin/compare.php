<?php

//Protetor de login
require "protect.php";

//Coleta informações do concurso
require "data.php";

//Verifica se a lista oficial e a categoria foram definidas
if (isset($contest['official_list_pageid']) AND isset($contest['category_pageid'])) {

    //Define arrays
    $list_cat = array();
    $list_official = array();

    //Coleta lista de artigos na página do concurso
    $list_api_params = [
        "action"        => "query",
        "format"        => "json",
        "prop"          => "links",
        "pageids"       => $contest['official_list_pageid'],
        "plnamespace"   => "0",
        "pllimit"       => "500"
    ];

    $list_api = json_decode(file_get_contents($contest['api_endpoint']."?".http_build_query($list_api_params)), true);
    $listmembers = end($list_api['query']['pages'])['links'];
    foreach ($listmembers as $pagetitle) $list_official[] = $pagetitle['title'];

    //Coleta segunda página da lista, caso exista
    while (isset($list_api['continue'])) {
        $list_api_params = [
            "action"        => "query",
            "format"        => "json",
            "prop"          => "links",
            "pageids"       => $contest['official_list_pageid'],
            "plnamespace"   => "0",
            "pllimit"       => "500",
            "plcontinue"    => $list_api['continue']['plcontinue']
        ];
        $list_api = json_decode(file_get_contents($contest['api_endpoint']."?".http_build_query($list_api_params)), true);
        $listmembers = end($list_api['query']['pages'])['links'];
        foreach ($listmembers as $pagetitle) $list_official[] = $pagetitle['title'];    
    }

    //Coleta lista de artigos na categoria
    $categorymembers_api_params = [
        "action"        => "query",
        "format"        => "json",
        "list"          => "categorymembers",
        "cmnamespace"   => "0",
        "cmpageid"      => $contest['category_pageid'],
        "cmprop"        => "title",
        "cmlimit"       => "500"
    ];
    $categorymembers_api = json_decode(file_get_contents($contest['api_endpoint']."?".http_build_query($categorymembers_api_params)), true);
    foreach ($categorymembers_api['query']['categorymembers'] as $pageid) $list_cat[] = $pageid['title'];

    //Coleta segunda página da categoria, caso exista
    while (isset($categorymembers_api['continue'])) {
        $categorymembers_api_params = [
            "action"        => "query",
            "format"        => "json",
            "list"          => "categorymembers",
            "cmnamespace"   => "0",
            "cmpageid"      => $contest['category_pageid'],
            "cmprop"        => "title",
            "cmlimit"       => "500",
            "cmcontinue"    => $categorymembers_api['continue']['cmcontinue']
        ];
        $categorymembers_api = json_decode(file_get_contents($contest['api_endpoint']."?".http_build_query($categorymembers_api_params)), true);
        foreach ($categorymembers_api['query']['categorymembers'] as $pageid) $list_cat[] = $pageid['title'];
    }

    //Processa listagens
    $adicionar = array_diff($list_cat, $list_official);
    asort($adicionar);
    $remover = array_diff($list_official, $list_cat);
    asort($remover);

} else {

    //Define variáveis como em branco, para evitar erro
    $adicionar = array();
    $remover = array();
}

//Lista páginas pendentes de eliminação
$ec_list = json_decode(file_get_contents($contest['api_endpoint']."?action=query&format=json&list=categorymembers&cmtitle=Categoria%3A!Itens%20propostos%20para%20elimina%C3%A7%C3%A3o&cmnamespace=4&cmlimit=500&cmprop=title"), true)['query']['categorymembers'];
$esr_list = json_decode(file_get_contents($contest['api_endpoint']."?action=query&format=json&list=categorymembers&cmtitle=Categoria%3A!Todas_as_p%C3%A1ginas_para_elimina%C3%A7%C3%A3o_semirr%C3%A1pida&cmprop=title&cmnamespace=0&cmlimit=500"), true)['query']['categorymembers'];
$er_list = json_decode(file_get_contents($contest['api_endpoint']."?action=query&format=json&list=categorymembers&cmtitle=Categoria%3A!P%C3%A1ginas%20para%20elimina%C3%A7%C3%A3o%20r%C3%A1pida&cmprop=title&cmnamespace=0&cmlimit=500"), true)['query']['categorymembers'];
$caa_list = json_decode(file_get_contents($contest['api_endpoint']."?action=query&format=json&list=categorymembers&cmtitle=Categoria%3A!Artigos%20propostos%20para%20a%20candidatura&cmprop=title&cmnamespace=0&cmlimit=500"), true)['query']['categorymembers'];
foreach ($ec_list as $page) $deletion[] = substr($page['title'], 34);
foreach ($esr_list as $page) $deletion[] = $page['title'];
foreach ($er_list as $page) $deletion[] = $page['title'];
foreach ($caa_list as $page) $deletion[] = $page['title'];

//Processa listagem
$eliminar = array_intersect($deletion, $list_cat);
asort($eliminar);

//Conecta ao banco de dados
require "connect.php";

//Coleta lista de diffs e artigos novos
$inconsistency_query = mysqli_query($con, 
    "SELECT 
      `diff`, 
      `valid_edit`,
      `timestamp`
    FROM 
      `edits` 
    WHERE 
      `article` NOT IN (
        SELECT 
          `articleID` 
        FROM 
          `articles`
      ) 
      AND `valid_user` = '1' 
      AND `bytes` > 0 
      AND `reverted` IS NULL
    ORDER BY `timestamp` ASC;");
$wd_query = mysqli_query($con,
    "SELECT 
      `article` 
    FROM 
      `edits` 
      INNER JOIN `articles` ON `edits`.`article` = `articles`.`articleID` 
    WHERE 
      `edits`.`new_page` = '1' 
    ORDER BY 
      `edits`.`timestamp` ASC;");
?>

<!DOCTYPE html>
<html>
    <title>Comparador - <?=$contest['name'];?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="bin/w3.css">
    <body>
        <header class="w3-container w3-<?=$contest['theme'];?>">
            <h1>Comparador - <?=$contest['name'];?></h1>
        </header>
        <br>
        <div class="w3-row-padding">
            <div class="w3-quarter">
                <div class="w3-card white">
                    <div class="w3-container w3-purple">
                        <h3>Não listados</h3>
                    </div>
                    <ul class="w3-ul w3-border-top">
                        <li>Os artigos abaixos estão inseridos na categoria, porém não estão inseridos na lista oficial</li>
                        <?php foreach ($adicionar as $artigo_add) {
                            $artigo_add_encoded = urlencode($artigo_add);
                            echo("<li>");
                            echo("<a target='_blank' href='{$contest['endpoint']}?title={$artigo_add_encoded}'>{$artigo_add}</a>");
                            echo("</li>\n");
                        }?>
                    </ul>
                </div>
            </div>
            <div class="w3-quarter">
                <div class="w3-card white">
                    <div class="w3-container w3-indigo">
                        <h3>Descategorizados</h3>
                    </div>
                    <ul class="w3-ul w3-border-top">
                        <li>Os artigos abaixos estão inseridos na lista oficial, porém não estão inseridos na categoria</li>
                        <?php foreach ($remover as $artigo_rem) {
                            $artigo_rem_params = [
                                "action"        => "query",
                                "format"        => "json",
                                "prop"          => "links",
                                "titles"        => $artigo_rem
                            ];
                            $artigo_rem_api = json_decode(file_get_contents($contest['api_endpoint']."?".http_build_query($artigo_rem_params)), true)['query']['pages']['-1'];
                            if (isset($artigo_rem_api)) continue;

                            $artigo_rem_encode = urlencode($artigo_rem);
                            echo("<li>");
                            echo("<a target='_blank' href='{$contest['endpoint']}?title={$artigo_rem_encode}'>{$artigo_rem}</a>");
                            echo("</li>\n");
                        }?>
                    </ul>
                </div>
            </div>
            <div class="w3-quarter">
                <div class="w3-card white">
                    <div class="w3-container w3-teal">
                        <h3>Passíveis de eliminação</h3>
                    </div>
                    <ul class="w3-ul w3-border-top">
                        <li>Os artigos abaixos estão inseridos na categoria e estão marcados em alguma modalidade de eliminação (rápida, semirrápida, por consenso ou por candidatura). Adicionalmente, também exibe artigos novos sem item no Wikidata.</li>
                        <?php foreach ($eliminar as $artigo_del) {
                            $artigo_del_encode = urlencode($artigo_del);
                            echo("<li>");
                            echo("<a target='_blank' href='{$contest['endpoint']}?title={$artigo_del_encode}'>{$artigo_del}</a>");
                            echo("</li>\n");
                        }
                        while ($row = mysqli_fetch_assoc($wd_query)) {
                            $wd_params = [
                                "action"        => "query",
                                "format"        => "json",
                                "prop"          => "pageprops",
                                "ppprop"        => "wikibase_item",
                                "pageids"       => $row['article']
                            ];
                            $wd_api = file_get_contents($contest['api_endpoint']."?".http_build_query($wd_params)), true);
                            $wd = end(json_decode($wd_api, true)["query"]["pages"]);
                            if (isset($wd["pageprops"]["wikibase_item"])) continue;

                            $wd_encode = urlencode($wd['pageid']);
                            echo("<li class='w3-green'>");
                            echo("<a target='_blank' href='{$contest['endpoint']}?curid={$wd_encode}'>{$wd['title']}</a> <small>(Sem Wikidata)</small>");
                            echo("</li>\n");
                        }
                        ?>
                    </ul>
                </div>
            </div>
            <div class="w3-quarter">
                <div class="w3-card white">
                    <div class="w3-container w3-black">
                        <h3>Inconsistências</h3>
                    </div>
                    <ul class="w3-ul w3-border-top">
                        <li>As edições listadas abaixo pertecem a artigos que estavam listados na categoria mas foram removidos. Caso estejam marcadas de vermelho, a edição foi validada e conferiu pontos ao participante.</li>
                        <?php 
                        while ($row = mysqli_fetch_assoc($inconsistency_query)) {
                            $diff_encode = urlencode($row['diff']);
                            
                            echo("<li class='");
                            if ($row['valid_edit'] == '1') echo("w3-red");
                            echo("'>");
                            echo("<a target='_blank' href='{$contest['endpoint']}?diff={$diff_encode}'>{$row['diff']}</a> <small>em {$row['timestamp']}</small>");
                            echo("</li>\n");
                        }
                        ?>
                    </ul>
                </div>
            </div>
        </div>
    </body>
</html>