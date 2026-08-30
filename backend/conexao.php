<?php

$servidor = "localhost";
$usuario = "root";
$senha = "";
$banco = "hvac";

$conexao = new mysqli(
    $servidor,
    $usuario,
    $senha,
    $banco
);

if ($conexao->connect_error) {
    die("Erro na conexão com o banco de dados: " . $conexao->connect_error);
}

$conexao->set_charset("utf8mb4");

echo "Conexão com o banco realizada com sucesso!";

?>