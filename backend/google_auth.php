<?php

declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function responderGoogle(int $status, array $dados): never
{
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

function iniciarSessaoGoogle(): void
{
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    if (!session_start()) {
        throw new RuntimeException('Falha ao iniciar sessão segura.');
    }
}

function clientIdGoogle(): string
{
    return trim((string) (getenv('HVAC_GOOGLE_CLIENT_ID') ?: ''));
}

function emailGoogleAutoritativo(string $email, array $payload): bool
{
    if (($payload['email_verified'] ?? false) !== true) {
        return false;
    }
    $dominioEmail = strtolower((string) substr(strrchr($email, '@') ?: '', 1));
    if ($dominioEmail === 'gmail.com') {
        return true;
    }
    $dominioWorkspace = strtolower(trim((string) ($payload['hd'] ?? '')));
    return $dominioWorkspace !== '' && hash_equals($dominioEmail, $dominioWorkspace);
}

function estabelecerSessaoGoogle(array $usuario): void
{
    if (!session_regenerate_id(true)) {
        throw new RuntimeException('Falha ao renovar sessão.');
    }
    $_SESSION = [
        'usuario_id' => (int) $usuario['id_usuario'],
        'usuario_nome' => (string) $usuario['nome'],
        'usuario_email' => (string) $usuario['email'],
        'usuario_perfil' => 'cliente',
    ];
}
