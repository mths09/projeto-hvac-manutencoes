<?php

declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function responderLogin(int $status, array $dados): never
{
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    responderLogin(405, [
        'sucesso' => false,
        'mensagem' => 'Acesse a página de login para entrar.'
    ]);
}

$email = $_POST['email'] ?? null;
$senha = $_POST['senha'] ?? null;
$area = $_POST['area'] ?? 'cliente';

if (!is_string($email) || !is_string($senha) || !is_string($area)) {
    responderLogin(422, [
        'sucesso' => false,
        'mensagem' => 'Informe um e-mail válido e sua senha.'
    ]);
}

$email = strtolower(trim($email));
$area = strtolower(trim($area));

if (!in_array($area, ['cliente', 'admin'], true)) {
    responderLogin(422, [
        'sucesso' => false,
        'mensagem' => 'Área de acesso inválida.'
    ]);
}

if (
    strlen($email) > 254
    || !filter_var($email, FILTER_VALIDATE_EMAIL)
    || $senha === ''
    || strlen($senha) > 72
    || str_contains($senha, "\0")
) {
    responderLogin(422, [
        'sucesso' => false,
        'mensagem' => 'Informe um e-mail válido e sua senha.'
    ]);
}

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    require_once __DIR__ . '/conexao.php';

    $stmt = $conexao->prepare(
        'SELECT id_usuario, nome, email, senha_hash, perfil, ativo
         FROM usuario
         WHERE email = ?
         LIMIT 1'
    );
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $usuario = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $conexao->close();

    if (
        !$usuario
        || (int) $usuario['ativo'] !== 1
        || !password_verify($senha, $usuario['senha_hash'])
    ) {
        responderLogin(401, [
            'sucesso' => false,
            'mensagem' => 'E-mail ou senha inválidos. Confira os dados e tente novamente.'
        ]);
    }

    $perfil = strtolower(trim((string) $usuario['perfil']));
    $ehAdministrador = in_array($perfil, ['admin', 'administrador'], true);
    $perfilPermitido = $area === 'admin'
        ? $ehAdministrador
        : $perfil === 'cliente';

    if (!$perfilPermitido) {
        responderLogin(401, [
            'sucesso' => false,
            'mensagem' => 'E-mail ou senha inválidos. Confira os dados e tente novamente.'
        ]);
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    if (!session_start() || !session_regenerate_id(true)) {
        throw new RuntimeException('Falha ao iniciar uma sessão segura.');
    }

    $_SESSION = [
        'usuario_id' => (int) $usuario['id_usuario'],
        'usuario_nome' => $usuario['nome'],
        'usuario_email' => $usuario['email'],
        'usuario_perfil' => $perfil
    ];

    session_write_close();

    responderLogin(200, [
        'sucesso' => true,
        'mensagem' => 'Login realizado! Abrindo seu painel...',
        'area' => $area,
        'destino' => $area === 'admin'
            ? './tela-adm.html'
            : './dashboard.html'
    ]);
} catch (Throwable $erro) {
    error_log(
        'Falha no login: ' . get_class($erro)
        . ' - código ' . $erro->getCode()
    );

    responderLogin(500, [
        'sucesso' => false,
        'mensagem' => 'Não foi possível realizar o login. Tente novamente.'
    ]);
}
