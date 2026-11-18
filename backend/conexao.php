<?php
date_default_timezone_set('America/Sao_Paulo');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Use HVAC_DB_* (ou as variáveis MYSQL* do Railway) sem sobrescrever dados do login.
$dbHost = getenv('HVAC_DB_HOST') ?: (getenv('MYSQLHOST') ?: 'localhost');
$dbUsuario = getenv('HVAC_DB_USER') ?: (getenv('MYSQLUSER') ?: 'root');
$dbSenha = getenv('HVAC_DB_PASSWORD');
if ($dbSenha === false) {
    $dbSenha = getenv('MYSQLPASSWORD');
}
$dbSenha = $dbSenha === false ? '' : $dbSenha;
$dbNome = getenv('HVAC_DB_NAME') ?: (getenv('MYSQLDATABASE') ?: 'hvac_db');
$dbPorta = (int) (getenv('HVAC_DB_PORT') ?: (getenv('MYSQLPORT') ?: 3306));

$conexao = new mysqli(
    $dbHost,
    $dbUsuario,
    $dbSenha,
    $dbNome,
    $dbPorta
);
$conexao->set_charset('utf8mb4');

// Usa o mesmo fuso nas funções de data do PHP e nas consultas MySQL desta conexão.
$offsetFusoBanco = (new DateTimeImmutable())->format('P');
$conexao->query("SET time_zone = '{$offsetFusoBanco}'");
