<?php
date_default_timezone_set('America/Sao_Paulo');

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

// Usa o mesmo fuso nas funções de data do PHP e nas consultas MySQL desta conexão.
$offsetFusoBanco = (new DateTimeImmutable())->format('P');
$conexao->query("SET time_zone = '{$offsetFusoBanco}'");
