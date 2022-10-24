<?php

// Logout, caso solicitado
session_start();
if (isset($_POST['logout'])) {
	unset($_SESSION['user']);
}

// Verifica se sessão pertence ao concurso em tela. Caso contrário, faz logout automaticamente.
if (isset($_SESSION['user'])) {
	if ($_SESSION['user']['contest'] != $contest['name_id']) {
		unset($_SESSION['user']);
	}
}

// Redireciona para login, caso necessário
if (!isset($_SESSION['user'])) {
	header("Location: index.php?contest=".$_GET['contest']."&page=login");
	exit();
}