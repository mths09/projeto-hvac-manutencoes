<?php

declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store');

// Aceitar somente o envio do formulário por POST.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');

    exit('Método não permitido. Acesse a página de login.');
}

// Receber os dados do formulário.
$email = $_POST['email'] ?? '';
$senha = $_POST['senha'] ?? '';

// Garantir que os valores sejam textos.
if (!is_string($email) || !is_string($senha)) {
    http_response_code(422);
    exit('Informe um e-mail válido e sua senha.');
}

$email = strtolower(trim($email));

// Não remover espaços da senha.
if (
    strlen($email) > 254 ||
    !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    $senha === '' ||
    strlen($senha) > 72 ||
    str_contains($senha, "\0")
) {
    http_response_code(422);
    exit('Informe um e-mail válido e sua senha.');
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Abrir a conexão com o banco hvac_db.
    require_once __DIR__ . '/conexao.php';

    // Buscar a conta cadastrada.
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

    // Conferir a senha e verificar se a conta está ativa.
    if (
        !$usuario ||
        (int) $usuario['ativo'] !== 1 ||
        !password_verify($senha, $usuario['senha_hash'])
    ) {
        http_response_code(401);
        exit('E-mail ou senha inválidos.');
    }

    // Configurar a sessão.
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

    // Renovar o identificador após autenticar.
    if (!session_regenerate_id(true)) {
        throw new RuntimeException('Falha ao renovar a sessão.');
    }

    // Guardar os dados que identificam o usuário.
    // Nunca guardar a senha na sessão.
    $_SESSION = [
        'usuario_id' => (int) $usuario['id_usuario'],
        'usuario_nome' => $usuario['nome'],
        'usuario_email' => $usuario['email'],
        'usuario_perfil' => $usuario['perfil']
    ];

    session_write_close();

    // Encaminhar para o dashboard.
    header(
        'Location: ../frontend/src/paginas/dashboard.html',
        true,
        303
    );

    exit;

} catch (Throwable $erro) {
    // Registrar o tipo da falha sem expor dados ao usuário.
    error_log(
        'Falha no login: '
        . get_class($erro)
        . ' - código '
        . $erro->getCode()
    );

    http_response_code(500);
    exit('Não foi possível realizar o login. Tente novamente.');
}