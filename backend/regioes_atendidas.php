<?php

declare(strict_types=1);

require_once __DIR__ . '/regioes_config.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

if(($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    http_response_code(405);

    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Método não permitido.',
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

echo json_encode([
    'sucesso' => true,
    'regioes' => REGIOES_ATENDIDAS,
], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);