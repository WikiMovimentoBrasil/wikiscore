<pre><?php

//Coleta lista de concursos
require __DIR__.'/bin/data.php';

//Define credenciais do banco de dados
$ts_pw = posix_getpwuid(posix_getuid());
$ts_mycnf = parse_ini_file($ts_pw['dir'] . "/replica.my.cnf");
$db_host = 'tools.db.svc.eqiad.wmflabs';
$db_user = $ts_mycnf['user'];
$db_pass = $ts_mycnf['password'];

//Conecta ao banco de dados
$con = mysqli_connect($db_host, $db_user, $db_pass);
if (mysqli_connect_errno()) {
	echo "Failed to connect to MySQL: " . mysqli_connect_error();
	exit();
}

//Coleta nomes dos bancos de dados
$dbs = array_keys($contests_array);

//Define nomes das tabelas
$tables = ["articles", "credencials", "edits", "users"];

//Loop para montagens das queries
foreach ($dbs as $db) {
	foreach ($tables as $table) {
		$queries[] = "CREATE TABLE `{$db_user}__wikiconcursos`.`{$db}__{$table}` LIKE `{$db_user}__{$db}`.`{$table}`;";
		$queries[] = "INSERT INTO `{$db_user}__wikiconcursos`.`{$db}__{$table}` SELECT * FROM `{$db_user}__{$db}`.`{$table}`;";
	}
}

//Loop para execução das queries
foreach ($queries as $query) {
	mysqli_query($con, $query);
}

//Mensagem de término
echo("Finalizado!");
?>