<?php

declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function responderNotificacoesLidas(int $status, array $dados): never
{
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    responderNotificacoesLidas(405, ['sucesso' => false, 'mensagem' => 'Método não permitido.']);
}

try {
    if (!session_start(['use_strict_mode' => true, 'use_only_cookies' => true, 'cookie_httponly' => true, 'cookie_samesite' => 'Lax', 'cookie_path' => '/'])) {
        throw new RuntimeException('Falha ao iniciar sessão.');
    }
    $idUsuario = filter_var($_SESSION['usuario_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $csrfSessao = $_SESSION['csrf_dashboard'] ?? '';
    $csrfEnviado = $_POST['csrf'] ?? '';
    if ($idUsuario === false || $idUsuario === null) {
        responderNotificacoesLidas(401, ['sucesso' => false, 'mensagem' => 'Entre na sua conta para continuar.']);
    }
    if (!is_string($csrfSessao) || $csrfSessao === '' || !is_string($csrfEnviado) || !hash_equals($csrfSessao, $csrfEnviado)) {
        responderNotificacoesLidas(403, ['sucesso' => false, 'mensagem' => 'Sessão expirada. Atualize o painel e tente novamente.']);
    }
    session_write_close();

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    require_once __DIR__ . '/conexao.php';
    $stmt = $conexao->prepare('SELECT perfil, ativo FROM usuario WHERE id_usuario = ? LIMIT 1');
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $usuario = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$usuario || (int) $usuario['ativo'] !== 1 || strtolower((string) $usuario['perfil']) !== 'cliente') {
        $conexao->close();
        responderNotificacoesLidas(403, ['sucesso' => false, 'mensagem' => 'Ação disponível somente para clientes.']);
    }

    $stmt = $conexao->prepare(
        "UPDATE notificacao
         SET lida_em = CURRENT_TIMESTAMP
         WHERE id_destinatario = ? AND lida_em IS NULL
           AND canal IN ('SITE', 'APP')
           AND status_envio IN ('ENVIADO', 'ENVIADA')"
    );
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $alteradas = $stmt->affected_rows;
    $stmt->close();
    $conexao->close();

    responderNotificacoesLidas(200, ['sucesso' => true, 'alteradas' => $alteradas, 'mensagem' => 'Notificações marcadas como lidas.']);
} catch (Throwable $erro) {
    if (isset($conexao) && $conexao instanceof mysqli) $conexao->close();
    error_log('Falha ao marcar notificações como lidas: ' . get_class($erro) . ', código ' . $erro->getCode());
    responderNotificacoesLidas(500, ['sucesso' => false, 'mensagem' => 'Não foi possível atualizar as notificações.']);
}
