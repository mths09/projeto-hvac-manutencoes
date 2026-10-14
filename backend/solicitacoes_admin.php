<?php

declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function responderSolicitacoesAdmin(int $status, array $dados): never
{
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

function consultarSolicitacoesAdmin(mysqli $db, string $sql, array $parametros = []): mysqli_stmt
{
    $stmt = $db->prepare($sql);
    $stmt->execute($parametros);
    return $stmt;
}

function transicoesStatusAdmin(): array
{
    return [
        'SOLICITADO' => ['AGENDADO', 'CANCELADO'],
        'AGENDADO' => ['EM_ANDAMENTO', 'CANCELADO'],
        'EM_ANDAMENTO' => ['CONCLUIDO', 'CANCELADO'],
        'CONCLUIDO' => [],
        'CANCELADO' => []
    ];
}

function validarProtocoloAdmin(mixed $protocolo): string
{
    if (!is_string($protocolo) || !preg_match('/^SRV-[A-Za-z0-9-]{1,26}$/D', $protocolo)) {
        throw new DomainException('Identificador do pedido inválido.');
    }
    return $protocolo;
}

try {
    $metodo = $_SERVER['REQUEST_METHOD'] ?? '';
    if (!in_array($metodo, ['GET', 'POST'], true)) {
        header('Allow: GET, POST');
        responderSolicitacoesAdmin(405, ['sucesso' => false, 'mensagem' => 'Método não permitido.']);
    }
    if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 32768) {
        responderSolicitacoesAdmin(413, ['sucesso' => false, 'mensagem' => 'Os dados enviados ultrapassam o limite permitido.']);
    }

    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    if (!session_start([
        'use_strict_mode' => true,
        'use_only_cookies' => true,
        'cookie_httponly' => true,
        'cookie_secure' => $https,
        'cookie_samesite' => 'Lax',
        'cookie_path' => '/'
    ])) {
        throw new RuntimeException('Falha ao iniciar sessão.');
    }

    $idAdministrador = filter_var(
        $_SESSION['usuario_id'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );
    if ($idAdministrador === false || $idAdministrador === null) {
        responderSolicitacoesAdmin(401, ['sucesso' => false, 'mensagem' => 'Entre como administrador para continuar.']);
    }

    $_SESSION['csrf_solicitacoes_admin'] ??= bin2hex(random_bytes(32));
    $csrfSessao = $_SESSION['csrf_solicitacoes_admin'];

    $entrada = [];
    if ($metodo === 'POST') {
        $corpo = file_get_contents('php://input');
        $entrada = json_decode($corpo === false ? '' : $corpo, true);
        if (!is_array($entrada)) {
            responderSolicitacoesAdmin(400, ['sucesso' => false, 'mensagem' => 'Dados da solicitação inválidos.']);
        }
        if (!is_string($entrada['csrf'] ?? null) || !hash_equals($csrfSessao, $entrada['csrf'])) {
            responderSolicitacoesAdmin(403, ['sucesso' => false, 'mensagem' => 'Sessão expirada. Atualize o painel e tente novamente.']);
        }
    }

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    require_once __DIR__ . '/conexao.php';
    date_default_timezone_set('America/Sao_Paulo');
    consultarSolicitacoesAdmin($conexao, 'SET time_zone = ?', [(new DateTimeImmutable())->format('P')]);

    $administrador = consultarSolicitacoesAdmin(
        $conexao,
        'SELECT perfil, ativo FROM usuario WHERE id_usuario = ? LIMIT 1',
        [$idAdministrador]
    )->get_result()->fetch_assoc();
    $perfil = strtolower(trim((string) ($administrador['perfil'] ?? '')));
    if (!$administrador || (int) $administrador['ativo'] !== 1 || !in_array($perfil, ['admin', 'administrador'], true)) {
        $conexao->close();
        responderSolicitacoesAdmin(403, ['sucesso' => false, 'mensagem' => 'Esta área é exclusiva do administrador.']);
    }

    if ($metodo === 'GET') {
        session_write_close();
        $stmtPedidos = consultarSolicitacoesAdmin(
            $conexao,
            "SELECT s.id_solicitacao, s.protocolo, s.nome_contato, s.telefone_contato,
                    s.descricao_problema, s.urgente, s.criada_em,
                    ss.codigo AS status_codigo, ss.nome AS status_nome,
                    ts.nome AS servico,
                    COALESCE(NULLIF(s.telefone_contato, ''), u.telefone, '') AS telefone,
                    ea.endereco_informado AS endereco,
                    d.inicio_previsto,
                    t.id_tecnico, t.nome AS tecnico,
                    (SELECT COUNT(*) FROM documento doc WHERE doc.id_solicitacao = s.id_solicitacao) AS total_documentos
             FROM solicitacao s
             INNER JOIN status_solicitacao ss ON ss.id_status = s.id_status_atual
             INNER JOIN tipo_servico ts ON ts.id_tipo_servico = s.id_tipo_servico
             LEFT JOIN usuario u ON u.id_usuario = s.id_cliente
             LEFT JOIN endereco_atendimento ea ON ea.id_solicitacao = s.id_solicitacao
             LEFT JOIN agendamento a ON a.id_agendamento = (
                SELECT a2.id_agendamento
                FROM agendamento a2
                WHERE a2.id_solicitacao = s.id_solicitacao
                  AND NOT EXISTS (
                    SELECT 1 FROM agendamento a3 WHERE a3.id_anterior = a2.id_agendamento
                  )
                ORDER BY a2.id_agendamento DESC
                LIMIT 1
             )
             LEFT JOIN disponibilidade d ON d.id_disponibilidade = a.id_disponibilidade
             LEFT JOIN atribuicao_tecnica at ON at.id_atribuicao = (
                SELECT at2.id_atribuicao
                FROM atribuicao_tecnica at2
                WHERE at2.id_solicitacao = s.id_solicitacao AND at2.fim_em IS NULL
                ORDER BY at2.inicio_em DESC, at2.id_atribuicao DESC
                LIMIT 1
             )
             LEFT JOIN tecnico t ON t.id_tecnico = at.id_tecnico AND t.ativo = 1
             WHERE s.arquivada_em IS NULL
             ORDER BY s.criada_em DESC, s.id_solicitacao DESC"
        );
        $resultado = $stmtPedidos->get_result();
        $pedidos = [];
        $transicoes = transicoesStatusAdmin();
        while ($linha = $resultado->fetch_assoc()) {
            $codigoStatus = (string) $linha['status_codigo'];
            $pedidos[] = [
                'id' => (string) $linha['id_solicitacao'],
                'protocolo' => (string) $linha['protocolo'],
                'cliente' => (string) $linha['nome_contato'],
                'telefone' => (string) $linha['telefone'],
                'servico' => (string) $linha['servico'],
                'descricao' => (string) ($linha['descricao_problema'] ?? ''),
                'endereco' => (string) ($linha['endereco'] ?? ''),
                'inicio_previsto' => (string) ($linha['inicio_previsto'] ?? ''),
                'criada_em' => (string) $linha['criada_em'],
                'status_codigo' => $codigoStatus,
                'status_nome' => (string) $linha['status_nome'],
                'status_permitidos' => $transicoes[$codigoStatus] ?? [],
                'urgente' => (bool) $linha['urgente'],
                'tecnico_id' => $linha['id_tecnico'] === null ? '' : (string) $linha['id_tecnico'],
                'tecnico' => (string) ($linha['tecnico'] ?? ''),
                'total_documentos' => (int) $linha['total_documentos']
            ];
        }
        $tecnicos = consultarSolicitacoesAdmin(
            $conexao,
            'SELECT id_tecnico AS id, nome FROM tecnico WHERE ativo = 1 ORDER BY nome, id_tecnico'
        )->get_result()->fetch_all(MYSQLI_ASSOC);
        foreach ($tecnicos as &$tecnico) {
            $tecnico['id'] = (string) $tecnico['id'];
        }
        unset($tecnico);
        $conexao->close();
        responderSolicitacoesAdmin(200, [
            'sucesso' => true,
            'csrf' => $csrfSessao,
            'pedidos' => $pedidos,
            'tecnicos' => $tecnicos
        ]);
    }

    $protocolo = validarProtocoloAdmin($entrada['protocolo'] ?? null);
    $acao = $entrada['acao'] ?? null;
    if (!is_string($acao) || !in_array($acao, ['status', 'tecnico'], true)) {
        responderSolicitacoesAdmin(422, ['sucesso' => false, 'mensagem' => 'Ação inválida.']);
    }

    $conexao->begin_transaction();
    try {
        $pedido = consultarSolicitacoesAdmin(
            $conexao,
            'SELECT s.id_solicitacao, s.id_cliente, s.id_status_atual, ss.codigo AS status_codigo, ss.nome AS status_nome
             FROM solicitacao s
             INNER JOIN status_solicitacao ss ON ss.id_status = s.id_status_atual
             WHERE s.protocolo = ? AND s.arquivada_em IS NULL
             LIMIT 1 FOR UPDATE',
            [$protocolo]
        )->get_result()->fetch_assoc();
        if (!$pedido) {
            throw new OutOfBoundsException('Pedido não encontrado.');
        }

        if ($acao === 'status') {
            $novoCodigo = $entrada['status'] ?? null;
            if (!is_string($novoCodigo) || !in_array($novoCodigo, transicoesStatusAdmin()[$pedido['status_codigo']] ?? [], true)) {
                throw new DomainException('Essa mudança de status não é permitida. Atualize o painel e confira o status atual.');
            }
            $statusDestino = consultarSolicitacoesAdmin(
                $conexao,
                'SELECT id_status, codigo, nome FROM status_solicitacao WHERE codigo = ? AND ativo = 1 LIMIT 1 FOR UPDATE',
                [$novoCodigo]
            )->get_result()->fetch_assoc();
            if (!$statusDestino) {
                throw new DomainException('O status selecionado não está disponível.');
            }

            $agendamento = consultarSolicitacoesAdmin(
                $conexao,
                'SELECT a.id_agendamento, a.status, d.inicio_previsto, (d.inicio_previsto > NOW()) AS horario_futuro
                 FROM agendamento a
                 INNER JOIN disponibilidade d ON d.id_disponibilidade = a.id_disponibilidade
                 WHERE a.id_solicitacao = ?
                   AND NOT EXISTS (SELECT 1 FROM agendamento a2 WHERE a2.id_anterior = a.id_agendamento)
                 ORDER BY a.id_agendamento DESC LIMIT 1 FOR UPDATE',
                [$pedido['id_solicitacao']]
            )->get_result()->fetch_assoc();

            if ($novoCodigo === 'AGENDADO') {
                if (!$agendamento || $agendamento['status'] !== 'PENDENTE') {
                    throw new DomainException('Este pedido não tem um horário pendente para confirmar.');
                }
                if ((int) $agendamento['horario_futuro'] !== 1) {
                    throw new DomainException('O horário escolhido já passou. Entre em contato com o cliente para reagendar.');
                }
                consultarSolicitacoesAdmin(
                    $conexao,
                    "UPDATE agendamento SET status = 'CONFIRMADO' WHERE id_agendamento = ? AND status = 'PENDENTE'",
                    [$agendamento['id_agendamento']]
                );
            } elseif ($novoCodigo === 'CANCELADO' && $agendamento && !in_array($agendamento['status'], ['CANCELADO', 'REAGENDADO'], true)) {
                consultarSolicitacoesAdmin(
                    $conexao,
                    "UPDATE agendamento SET status = 'CANCELADO', motivo_cancelamento = 'Cancelado pela equipe no painel administrativo' WHERE id_agendamento = ?",
                    [$agendamento['id_agendamento']]
                );
            }

            consultarSolicitacoesAdmin(
                $conexao,
                'UPDATE solicitacao SET id_status_atual = ?, atualizada_em = NOW() WHERE id_solicitacao = ?',
                [$statusDestino['id_status'], $pedido['id_solicitacao']]
            );
            consultarSolicitacoesAdmin(
                $conexao,
                'INSERT INTO historico_status (id_solicitacao, id_status_anterior, id_status_novo, id_responsavel, observacao)
                 VALUES (?, ?, ?, ?, ?)',
                [$pedido['id_solicitacao'], $pedido['id_status_atual'], $statusDestino['id_status'], $idAdministrador,
                 'Status alterado pelo painel administrativo.']
            );
            if ($pedido['id_cliente'] !== null) {
                consultarSolicitacoesAdmin(
                    $conexao,
                    "INSERT INTO notificacao (id_destinatario, id_solicitacao, id_agendamento, tipo_evento,
                     titulo, mensagem, canal, status_envio, enviada_em)
                     VALUES (?, ?, ?, 'STATUS_ALTERADO', ?, ?, 'SITE', 'ENVIADO', NOW())",
                    [$pedido['id_cliente'], $pedido['id_solicitacao'], $agendamento['id_agendamento'] ?? null,
                     'Status do pedido atualizado', 'Seu pedido ' . $protocolo . ' agora está ' . $statusDestino['nome'] . '.']
                );
            }
            $mensagem = 'Status atualizado para ' . $statusDestino['nome'] . '.';
        } else {
            $idTecnico = $entrada['tecnico_id'] ?? null;
            if (!is_string($idTecnico) || ($idTecnico !== '' && !ctype_digit($idTecnico))) {
                throw new DomainException('Selecione um técnico válido.');
            }
            if (in_array($pedido['status_codigo'], ['CONCLUIDO', 'CANCELADO'], true)) {
                throw new DomainException('Não é possível alterar o técnico de um pedido encerrado.');
            }
            $idTecnicoNovo = $idTecnico === '' ? null : (int) $idTecnico;
            $atribuicao = consultarSolicitacoesAdmin(
                $conexao,
                'SELECT id_atribuicao, id_tecnico FROM atribuicao_tecnica
                 WHERE id_solicitacao = ? AND fim_em IS NULL
                 ORDER BY inicio_em DESC, id_atribuicao DESC LIMIT 1 FOR UPDATE',
                [$pedido['id_solicitacao']]
            )->get_result()->fetch_assoc();
            $idTecnicoAtual = $atribuicao ? (int) $atribuicao['id_tecnico'] : null;

            if ($idTecnicoNovo !== $idTecnicoAtual) {
                $nomeTecnico = '';
                if ($idTecnicoNovo !== null) {
                    $tecnico = consultarSolicitacoesAdmin(
                        $conexao,
                        'SELECT nome FROM tecnico WHERE id_tecnico = ? AND ativo = 1 LIMIT 1 FOR UPDATE',
                        [$idTecnicoNovo]
                    )->get_result()->fetch_assoc();
                    if (!$tecnico) {
                        throw new DomainException('O técnico selecionado não está disponível.');
                    }
                    $nomeTecnico = (string) $tecnico['nome'];
                }
                if ($atribuicao) {
                    consultarSolicitacoesAdmin(
                        $conexao,
                        "UPDATE atribuicao_tecnica SET fim_em = NOW(), motivo = 'Atribuição alterada pelo painel administrativo'
                         WHERE id_atribuicao = ? AND fim_em IS NULL",
                        [$atribuicao['id_atribuicao']]
                    );
                }
                $idNovaAtribuicao = null;
                if ($idTecnicoNovo !== null) {
                    consultarSolicitacoesAdmin(
                        $conexao,
                        'INSERT INTO atribuicao_tecnica (id_solicitacao, id_tecnico, id_responsavel, inicio_em)
                         VALUES (?, ?, ?, NOW())',
                        [$pedido['id_solicitacao'], $idTecnicoNovo, $idAdministrador]
                    );
                    $idNovaAtribuicao = $conexao->insert_id;
                }
                consultarSolicitacoesAdmin(
                    $conexao,
                    'UPDATE agendamento SET id_atribuicao = ?
                     WHERE id_agendamento = (
                       SELECT atual.id_agendamento FROM (
                         SELECT a.id_agendamento FROM agendamento a
                         WHERE a.id_solicitacao = ?
                           AND NOT EXISTS (SELECT 1 FROM agendamento seguinte WHERE seguinte.id_anterior = a.id_agendamento)
                         ORDER BY a.id_agendamento DESC LIMIT 1
                       ) atual
                     )',
                    [$idNovaAtribuicao, $pedido['id_solicitacao']]
                );
                if ($pedido['id_cliente'] !== null) {
                    $mensagemTecnico = $idTecnicoNovo === null
                        ? 'A equipe removeu a atribuição de técnico do pedido ' . $protocolo . '.'
                        : 'O técnico ' . $nomeTecnico . ' foi atribuído ao pedido ' . $protocolo . '.';
                    consultarSolicitacoesAdmin(
                        $conexao,
                        "INSERT INTO notificacao (id_destinatario, id_solicitacao, tipo_evento,
                         titulo, mensagem, canal, status_envio, enviada_em)
                         VALUES (?, ?, 'TECNICO_ATRIBUIDO', 'Equipe do pedido atualizada', ?, 'SITE', 'ENVIADO', NOW())",
                        [$pedido['id_cliente'], $pedido['id_solicitacao'], $mensagemTecnico]
                    );
                }
            }
            $mensagem = $idTecnicoNovo === null ? 'Técnico removido do pedido.' : 'Técnico atribuído com sucesso.';
        }

        $conexao->commit();
        $conexao->close();
        session_write_close();
        responderSolicitacoesAdmin(200, ['sucesso' => true, 'mensagem' => $mensagem]);
    } catch (DomainException $erro) {
        $conexao->rollback();
        $conexao->close();
        session_write_close();
        responderSolicitacoesAdmin(422, ['sucesso' => false, 'mensagem' => $erro->getMessage()]);
    } catch (OutOfBoundsException $erro) {
        $conexao->rollback();
        $conexao->close();
        session_write_close();
        responderSolicitacoesAdmin(404, ['sucesso' => false, 'mensagem' => $erro->getMessage()]);
    } catch (Throwable $erro) {
        $conexao->rollback();
        throw $erro;
    }
} catch (DomainException $erro) {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    responderSolicitacoesAdmin(422, ['sucesso' => false, 'mensagem' => $erro->getMessage()]);
} catch (Throwable $erro) {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    error_log('Falha ao processar solicitações administrativas: ' . get_class($erro) . ', código ' . $erro->getCode() . ' - ' . $erro->getMessage());
    responderSolicitacoesAdmin(500, ['sucesso' => false, 'mensagem' => 'Não foi possível atualizar o pedido. Tente novamente.']);
}
