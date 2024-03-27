<?php

//Realiza query para tabela de estatísticas
$stats_query = mysqli_prepare(
    $con,
    "SELECT
        most_edited.title AS most_edited_title,
        most_edited.article AS most_edited_article,
        most_edited.total AS most_edited_total,
        biggest_delta.title AS biggest_delta_title,
        biggest_delta.article AS biggest_delta_article,
        biggest_delta.total AS biggest_delta_total,
        biggest_edition.title AS biggest_edition_title,
        biggest_edition.article AS biggest_edition_article,
        biggest_edition.diff AS biggest_edition_diff,
        biggest_edition.bytes AS biggest_edition_bytes,
        edits_summary.new_pages AS new_pages,
        edits_summary.edited_articles AS edited_articles,
        edits_summary.valid_edits AS valid_edits,
        edits_summary.all_bytes AS all_bytes,
        edits_summary.all_users AS all_users
    FROM
        (SELECT title, article, COUNT(diff) AS total, SUM(bytes) AS bytes
        FROM {$contest['name_id']}__edits
        LEFT JOIN {$contest['name_id']}__articles ON article = articleID
        GROUP BY article
        ORDER BY total DESC
        LIMIT 1) AS most_edited
    LEFT JOIN
        (SELECT title, article, SUM(bytes) AS total
        FROM {$contest['name_id']}__edits
        LEFT JOIN {$contest['name_id']}__articles ON article = articleID
        GROUP BY article
        ORDER BY total DESC
        LIMIT 1) AS biggest_delta
    ON 1=1
    LEFT JOIN
        (SELECT title, article, diff, bytes
        FROM {$contest['name_id']}__edits
        LEFT JOIN {$contest['name_id']}__articles ON article = articleID
        WHERE valid_edit = '1'
        ORDER BY bytes DESC
        LIMIT 1) AS biggest_edition
    ON 1=1
    CROSS JOIN
        (SELECT
            SUM(`new_page`) AS `new_pages`,
            COUNT(DISTINCT `article`) AS `edited_articles`,
            SUM(`valid_edit`) AS `valid_edits`,
            (
                SELECT
                    SUM(`bytes`)
                FROM
                    `{$contest['name_id']}__edits`
                WHERE
                    `bytes` > 0
            ) AS `all_bytes`,
            (
                SELECT
                    COUNT(*)
                FROM
                    `{$contest['name_id']}__users`
                WHERE
                    `timestamp` != '0'
            ) AS `all_users`
        FROM
            `{$contest['name_id']}__edits`) AS edits_summary;"
);
mysqli_stmt_execute($stats_query);
$stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stats_query));

//Coleta lista de artigos na página do concurso
$list_api_params = [
    "action"        => "query",
    "format"        => "php",
    "generator"     => "links",
    "pageids"       => $contest['official_list_pageid'],
    "gplnamespace"  => "0",
    "gpllimit"      => "max"
];

$list_api = unserialize(file_get_contents($contest['api_endpoint']."?".http_build_query($list_api_params)));
$stats['listed_articles'] = count($list_api['query']['pages'] ?? []);

//Coleta segunda página da lista, caso exista
while (isset($list_api['continue'])) {
    $list_api_params = [
        "action"        => "query",
        "format"        => "php",
        "generator"     => "links",
        "pageids"       => $contest['official_list_pageid'],
        "gplnamespace"  => "0",
        "gpllimit"      => "max",
        "gplcontinue"   => $list_api['continue']['gplcontinue']
    ];
    $list_api = unserialize(
        file_get_contents($contest['api_endpoint']."?".http_build_query($list_api_params))
    );
    $stats['listed_articles'] += count($list_api['query']['pages']);
}

?>

<div class="w3-container w3-card w3-margin w3-<?=$contest['theme'];?>">
    <div class="w3-center">
        <h3 class="w3-margin-top w3-padding"><?=§('main-title')?></h3>
    </div>
    <div class="w3-row">
        <div class="w3-half">
            <div class="w3-third">
                <h6 class="w3-center"><?=§('counter-allpages')?></h6>
                <h1 class="w3-center"><?=number_format($stats['listed_articles'], 0, ',', '.');?></h1>
            </div>
            <div class="w3-third">
                <h6 class="w3-center"><?=§('counter-alledited')?></h6>
                <h1 class="w3-center"><?=number_format($stats['edited_articles'], 0, ',', '.');?></h1>
            </div>
            <div class="w3-third">
                <h6 class="w3-center"><?=§('counter-allcreated')?></h6>
                <h1 class="w3-center"><?=number_format($stats['new_pages'], 0, ',', '.');?></h1>
            </div>
        </div>
        <div class="w3-half">
            <div class="w3-third">
                <h6 class="w3-center"><?=§('counter-allenrolled')?></h6>
                <h1 class="w3-center"><?=number_format($stats['all_users'], 0, ',', '.');?></h1>
            </div>
            <div class="w3-third">
                <h6 class="w3-center"><?=§('counter-allvalidated')?></h6>
                <h1 class="w3-center"><?=number_format($stats['valid_edits'], 0, ',', '.');?></h1>
            </div>
            <div class="w3-third">
                <h6 class="w3-center"><?=§('counter-allbytes')?></h6>
                <h1 class="w3-center"><?=number_format($stats['all_bytes'], 0, ',', '.');?></h1>
            </div>
        </div>
    </div>
</div>
<div>
    <div class="w3-row-padding w3-center w3-section">
        <div class="w3-third">
            <div class="w3-container w3-card w3-<?=$contest['theme'];?>">
                <div class="w3-half">
                    <h6 class="w3-center"><?=§('counter-most-edited')?></h6>
                </div>
                <div class="w3-half">
                    <h4 class="w3-center">
                        <a href="<?=$contest['endpoint']?>?curid=<?=$stats['most_edited_article']?>" target="_blank" rel="noopener">
                            <?=$stats['most_edited_title']?:§('edits-title')?>
                        </a>
                        <br>
                        <?=§('counter-editions', number_format($stats['most_edited_total'], 0, ',', '.'))?>
                    </h4>
                </div>
            </div>
        </div>
        <div class="w3-third">
            <div class="w3-container w3-card w3-<?=$contest['theme'];?>">
                <div class="w3-half">
                    <h6 class="w3-center"><?=§('counter-biggest-delta')?></h6>
                </div>
                <div class="w3-half">
                    <h4 class="w3-center">
                        <a href="<?=$contest['endpoint']?>?curid=<?=$stats['biggest_delta_article']?>" target="_blank" rel="noopener">
                            <?=$stats['biggest_delta_title']?:§('edits-title')?>
                        </a>
                        <br>
                        <?=§('triage-bytes', number_format($stats['biggest_delta_total'], 0, ',', '.'))?>
                    </h4>
                </div>
            </div>
        </div>
        <div class="w3-third">
            <div class="w3-container w3-card w3-<?=$contest['theme'];?>">
                <div class="w3-half">
                    <h6 class="w3-center"><?=§('counter-biggest-edition')?></h6>
                </div>
                <div class="w3-half">
                    <h4 class="w3-center">
                        <a href="<?=$contest['endpoint']?>?diff=<?=$stats['biggest_edition_diff']?>" target="_blank" rel="noopener">
                            <?=$stats['biggest_edition_title']?:§('edits-title')?>
                        </a>
                        <br>
                        <?=§('triage-bytes', number_format($stats['biggest_edition_bytes'], 0, ',', '.'))?>
                    </h4>
                </div>
            </div>
        </div>
    </div>
</div>
