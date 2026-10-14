<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '0');

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function responder(int $status, array $dados) {
    http_response_code($status);

    echo json_encode(
        $dados,
        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );

    exit;
}

// so aceita o metodo PATCH
if(($_SERVER['REQUEST_METHOD'] ?? '') !== 'PATCH') {
    header('Allow: PATCH');

    responder(405, [
        'mensagem' => 'Método não permitido.'
    ]);
}

// descobrir o perfil logado
$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

session_start([
    'use_strict_mode' => true,
    'use_only_cookies' => true,
    'cookie_httponly' => true,
    'cookie_secure' => $https,
    'cookie_samesite' => 'Lax',
    'cookie_path' => '/',
    'read_and_close' => true
]);

$idUsuario = filter_var(
    $_SESSION['usuario_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if($idUsuario === false) {
    responder(401, [
        'mensagem' => 'Entre na sua conta para continuar.'    
    ]);
}

// confirmar funcionamento (por enquanto)
responder(200, [
    'sucesso' => true,
    'mensagem' => 'Rota funcionando.',
    'usuario_id' => $idUsuario
]);