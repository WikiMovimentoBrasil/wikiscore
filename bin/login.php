<?php
// (A) PROCESS LOGIN ON SUBMIT
session_start();
if (isset($_POST['email'])) {
  require "credencials-lib.php";
  $USR->login($_POST['email'], $_POST['password']);
}
 
// (B) REDIRECT USER IF SIGNED IN
if (isset($_SESSION['user'])) {
  header("Location: index.php?contest=".$_GET['contest']."&page=triage");
  exit();
}
 
// (C) SHOW LOGIN FORM OTHERWISE ?>
<?php
if (isset($_POST['email'])) {
  echo "<div id='notify'>E-mail/senha inválidos</div>";
}

//Coleta informações do concurso
require "data.php";
?>

<!DOCTYPE html>
<html>
	<title><?php echo $contest['name']; ?></title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="stylesheet" href="./bin/w3.css">
	<body>
		<header class=<?php echo("\"w3-container w3-".$contest['theme']."\"");?>>
			<h1><?php echo $contest['name']; ?></h1>
		</header>
		<div class="w3-container w3-half w3-margin-top">
			<form class="w3-container w3-card-4" id="login" method="post">
				<p>
					<input class="w3-input" type="email" name="email" style="width:90%" required>
					<label>E-mail</label>
				</p>
				<p>
					<input class="w3-input" type="password" name="password" style="width:90%" required>
					<label>Senha</label>
				</p>
				<p>
					<button class="w3-button w3-section w3-<?php echo($contest['theme']);?> w3-ripple">Entrar</button>
				</p>
			</form>
		</div>
</body>
</html> 