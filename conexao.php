<?php
$hostname = "localhost";
$bancodedados = "db_biblios_b";
$usuario = "root";
$senha = "";

$mysqli = new mysqli($hostname,$usuario, $senha,$bancodedados);

if ($mysqli->connect_errno) {
    die("Falha ao conectar: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error);
}

// Garante que a acentuação venha correta do banco
$mysqli->set_charset("utf8mb4");
?>