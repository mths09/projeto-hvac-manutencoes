<?php

declare(strict_types=1);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function responderSolicitacoesTecnico(int $status, array $dados): never
{
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

function consultarSolicitacoesTecnico(mysqli $db, string $sql, array $params = []): mysqli_stmt
{
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

try {
    $metodo = $_SERVER['REQUEST_METHOD'] ?? '';
    if (!in_array($metodo, ['GET', 'POST'], true)) {
        header('Allow: GET, POST');
        responderSolicitacoesTecnico(405, ['sucesso' => false, 'mensagem' => 'Método não permitido.']);
    }
    if (!session_start([
        'use_strict_mode' => true, 'use_only_cookies' => true, 'cookie_httponly' => true,
        'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'cookie_samesite' => 'Lax', 'cookie_path' => '/',
    ])) throw new RuntimeException('Falha ao iniciar sessão.');
    $idUsuario = filter_var($_SESSION['usuario_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($idUsuario === false || $idUsuario === null) responderSolicitacoesTecnico(401, ['sucesso' => false, 'mensagem' => 'Entre como técnico.']);
    $_SESSION['csrf_solicitacoes_tecnico'] ??= bin2hex(random_bytes(32));
    $csrf = (string) $_SESSION['csrf_solicitacoes_tecnico'];
    $entrada = [];
    if ($metodo === 'POST') {
        $corpo = file_get_contents('php://input');
        $entrada = json_decode($corpo === false ? '' : $corpo, true);
        if (!is_array($entrada)) responderSolicitacoesTecnico(400, ['sucesso' => false, 'mensagem' => 'Dados inválidos.']);
        if (!is_string($entrada['csrf'] ?? null) || !hash_equals($csrf, $entrada['csrf'])) responderSolicitacoesTecnico(403, ['sucesso' => false, 'mensagem' => 'Sessão expirada. Atualize o painel.']);
    }
    session_write_close();
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    require_once __DIR__ . '/conexao.php';
    $tecnico = consultarSolicitacoesTecnico(
        $conexao,
        "SELECT u.nome, t.id_tecnico FROM usuario u
         JOIN tecnico t ON t.id_usuario = u.id_usuario
         WHERE u.id_usuario = ? AND u.perfil = 'tecnico' AND u.ativo = 1 AND t.ativo = 1 LIMIT 1",
        [$idUsuario]
    )->get_result()->fetch_assoc();
    if (!$tecnico) {
        $conexao->close();
        responderSolicitacoesTecnico(403, ['sucesso' => false, 'mensagem' => 'Conta de técnico inativa.']);
    }
    $idTecnico = (int) $tecnico['id_tecnico'];

    if ($metodo === 'GET') {
        $linhas = consultarSolicitacoesTecnico(
            $conexao,
            "SELECT s.protocolo, s.nome_contato, s.telefone_contato, s.descricao_problema, s.urgente,
                    ss.codigo AS status_codigo, ss.nome AS status_nome, ts.nome AS servico,
                    ea.endereco_informado AS endereco, d.inicio_previsto,
                    at.id_tecnico AS tecnico_atribuido,
                    COALESCE(NULLIF(s.telefone_contato, ''), u.telefone, '') AS telefone
             FROM solicitacao s
             JOIN status_solicitacao ss ON ss.id_status = s.id_status_atual
             JOIN tipo_servico ts ON ts.id_tipo_servico = s.id_tipo_servico
             LEFT JOIN usuario u ON u.id_usuario = s.id_cliente
             LEFT JOIN endereco_atendimento ea ON ea.id_solicitacao = s.id_solicitacao
             LEFT JOIN agendamento a ON a.id_agendamento = (
                SELECT a2.id_agendamento FROM agendamento a2
                WHERE a2.id_solicitacao = s.id_solicitacao
                  AND NOT EXISTS (SELECT 1 FROM agendamento a3 WHERE a3.id_anterior = a2.id_agendamento)
                ORDER BY a2.id_agendamento DESC LIMIT 1
             )
             LEFT JOIN disponibilidade d ON d.id_disponibilidade = a.id_disponibilidade
             LEFT JOIN atribuicao_tecnica at ON at.id_atribuicao = (
                SELECT at2.id_atribuicao FROM atribuicao_tecnica at2
                WHERE at2.id_solicitacao = s.id_solicitacao AND at2.fim_em IS NULL
                ORDER BY at2.inicio_em DESC, at2.id_atribuicao DESC LIMIT 1
             )
             WHERE s.arquivada_em IS NULL
             ORDER BY (at.id_tecnico = ?) DESC, d.inicio_previsto IS NULL, d.inicio_previsto DESC, s.criada_em DESC",
            [$idTecnico]
        )->get_result()->fetch_all(MYSQLI_ASSOC);
        $pedidos = [];
        foreach ($linhas as $linha) {
            $codigo = (string) $linha['status_codigo'];
            $atribuido = $linha['tecnico_atribuido'] !== null && (int) $linha['tecnico_atribuido'] === $idTecnico;
            $permitidos = $atribuido ? match ($codigo) {
                'AGENDADO' => ['EM_ANDAMENTO'],
                'EM_ANDAMENTO' => ['CONCLUIDO'],
                default => [],
            } : [];
            $pedidos[] = [
                'protocolo' => (string) $linha['protocolo'],
                'cliente' => (string) $linha['nome_contato'],
                'telefone' => (string) $linha['telefone'],
                'servico' => (string) $linha['servico'],
                'descricao' => (string) ($linha['descricao_problema'] ?? ''),
                'endereco' => (string) ($linha['endereco'] ?? ''),
                'inicio_previsto' => (string) ($linha['inicio_previsto'] ?? ''),
                'status_codigo' => $codigo,
                'status_nome' => (string) $linha['status_nome'],
                'urgente' => (bool) $linha['urgente'],
                'atribuido_a_mim' => $atribuido,
                'status_permitidos' => $permitidos,
            ];
        }
        $conexao->close();
        responderSolicitacoesTecnico(200, ['sucesso' => true, 'csrf' => $csrf, 'tecnico' => (string) $tecnico['nome'], 'pedidos' => $pedidos]);
    }

    $acao = $entrada['acao'] ?? '';
    if ($acao !== 'status') {
        $conexao->close();
        responderSolicitacoesTecnico(422, ['sucesso' => false, 'mensagem' => 'Ação inválida.']);
    }
    $protocolo = $entrada['protocolo'] ?? null;
    $novoStatus = $entrada['status'] ?? null;
    if (!is_string($protocolo) || !preg_match('/^SRV-[A-Za-z0-9-]{1,26}$/D', $protocolo)
        || !is_string($novoStatus) || !in_array($novoStatus, ['EM_ANDAMENTO', 'CONCLUIDO'], true)) {
        $conexao->close();
        responderSolicitacoesTecnico(422, ['sucesso' => false, 'mensagem' => 'Pedido ou status inválido.']);
    }
    $conexao->begin_transaction();
    try {
        $pedido = consultarSolicitacoesTecnico(
            $conexao,
            "SELECT s.id_solicitacao, s.id_cliente, s.id_status_atual, ss.codigo AS status_codigo,
                    a.id_agendamento, a.id_disponibilidade
             FROM solicitacao s JOIN status_solicitacao ss ON ss.id_status = s.id_status_atual
             LEFT JOIN agendamento a ON a.id_agendamento = (
                SELECT a2.id_agendamento FROM agendamento a2 WHERE a2.id_solicitacao = s.id_solicitacao
                  AND NOT EXISTS (SELECT 1 FROM agendamento a3 WHERE a3.id_anterior = a2.id_agendamento)
                ORDER BY a2.id_agendamento DESC LIMIT 1
             )
             WHERE s.protocolo = ? AND s.arquivada_em IS NULL LIMIT 1 FOR UPDATE",
            [$protocolo]
        )->get_result()->fetch_assoc();
        if (!$pedido) throw new OutOfBoundsException('Pedido não encontrado.');
        $atribuicao = consultarSolicitacoesTecnico(
            $conexao,
            'SELECT id_atribuicao FROM atribuicao_tecnica WHERE id_solicitacao = ? AND id_tecnico = ? AND fim_em IS NULL ORDER BY inicio_em DESC LIMIT 1 FOR UPDATE',
            [$pedido['id_solicitacao'], $idTecnico]
        )->get_result()->fetch_assoc();
        if (!$atribuicao) throw new DomainException('Somente o técnico atualmente atribuído pode alterar este pedido.');
        $transicoes = ['AGENDADO' => 'EM_ANDAMENTO', 'EM_ANDAMENTO' => 'CONCLUIDO'];
        if (($transicoes[$pedido['status_codigo']] ?? null) !== $novoStatus) throw new DomainException('Esta mudança de status não é permitida.');
        $statusDestino = consultarSolicitacoesTecnico($conexao, 'SELECT id_status, nome FROM status_solicitacao WHERE codigo = ? AND ativo = 1 LIMIT 1', [$novoStatus])->get_result()->fetch_assoc();
        if (!$statusDestino) throw new RuntimeException('Status não configurado.');
        consultarSolicitacoesTecnico($conexao, 'UPDATE solicitacao SET id_status_atual = ?, atualizada_em = NOW() WHERE id_solicitacao = ?', [$statusDestino['id_status'], $pedido['id_solicitacao']]);
        consultarSolicitacoesTecnico($conexao,
            'INSERT INTO historico_status (id_solicitacao, id_status_anterior, id_status_novo, id_responsavel, observacao) VALUES (?, ?, ?, ?, ?)',
            [$pedido['id_solicitacao'], $pedido['id_status_atual'], $statusDestino['id_status'], $idUsuario, 'Status atualizado pelo técnico atribuído.']
        );
        if ($pedido['id_agendamento'] !== null) {
            if ($novoStatus === 'EM_ANDAMENTO') {
                consultarSolicitacoesTecnico($conexao, "UPDATE agendamento SET status = 'EM_ANDAMENTO', inicio_real = COALESCE(inicio_real, NOW()) WHERE id_agendamento = ?", [$pedido['id_agendamento']]);
            } else {
                consultarSolicitacoesTecnico($conexao, "UPDATE agendamento SET status = 'CONCLUIDO', fim_real = NOW() WHERE id_agendamento = ?", [$pedido['id_agendamento']]);
            }
        }
        $mensagem = 'Seu pedido ' . $protocolo . ' agora está ' . $statusDestino['nome'] . '.';
        if ($pedido['id_cliente'] !== null) {
            consultarSolicitacoesTecnico(
                $conexao,
                "INSERT INTO notificacao (id_destinatario, id_solicitacao, id_agendamento, tipo_evento, titulo, mensagem, canal, status_envio, enviada_em)
                 VALUES (?, ?, ?, 'STATUS_ALTERADO', 'Atualização do seu pedido', ?, 'SITE', 'ENVIADO', NOW())",
                [$pedido['id_cliente'], $pedido['id_solicitacao'], $pedido['id_agendamento'], $mensagem]
            );
        }
        $conexao->commit();
        $conexao->close();
        responderSolicitacoesTecnico(200, ['sucesso' => true, 'mensagem' => 'Status atualizado para ' . $statusDestino['nome'] . '.']);
    } catch (Throwable $erro) {
        $conexao->rollback();
        throw $erro;
    }
} catch (DomainException | OutOfBoundsException $erro) {
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    if (isset($conexao) && $conexao instanceof mysqli) $conexao->close();
    responderSolicitacoesTecnico(422, ['sucesso' => false, 'mensagem' => $erro->getMessage()]);
} catch (Throwable $erro) {
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    if (isset($conexao) && $conexao instanceof mysqli) $conexao->close();
    error_log('Falha ao processar pedidos do técnico: ' . get_class($erro) . ', código ' . $erro->getCode());
    responderSolicitacoesTecnico(500, ['sucesso' => false, 'mensagem' => 'Não foi possível atualizar o pedido.']);
}
