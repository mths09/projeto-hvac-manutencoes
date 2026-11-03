<?php

declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

final class ErroAgendamentoCliente extends RuntimeException
{
    public function __construct(string $mensagem, public int $http)
    {
        parent::__construct($mensagem);
    }
}

function responderAgendamentoCliente(int $http, array $dados): never
{
    http_response_code($http);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

function consultarAgendamentoCliente(mysqli $db, string $sql, array $parametros = []): mysqli_stmt
{
    $stmt = $db->prepare($sql);
    $stmt->execute($parametros);
    return $stmt;
}

function validarProtocoloAgendamentoCliente(mixed $protocolo): string
{
    if (!is_string($protocolo) || !preg_match('/^SRV-[a-f0-9]{24}$/D', $protocolo)) {
        throw new ErroAgendamentoCliente('Identificador do pedido inválido.', 422);
    }
    return $protocolo;
}

function obterPedidoAgendamentoCliente(mysqli $db, string $protocolo, int $idCliente, bool $bloquear = false): ?array
{
    $sql = "SELECT s.id_solicitacao, s.id_status_atual, s.nome_contato,
                   st.codigo AS status_codigo, st.nome AS status_nome
            FROM solicitacao s
            JOIN status_solicitacao st ON st.id_status = s.id_status_atual
            WHERE s.protocolo = ? AND s.id_cliente = ? AND s.arquivada_em IS NULL
            LIMIT 1" . ($bloquear ? ' FOR UPDATE' : '');
    return consultarAgendamentoCliente($db, $sql, [$protocolo, $idCliente])
        ->get_result()->fetch_assoc() ?: null;
}

function obterAgendamentoAtualCliente(mysqli $db, int $idSolicitacao, bool $bloquear = false): ?array
{
    $sql = "SELECT a.id_agendamento, a.id_disponibilidade, a.status,
                   d.inicio_previsto, (d.inicio_previsto > NOW()) AS horario_futuro
            FROM agendamento a
            JOIN disponibilidade d ON d.id_disponibilidade = a.id_disponibilidade
            WHERE a.id_solicitacao = ?
              AND NOT EXISTS (
                SELECT 1 FROM agendamento seguinte WHERE seguinte.id_anterior = a.id_agendamento
              )
            ORDER BY a.id_agendamento DESC
            LIMIT 1" . ($bloquear ? ' FOR UPDATE' : '');
    return consultarAgendamentoCliente($db, $sql, [$idSolicitacao])
        ->get_result()->fetch_assoc() ?: null;
}

function pedidoClientePodeAlterar(array $pedido, ?array $agendamento): bool
{
    if (!in_array($pedido['status_codigo'], ['SOLICITADO', 'AGENDADO'], true)) {
        return false;
    }
    if ($agendamento === null) {
        return true;
    }
    return in_array($agendamento['status'], ['PENDENTE', 'CONFIRMADO'], true)
        && (int) $agendamento['horario_futuro'] === 1;
}

function inserirNotificacaoAgendamentoCliente(
    mysqli $db,
    int $idCliente,
    int $idSolicitacao,
    ?int $idAgendamento,
    string $tipoEvento,
    string $tituloCliente,
    string $mensagemCliente,
    string $tituloEquipe,
    string $mensagemEquipe
): void {
    consultarAgendamentoCliente(
        $db,
        "INSERT INTO notificacao
            (id_destinatario, id_solicitacao, id_agendamento, tipo_evento, titulo, mensagem, canal, status_envio, enviada_em)
         VALUES (?, ?, ?, ?, ?, ?, 'SITE', 'ENVIADO', NOW())",
        [$idCliente, $idSolicitacao, $idAgendamento, $tipoEvento, $tituloCliente, $mensagemCliente]
    );

    $administradores = consultarAgendamentoCliente(
        $db,
        "SELECT id_usuario FROM usuario
         WHERE ativo = 1 AND LOWER(perfil) IN ('admin', 'administrador')
         ORDER BY id_usuario"
    )->get_result()->fetch_all(MYSQLI_ASSOC);

    foreach ($administradores as $administrador) {
        consultarAgendamentoCliente(
            $db,
            "INSERT INTO notificacao
                (id_destinatario, id_solicitacao, id_agendamento, tipo_evento, titulo, mensagem, canal, status_envio, enviada_em)
             VALUES (?, ?, ?, ?, ?, ?, 'SITE', 'ENVIADO', NOW())",
            [(int) $administrador['id_usuario'], $idSolicitacao, $idAgendamento, $tipoEvento, $tituloEquipe, $mensagemEquipe]
        );
    }
}

function registrarHistoricoAgendamentoCliente(
    mysqli $db,
    int $idSolicitacao,
    int $statusAnterior,
    int $statusNovo,
    int $idCliente,
    string $observacao
): void {
    consultarAgendamentoCliente(
        $db,
        'INSERT INTO historico_status
            (id_solicitacao, id_status_anterior, id_status_novo, id_responsavel, observacao)
         VALUES (?, ?, ?, ?, ?)',
        [$idSolicitacao, $statusAnterior, $statusNovo, $idCliente, $observacao]
    );
}

$metodo = $_SERVER['REQUEST_METHOD'] ?? '';
if (!in_array($metodo, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    responderAgendamentoCliente(405, ['sucesso' => false, 'mensagem' => 'Método não permitido.']);
}
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 8192) {
    responderAgendamentoCliente(413, ['sucesso' => false, 'mensagem' => 'Os dados enviados ultrapassam o limite permitido.']);
}

$conexao = null;
$transacaoAberta = false;

try {
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

    $idCliente = filter_var($_SESSION['usuario_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($idCliente === false || $idCliente === null) {
        throw new ErroAgendamentoCliente('Entre na sua conta para continuar.', 401);
    }

    $_SESSION['csrf_dashboard'] ??= bin2hex(random_bytes(32));
    $csrfSessao = (string) $_SESSION['csrf_dashboard'];
    $entrada = [];

    if ($metodo === 'POST') {
        $corpo = file_get_contents('php://input');
        try {
            $entrada = json_decode($corpo === false ? '' : $corpo, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ErroAgendamentoCliente('Os dados enviados são inválidos.', 400);
        }
        if (!is_array($entrada)) {
            throw new ErroAgendamentoCliente('Os dados enviados são inválidos.', 400);
        }
        if (!is_string($entrada['csrf'] ?? null) || !hash_equals($csrfSessao, $entrada['csrf'])) {
            throw new ErroAgendamentoCliente('Sessão expirada. Atualize o painel e tente novamente.', 403);
        }
    }
    session_write_close();

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    require_once __DIR__ . '/conexao.php';

    $usuario = consultarAgendamentoCliente(
        $conexao,
        "SELECT id_usuario, nome, perfil, ativo FROM usuario WHERE id_usuario = ? LIMIT 1",
        [$idCliente]
    )->get_result()->fetch_assoc();

    if (!$usuario || (int) $usuario['ativo'] !== 1 || strtolower((string) $usuario['perfil']) !== 'cliente') {
        throw new ErroAgendamentoCliente('Entre na sua conta de cliente para continuar.', 401);
    }

    if ($metodo === 'GET') {
        $protocolo = validarProtocoloAgendamentoCliente($_GET['protocolo'] ?? null);
        $pedido = obterPedidoAgendamentoCliente($conexao, $protocolo, (int) $idCliente);
        if (!$pedido) {
            throw new ErroAgendamentoCliente('Pedido não encontrado.', 404);
        }

        $agendamento = obterAgendamentoAtualCliente($conexao, (int) $pedido['id_solicitacao']);
        if (!pedidoClientePodeAlterar($pedido, $agendamento) || $agendamento === null) {
            throw new ErroAgendamentoCliente('Este pedido não pode mais ser reagendado.', 409);
        }

        $horarios = consultarAgendamentoCliente(
            $conexao,
            "SELECT d.id_disponibilidade AS id, d.inicio_previsto AS inicio, d.fim_previsto AS fim
             FROM disponibilidade d
             WHERE d.id_disponibilidade <> ?
               AND d.situacao = 'LIVRE'
               AND d.capacidade > 0
               AND d.inicio_previsto > NOW()
               AND d.inicio_previsto <= DATE_ADD(NOW(), INTERVAL 30 DAY)
               AND d.fim_previsto > d.inicio_previsto
               AND d.capacidade > (
                 SELECT COUNT(*)
                 FROM agendamento a
                 JOIN solicitacao s ON s.id_solicitacao = a.id_solicitacao
                 JOIN status_solicitacao st ON st.id_status = s.id_status_atual
                 WHERE a.id_disponibilidade = d.id_disponibilidade
                   AND a.status NOT IN ('CANCELADO', 'REAGENDADO')
                   AND st.terminal = 0
               )
             ORDER BY d.inicio_previsto, d.id_disponibilidade
             LIMIT 100",
            [$agendamento['id_disponibilidade']]
        )->get_result()->fetch_all(MYSQLI_ASSOC);

        foreach ($horarios as &$horario) {
            $horario['id'] = (string) $horario['id'];
            $horario['inicio'] = (string) $horario['inicio'];
            $horario['fim'] = (string) $horario['fim'];
        }
        unset($horario);

        $conexao->close();
        responderAgendamentoCliente(200, ['sucesso' => true, 'horarios' => $horarios]);
    }

    $protocolo = validarProtocoloAgendamentoCliente($entrada['protocolo'] ?? null);
    $acao = $entrada['acao'] ?? null;
    if (!is_string($acao) || !in_array($acao, ['cancelar', 'reagendar'], true)) {
        throw new ErroAgendamentoCliente('Ação inválida.', 422);
    }

    $conexao->begin_transaction();
    $transacaoAberta = true;

    $pedido = obterPedidoAgendamentoCliente($conexao, $protocolo, (int) $idCliente, true);
    if (!$pedido) {
        throw new ErroAgendamentoCliente('Pedido não encontrado.', 404);
    }

    if ($pedido['status_codigo'] === 'CANCELADO' && $acao === 'cancelar') {
        $conexao->commit();
        $transacaoAberta = false;
        $conexao->close();
        responderAgendamentoCliente(200, [
            'sucesso' => true,
            'repetido' => true,
            'mensagem' => 'Este pedido já está cancelado.'
        ]);
    }

    if (!in_array($pedido['status_codigo'], ['SOLICITADO', 'AGENDADO'], true)) {
        throw new ErroAgendamentoCliente('Este pedido não pode mais ser alterado.', 409);
    }

    $agendamento = obterAgendamentoAtualCliente($conexao, (int) $pedido['id_solicitacao'], true);
    if (!pedidoClientePodeAlterar($pedido, $agendamento)) {
        throw new ErroAgendamentoCliente('Este pedido não pode mais ser alterado; confira o status e a data do atendimento.', 409);
    }

    $idAgendamento = $agendamento === null ? null : (int) $agendamento['id_agendamento'];

    if ($acao === 'cancelar') {
        if ($agendamento !== null) {
            consultarAgendamentoCliente(
                $conexao,
                "UPDATE agendamento
                 SET status = 'CANCELADO', motivo_cancelamento = 'Cancelado pelo cliente'
                 WHERE id_agendamento = ? AND status IN ('PENDENTE', 'CONFIRMADO')",
                [$idAgendamento]
            );
            if ($conexao->affected_rows !== 1) {
                throw new ErroAgendamentoCliente('O status do agendamento mudou. Atualize o painel e tente novamente.', 409);
            }
        }

        $statusCancelado = consultarAgendamentoCliente(
            $conexao,
            "SELECT id_status, nome FROM status_solicitacao WHERE codigo = 'CANCELADO' AND ativo = 1 AND terminal = 1 LIMIT 1 FOR UPDATE"
        )->get_result()->fetch_assoc();
        if (!$statusCancelado) {
            throw new RuntimeException('Status CANCELADO não configurado.');
        }

        consultarAgendamentoCliente(
            $conexao,
            'UPDATE solicitacao SET id_status_atual = ?, atualizada_em = NOW() WHERE id_solicitacao = ?',
            [$statusCancelado['id_status'], $pedido['id_solicitacao']]
        );
        consultarAgendamentoCliente(
            $conexao,
            'UPDATE atribuicao_tecnica SET fim_em = NOW()
             WHERE id_solicitacao = ? AND fim_em IS NULL',
            [$pedido['id_solicitacao']]
        );
        registrarHistoricoAgendamentoCliente(
            $conexao,
            (int) $pedido['id_solicitacao'],
            (int) $pedido['id_status_atual'],
            (int) $statusCancelado['id_status'],
            (int) $idCliente,
            'Pedido cancelado pelo cliente.'
        );

        $mensagemCliente = 'Você cancelou o pedido ' . $protocolo . '.';
        $mensagemEquipe = 'O cliente ' . $pedido['nome_contato'] . ' cancelou o pedido ' . $protocolo . '.';
        inserirNotificacaoAgendamentoCliente(
            $conexao,
            (int) $idCliente,
            (int) $pedido['id_solicitacao'],
            $idAgendamento,
            'PEDIDO_CANCELADO_CLIENTE',
            'Pedido cancelado',
            $mensagemCliente,
            'Pedido cancelado pelo cliente',
            $mensagemEquipe
        );

        $conexao->commit();
        $transacaoAberta = false;
        $conexao->close();
        responderAgendamentoCliente(200, ['sucesso' => true, 'mensagem' => 'Pedido cancelado. O horário foi liberado.']);
    }

    if ($agendamento === null || !is_string($entrada['disponibilidade'] ?? null)) {
        throw new ErroAgendamentoCliente('Selecione um novo horário disponível.', 422);
    }
    $idDisponibilidade = filter_var(
        $entrada['disponibilidade'],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );
    if ($idDisponibilidade === false) {
        throw new ErroAgendamentoCliente('Selecione um novo horário disponível.', 422);
    }
    if ((int) $idDisponibilidade === (int) $agendamento['id_disponibilidade']) {
        $conexao->commit();
        $transacaoAberta = false;
        $conexao->close();
        responderAgendamentoCliente(200, [
            'sucesso' => true,
            'repetido' => true,
            'mensagem' => 'Este já é o horário atual do pedido.'
        ]);
    }

    $novoHorario = consultarAgendamentoCliente(
        $conexao,
        "SELECT id_disponibilidade, capacidade
         FROM disponibilidade
         WHERE id_disponibilidade = ?
           AND situacao = 'LIVRE'
           AND capacidade > 0
           AND inicio_previsto > NOW()
           AND inicio_previsto <= DATE_ADD(NOW(), INTERVAL 30 DAY)
           AND fim_previsto > inicio_previsto
         LIMIT 1 FOR UPDATE",
        [$idDisponibilidade]
    )->get_result()->fetch_assoc();

    if (!$novoHorario) {
        throw new ErroAgendamentoCliente('Esse horário não está mais disponível. Atualize a lista e escolha outro.', 409);
    }

    $reservas = consultarAgendamentoCliente(
        $conexao,
        "SELECT a.id_agendamento
         FROM agendamento a
         JOIN solicitacao s ON s.id_solicitacao = a.id_solicitacao
         JOIN status_solicitacao st ON st.id_status = s.id_status_atual
         WHERE a.id_disponibilidade = ?
           AND a.status NOT IN ('CANCELADO', 'REAGENDADO')
           AND st.terminal = 0
         FOR UPDATE",
        [$idDisponibilidade]
    )->get_result()->num_rows;
    if ($reservas >= (int) $novoHorario['capacidade']) {
        throw new ErroAgendamentoCliente('Esse horário acabou de ser reservado. Escolha outro.', 409);
    }

    consultarAgendamentoCliente(
        $conexao,
        "UPDATE agendamento SET status = 'REAGENDADO'
         WHERE id_agendamento = ? AND status IN ('PENDENTE', 'CONFIRMADO')",
        [$agendamento['id_agendamento']]
    );
    if ($conexao->affected_rows !== 1) {
        throw new ErroAgendamentoCliente('O status do agendamento mudou. Atualize o painel e tente novamente.', 409);
    }

    consultarAgendamentoCliente(
        $conexao,
        "INSERT INTO agendamento (id_solicitacao, id_disponibilidade, id_anterior, status, observacao)
         VALUES (?, ?, ?, 'PENDENTE', 'Reagendamento solicitado pelo cliente; aguarda aprovação da equipe.')",
        [$pedido['id_solicitacao'], $idDisponibilidade, $agendamento['id_agendamento']]
    );
    $novoIdAgendamento = (int) $conexao->insert_id;

    consultarAgendamentoCliente(
        $conexao,
        'UPDATE atribuicao_tecnica SET fim_em = NOW()
         WHERE id_solicitacao = ? AND fim_em IS NULL',
        [$pedido['id_solicitacao']]
    );

    if ($pedido['status_codigo'] === 'AGENDADO') {
        $statusSolicitado = consultarAgendamentoCliente(
            $conexao,
            "SELECT id_status FROM status_solicitacao WHERE codigo = 'SOLICITADO' AND ativo = 1 AND terminal = 0 LIMIT 1 FOR UPDATE"
        )->get_result()->fetch_assoc();
        if (!$statusSolicitado) {
            throw new RuntimeException('Status SOLICITADO não configurado.');
        }
        consultarAgendamentoCliente(
            $conexao,
            'UPDATE solicitacao SET id_status_atual = ?, atualizada_em = NOW() WHERE id_solicitacao = ?',
            [$statusSolicitado['id_status'], $pedido['id_solicitacao']]
        );
        registrarHistoricoAgendamentoCliente(
            $conexao,
            (int) $pedido['id_solicitacao'],
            (int) $pedido['id_status_atual'],
            (int) $statusSolicitado['id_status'],
            (int) $idCliente,
            'Cliente solicitou reagendamento; novo horário aguarda confirmação da equipe.'
        );
    } else {
        consultarAgendamentoCliente(
            $conexao,
            'UPDATE solicitacao SET atualizada_em = NOW() WHERE id_solicitacao = ?',
            [$pedido['id_solicitacao']]
        );
    }

    $inicioNovoHorario = consultarAgendamentoCliente(
        $conexao,
        'SELECT inicio_previsto FROM disponibilidade WHERE id_disponibilidade = ?',
        [$idDisponibilidade]
    )->get_result()->fetch_assoc()['inicio_previsto'];
    $mensagemCliente = 'O pedido ' . $protocolo . ' foi reagendado para ' . $inicioNovoHorario . '; o novo horário aguarda confirmação da equipe.';
    $mensagemEquipe = 'O cliente ' . $pedido['nome_contato'] . ' solicitou reagendamento do pedido ' . $protocolo . ' para ' . $inicioNovoHorario . '.';
    inserirNotificacaoAgendamentoCliente(
        $conexao,
        (int) $idCliente,
        (int) $pedido['id_solicitacao'],
        $novoIdAgendamento,
        'REAGENDAMENTO_CLIENTE',
        'Reagendamento solicitado',
        $mensagemCliente,
        'Reagendamento solicitado pelo cliente',
        $mensagemEquipe
    );

    $conexao->commit();
    $transacaoAberta = false;
    $conexao->close();
    responderAgendamentoCliente(200, [
        'sucesso' => true,
        'mensagem' => 'Reagendamento solicitado. O novo horário ficará pendente de confirmação da equipe.'
    ]);
} catch (Throwable $erro) {
    if ($transacaoAberta && $conexao instanceof mysqli) {
        try {
            $conexao->rollback();
        } catch (Throwable) {
            // A conexão pode já ter sido encerrada.
        }
    }
    if ($conexao instanceof mysqli) {
        try {
            $conexao->close();
        } catch (Throwable) {
            // A conexão pode já ter sido encerrada.
        }
    }
    if ($erro instanceof ErroAgendamentoCliente) {
        responderAgendamentoCliente($erro->http, ['sucesso' => false, 'mensagem' => $erro->getMessage()]);
    }
    error_log('Falha na ação do cliente sobre o agendamento: ' . get_class($erro) . ', código ' . $erro->getCode());
    responderAgendamentoCliente(500, ['sucesso' => false, 'mensagem' => 'Não foi possível atualizar o pedido. Atualize o painel e tente novamente.']);
}