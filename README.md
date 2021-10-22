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


## Setting up cron jobs


## Setting up a new Wiki Contest
### Set up a new database
Create a new local database for the contest. Name it s54728__CONTEST-NAME. 

### Add the contest to bin/data.php
Manually add a new contest 'CONTEST-NAME' in $contests_array. We suggest copying and editing 'casabr'.

### Set up a cron job to update your file.


## Contributing
Pull requests are welcome. For major changes, please open an issue first to discuss what you would like to change.

Please make sure to update tests as appropriate.

## License
[GNU General Public License v3.0](https://github.com/WikiMovimentoBrasil/wikimotivos/blob/master/LICENSE)

## Credits
This application was developed by the Wiki Movimento Brasil User Group.