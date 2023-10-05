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

//Captura horário de última edição avaliada no banco de dados
$lastedit_query = mysqli_query(
    $con,
    "SELECT
        `timestamp` AS `lastedit`
    FROM
        `{$contest['name_id']}__edits`
    WHERE
        `valid_edit` IS NOT NULL
    ORDER BY
        `timestamp` DESC
    LIMIT
        1;"
);
$lastedit = "-";
if ($lastedit_query != false) {
    $lastedit_query = mysqli_fetch_assoc($lastedit_query);
    if (isset($lastedit_query["lastedit"]) && !is_null($lastedit_query["lastedit"])) {
        $lastedit = date('Y/m/d H:i:s (\U\T\C)', strtotime($lastedit_query["lastedit"]));
    }
}


?>

<!DOCTYPE html>
<html lang="pt-br">
    <head>
        <title><?=$contest['name'];?></title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="./bin/w3.css">
        <link rel="stylesheet" type="text/css" href="bin/color.php?color=<?=@$contest['color'];?>">
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
        <header class="w3-<?=$contest['theme'];?> w3-container">
            <h1><?=$contest['name'];?></h1>
        </header>
        <div class="w3-block w3-card w3-margin w3-hide-medium w3-hide-small" style="width: inherit;">
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
        <?php if (time() > $contest['start_time']) require_once "stats.php"; ?> 
        <div class="w3-row-padding">
            <div class="w3-third w3-margin-bottom">
                <div class="w3-card-4 w3-padding">
                    <canvas id="total_edits"></canvas>
                </div>
            </div>
            <div class="w3-third w3-margin-bottom">
                <div class="w3-card-4 w3-padding">
                    <canvas id="valid_edits"></canvas>
                </div>
            </div>
            <div class="w3-third w3-margin-bottom">
                <div class="w3-card-4 w3-padding">
                    <canvas id="new_articles"></canvas>
                </div>
            </div>
        </div>
        <div class="w3-row-padding">
            <div class="w3-third w3-margin-bottom">
                <div class="w3-card-4 w3-padding">
                    <canvas id="new_bytes"></canvas>
                </div>
            </div>
            <div class="w3-third w3-margin-bottom">
                <div class="w3-card-4 w3-padding">
                    <canvas id="valid_bytes"></canvas>
                </div>
            </div>
            <div class="w3-third w3-margin-bottom">
                <div class="w3-card-4 w3-light-grey w3-justify">
                    <header class="w3-<?=$contest['theme'];?> w3-container">
                        <h3><?=§('login-about')?></h3>
                    </header>
                    <div class="w3-container">
                        <p class="w3-small">
                            <strong><?=§('login-start')?></strong>
                            <br>
                            <?=date('Y/m/d H:i:s (\U\T\C)', $contest['start_time']);?>
                        </p>
                        <p class="w3-small">
                            <strong><?=§('login-end')?></strong>
                            <br>
                            <?=date('Y/m/d H:i:s (\U\T\C)', $contest['end_time']);?>
                        </p>
                        <p class="w3-small">
                            <strong><?=§('login-recent')?></strong>
                            <br>
                            <?=$lastedit;?>
                        </p>
                    </div>
                    <div class="w3-container w3-padding-small">
                        <button class="w3-button w3-half w3-<?=$contest['theme'];?> w3-block" style="filter: hue-rotate(120deg);" type="button" onclick="window.open('index.php?lang=<?=$lang?>&contest=<?=$contest['name_id'];?>&page=graph', '_blank');"><?=§('login-graph')?></button>
                        <button onclick="document.getElementById('id01').style.display='block'" class="w3-button w3-half w3-block w3-<?=$contest['theme'];?>" style="filter: hue-rotate(240deg);"><?=§('login')?></button>
                    </div>
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
                            }
                        ]
                    }
                }
            );

            const valid_edits = new Chart(
                document.getElementById('valid_edits'),
                {
                    type: 'line',
                    options: {
                        aspectRatio: 1.5,
                        plugins: {
                            title: {
                                display: true,
                                text: "<?=§('login-validedits')?>"
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
                                text: "<?=§('login-newpages')?>"
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
                            }
                        ]
                    }
                }
            );

            const valid_bytes = new Chart(
                document.getElementById('valid_bytes'),
                {
                    type: 'line',
                    options: {
                        aspectRatio: 1.5,
                        plugins: {
                            title: {
                                display: true,
                                text: "<?=§('login-validbytes')?>"
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
