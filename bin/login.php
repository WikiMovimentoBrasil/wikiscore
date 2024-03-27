<?php
// (A) PROCESS LOGIN ON SUBMIT
session_start();
if (isset($_POST['email'])) {
    require_once "credentials-lib.php";
    $USR->login($_POST['email'], $_POST['password']);
}

// (B) REDIRECT USER IF SIGNED IN
if (isset($_SESSION['user'])) {
    header("Location: index.php?lang=$lang&contest=".$_GET['contest']."&page=triage");
    exit();
}

// (C) SHOW LOGIN FORM OTHERWISE

//Calcula número total de dias do wikiconcurso e monta eixo X dos gráficos
$elapsed_days = floor((time() - $contest['start_time']) / 60 / 60 / 24);
$total_days = ceil(($contest['end_time'] - $contest['start_time']) / 60 / 60 / 24) + 2;
for ($i=1; $i < $total_days; $i++) {
    $all_days[] = $i;
}
$all_days = implode(", ", $all_days);

//Define faixa de dias para queries dos gráficos
$start_day = date('Y-m-d', $contest['start_time']);
$end_day = date('Y-m-d', $contest['end_time']);
mysqli_query($con, "SET @date_min = '{$start_day}';");
mysqli_query($con, "SET @date_max = '{$end_day}';");

//Processa queries para gráficos
$date_generator =
    "SELECT
        DATE_ADD(@date_min, INTERVAL(@i:= @i + 1) - 1 DAY) AS `date`
    FROM
        information_schema.columns, (
            SELECT @i:= 0
        ) gen_sub
    WHERE
        DATE_ADD(@date_min, INTERVAL @i DAY) BETWEEN @date_min AND @date_max";

$total_edits = mysqli_query(
    $con,
    "SELECT
       date_generator.date as the_date,
       IFNULL(COUNT(`{$contest['name_id']}__edits`.n), 0) as count
    from ( {$date_generator} ) date_generator
    left join `{$contest['name_id']}__edits` on DATE(`timestamp`) = date_generator.date
    GROUP BY date;
");
if ($total_edits != false) {
    while ($row = mysqli_fetch_assoc($total_edits)) $total_edits_rows[] = $row['count'];
    array_splice($total_edits_rows, $elapsed_days);
    $total_edits_rows = implode(", ", $total_edits_rows);
} else {
    $total_edits_rows = '';
}


$valid_edits = mysqli_query($con, "
    SELECT
       date_generator.date as the_date,
       IFNULL(COUNT(`queried`.n), 0) as count
    from ( {$date_generator} ) date_generator
    left join (SELECT `n`, DATE(`timestamp`) AS date_timestamp from `{$contest['name_id']}__edits`
        WHERE `valid_edit` = 1) AS queried on date_timestamp = date_generator.date
    GROUP BY date;
");
if ($valid_edits != false) {
    while ($row = mysqli_fetch_assoc($valid_edits)) $valid_edits_rows[] = $row['count'];
    array_splice($valid_edits_rows, $elapsed_days);
    $valid_edits_rows = implode(", ", $valid_edits_rows);
} else {
    $valid_edits_rows = '';
}

$new_articles = mysqli_query($con, "
    SELECT
       date_generator.date as the_date,
       IFNULL(COUNT(`queried`.n), 0) as count
    from ( {$date_generator} ) date_generator
    left join (SELECT `n`, DATE(`timestamp`) AS date_timestamp from `{$contest['name_id']}__edits`
        WHERE `new_page` = 1) AS queried on date_timestamp = date_generator.date
    GROUP BY date;
");
if ($new_articles != false) {
    while ($row = mysqli_fetch_assoc($new_articles)) $new_articles_rows[] = $row['count'];
    array_splice($new_articles_rows, $elapsed_days);
    $new_articles_rows = implode(", ", $new_articles_rows);
} else {
    $new_articles_rows = '';
}

$new_participants = mysqli_query($con, "
    SELECT
       date_generator.date as the_date,
       IFNULL(COUNT(`queried`.n), 0) as count
    from ( {$date_generator} ) date_generator
    left join (SELECT `n`, DATE(`timestamp`) AS date_timestamp from `{$contest['name_id']}__users`
        ) AS queried on date_timestamp = date_generator.date
    GROUP BY date;
");
if ($new_participants) {
    while ($row = mysqli_fetch_assoc($new_participants)) {
        $new_participants_rows[] = $row['count'];
    }
    array_splice($new_participants_rows, $elapsed_days);
    $new_participants_rows = implode(", ", $new_participants_rows);
} else {
    $new_participants_rows = '';
}

$new_bytes = mysqli_query($con, "
    SELECT
       date_generator.date as the_date,
       IFNULL(SUM(`queried`.`bytes`) / 1024, 0) as count
    from ( {$date_generator} ) date_generator
    left join (SELECT `n`, DATE(`timestamp`) AS date_timestamp, `bytes`, `valid_edit` from `{$contest['name_id']}__edits`
        WHERE `bytes` > 0) as `queried` on `queried`.date_timestamp = date_generator.date
    GROUP BY date;
");
if ($new_bytes != false) {
    while ($row = mysqli_fetch_assoc($new_bytes)) $new_bytes_rows[] = $row['count'];
    array_splice($new_bytes_rows, $elapsed_days);
    $new_bytes_rows = implode(", ", $new_bytes_rows);
} else {
    $new_bytes_rows = '';
}

$valid_bytes = mysqli_query($con, "
    SELECT
       date_generator.date as the_date,
       IFNULL(SUM(`queried`.`bytes`) / 1024, 0) as count
    from ( {$date_generator} ) date_generator
    left join (SELECT `n`, DATE(`timestamp`) AS date_timestamp, `bytes`, `valid_edit` from `{$contest['name_id']}__edits`
        WHERE `valid_edit` = 1) as `queried` on `queried`.date_timestamp = date_generator.date
    GROUP BY date;
");
if ($valid_bytes != false) {
    while ($row = mysqli_fetch_assoc($valid_bytes)) $valid_bytes_rows[] = $row['count'];
    array_splice($valid_bytes_rows, $elapsed_days);
    $valid_bytes_rows = implode(", ", $valid_bytes_rows);
} else {
    $valid_bytes_rows = '';
}
?>

<!DOCTYPE html>
<html lang="pt-br">
    <head>
        <title><?=$contest['name'];?></title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="./bin/w3.css">
        <link rel="stylesheet" type="text/css" href="bin/color.php?color=<?=@$contest['color'];?>">
        <link rel="stylesheet" href="https://tools-static.wmflabs.org/cdnjs/ajax/libs/font-awesome/6.2.0/css/all.css">
        <?php if (isset($_POST['do_login'])): ?>
            <script>alert('<?=§('login-invalid')?>');</script>
        <?php elseif (isset($_POST['do_create'])) : ?>
            <script>alert('<?=§('login-pending')?>');</script>
        <?php endif; ?>
        <script
        src="https://tools-static.wmflabs.org/cdnjs/ajax/libs/Chart.js/3.9.1/chart.min.js"
        integrity="sha384-9MhbyIRcBVQiiC7FSd7T38oJNj2Zh+EfxS7/vjhBi4OOT78NlHSnzM31EZRWR1LZ"
        crossorigin="anonymous"></script>
    </head>
    <body>
        <div class="w3-<?=$contest['theme'];?> w3-large w3-bar">
            <img src="images/Logo_Branco.svg" alt="logo" class="w3-bar-item" style="width: 128px;">
            <?php if(str_contains(getcwd(), 'test')): ?>
                <span class="w3-bar-item w3-black">
                    <i class="fa-solid fa-flask-vial fa-fade"></i>
                    &nbsp;
                    <i class="fa-solid fa-server fa-fade"></i>
                </span>
            <?php endif; ?>
            <button onclick="window.open('index.php?lang=<?=$lang?>&contest=<?=$contest['name_id'];?>&page=graph', '_blank');"
            class="w3-button w3-bar-item" ><?=§('login-graph')?></button>
            <button onclick="document.getElementById('id01').style.display='block'"
            class="w3-button w3-bar-item" ><?=§('login')?></button>
            <span class="w3-bar-item w3-right w3-hide-small"><?=$contest['name'];?></span>
        </div>
        <div class="w3-block w3-card w3-margin w3-hide-medium w3-hide-small"  style="width: inherit;">
            <div class="w3-center">
                <a href="https://outreachdashboard.wmflabs.org/courses/<?=$contest['outreach_name'];?>"
                target="_blank" rel="noopener" style="color: #fff; background-color: #676eb4;"
                class="w3-button w3-margin-top w3-padding">
                <?=§('triage-outreach')?> 
                    <i class="fa-solid fa-arrow-up-right-from-square fa-xs"></i>
                </a>
            </div>
            <iframe scrolling="no" sandbox
            src="https://outreachdashboard.wmflabs.org/embed/course_stats/<?=$contest['outreach_name'];?>" 
            style="width: 100%; border:0px none transparent;"></iframe>
        </div>
        <?php if (time() > $contest['start_time']) {
            require_once "stats.php";
        } ?> 
        <div class="w3-row-padding">
            <div class="w3-third w3-margin-bottom">
                <div class="w3-card-4 w3-padding">
                    <canvas id="total_edits"></canvas>
                </div>
            </div>
            <div class="w3-third w3-margin-bottom">
                <div class="w3-card-4 w3-padding">
                    <canvas id="new_bytes"></canvas>
                </div>
            </div>
            <div class="w3-third w3-margin-bottom">
                <div class="w3-card-4 w3-padding">
                    <canvas id="new_articles"></canvas>
                </div>
            </div>
        </div>
        <div id="id01" class="w3-modal">
            <div class="w3-modal-content w3-card-4 w3-animate-zoom" style="max-width:600px">
                <div class="w3-center">
                    <br>
                    <span onclick="document.getElementById('id01').style.display='none'" class="w3-button w3-xlarge w3-hover-red w3-display-topright" title="Close Modal">&times;</span>
                    <svg width="240" height="240" stroke-width="1.5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M7 18V17C7 14.2386 9.23858 12 12 12V12C14.7614 12 17 14.2386 17 17V18" stroke="currentColor" stroke-linecap="round" />
                        <path d="M12 12C13.6569 12 15 10.6569 15 9C15 7.34315 13.6569 6 12 6C10.3431 6 9 7.34315 9 9C9 10.6569 10.3431 12 12 12Z" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" />
                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.5" />
                    </svg>
                </div>
                <form class="w3-container" id="login" method="post">
                    <div class="w3-section">
                        <label>
                            <strong><?=§('login-email')?></strong>
                        </label>
                        <input class="w3-input w3-border w3-margin-bottom" type="email" placeholder="Insira seu e-mail" name="email" required>
                        <label>
                            <strong><?=§('login-password')?></strong>
                        </label>
                        <input class="w3-input w3-border" type="password" placeholder="Insira sua senha" name="password" required>
                        <button class="w3-button w3-block w3-<?=$contest['theme'];?> w3-section w3-padding" name="do_login" type="submit"><?=§('login')?></button>
                    </div>
                </form>
                <div class="w3-container w3-border-top w3-padding-16 w3-light-grey">
                    <button
                    onclick="window.open(
                        'index.php?lang=<?=$lang?>&contest=<?=$contest['name_id'];?>&page=recover',
                        '_blank'
                    );"
                    type="button"
                    class="w3-button w3-red"
                    ><?=§('login-recover')?></button>
                </div>
            </div>
        </div>
        <script type="text/javascript">
            const dias = [ <?=$all_days;?> ];

            const total_edits = new Chart(
                document.getElementById('total_edits'),
                {
                    type: 'line',
                    options: {
                        aspectRatio: 1.5,
                        plugins: {
                            title: {
                                display: true,
                                text: "<?=§('login-alledits')?>"
                            },
                            legend: {
                                display: false
                            }
                        }
                    },
                    data: {
                        labels: dias,
                        datasets: [
                            {
                                label: '<?=§('login-alledits')?>',
                                data: [ <?=$total_edits_rows;?> ],
                                fill: false,
                                borderColor: 'rgb(128, 0, 128)',
                                tension: 0.1
                            },
                            {
                                label: '<?=§('login-validedits')?>',
                                data: [ <?=$valid_edits_rows;?> ],
                                fill: false,
                                borderColor: 'rgb(143, 188, 143)',
                                tension: 0.1
                            }
                        ]
                    }
                }
            );

            const new_articles = new Chart(
                document.getElementById('new_articles'),
                {
                    type: 'line',
                    options: {
                        aspectRatio: 1.5,
                        plugins: {
                            title: {
                                display: true,
                                text: "<?=§('login-newevents')?>"
                            },
                            legend: {
                                display: false
                            }
                        }
                    },
                    data: {
                        labels: dias,
                        datasets: [
                            {
                                label: '<?=§('login-newpages')?>',
                                data: [ <?=$new_articles_rows;?> ],
                                fill: false,
                                borderColor: 'rgb(65, 105, 225)',
                                tension: 0.1
                            },
                            {
                                label: '<?=§('login-newparticipants')?>',
                                data: [ <?=$new_participants_rows;?> ],
                                fill: false,
                                borderColor: 'rgb(219, 112, 147)',
                                tension: 0.1
                            }
                        ]
                    }
                }
            );

            const new_bytes = new Chart(
                document.getElementById('new_bytes'),
                {
                    type: 'line',
                    options: {
                        aspectRatio: 1.5,
                        plugins: {
                            title: {
                                display: true,
                                text: "<?=§('login-newbytes')?>"
                            },
                            legend: {
                                display: false
                            }
                        }
                    },
                    data: {
                        labels: dias,
                        datasets: [
                            {
                                label: '<?=§('login-newbytes')?>',
                                data: [ <?=$new_bytes_rows;?> ],
                                fill: false,
                                borderColor: 'rgb(219, 112, 147)',
                                tension: 0.1
                            },
                            {
                                label: '<?=§('login-validbytes')?>',
                                data: [ <?=$valid_bytes_rows;?> ],
                                fill: false,
                                borderColor: 'rgb(255, 69, 0)',
                                tension: 0.1
                            }
                        ]
                    }
                }
            );
        </script>
    </body>
</html>
