<?php

declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/perfil_acesso.php';

const JANELA_TENTATIVAS_LOGIN_MINUTOS = 5;
const LIMITE_TENTATIVAS_EMAIL_LOGIN = 5;
const LIMITE_TENTATIVAS_IP_LOGIN = 30;

function responderLogin(int $status, array $dados): never
{
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

function contarTentativasPorEmail(mysqli $conexao, string $emailHash): int
{
    $stmt = $conexao->prepare(
        'SELECT COUNT(*) AS total FROM tentativa_login
         WHERE email_hash = ? AND tentada_em >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)'
    );
    $stmt->bind_param('s', $emailHash);
    $stmt->execute();
    $linha = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int) ($linha['total'] ?? 0);
}

function contarTentativasPorIp(mysqli $conexao, string $ipHash): int
{
    $stmt = $conexao->prepare(
        'SELECT COUNT(*) AS total FROM tentativa_login
         WHERE ip_hash = ? AND tentada_em >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)'
    );
    $stmt->bind_param('s', $ipHash);
    $stmt->execute();
    $linha = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int) ($linha['total'] ?? 0);
}

function duracaoBloqueioEmail(int $tentativas): int
{
    if ($tentativas < LIMITE_TENTATIVAS_EMAIL_LOGIN) return 0;
    return min(($tentativas - LIMITE_TENTATIVAS_EMAIL_LOGIN + 1) * 20, 60);
}

function segundosRestantesBloqueio(int $ultimaTentativa, int $duracao, ?int $agora = null): int
{
    return max(0, $ultimaTentativa + $duracao - ($agora ?? time()));
}

function timestampTentativaEmail(mysqli $conexao, string $emailHash, bool $maisRecente): ?int
{
    $funcao = $maisRecente ? 'MAX' : 'MIN';
    $stmt = $conexao->prepare(
        "SELECT UNIX_TIMESTAMP({$funcao}(tentada_em)) AS instante FROM tentativa_login
         WHERE email_hash = ? AND tentada_em >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)"
    );
    $stmt->bind_param('s', $emailHash);
    $stmt->execute();
    $valor = $stmt->get_result()->fetch_assoc()['instante'] ?? null;
    $stmt->close();
    return $valor === null ? null : (int) $valor;
}

function timestampTentativaIpMaisAntiga(mysqli $conexao, string $ipHash): ?int
{
    $stmt = $conexao->prepare(
        'SELECT UNIX_TIMESTAMP(MIN(tentada_em)) AS instante FROM tentativa_login
         WHERE ip_hash = ? AND tentada_em >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)'
    );
    $stmt->bind_param('s', $ipHash);
    $stmt->execute();
    $valor = $stmt->get_result()->fetch_assoc()['instante'] ?? null;
    $stmt->close();
    return $valor === null ? null : (int) $valor;
}

function segundosBloqueioLogin(mysqli $conexao, string $emailHash, string $ipHash): int
{
    $falhasEmail = contarTentativasPorEmail($conexao, $emailHash);
    $falhasIp = contarTentativasPorIp($conexao, $ipHash);
    $agora = time();
    $esperaEmail = 0;
    $esperaIp = 0;

    if ($falhasEmail >= LIMITE_TENTATIVAS_EMAIL_LOGIN) {
        $ultima = timestampTentativaEmail($conexao, $emailHash, true);
        if ($ultima !== null) $esperaEmail = segundosRestantesBloqueio($ultima, duracaoBloqueioEmail($falhasEmail), $agora);
    }
    if ($falhasIp >= LIMITE_TENTATIVAS_IP_LOGIN) {
        $primeira = timestampTentativaIpMaisAntiga($conexao, $ipHash);
        if ($primeira !== null) $esperaIp = segundosRestantesBloqueio($primeira, JANELA_TENTATIVAS_LOGIN_MINUTOS * 60, $agora);
    }
    return max($esperaEmail, $esperaIp);
}

function loginBloqueadoPorTentativas(mysqli $conexao, string $emailHash, string $ipHash): bool
{
    return segundosBloqueioLogin($conexao, $emailHash, $ipHash) > 0;
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

function responderBloqueioLogin(int $segundos): never
{
    header('Retry-After: ' . $segundos);
    responderLogin(429, [
        'sucesso' => false,
        'mensagem' => 'Muitas tentativas de acesso. Aguarde antes de tentar novamente.',
        'aguardar_segundos' => $segundos,
    ]);
}

function negarLogin(mysqli $conexao, string $emailHash, string $ipHash): never
{
    registrarFalhaLogin($conexao, $emailHash, $ipHash);
    $segundos = segundosBloqueioLogin($conexao, $emailHash, $ipHash);
    $conexao->close();
    if ($segundos > 0) responderBloqueioLogin($segundos);
    responderLogin(401, [
        'sucesso' => false,
        'mensagem' => 'E-mail ou senha inválidos. Confira os dados e tente novamente.'
    ]);
}

function limparTentativasLoginBemSucedidas(mysqli $conexao, string $emailHash): void
{
    $stmt = $conexao->prepare('DELETE FROM tentativa_login WHERE email_hash = ?');
    $stmt->bind_param('s', $emailHash);
    $stmt->execute();
    $stmt->close();
}

if (defined('HVAC_LOGIN_TESTE')) {
    return;
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

    $segundosBloqueio = segundosBloqueioLogin($conexao, $emailHash, $ipHash);
    if ($segundosBloqueio > 0) {
        $conexao->close();
        responderBloqueioLogin($segundosBloqueio);
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
    $area = areaPermitidaLogin($perfil);
    if ($area === null) {
        negarLogin($conexao, $emailHash, $ipHash);
    }

    limparTentativasLoginBemSucedidas($conexao, $emailHash);
    $conexao->close();

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
