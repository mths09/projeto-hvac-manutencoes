<?php
declare(strict_types=1);

function responderJson(int $status, array $dados): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

function iniciarSessaoSegura(): void {
    if(session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    if(!session_start([
        'use_strict_mode' => true,
        'use_only_cookies' => true,
        'cookie_httponly' => true,
        'cookie_secure' => $https,
        'cookie_samesite' => 'Lax',
        'cookie_path' => '/',
    ])) {
        throw new RuntimeException('Falha ao iniciar sessão.');
    }
}

function tokenCsrf(string $chave): string {
    iniciarSessaoSegura();
    $_SESSION[$chave] ??= bin2hex(random_bytes(32));

    return $_SESSION[$chave];
}

function exigirCsrf(string $chave, mixed $enviado): void {
    $esperado = tokenCsrf($chave);

    if(!is_string($enviado) || !hash_equals($esperado, $enviado)) {
        responderJson(403, [
            'sucesso' => false,
            'mensagem' => 'Sessão expirada. Atualize a página e tente novamente.',
        ]);
    }
}