<?php

session_start();

header("Content-Type: text/html; charset=UTF-8");

// Aceita somente requisições POST.
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    header("Allow: POST");
    exit("Método não permitido.");
}

// Recebe os campos do formulário.
$email = $_POST["email"] ?? "";
$senha = $_POST["senha"] ?? "";

// Verifica se os dados recebidos são textos.
if (!is_string($email) || !is_string($senha)) {
    http_response_code(400);
    exit("Dados inválidos.");
}

$email = trim($email);

// Valida os campos obrigatórios.
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $senha === "") {
    http_response_code(400);
    exit("Informe um e-mail válido e sua senha.");
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    require_once __DIR__ . "/conexao.php";

    // Busca o usuário usando o nome correto da coluna.
    $sql = "SELECT id_usuario, nome, email, senha
            FROM usuario
            WHERE email = ?
            LIMIT 1";

    $stmt = $conexao->prepare($sql);

    // Envia o e-mail como parâmetro da consulta.
    $stmt->bind_param("s", $email);
    $stmt->execute();

    $resultado = $stmt->get_result();
    $usuario = $resultado->fetch_assoc();

    $stmt->close();
    $conexao->close();

    // Confere a senha digitada com o hash salvo no banco.
    if (!$usuario || !password_verify($senha, $usuario["senha"])) {
        http_response_code(401);
        exit("E-mail ou senha inválidos.");
    }

    // Renova o identificador da sessão após o login.
    session_regenerate_id(true);

    $_SESSION["usuario_id"] = $usuario["id_usuario"];
    $_SESSION["usuario_nome"] = $usuario["nome"];
    $_SESSION["usuario_email"] = $usuario["email"];

    // Protege a saída HTML ao exibir o nome.
    $nomeSeguro = htmlspecialchars(
        $usuario["nome"],
        ENT_QUOTES | ENT_SUBSTITUTE,
        "UTF-8"
    );

    echo "Login realizado com sucesso! Bem-vindo(a), {$nomeSeguro}!";

} catch (mysqli_sql_exception $erro) {
    // Registra os detalhes no servidor.
    error_log($erro->getMessage());

    http_response_code(500);
    echo "Não foi possível realizar o login. Tente novamente.";
}   