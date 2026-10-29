<?php

declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

const JANELA_TENTATIVAS_LOGIN_MINUTOS = 5;
const LIMITE_TENTATIVAS_EMAIL_LOGIN = 5;
const LIMITE_TENTATIVAS_IP_LOGIN = 30;

function responderLogin(int $status, array $dados): never
{
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

function contarTentativasLogin(mysqli $conexao, string $coluna, string $hash): int
{
    // O nome da coluna é escolhido internamente, nunca vem da requisição.
    if (!in_array($coluna, ['email_hash', 'ip_hash'], true)) {
        throw new InvalidArgumentException('Identificador de limite inválido.');
    }

    $stmt = $conexao->prepare(
        "SELECT COUNT(*) AS total
         FROM tentativa_login
         WHERE {$coluna} = ?
           AND tentada_em >= DATE_SUB(NOW(), INTERVAL " . JANELA_TENTATIVAS_LOGIN_MINUTOS . " MINUTE)"
    );
    $stmt->bind_param('s', $hash);
    $stmt->execute();
    $linha = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int) ($linha['total'] ?? 0);
}

function registrarFalhaLogin(mysqli $conexao, string $emailHash, string $ipHash): void
{
    $stmt = $conexao->prepare(
        'INSERT INTO tentativa_login (email_hash, ip_hash) VALUES (?, ?)'
    );
    $stmt->bind_param('ss', $emailHash, $ipHash);
    $stmt->execute();
    $stmt->close();
}

function negarLogin(mysqli $conexao, string $emailHash, string $ipHash): never
{
    registrarFalhaLogin($conexao, $emailHash, $ipHash);

    $porEmail = contarTentativasLogin($conexao, 'email_hash', $emailHash);
    $porIp = contarTentativasLogin($conexao, 'ip_hash', $ipHash);
    $bloqueado = $porEmail >= LIMITE_TENTATIVAS_EMAIL_LOGIN
        || $porIp >= LIMITE_TENTATIVAS_IP_LOGIN;

    $conexao->close();

    if ($bloqueado) {
        responderLogin(429, [
            'sucesso' => false,
            'mensagem' => 'Muitas tentativas de acesso. Aguarde ' . JANELA_TENTATIVAS_LOGIN_MINUTOS . ' minutos e tente novamente.'
        ]);
    }

    responderLogin(401, [
        'sucesso' => false,
        'mensagem' => 'E-mail ou senha inválidos. Confira os dados e tente novamente.'
    ]);
}

function limparTentativasLoginBemSucedidas(
    mysqli $conexao,
    string $emailHash,
    string $ipHash
): void {
    $stmt = $conexao->prepare(
        'DELETE FROM tentativa_login WHERE email_hash = ? AND ip_hash = ?'
    );
    $stmt->bind_param('ss', $emailHash, $ipHash);
    $stmt->execute();
    $stmt->close();
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    responderLogin(405, [
        'sucesso' => false,
        'mensagem' => 'Acesse a página de login para entrar.'
    ]);
}

$email = $_POST['email'] ?? null;
$senhaDigitada = $_POST['senha'] ?? null;

if (!is_string($email) || !is_string($senhaDigitada)) {
    responderLogin(422, [
        'sucesso' => false,
        'mensagem' => 'Informe um e-mail válido e sua senha.'
    ]);
}

$email = strtolower(trim($email));

if (
    strlen($email) > 254
    || !filter_var($email, FILTER_VALIDATE_EMAIL)
    || $senhaDigitada === ''
    || strlen($senhaDigitada) > 72
    || str_contains($senhaDigitada, "\0")
) {
    responderLogin(422, [
        'sucesso' => false,
        'mensagem' => 'Informe um e-mail válido e sua senha.'
    ]);
}

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    require_once __DIR__ . '/conexao.php';

    // Usa somente REMOTE_ADDR; cabeçalhos encaminhados podem ser falsificados.
    $ipCliente = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    if (filter_var($ipCliente, FILTER_VALIDATE_IP) === false) {
        $ipCliente = 'ip-desconhecido';
    }

    // A tabela guarda hashes em vez de e-mail e IP em texto puro.
    $emailHash = hash('sha256', $email);
    $ipHash = hash('sha256', $ipCliente);

    // Remove até 500 registros antigos por requisição, usando o índice de data.
    $conexao->query(
        'DELETE FROM tentativa_login
         WHERE tentada_em < DATE_SUB(NOW(), INTERVAL 1 DAY)
         LIMIT 500'
    );

    $porEmail = contarTentativasLogin($conexao, 'email_hash', $emailHash);
    $porIp = contarTentativasLogin($conexao, 'ip_hash', $ipHash);

    if (
        $porEmail >= LIMITE_TENTATIVAS_EMAIL_LOGIN
        || $porIp >= LIMITE_TENTATIVAS_IP_LOGIN
    ) {
        $conexao->close();
        responderLogin(429, [
            'sucesso' => false,
            'mensagem' => 'Muitas tentativas de acesso. Aguarde ' . JANELA_TENTATIVAS_LOGIN_MINUTOS . ' minutos e tente novamente.'
        ]);
    }

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

    if (
        !$usuario
        || (int) $usuario['ativo'] !== 1
        || !password_verify($senhaDigitada, $usuario['senha_hash'])
    ) {
        negarLogin($conexao, $emailHash, $ipHash);
    }

    $perfil = strtolower(trim((string) $usuario['perfil']));
    $ehAdministrador = in_array($perfil, ['admin', 'administrador'], true);
    $ehCliente = $perfil === 'cliente';

    if (!$ehAdministrador && !$ehCliente) {
        negarLogin($conexao, $emailHash, $ipHash);
    }

    limparTentativasLoginBemSucedidas($conexao, $emailHash, $ipHash);
    $conexao->close();

    $area = $ehAdministrador ? 'admin' : 'cliente';

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
        'destino' => $ehAdministrador ? './tela-adm.html' : './dashboard.html'
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
