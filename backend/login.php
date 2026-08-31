<?php
require_once "conexao.php";
session_start();

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = $_POST["email"];
    $senha = $_POST["senha"];

    // Busca o usuário pelo e-mail
    $sql = "SELECT id, nome, email, senha FROM usuario WHERE email = ?";
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();

    $resultado = $stmt->get_result();
    $usuario = $resultado->fetch_assoc();

    if ($usuario && password_verify($senha, $usuario["senha"])) {
        // Senha correta: cria a sessão
        session_regenerate_id(true);

        $_SESSION["usuario_id"] = $usuario["id"];
        $_SESSION["usuario_nome"] = $usuario["nome"];
        $_SESSION["usuario_email"] = $usuario["email"];

        echo "Login realizado com sucesso! Bem-vindo(a), " . $usuario["nome"] . "!";
    } else {
        // Mensagem genérica: não revela se o e-mail existe ou não
        echo "E-mail ou senha inválidos.";
    }

    $stmt->close();

} else {
    echo "Método não permitido.";
}

$conexao->close();
?>