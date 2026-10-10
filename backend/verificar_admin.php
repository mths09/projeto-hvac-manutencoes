<?php

declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function responderVerificacaoAdmin(int $status, array $dados): never
{
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    responderVerificacaoAdmin(405, [
        'sucesso' => false,
        'mensagem' => 'Método não permitido.'
    ]);
}

try {
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    if (!session_start([
        'use_strict_mode' => true,
        'use_only_cookies' => true,
        'cookie_httponly' => true,
        'cookie_secure' => $https,
        'cookie_samesite' => 'Lax',
        'cookie_path' => '/',
        'read_and_close' => true
    ])) {
        throw new RuntimeException('Falha ao recuperar sessão.');
    }

    $idSessao = filter_var(
        $_SESSION['usuario_id'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($idSessao === false || $idSessao === null) {
        responderVerificacaoAdmin(401, [
            'sucesso' => false,
            'mensagem' => 'Entre como administrador para continuar.'
        ]);
    }

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    require_once __DIR__ . '/conexao.php';

    $stmt = $conexao->prepare(
        'SELECT nome, email, perfil, ativo
         FROM usuario
         WHERE id_usuario = ?
         LIMIT 1'
    );
    $stmt->bind_param('i', $idSessao);
    $stmt->execute();
    $usuario = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $conexao->close();

    $perfilAtual = strtolower(trim((string) ($usuario['perfil'] ?? '')));
    if (
        !$usuario
        || (int) $usuario['ativo'] !== 1
        || !in_array($perfilAtual, ['admin', 'administrador'], true)
    ) {
        responderVerificacaoAdmin(403, [
            'sucesso' => false,
            'mensagem' => 'Esta área é exclusiva do administrador.'
        ]);
    }

    responderVerificacaoAdmin(200, [
        'sucesso' => true,
        'usuario' => [
            'nome' => $usuario['nome'],
            'email' => $usuario['email']
        ]
    ]);
} catch (Throwable $erro) {
    error_log(
        'Falha ao verificar acesso administrativo: '
        . get_class($erro) . ' - código ' . $erro->getCode()
    );

    responderVerificacaoAdmin(500, [
        'sucesso' => false,
        'mensagem' => 'Não foi possível verificar o acesso administrativo.'
    ]);
}
