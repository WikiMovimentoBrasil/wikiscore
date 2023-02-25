<pre><?php

//Conecta ao banco de dados
require_once __DIR__.'/bin/connect.php';

//Coleta lista de concursos
$contests_statement = '
    SELECT
        `name_id`,
        `api_endpoint`
    FROM
        `manage__contests`
    ORDER BY
        `start_time` DESC
';
$contests_query = mysqli_prepare($con, $contests_statement);
mysqli_stmt_execute($contests_query);
$contests_result = mysqli_stmt_get_result($contests_query);

//Loop para cada concurso
while ($contest = mysqli_fetch_assoc($contests_result)) {

    //Insere nova coluna e remove coluna anterior
    echo $contest['name_id']."\n";
    mysqli_query($con, "ALTER TABLE `{$contest['name_id']}__edits` ADD COLUMN IF NOT EXISTS `user_id` INT NOT NULL AFTER `n`;");
    mysqli_query($con, "ALTER TABLE `{$contest['name_id']}__edits` DROP COLUMN `user`;");

    //Coleta revisões já inseridas no banco de dados
    $revision_list = array();
    $revision_query = mysqli_query(
        $con,
        "SELECT
            `diff`
        FROM
            `{$contest['name_id']}__edits`
        WHERE
            `user_id` = '0'
        ORDER BY
            `diff`
        ;"
    );
    while ($diff = mysqli_fetch_assoc($revision_query)) {

        //Isola número da edição
        $diff = $diff['diff'];
        echo $diff;

        //Coleta userID da edição
        $diff_params = [
            "action"        => "query",
            "format"        => "php",
            "prop"          => "revisions",
            "revids"        => $diff,
            "formatversion" => "2",
            "rvprop"        => "userid"
        ];

        $ch1 = curl_init( $contest['api_endpoint'] . "?" . http_build_query($diff_params) );
        curl_setopt( $ch1, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch1, CURLOPT_COOKIEJAR, "cookie.inc" );
        curl_setopt( $ch1, CURLOPT_COOKIEFILE, "cookie.inc" );
        $userid = curl_exec( $ch1 );
        curl_close( $ch1 );

        $userid = unserialize($userid)["query"]["pages"]['0']["revisions"]['0']["userid"] ?? false;

        if(!$userid) {

            //Coleta userID da edição ediminada
            $diff_params = [
                "action"        => "query",
                "format"        => "php",
                "prop"          => "deletedrevisions",
                "revids"        => $diff,
                "formatversion" => "2",
                "drvprop"        => "userid"
            ];

            $ch1 = curl_init( $contest['api_endpoint'] . "?" . http_build_query($diff_params) );
            curl_setopt( $ch1, CURLOPT_RETURNTRANSFER, true );
            curl_setopt( $ch1, CURLOPT_COOKIEJAR, "cookie.inc" );
            curl_setopt( $ch1, CURLOPT_COOKIEFILE, "cookie.inc" );
            $userid = curl_exec( $ch1 );
            curl_close( $ch1 );

            $userid = unserialize($userid)["query"]["pages"]['0']["deletedrevisions"]['0']["userid"] ?? '1';

        }

        //Atualiza DB
        mysqli_query($con, "UPDATE `{$contest['name_id']}__edits` SET `user_id`='{$userid}' WHERE  `diff`='{$diff}';");
        echo " = ".$userid."\n";
    }
}