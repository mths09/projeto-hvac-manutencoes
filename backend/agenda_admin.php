<?php

declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function responderAgendaAdmin(int $status, array $dados): never
{
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

function consultarAgendaAdmin(mysqli $db, string $sql, array $parametros = []): mysqli_stmt
{
    $stmt = $db->prepare($sql);
    $stmt->execute($parametros);
    return $stmt;
}

function validarDataAgenda(mixed $valor): string
{
    if (!is_string($valor) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $valor)) {
        throw new InvalidArgumentException('Informe uma data válida.');
    }
    $data = DateTimeImmutable::createFromFormat('!Y-m-d', $valor, new DateTimeZone('America/Sao_Paulo'));
    $erros = DateTimeImmutable::getLastErrors();
    if (!$data || ($erros !== false && ($erros['warning_count'] > 0 || $erros['error_count'] > 0)) || $data->format('Y-m-d') !== $valor) {
        throw new InvalidArgumentException('Informe uma data válida.');
    }
    return $valor;
}

function validarHorarioAgenda(mixed $inicio, mixed $fim, mixed $capacidade, string $data): array
{
    if (!is_string($inicio) || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $inicio)
        || !is_string($fim) || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $fim)) {
        throw new InvalidArgumentException('Informe os horários de início e fim.');
    }
    $inicioData = DateTimeImmutable::createFromFormat('!Y-m-d H:i', "$data $inicio", new DateTimeZone('America/Sao_Paulo'));
    $fimData = DateTimeImmutable::createFromFormat('!Y-m-d H:i', "$data $fim", new DateTimeZone('America/Sao_Paulo'));
    if (!$inicioData || !$fimData || $fimData <= $inicioData) {
        throw new InvalidArgumentException('O fim precisa ser posterior ao início no mesmo dia.');
    }
    if (!is_string($capacidade) && !is_int($capacidade)) {
        throw new InvalidArgumentException('Informe uma capacidade válida.');
    }
    $capacidadeValidada = filter_var((string) $capacidade, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
    if ($capacidadeValidada === false) {
        throw new InvalidArgumentException('A capacidade deve ser um número entre 1 e 100.');
    }
    $agora = new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo'));
    if ($inicioData <= $agora || $inicioData > $agora->modify('+30 days')) {
        throw new InvalidArgumentException('Não é possivel criar horários em datas antigas');
    }
    return [$inicioData->format('Y-m-d H:i:s'), $fimData->format('Y-m-d H:i:s'), $capacidadeValidada];
}

function contarReservasAtivasAgenda(mysqli $db, int $idDisponibilidade): int
{
    $resultado = consultarAgendaAdmin(
        $db,
        "SELECT COUNT(*) AS total
         FROM agendamento a
         JOIN solicitacao s ON s.id_solicitacao = a.id_solicitacao
         JOIN status_solicitacao st ON st.id_status = s.id_status_atual
         WHERE a.id_disponibilidade = ?
           AND a.status NOT IN ('CANCELADO', 'REAGENDADO')
           AND st.terminal = 0",
        [$idDisponibilidade]
    )->get_result()->fetch_assoc();
    return (int) ($resultado['total'] ?? 0);
}

function adquirirTravaAgenda(mysqli $db): void
{
    $resultado = consultarAgendaAdmin($db, 'SELECT GET_LOCK(?, 5) AS adquirido', ['hvac_agenda_mutacao'])
        ->get_result()->fetch_assoc();
    if ((int) ($resultado['adquirido'] ?? 0) !== 1) {
        throw new RuntimeException('A agenda está sendo atualizada. Tente novamente em alguns segundos.');
    }
}

function liberarTravaAgenda(mysqli $db): void
{
    consultarAgendaAdmin($db, 'SELECT RELEASE_LOCK(?)', ['hvac_agenda_mutacao']);
}

if (!defined('HVAC_AGENDA_ADMIN_TESTE')) {
try {
    $metodo = $_SERVER['REQUEST_METHOD'] ?? '';
    if (!in_array($metodo, ['GET', 'POST'], true)) {
        header('Allow: GET, POST');
        responderAgendaAdmin(405, ['sucesso' => false, 'mensagem' => 'Método não permitido.']);
    }
    if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 32768) {
        responderAgendaAdmin(413, ['sucesso' => false, 'mensagem' => 'Os dados enviados ultrapassam o limite permitido.']);
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
    $idUsuario = filter_var($_SESSION['usuario_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($idUsuario === false || $idUsuario === null) {
        responderAgendaAdmin(401, ['sucesso' => false, 'mensagem' => 'Entre como administrador para continuar.']);
    }
    $_SESSION['csrf_solicitacoes_admin'] ??= bin2hex(random_bytes(32));
    $csrf = (string) $_SESSION['csrf_solicitacoes_admin'];

    $entrada = [];
    if ($metodo === 'POST') {
        $corpo = file_get_contents('php://input');
        $entrada = json_decode($corpo === false ? '' : $corpo, true);
        if (!is_array($entrada)) {
            responderAgendaAdmin(400, ['sucesso' => false, 'mensagem' => 'Dados da agenda inválidos.']);
        }
        if (!is_string($entrada['csrf'] ?? null) || !hash_equals($csrf, $entrada['csrf'])) {
            responderAgendaAdmin(403, ['sucesso' => false, 'mensagem' => 'Sessão expirada. Atualize o painel e tente novamente.']);
        }
    }
    session_write_close();

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    require_once __DIR__ . '/conexao.php';
    consultarAgendaAdmin($conexao, 'SET SESSION group_concat_max_len = 8192');
    $usuario = consultarAgendaAdmin($conexao, 'SELECT perfil, ativo FROM usuario WHERE id_usuario = ? LIMIT 1', [$idUsuario])
        ->get_result()->fetch_assoc();
    $perfil = strtolower(trim((string) ($usuario['perfil'] ?? '')));
    if (!$usuario || (int) $usuario['ativo'] !== 1 || !in_array($perfil, ['admin', 'administrador'], true)) {
        $conexao->close();
        responderAgendaAdmin(403, ['sucesso' => false, 'mensagem' => 'Esta área é exclusiva do administrador.']);
    }

    if ($metodo === 'GET') {
        $ano = filter_var($_GET['ano'] ?? date('Y'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 2020, 'max_range' => 2100]]);
        $mes = filter_var($_GET['mes'] ?? date('n'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 12]]);
        if ($ano === false || $mes === false) {
            $conexao->close();
            responderAgendaAdmin(422, ['sucesso' => false, 'mensagem' => 'Mês inválido.']);
        }
        $inicioMes = sprintf('%04d-%02d-01 00:00:00', $ano, $mes);
        $fimMes = (new DateTimeImmutable($inicioMes, new DateTimeZone('America/Sao_Paulo')))->modify('first day of next month')->format('Y-m-d H:i:s');
        $linhas = consultarAgendaAdmin(
            $conexao,
            "SELECT d.id_disponibilidade AS id, d.inicio_previsto AS inicio, d.fim_previsto AS fim,
                    d.situacao, d.capacidade, COALESCE(r.total, 0) AS reservas,
                    COALESCE(r.protocolos, '') AS protocolos
             FROM disponibilidade d
             LEFT JOIN (
                 SELECT a.id_disponibilidade, COUNT(*) AS total,
                        GROUP_CONCAT(s.protocolo ORDER BY s.criada_em SEPARATOR ',') AS protocolos
                 FROM agendamento a
                 JOIN solicitacao s ON s.id_solicitacao = a.id_solicitacao
                 JOIN status_solicitacao st ON st.id_status = s.id_status_atual
                 WHERE a.status NOT IN ('CANCELADO', 'REAGENDADO') AND st.terminal = 0
                 GROUP BY a.id_disponibilidade
             ) r ON r.id_disponibilidade = d.id_disponibilidade
             WHERE d.inicio_previsto >= ? AND d.inicio_previsto < ? AND d.situacao <> 'REMOVIDO'
             ORDER BY d.inicio_previsto, d.id_disponibilidade",
            [$inicioMes, $fimMes]
        )->get_result();
        $horarios = [];
        while ($linha = $linhas->fetch_assoc()) {
            $reservas = (int) $linha['reservas'];
            $capacidade = (int) $linha['capacidade'];
            $situacao = strtoupper((string) $linha['situacao']);
            $estado = $situacao === 'LIVRE'
                ? ($reservas >= $capacidade ? 'full' : 'free')
                : 'blocked';
            $horarios[] = [
                'id' => (string) $linha['id'],
                'inicio' => (string) $linha['inicio'],
                'fim' => (string) $linha['fim'],
                'estado' => $estado,
                'capacidade' => $capacidade,
                'reservas' => $reservas,
                'protocolos' => $linha['protocolos'] === '' ? [] : explode(',', (string) $linha['protocolos'])
            ];
        }
        $conexao->close();
        responderAgendaAdmin(200, ['sucesso' => true, 'csrf' => $csrf, 'horarios' => $horarios]);
    }

    $acao = $entrada['acao'] ?? null;
    if (!is_string($acao) || !in_array($acao, ['criar', 'bloquear', 'liberar', 'remover'], true)) {
        $conexao->close();
        responderAgendaAdmin(422, ['sucesso' => false, 'mensagem' => 'Ação da agenda inválida.']);
    }

    $travaAdquirida = false;
    try {
        adquirirTravaAgenda($conexao);
        $travaAdquirida = true;
        $conexao->begin_transaction();
        if ($acao === 'criar') {
            $data = validarDataAgenda($entrada['data'] ?? null);
            [$inicio, $fim, $capacidade] = validarHorarioAgenda($entrada['inicio'] ?? null, $entrada['fim'] ?? null, $entrada['capacidade'] ?? null, $data);
            $sobreposicao = consultarAgendaAdmin(
                $conexao,
                "SELECT id_disponibilidade FROM disponibilidade
                 WHERE situacao <> 'REMOVIDO' AND inicio_previsto < ? AND fim_previsto > ?
                 LIMIT 1 FOR UPDATE",
                [$fim, $inicio]
            )->get_result()->fetch_assoc();
            if ($sobreposicao) {
                throw new InvalidArgumentException('Esse horário se sobrepõe a outro horário existente.');
            }
            consultarAgendaAdmin(
                $conexao,
                "INSERT INTO disponibilidade (inicio_previsto, fim_previsto, situacao, capacidade, id_editor, atualizada_em)
                 VALUES (?, ?, 'LIVRE', ?, ?, NOW())",
                [$inicio, $fim, $capacidade, $idUsuario]
            );
            $mensagem = 'Horário adicionado à agenda.';
        } else {
            $idDisponibilidade = filter_var($entrada['horario_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($idDisponibilidade === false) {
                throw new InvalidArgumentException('Selecione um horário válido.');
            }
            $horario = consultarAgendaAdmin(
                $conexao,
                'SELECT id_disponibilidade, situacao FROM disponibilidade WHERE id_disponibilidade = ? LIMIT 1 FOR UPDATE',
                [$idDisponibilidade]
            )->get_result()->fetch_assoc();
            if (!$horario || $horario['situacao'] === 'REMOVIDO') {
                throw new OutOfBoundsException('O horário não foi encontrado. Atualize a agenda.');
            }
            $reservas = contarReservasAtivasAgenda($conexao, (int) $idDisponibilidade);
            if ($acao === 'bloquear') {
                if ($horario['situacao'] !== 'LIVRE') {
                    throw new InvalidArgumentException('Este horário não está livre. Atualize a agenda.');
                }
                if ($reservas > 0) {
                    throw new InvalidArgumentException('Não é possível bloquear um horário com reservas ativas.');
                }
                consultarAgendaAdmin($conexao,
                    "UPDATE disponibilidade SET situacao = 'BLOQUEADO', id_editor = ?, atualizada_em = NOW()
                     WHERE id_disponibilidade = ? AND situacao = 'LIVRE'",
                    [$idUsuario, $idDisponibilidade]
                );
                $mensagem = 'Horário bloqueado.';
            } elseif ($acao === 'liberar') {
                if ($horario['situacao'] !== 'BLOQUEADO') {
                    throw new InvalidArgumentException('Este horário não está bloqueado. Atualize a agenda.');
                }
                consultarAgendaAdmin($conexao,
                    "UPDATE disponibilidade SET situacao = 'LIVRE', id_editor = ?, atualizada_em = NOW()
                     WHERE id_disponibilidade = ? AND situacao = 'BLOQUEADO'",
                    [$idUsuario, $idDisponibilidade]
                );
                $mensagem = 'Horário liberado manualmente.';
            } else {
                if ($reservas > 0) {
                    throw new InvalidArgumentException('Cancele ou reagende as reservas antes de remover este horário.');
                }
                consultarAgendaAdmin($conexao,
                    "UPDATE disponibilidade SET situacao = 'REMOVIDO', id_editor = ?, atualizada_em = NOW()
                     WHERE id_disponibilidade = ? AND situacao <> 'REMOVIDO'",
                    [$idUsuario, $idDisponibilidade]
                );
                $mensagem = 'Horário removido da agenda.';
            }
        }
        $conexao->commit();
        if ($travaAdquirida) {
            liberarTravaAgenda($conexao);
            $travaAdquirida = false;
        }
        $conexao->close();
        responderAgendaAdmin(200, ['sucesso' => true, 'mensagem' => $mensagem]);
    } catch (InvalidArgumentException $erro) {
        $conexao->rollback();
        if ($travaAdquirida) {
            liberarTravaAgenda($conexao);
            $travaAdquirida = false;
        }
        $conexao->close();
        responderAgendaAdmin(422, ['sucesso' => false, 'mensagem' => $erro->getMessage()]);
    } catch (OutOfBoundsException $erro) {
        $conexao->rollback();
        if ($travaAdquirida) {
            liberarTravaAgenda($conexao);
            $travaAdquirida = false;
        }
        $conexao->close();
        responderAgendaAdmin(404, ['sucesso' => false, 'mensagem' => $erro->getMessage()]);
    } catch (Throwable $erro) {
        try {
            $conexao->rollback();
        } catch (Throwable) {
        }
        if ($travaAdquirida && $conexao->ping()) {
            try {
                liberarTravaAgenda($conexao);
            } catch (Throwable $erroTrava) {
                error_log('Falha ao liberar trava da agenda: ' . $erroTrava->getMessage());
            }
        }
        $indiceLegadoImpedeRecriacao = $erro instanceof mysqli_sql_exception
            && $erro->getCode() === 1062
            && str_contains($erro->getMessage(), 'un_disponibilidade_inicio');
        $conexao->close();
        if ($indiceLegadoImpedeRecriacao) {
            responderAgendaAdmin(409, [
                'sucesso' => false,
                'mensagem' => 'O banco ainda impede recriar horários removidos. No phpMyAdmin, selecione hvac_db e execute: ALTER TABLE disponibilidade DROP INDEX un_disponibilidade_inicio, ADD INDEX idx_disponibilidade_inicio (inicio_previsto).'
            ]);
        }
        throw $erro;
    }
} catch (InvalidArgumentException $erro) {
    responderAgendaAdmin(422, ['sucesso' => false, 'mensagem' => $erro->getMessage()]);
} catch (Throwable $erro) {
    error_log('Falha ao processar agenda administrativa: ' . get_class($erro) . ', código ' . $erro->getCode() . ' - ' . $erro->getMessage());
    responderAgendaAdmin(500, ['sucesso' => false, 'mensagem' => 'Não foi possível atualizar a agenda. Tente novamente.']);
}
}
