<img src="https://img.shields.io/github/issues/WikiMovimentoBrasil/wikiconcursos?style=for-the-badge"/> <img src="https://img.shields.io/github/license/WikiMovimentoBrasil/wikiconcursos?style=for-the-badge"/> <img src="https://img.shields.io/github/languages/top/WikiMovimentoBrasil/wikiconcursos?style=for-the-badge"/>
# WikiConcursos

This is an internal tool used to manage the Wiki Contests created and managed by WikiMovimento Brasil. It allows evaluators to validate editions made to articles participating in said contests, and also adds up the points earned by participants. Different contest evaluators can have their own profile, and their own separate validation history of the contributions they have checked.

The system's informations comes from local databases, which contain the data on the articles editions. These databases are fed by cron jobs, which must be set up separetely. 


## Running the script locally
Use the following command:
```bash
php -S 127.0.0.1:8000
```

Then visit 127.0.0.1:8000 on your prefered browser. 

## Setting up a new Wiki Contest

### Set up a new database
Create a new local database for the contest. Instructions are described in NewDB.sql. When running, replace "DatabaseName" with the database name of your choice.

### Define database connection credentials
Database connection credentials must be entered on line 16 of the index.php file.

```php
//Define credenciais do banco de dados
$db_user = //DB user
$db_pass = //DB pass
$db_host = //DB host
$database = //Database name
```

### Add the contest to bin/data.php
```php
'example' => array(
        'name_id'               => "example",              //Must be the same as the array key
        'start_time'            => "1615766399",           //Unix time
        'end_time'              => "1621123200",           //Unix time
        'name'                  => "Example Contest 2020", //Long name of the contest
        'revert_time'           => "-24 hours",            //Recomended, but can be changed
        'official_list_pageid'  => "6496164",              //Page ID of the list of articles
        'category_pageid'       => "6517644",              //Category containing the articles
        'endpoint'              => "https://pt.wikipedia.org/w/index.php",
        'api_endpoint'          => "https://pt.wikipedia.org/w/api.php",
        'outreach_name'         => 'Museu/Wikiconcurso',   //Course adress at outreachdashboard.wmflabs.org
        'bytes_per_points'      => "3000",                 //Number of bytes needed to reach 1 point
        'max_bytes_per_article' => "90000",                //Maximum number of bytes allowed per article, per participant
        'pictures_per_points'   => "5",                    //Number of images needed to reach 1 point
        'pictures_mode'         => "0",                    //See below
        'max_pic_per_article'   => "3",                    //Maximum number of pictures allowed per article, per participant
        'theme'                 => "amber"                 //See list: https://www.w3schools.com/w3css/w3css_colors.asp
    ),
```

### Pictures mode
0 = Sistema booleano por artigo. Contabiliza apenas a inserção de uma imagem em um artigo, ignorando outras imagens adicionadas no mesmo artigo.
1 = Sistema booleano por edição. Contabiliza apenas a inserção de uma imagem em uma edição, ignorando outras imagens adicionadas na mesma edição.
2 = Sistema integer. Contabiliza cada imagem inserida no artigo até o máximo estabelecido em 'max_bytes_per_article'.

### Set up the cron jobs to update your file
Set up the recurring scripts for your new contest as described in the previous section.

### Enter the reviewers' credentials
Enter the evaluators' data in the "credentials" table in the database. The model below is suggested, replacing the NAME and E-MAIL with the evaluator's information. The password hash can be calculated in: https://phppasswordhash.com/

```sql
INSERT INTO `credencials` (`user_name`, `user_email`, `user_password`, `user_status`) 
VALUES ('NAME', 'E-MAIL', 'HASH', 'A');
```

If the evaluator needs to be blocked, replace the user_status value in the database to "P".

## Setting up cron jobs
Three scripts must be set up to run daily, or on whichever frequency you prefer, to feed the contests' databases:
1. load_edits
2. load_users
3. load_reverts

Scripts are required to run in the order above, as some processes depend on running the previous script. They are responsible for gathering the information on:
1. new editions made in listed articles,
2. which users are participating in the contest,
3. mark edits made by participants on edit's table, if made after the participant's time of enrollment,
4. checks if users has been renamed during the course of the contest,
6. if any previous edition has been reverted, unmade or deleted

The URL to run the scripts is:
```
index.php?contest=CONTEST_NAME&page=SCRIPT_NAME
```

## Contributing
Pull requests are welcome. For major changes, please open an issue first to discuss what you would like to change.

Please make sure to update tests as appropriate.

## License
[GNU General Public License v3.0](https://github.com/WikiMovimentoBrasil/wikimotivos/blob/master/LICENSE)

## Credits
This application was developed by the Wiki Movimento Brasil User Group.
