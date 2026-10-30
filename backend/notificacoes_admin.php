<?php

declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function responderNotificacoesAdmin(int $status, array $dados): never
{
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

function consultarNotificacoesAdmin(mysqli $db, string $sql, array $parametros = []): mysqli_stmt
{
    $stmt = $db->prepare($sql);
    $stmt->execute($parametros);
    return $stmt;
}

try {
    $metodo = $_SERVER['REQUEST_METHOD'] ?? '';
    if (!in_array($metodo, ['GET', 'POST'], true)) {
        header('Allow: GET, POST');
        responderNotificacoesAdmin(405, ['sucesso' => false, 'mensagem' => 'Método não permitido.']);
    }
    if (!session_start([
        'use_strict_mode' => true,
        'use_only_cookies' => true,
        'cookie_httponly' => true,
        'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'cookie_samesite' => 'Lax',
        'cookie_path' => '/',
    ])) {
        throw new RuntimeException('Falha ao iniciar sessão.');
    }
    $idAdmin = filter_var($_SESSION['usuario_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $csrfSessao = $_SESSION['csrf_solicitacoes_admin'] ?? '';
    if ($idAdmin === false || $idAdmin === null) {
        responderNotificacoesAdmin(401, ['sucesso' => false, 'mensagem' => 'Entre como administrador para continuar.']);
    }
    $entrada = [];
    if ($metodo === 'POST') {
        $corpo = file_get_contents('php://input');
        $entrada = json_decode($corpo === false ? '' : $corpo, true);
        if (!is_array($entrada)) {
            responderNotificacoesAdmin(400, ['sucesso' => false, 'mensagem' => 'Requisição inválida.']);
        }
        if (!is_string($entrada['csrf'] ?? null) || !is_string($csrfSessao) || !hash_equals($csrfSessao, $entrada['csrf'])) {
            responderNotificacoesAdmin(403, ['sucesso' => false, 'mensagem' => 'Sessão expirada. Atualize o painel.']);
        }
    }
    session_write_close();

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    require_once __DIR__ . '/conexao.php';
    $admin = consultarNotificacoesAdmin(
        $conexao,
        'SELECT perfil, ativo FROM usuario WHERE id_usuario = ? LIMIT 1',
        [$idAdmin]
    )->get_result()->fetch_assoc();
    $perfil = strtolower(trim((string) ($admin['perfil'] ?? '')));
    if (!$admin || (int) $admin['ativo'] !== 1 || !in_array($perfil, ['admin', 'administrador'], true)) {
        $conexao->close();
        responderNotificacoesAdmin(403, ['sucesso' => false, 'mensagem' => 'Ação disponível somente para administradores.']);
    }

    if ($metodo === 'GET') {
        $notificacoes = consultarNotificacoesAdmin(
            $conexao,
            "SELECT n.id_notificacao AS id, n.titulo, n.mensagem, n.lida_em, n.criada_em,
                    s.protocolo
             FROM notificacao n
             LEFT JOIN solicitacao s ON s.id_solicitacao = n.id_solicitacao
             WHERE n.id_destinatario = ? AND n.canal = 'SITE'
               AND n.status_envio IN ('ENVIADO', 'ENVIADA')
             ORDER BY n.criada_em DESC, n.id_notificacao DESC LIMIT 50",
            [$idAdmin]
        )->get_result()->fetch_all(MYSQLI_ASSOC);
        $naoLidas = (int) consultarNotificacoesAdmin(
            $conexao,
            "SELECT COUNT(*) AS total FROM notificacao
             WHERE id_destinatario = ? AND canal = 'SITE'
               AND status_envio IN ('ENVIADO', 'ENVIADA') AND lida_em IS NULL",
            [$idAdmin]
        )->get_result()->fetch_assoc()['total'];
        foreach ($notificacoes as &$item) {
            $item = [
                'id' => (string) $item['id'],
                'titulo' => (string) $item['titulo'],
                'mensagem' => (string) $item['mensagem'],
                'lida' => $item['lida_em'] !== null,
                'criada_em' => (string) $item['criada_em'],
                'protocolo' => (string) ($item['protocolo'] ?? ''),
            ];
        }
        unset($item);
        $conexao->close();
        responderNotificacoesAdmin(200, ['sucesso' => true, 'csrf' => $csrfSessao, 'nao_lidas' => $naoLidas, 'notificacoes' => $notificacoes]);
    }

    $acao = $entrada['acao'] ?? '';
    if ($acao === 'ler') {
        $idNotificacao = filter_var($entrada['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($idNotificacao === false || $idNotificacao === null) {
            $conexao->close();
            responderNotificacoesAdmin(422, ['sucesso' => false, 'mensagem' => 'Notificação inválida.']);
        }
        consultarNotificacoesAdmin(
            $conexao,
            "UPDATE notificacao SET lida_em = COALESCE(lida_em, NOW())
             WHERE id_notificacao = ? AND id_destinatario = ? AND canal = 'SITE'",
            [$idNotificacao, $idAdmin]
        );
    } elseif ($acao === 'ler_todas') {
        consultarNotificacoesAdmin(
            $conexao,
            "UPDATE notificacao SET lida_em = NOW()
             WHERE id_destinatario = ? AND canal = 'SITE'
               AND status_envio IN ('ENVIADO', 'ENVIADA') AND lida_em IS NULL",
            [$idAdmin]
        );
    } elseif ($acao === 'limpar_lidas') {
        consultarNotificacoesAdmin(
            $conexao,
            "DELETE FROM notificacao
             WHERE id_destinatario = ? AND canal = 'SITE' AND lida_em IS NOT NULL
               AND status_envio IN ('ENVIADO', 'ENVIADA')",
            [$idAdmin]
        );
    } else {
        $conexao->close();
        responderNotificacoesAdmin(422, ['sucesso' => false, 'mensagem' => 'Ação de notificação inválida.']);
    }
    $conexao->close();
    responderNotificacoesAdmin(200, ['sucesso' => true, 'mensagem' => 'Notificações atualizadas.']);
} catch (Throwable $erro) {
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    if (isset($conexao) && $conexao instanceof mysqli) $conexao->close();
    error_log('Falha nas notificações administrativas: ' . get_class($erro) . ', código ' . $erro->getCode());
    responderNotificacoesAdmin(500, ['sucesso' => false, 'mensagem' => 'Não foi possível atualizar as notificações.']);
}
