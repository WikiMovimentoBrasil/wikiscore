<?php
echo "<pre>";

//Aumenta tempo limite de execução do script
set_time_limit(1200);

//Verifica se PSID do PetScan foi fornecido ao invés de uma categoria comum
if (isset($contest['category_petscan'])) {

    //Recupera lista do PetScan
    $petscan_list = json_decode(
        file_get_contents("https://petscan.wmflabs.org/?format=json&psid=".$contest['category_petscan']),
        true
    );
    $petscan_list = $petscan_list['*']['0']['a']['*'];

    //Insere lista em uma array
    $list = array();
    foreach ($petscan_list as $petscan_id) { 
        $list[] = [
            "id"    => $petscan_id['id'],
            "title" => $petscan_id['title']
        ];
    }

} else {

    //Coleta lista de artigos na categoria
    $categorymembers_api_params = [
        "action"        => "query",
        "format"        => "php",
        "list"          => "categorymembers",
        "cmnamespace"   => "0",
        "cmpageid"      => $contest['category_pageid'],
        "cmprop"        => "ids|title",
        "cmlimit"       => "max"
    ];
    $categorymembers_api = unserialize(
        file_get_contents($contest['api_endpoint']."?".http_build_query($categorymembers_api_params))
    );

    //Insere lista em uma array
    $list = array();
    foreach ($categorymembers_api['query']['categorymembers'] as $pageid) { 
        $list[] = [
            "id" => $pageid['pageid'],
            "title"  => $pageid['title']
        ];
    }

    //Coleta segunda página da lista, caso exista
    while (isset($categorymembers_api['continue'])) {
        $categorymembers_api_params = [
            "action"        => "query",
            "format"        => "php",
            "list"          => "categorymembers",
            "cmnamespace"   => "0",
            "cmpageid"      => $contest['category_pageid'],
            "cmprop"        => "ids|title",
            "cmlimit"       => "max",
            "cmcontinue"    => $categorymembers_api['continue']['cmcontinue']
        ];
        $categorymembers_api = unserialize(
            file_get_contents($contest['api_endpoint']."?".http_build_query($categorymembers_api_params))
        );
        foreach ($categorymembers_api['query']['categorymembers'] as $pageid) { 
            $list[] = [
                "id" => $pageid['pageid'],
                "title"  => $pageid['title']
            ];
        }
    }
}

//Realiza manutenção do banco de dados
mysqli_query($con, "ALTER TABLE `{$contest['name_id']}__articles` ADD COLUMN IF NOT EXISTS `title` VARCHAR(100) NOT NULL AFTER `articleID`;");
mysqli_query($con, "ALTER TABLE `{$contest['name_id']}__edits` ADD COLUMN IF NOT EXISTS `orig_bytes` INT(11) DEFAULT NULL AFTER  `bytes`;");
mysqli_query($con, "UPDATE `{$contest['name_id']}__edits` SET `orig_bytes` = CASE 
    WHEN `obs` REGEXP 'Aval: de (-?[0-9]+) para (-?[0-9]+)' THEN CAST(REGEXP_SUBSTR(`obs`, 'Aval: de \\\\K-?[0-9]*') AS SIGNED) 
    WHEN `obs` REGEXP 'bytes: (-?[0-9]+) -> (-?[0-9]+)' THEN CAST(REGEXP_SUBSTR(`obs`, 'bytes: \\\\K-?[0-9]*') AS SIGNED) 
    ELSE `bytes` END WHERE `orig_bytes` IS NULL;");

//Monta e executa query para atualização da tabela de artigos
mysqli_query($con, "TRUNCATE `{$contest['name_id']}__articles`;");
$addarticle_statement = "
    INSERT INTO
        `{$contest['name_id']}__articles` (`articleID`, `title`)
    VALUES
        (?, ?)
";
$addarticle_query = mysqli_prepare($con, $addarticle_statement);
mysqli_stmt_bind_param($addarticle_query, "is", $articleID, $title);
foreach ($list as $add_title) {
    $articleID  = $add_title['id'];
    $title      = $add_title['title'];
    mysqli_stmt_execute($addarticle_query);
}
mysqli_stmt_close($addarticle_query);

//Coleta lista de artigos
$articles_query = mysqli_prepare($con, "SELECT * FROM `{$contest['name_id']}__articles`;");
mysqli_stmt_execute($articles_query);
$articles_result = mysqli_stmt_get_result($articles_query);
mysqli_stmt_close($articles_query);
if (mysqli_num_rows($articles_result) == 0) { die("No articles"); }

//Coleta revisões já inseridas no banco de dados
$revision_list = array();
$revision_query = mysqli_query(
    $con,
    "SELECT
        `diff`
    FROM
        `{$contest['name_id']}__edits`
    ORDER BY
        `diff`
    ;"
);
foreach (mysqli_fetch_all($revision_query, MYSQLI_ASSOC) as $diff) { $revision_list[] = $diff['diff']; }

//Loop para análise de cada artigo
while ($row = mysqli_fetch_assoc($articles_result)) {

    //Coleta revisões do artigo
    echo "\nCurID: ".$row["articleID"];
    $revisions_api_params = [
        "action"    => "query",
        "format"    => "php",
        "prop"      => "revisions",
        "rvprop"    => "ids",
        "rvlimit"   => "max",
        "rvstart"   => date('Y-m-d\TH:i:s.000\Z', $contest['end_time']),
        "rvend"     => date('Y-m-d\TH:i:s.000\Z', $contest['start_time']),
        "pageids"   => $row["articleID"]
    ];

    $revisions_api = unserialize(
        file_get_contents($contest['api_endpoint']."?".http_build_query($revisions_api_params))
    );
    $revisions_api = $revisions_api['query']['pages'][$row['articleID']];

    //Verifica se artigo possui revisões dentro dos parâmetros escolhidos
    if (!isset($revisions_api['revisions'])) { continue; }

    //Prepara query para atualizações no banco de dados
    $addedit_statement = "
        INSERT IGNORE INTO
            `{$contest['name_id']}__edits` (
                `diff`,
                `article`,
                `timestamp`,
                `user_id`,
                `bytes`,
                `orig_bytes`,
                `new_page`
            )
        VALUES
            ( ? , ? , ? , ? , ? , ? , ? )
    ";
    $addedit_query = mysqli_prepare($con, $addedit_statement);
    mysqli_stmt_bind_param(
        $addedit_query,
        "iisiiii",
        $addedit_diff,
        $addedit_article,
        $addedit_timestamp,
        $addedit_user_id,
        $addedit_bytes,
        $addedit_bytes,
        $addedit_newpage
    );

    //Loop para cada revisão do artigo
    foreach ($revisions_api['revisions'] as $revision) {

        //Verifica se revisão ainda não existe no banco de dados
        echo "\n- Diff: ".$revision['revid'];
        if (in_array($revision['revid'], $revision_list)) { continue; }
        echo " -> inserindo";

        //Coleta dados de diferenciais da revisão
        $compare_api_params = [
            "action"    => "compare",
            "format"    => "php",
            "torelative"=> "prev",
            "prop"      => "diffsize|size|title|user|timestamp",
            "fromrev"   => $revision['revid']
        ];
        $compare_api = unserialize(
            file_get_contents(
                $contest['api_endpoint']."?".http_build_query($compare_api_params)
            )
        );

        //Verifica se edição foi ocultada. Caso sim, define valores da edição como nulos
        if (!isset($compare_api['compare'])) {
            $compare_api['touserid']    = null;
            $compare_api['tosize']      = null;
            $compare_api['new_page']    = null;
            $compare_api['totimestamp'] = null;
        } else {

            //Acessa subarray
            $compare_api = $compare_api['compare'];

            //Verifica se página é nova.
            //Caso sim, mantem tamanho atual do artigo
            //Caso não, subtrai tamanho anterior do artigo
            if (!isset($compare_api['fromsize'])) {
                $compare_api['new_page'] = 1;
            } else {
                $compare_api['new_page'] = null;
                $compare_api['tosize'] = $compare_api['tosize'] - $compare_api['fromsize'];
            }

        }

        //Executa query
        $addedit_diff       = $revision['revid'];
        $addedit_article    = $row["articleID"];
        $addedit_timestamp  = $compare_api['totimestamp'];
        $addedit_user_id    = $compare_api['touserid'];
        $addedit_bytes      = $compare_api['tosize'];
        $addedit_newpage    = $compare_api['new_page'];
        mysqli_stmt_execute($addedit_query);
        if (mysqli_stmt_affected_rows($addedit_query) != 0) {
            echo " -> feito!";
        }
    }
}

//Encerra conexão
mysqli_close($con);
echo "<br>Concluido! (1/3)<br>";
echo "<a href='index.php?contest={$contest['name_id']}&page=load_users'>Próxima etapa, clique aqui.</a>";
