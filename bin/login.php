<?php
// (A) PROCESS LOGIN ON SUBMIT
session_start();
if (isset($_POST['email'])) {
    require "credencials-lib.php";

    if (isset($_POST['do_create'])) {
        $USR->save($_POST['email'], $_POST['password']);
    } else {
        $USR->login($_POST['email'], $_POST['password']);
    }
}
 
// (B) REDIRECT USER IF SIGNED IN
if (isset($_SESSION['user'])) {
    header("Location: index.php?contest=".$_GET['contest']."&page=triage");
    exit();
}
 
// (C) SHOW LOGIN FORM OTHERWISE 
if (isset($_POST['do_login'])) {
    echo "<script>alert('E-mail/senha inválidos');</script>";
} elseif (isset($_POST['do_create'])) {
    echo "<script>alert('Avaliador pré-cadastrado. Solicite autorização do gestor do wikiconcurso.');</script>";
}

//Coleta informações do concurso
require "data.php";

//Conecta ao banco de dados
require "connect.php";

//Calcula número total de dias do wikiconcurso e monta eixo X dos gráficos
$elapsed_days = floor((time() - $contest['start_time']) / 60 / 60 / 24 );
$total_days = ceil(($contest['end_time'] - $contest['start_time']) / 60 / 60 / 24 ) + 2;
for ($i=1; $i < $total_days; $i++) $all_days[] = $i;
$all_days = implode(", ", $all_days);

//Define faixa de dias para queries dos gráficos
$start_day = date('Y-m-d', $contest['start_time']); 
$end_day = date('Y-m-d', $contest['end_time']);
mysqli_query($con, "SET @date_min = '{$start_day}';");
mysqli_query($con, "SET @date_max = '{$end_day}';");

//Processa queries para gráficos
$date_generator = "SELECT DATE_ADD(@date_min, INTERVAL(@i:= @i + 1) - 1 DAY) AS `date` FROM information_schema.columns, ( SELECT @i:= 0) gen_sub WHERE DATE_ADD(@date_min, INTERVAL @i DAY) BETWEEN @date_min AND @date_max";

$total_edits = mysqli_query($con, "
    SELECT
       date_generator.date as the_date,
       IFNULL(COUNT(`edits`.n), 0) as count
    from ( {$date_generator} ) date_generator
    left join `edits` on DATE(`timestamp`) = date_generator.date
    GROUP BY date;
");
while ($row = mysqli_fetch_assoc($total_edits)) $total_edits_rows[] = $row['count'];
array_splice($total_edits_rows, $elapsed_days);
$total_edits_rows = implode(", ", $total_edits_rows);

$valid_edits = mysqli_query($con, "
    SELECT
       date_generator.date as the_date,
       IFNULL(COUNT(`queried`.n), 0) as count
    from ( {$date_generator} ) date_generator
    left join (SELECT `n`, DATE(`timestamp`) AS date_timestamp from edits WHERE `valid_edit` = 1) AS queried on date_timestamp = date_generator.date
    GROUP BY date;
");
while ($row = mysqli_fetch_assoc($valid_edits)) $valid_edits_rows[] = $row['count'];
array_splice($valid_edits_rows, $elapsed_days);
$valid_edits_rows = implode(", ", $valid_edits_rows);

$new_articles = mysqli_query($con, "
    SELECT
       date_generator.date as the_date,
       IFNULL(COUNT(`queried`.n), 0) as count
    from ( {$date_generator} ) date_generator
    left join (SELECT `n`, DATE(`timestamp`) AS date_timestamp from edits WHERE `new_page` = 1) AS queried on date_timestamp = date_generator.date
    GROUP BY date;
");
while ($row = mysqli_fetch_assoc($new_articles)) $new_articles_rows[] = $row['count'];
array_splice($new_articles_rows, $elapsed_days);
$new_articles_rows = implode(", ", $new_articles_rows);

$new_bytes = mysqli_query($con, "
    SELECT
       date_generator.date as the_date,
       IFNULL(SUM(`queried`.`bytes`) / 1024, 0) as count
    from ( {$date_generator} ) date_generator
    left join (SELECT `n`, DATE(`timestamp`) AS date_timestamp, `bytes`, `valid_edit` from edits WHERE `bytes` > 0) as `queried` on `queried`.date_timestamp = date_generator.date
    GROUP BY date;
");
while ($row = mysqli_fetch_assoc($new_bytes)) $new_bytes_rows[] = $row['count'];
array_splice($new_bytes_rows, $elapsed_days);
$new_bytes_rows = implode(", ", $new_bytes_rows);

$valid_bytes = mysqli_query($con, "
    SELECT
       date_generator.date as the_date,
       IFNULL(SUM(`queried`.`bytes`) / 1024, 0) as count
    from ( {$date_generator} ) date_generator
    left join (SELECT `n`, DATE(`timestamp`) AS date_timestamp, `bytes`, `valid_edit` from edits WHERE `valid_edit` = 1) as `queried` on `queried`.date_timestamp = date_generator.date
    GROUP BY date;
");
while ($row = mysqli_fetch_assoc($valid_bytes)) $valid_bytes_rows[] = $row['count'];
array_splice($valid_bytes_rows, $elapsed_days);
$valid_bytes_rows = implode(", ", $valid_bytes_rows);

//Captura horário de última edição avaliada no banco de dados
$lastedit_query = mysqli_query($con, "SELECT `timestamp` AS `lastedit` FROM `edits` WHERE `valid_edit` IS NOT NULL ORDER BY `timestamp` DESC LIMIT 1;");
$lastedit = mysqli_fetch_assoc($lastedit_query);
if (isset($lastedit["lastedit"])) {
    $lastedit = strtotime($lastedit["lastedit"]);
} else {
    $lastedit = "-";
}


?>

<!DOCTYPE html>
<html>
    <title><?=$contest['name'];?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="./bin/w3.css">
    <script src="https://tools-static.wmflabs.org/cdnjs/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <body>
        <header class="w3-<?=$contest['theme'];?> w3-container">
            <h1><?=$contest['name'];?></h1>
        </header>
        <div class="w3-container w3-padding-32">
            <div class="w3-row-padding"> 
                <div class="w3-third w3-section">
                    <div class="w3-card-4 w3-padding">
                        <canvas id="total_edits"></canvas>
                    </div>
                </div>
                <div class="w3-third w3-section">
                    <div class="w3-card-4 w3-padding">
                        <canvas id="valid_edits"></canvas>
                    </div>
                </div>
                <div class="w3-third w3-section">
                    <div class="w3-card-4 w3-padding">
                        <canvas id="new_articles"></canvas>
                    </div>
                </div>
            </div>
            <div class="w3-row-padding">
                <div class="w3-third w3-section">
                    <div class="w3-card-4 w3-padding">
                        <canvas id="new_bytes"></canvas>
                    </div>
                </div>
                <div class="w3-third w3-section">
                    <div class="w3-card-4 w3-padding">
                        <canvas id="valid_bytes"></canvas>
                    </div>
                </div>
                <div class="w3-third w3-section">
                    <div class="w3-card-4 w3-light-grey w3-justify">
                        <header class="w3-<?=$contest['theme'];?> w3-container">
                            <h1>Sistema de avaliações</h1>
                        </header>
                        <div class="w3-container">
                            <p class="w3-small">
                                <b>Horário de início do wikiconcurso</b>
                                <br>
                                <?=date('d/m/Y H:i:s (\U\T\C)', $contest['start_time']);?>
                            </p>
                            <p class="w3-small">
                                <b>Horário de término do wikiconcurso</b>
                                <br>
                                <?=date('d/m/Y H:i:s (\U\T\C)', $contest['end_time']);?>
                            </p>
                            <p class="w3-small">
                                <b>Horário da última edição avaliada</b>
                                <br>
                                <?=date('d/m/Y H:i:s (\U\T\C)', $lastedit);?>
                            </p>
                        </div>
                        <button onclick="document.getElementById('id01').style.display='block'" class="w3-button w3-block w3-<?=$contest['theme'];?> w3-large" style="filter: hue-rotate(180deg);">Clique aqui para entrar</button>
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
                                <b>E-mail</b>
                            </label>
                            <input class="w3-input w3-border w3-margin-bottom" type="email" placeholder="Insira seu e-mail" name="email" required>
                            <label>
                                <b>Senha</b>
                            </label>
                            <input class="w3-input w3-border" type="password" placeholder="Insira sua senha" name="password" required>
                            <button class="w3-button w3-block w3-<?=$contest['theme'];?> w3-section w3-padding" name="do_login" type="submit">Entrar</button>
                        </div>
                    </form>
                    <div class="w3-container w3-border-top w3-padding-16 w3-light-grey">
                        <button onclick="document.getElementById('id01').style.display='none'" type="button" class="w3-button w3-red">Cancelar</button>
                        <button class="w3-right w3-button w3-blue" type="submit" form="login" name="do_create">Pré-cadastrar</button>
                    </div>
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
                                text: "Edições por dia"
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
                                label: 'Edições por dia',
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
                                text: "Edições validadas por dia"
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
                                label: 'Edições validadas por dia',
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
                                text: "Novos artigos por dia"
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
                                label: 'Novos artigos por dia',
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
                                text: "KBytes adicionados por dia"
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
                                label: 'KBytes adicionados por dia',
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
                                text: "KBytes validados por dia"
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
                                label: 'KBytes validados por dia',
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
