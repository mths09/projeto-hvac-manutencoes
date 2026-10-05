<?php

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Nomes próprios para não sobrescrever os dados do login.
$dbHost = 'localhost';
$dbUsuario = 'root';
$dbSenha = '';
$dbNome = 'hvac_db';

$conexao = new mysqli(
    $dbHost,
    $dbUsuario,
    $dbSenha,
    $dbNome
);

$conexao->set_charset('utf8mb4');