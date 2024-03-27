<?php
//Protetor de login
require_once "protect.php";

//Coleta lista de edições
$edits_statement = "
    SELECT
        `{$contest['name_id']}__edits`.`diff`,
        `{$contest['name_id']}__edits`.`article`,
        `{$contest['name_id']}__articles`.`title`,
        `{$contest['name_id']}__edits`.`timestamp`,
        `{$contest['name_id']}__edits`.`n`,
        IFNULL(
            `{$contest['name_id']}__users`.`user`,
            CONCAT(
                'Special:Redirect/user/', `{$contest['name_id']}__edits`.`user_id`
            )
        ) AS `user`,
        `{$contest['name_id']}__users`.`attached`,
        `{$contest['name_id']}__edits`.`bytes`,
        `{$contest['name_id']}__edits`.`new_page`,
        `{$contest['name_id']}__edits`.`valid_edit`,
        `{$contest['name_id']}__edits`.`valid_user`,
        `{$contest['name_id']}__edits`.`pictures`,
        `{$contest['name_id']}__edits`.`reverted`,
        `{$contest['name_id']}__edits`.`by`,
        `{$contest['name_id']}__edits`.`when`,
        `{$contest['name_id']}__edits`.`obs`
    FROM
        `{$contest['name_id']}__edits`
    LEFT JOIN
        `{$contest['name_id']}__users`
    ON
        `{$contest['name_id']}__edits`.`user_id` = `{$contest['name_id']}__users`.`local_id`
    LEFT JOIN 
        `{$contest['name_id']}__articles`
    ON
        `{$contest['name_id']}__edits`.`article` = `{$contest['name_id']}__articles`.`articleID`;
";
$edits_query = mysqli_prepare($con, $edits_statement);
mysqli_stmt_execute($edits_query);
$edits_result = mysqli_stmt_get_result($edits_query);
mysqli_stmt_close($edits_query);

//Verifica se existem edições cadastradas bo banco de dados
$rows = mysqli_num_rows($edits_result);
if ($rows == 0) { die("No edits"); }

//Exportação em CSV
if ($_POST["csv"]) {

    //Cria header especial para exportar arquivo
    header("Expires: 0");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
    header("Content-Description: File Transfer");
    header("Content-disposition: attachment; filename=".$contest['name_id'].time().".csv");
    header("Cache-control: private");
    header("Content-type: text/csv; charset=windows-1252");

    //Cabeçalho do CSV
    $sep = '";"';
    $csv =  "sep=;\r\n".'"'.
            §('edits-diff').$sep.
            §('edits-curid').$sep.
            §('edits-title').$sep.
            §('edits-timestamp').$sep.
            §('edits-user').$sep.
            §('edits-attached').$sep.
            §('edits-bytes').$sep.
            §('edits-newpage').$sep.
            §('edits-valid').$sep.
            §('edits-enrolled').$sep.
            §('edits-withimage').$sep.
            §('edits-reverted').$sep.
            §('edits-evaluator').$sep.
            §('edits-evaltimestamp').$sep.
            §('edits-comment')."\"\r\n";

    while ($query = mysqli_fetch_assoc($edits_result)) {
        $csv .= '"'.
                $query["diff"].$sep.
                $query["article"].$sep.
                $query["title"].$sep.
                $query["timestamp"].$sep.
                $query["user"].$sep.
                $query["attached"].$sep.
                $query["bytes"].$sep.
                $query["new_page"].$sep.
                $query["valid_edit"].$sep.
                $query["valid_user"].$sep.
                $query["pictures"].$sep.
                $query["reverted"].$sep.
                $query["by"].$sep.
                $query["when"].$sep.
                str_replace('"', '""', $query["obs"])."\"\r\n";
    }

    echo mb_convert_encoding($csv, 'CP1252', 'UTF-8');
    exit();
}
?>
<!DOCTYPE html>
<html lang="pt-br">
    <head>
        <title><?=§('triage-evaluated')?> - <?=$contest['name'];?></title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="bin/w3.css">
        <link rel="stylesheet" type="text/css" href="bin/color.php?color=<?=@$contest['color'];?>">
        <link rel="stylesheet" href="https://tools-static.wmflabs.org/cdnjs/ajax/libs/datatables/1.10.21/css/jquery.dataTables.min.css" />
        <link rel="stylesheet" href="https://tools-static.wmflabs.org/cdnjs/ajax/libs/datatables.net-buttons-dt/2.3.6/buttons.dataTables.min.css" />
        <link rel="stylesheet" href="https://tools-static.wmflabs.org/cdnjs/ajax/libs/datatables.net-responsive-dt/2.4.1/responsive.dataTables.min.css" />
        <script src="https://tools-static.wmflabs.org/cdnjs/ajax/libs/jquery/3.7.0/jquery.min.js"></script>
        <script src="https://tools-static.wmflabs.org/cdnjs/ajax/libs/datatables.net/2.1.1/jquery.dataTables.min.js"></script>
        <script src="https://tools-static.wmflabs.org/cdnjs/ajax/libs/datatables.net-responsive/2.4.1/dataTables.responsive.min.js"></script>
        <script src="https://tools-static.wmflabs.org/cdnjs/ajax/libs/datatables.net-buttons/2.3.6/js/dataTables.buttons.min.js"></script>
        <script src="https://tools-static.wmflabs.org/cdnjs/ajax/libs/datatables.net-buttons/2.3.6/js/buttons.colVis.min.js"></script>
        <style>
        .loader {
            border: 16px solid #f3f3f3;
            border-radius: 50%;
            border-top: 16px solid #000000;
            width: 120px;
            height: 120px;
            margin: auto;
            animation: spin 2s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        #myTable {
            display: none;
            font-size: small;
        }
        </style>
    </head>
    <body>
        <?php require_once "sidebar.php"; ?>
        <div class="w3-row-padding w3-content w3-main" style="max-width:1200px;margin-top:43px;padding-top:16px;">
            <div class="w3-container w3-margin-top w3-card-4">
                <div class="w3-container w3-row w3-padding-16">
                    <div class="w3-threequarter"><?=§('edits-about')?></div>
                    <form method="post" target="_blank" class="w3-quarter">
                        <input type="hidden" name="csv" value="csv">
                        <button class="w3-button w3-right w3-green" type="submit">
                            <i class="fa-solid fa-file-csv"></i> <?=§('edits-csv')?>
                        </button>
                    </form>
                </div>
            </div>
            <div class="w3-margin-top w3-card-4">
                <div class="w3-padding">
                    <div class="loader"></div>
                    <table id="myTable" class="display responsive" style="width:100%">
                        <thead>
                            <tr>
                                <th><?=§('edits-diff')?></th>
                                <th><?=§('edits-title')?></th>
                                <th><?=§('edits-timestamp')?></th>
                                <th><?=§('edits-user')?></th>
                                <th><?=§('edits-attached')?></th>
                                <th><?=§('edits-bytes')?></th>
                                <th><?=§('edits-newpage')?></th>
                                <th><?=§('edits-valid')?></th>
                                <th><?=§('edits-withimage')?></th>
                                <th><?=§('edits-reverted')?></th>
                                <th><?=§('edits-evaluator')?></th>
                                <th><?=§('edits-evaltimestamp')?></th>
                                <th><?=§('edits-comment')?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php function icon($x, $inverter = false)
                            {
                                $green = 'green';
                                $red = 'red';
                                if ($inverter) {
                                    $green = 'red';
                                    $red = 'green';
                                }

                                if ($x === 1) {
                                    return "<span class='w3-text-$green w3-large'>✓</span>";
                                } elseif ($x === 0) {
                                    return "<span class='w3-text-$red w3-large'>✗</span>";
                                } else {
                                    return $x;
                                }
                            }
                            ?>
                            <?php while ($query = mysqli_fetch_assoc($edits_result)): ?>
                                <?php if ($query["valid_user"] != 1) { 
                                    continue; 
                                } ?>
                                <tr>
                                    <td><?=$query["diff"]?></td>
                                    <td><?=$query["title"]?></td>
                                    <td><?=$query["timestamp"]?></td>
                                    <td><?=$query["user"]?></td>
                                    <td><?=$query["attached"]?></td>
                                    <td><?=$query["bytes"]?></td>
                                    <td><?=icon($query["new_page"])?></td>
                                    <td><?=icon($query["valid_edit"])?></td>
                                    <td><?=$query["pictures"]?></td>
                                    <td><?=icon($query["reverted"],true)?></td>
                                    <td><?=$query["by"]?></td>
                                    <td><?=$query["when"]?></td>
                                    <td><?=trim($query["obs"])?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <script type="text/javascript">
                        $(document).ready( function () {
                            $('.loader').hide();
                            $('#myTable').show();
                            $('#myTable').DataTable( {
                                responsive: true,
                                columnDefs: [
                                    {
                                        targets: "_all",
                                        className: 'dt-body-center'
                                    }
                                  ]
                            } );
                        } );
                    </script>
                </div>
            </div>
        </div>
    </body>
</html>
