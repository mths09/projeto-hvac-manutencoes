<?php
date_default_timezone_set('America/Sao_Paulo');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$configBanco = require __DIR__ . '/db_config.local.php';

$conexao = new mysqli(
    $configBanco['host'],
    $configBanco['usuario'],
    $configBanco['senha'],
    $configBanco['banco']
);

$conexao->set_charset('utf8mb4');

// Usa o mesmo fuso nas funções de data do PHP e nas consultas MySQL desta conexão.
$offsetFusoBanco = (new DateTimeImmutable())->format('P');
$conexao->query("SET time_zone = '{$offsetFusoBanco}'");
