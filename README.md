<img src="https://img.shields.io/github/issues/WikiMovimentoBrasil/wikiconcursos?style=for-the-badge"/> <img src="https://img.shields.io/github/license/WikiMovimentoBrasil/wikiconcursos?style=for-the-badge"/> <img src="https://img.shields.io/github/languages/top/WikiMovimentoBrasil/wikiconcursos?style=for-the-badge"/> [![Quality Gate Status](https://sonarcloud.io/api/project_badges/measure?project=wikimovimentobrasil_wikiconcursos&metric=alert_status)](https://sonarcloud.io/summary/new_code?id=wikimovimentobrasil_wikiconcursos)
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

### Define database connection credentials
If you use Toolforge, credentials for connecting to the database are automatically registered. If you use another host, credentials must be entered on lines 17-23 of the index.php file.

### Set up a new database and tables
Create a new database for the contest. Instructions for the tables are described in NewTables.sql. When running, replace "NameID" with the contest ID of your choice.

### Add the contest
Please check the instructions on this repository's wiki for instructions.

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
