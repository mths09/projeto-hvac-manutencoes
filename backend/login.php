<?php

declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function responderLogin(
    int $status,
    bool $sucesso,
    string $mensagem
): void {
    http_response_code($status);

    echo json_encode(
        [
            'sucesso' => $sucesso,
            'mensagem' => $mensagem
        ],
        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );

    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');

    responderLogin(
        405,
        false,
        'Acesse a página de login para entrar.'
    );
}

$email = $_POST['email'] ?? '';
$senha = $_POST['senha'] ?? '';

if (!is_string($email) || !is_string($senha)) {
    responderLogin(
        422,
        false,
        'Informe um e-mail válido e sua senha.'
    );
}

$email = strtolower(trim($email));

if (
    strlen($email) > 254 ||
    !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    $senha === '' ||
    strlen($senha) > 72 ||
    str_contains($senha, "\0")
) {
    responderLogin(
        422,
        false,
        'Informe um e-mail válido e sua senha.'
    );
}

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    require_once __DIR__ . '/conexao.php';

    $sql = '
        SELECT
            id_usuario,
            nome,
            email,
            senha_hash,
            perfil,
            ativo
        FROM usuario
        WHERE email = ?
        LIMIT 1
    ';

    $stmt = $conexao->prepare($sql);
    $stmt->bind_param('s', $email);
    $stmt->execute();

    $usuario = $stmt->get_result()->fetch_assoc();

    $stmt->close();
    $conexao->close();

    if (
        !$usuario ||
        (int) $usuario['ativo'] !== 1 ||
        !password_verify($senha, $usuario['senha_hash'])
    ) {
        responderLogin(
            401,
            false,
            'E-mail ou senha inválidos. Confira os dados e tente novamente.'
        );
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    $https = !empty($_SERVER['HTTPS'])
        && $_SERVER['HTTPS'] !== 'off';

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    if (!session_start()) {
        throw new RuntimeException('Falha ao iniciar a sessão.');
    }

    if (!session_regenerate_id(true)) {
        throw new RuntimeException('Falha ao renovar a sessão.');
    }

    $_SESSION = [
        'usuario_id' => (int) $usuario['id_usuario'],
        'usuario_nome' => $usuario['nome'],
        'usuario_email' => $usuario['email'],
        'usuario_perfil' => $usuario['perfil']
    ];

    session_write_close();

    // O JavaScript fará o redirecionamento.
    responderLogin(
        200,
        true,
        'Login realizado! Abrindo seu painel...'
    );

} catch (Throwable $erro) {
    error_log(
        'Falha no login: '
        . get_class($erro)
        . ' - código '
        . $erro->getCode()
    );

    responderLogin(
        500,
        false,
        'Não foi possível realizar o login. Tente novamente.'
    );
}