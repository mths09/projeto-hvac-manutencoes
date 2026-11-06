<?php

declare(strict_types=1);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
http_response_code(410);
echo json_encode([
    'sucesso' => false,
    'mensagem' => 'A área de técnico não está disponível neste sistema.',
], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
