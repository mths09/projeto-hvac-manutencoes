<?php

declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

function responderConfirmacaoCadastro(int $status, string $mensagem): never
{
    http_response_code($status);
    echo json_encode(['sucesso' => $status === 200, 'mensagem' => $mensagem], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    responderConfirmacaoCadastro(405, 'Método não permitido.');
}

$entrada = json_decode((string) file_get_contents('php://input'), true);
$token = is_array($entrada) ? ($entrada['token'] ?? null) : null;
if (!is_string($token)) {
    responderConfirmacaoCadastro(422, 'O link de confirmação é inválido.');
}

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    require_once __DIR__ . '/conexao.php';
    require_once __DIR__ . '/cadastro_confirmacao.php';
    $resultado = confirmarCadastroPendente($conexao, $token);
    responderConfirmacaoCadastro($resultado['status'], $resultado['mensagem']);
} catch (Throwable $erro) {
    error_log('Falha no endpoint de confirmação de cadastro: class=' . get_class($erro));
    responderConfirmacaoCadastro(503, 'Não foi possível confirmar o cadastro agora. Tente novamente mais tarde.');
}