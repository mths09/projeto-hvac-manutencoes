<?php

declare(strict_types=1);
require_once __DIR__ . '/google_auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    responderGoogle(405, ['sucesso' => false, 'mensagem' => 'Método não permitido.']);
}

try {
    $clientId = clientIdGoogle();
    if ($clientId === '') {
        responderGoogle(503, ['sucesso' => false, 'mensagem' => 'O login Google ainda não foi configurado.']);
    }
    iniciarSessaoGoogle();
    $_SESSION['csrf_google'] = bin2hex(random_bytes(32));
    $_SESSION['nonce_google'] = bin2hex(random_bytes(32));
    responderGoogle(200, [
        'sucesso' => true,
        'client_id' => $clientId,
        'csrf' => $_SESSION['csrf_google'],
        'nonce' => $_SESSION['nonce_google'],
    ]);
} catch (Throwable $erro) {
    error_log('Falha ao preparar login Google: ' . get_class($erro));
    responderGoogle(500, ['sucesso' => false, 'mensagem' => 'Não foi possível preparar o login Google.']);
}
