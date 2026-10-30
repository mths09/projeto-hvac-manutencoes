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

function normalizarNovoPedidoAdmin(mixed $entrada): array
{
    if (!is_array($entrada)) {
        throw new DomainException('Os dados do pedido são inválidos.');
    }
    $texto = static function (string $campo, int $limite, bool $obrigatorio = false) use ($entrada): string {
        $valor = $entrada[$campo] ?? '';
        if (!is_string($valor)) {
            throw new DomainException('Confira os dados do formulário.');
        }
        $valor = trim($valor);
        if (($obrigatorio && $valor === '') || mb_strlen($valor, 'UTF-8') > $limite) {
            throw new DomainException($obrigatorio ? 'Preencha os campos obrigatórios.' : 'Um dos campos ultrapassa o limite permitido.');
        }
        return $valor;
    };
    $id = static function (string $campo, bool $opcional = false) use ($entrada): ?int {
        $valor = $entrada[$campo] ?? '';
        if ($opcional && $valor === '') {
            return null;
        }
        if (!is_int($valor) && !is_string($valor)) {
            throw new DomainException('Selecione um serviço, horário e técnico válidos.');
        }
        $validado = filter_var((string) $valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($validado === false) {
            throw new DomainException('Selecione um serviço e horário disponíveis.');
        }
        return $validado;
    };

    $nome = $texto('nome', 150, true);
    $telefone = $texto('telefone', 25);
    $endereco = $texto('endereco', 500, true);
    $descricao = $texto('descricao', 3000);
    if ($telefone !== '' && (!preg_match('/^[0-9+().\s-]{8,25}$/D', $telefone) || strlen(preg_replace('/\D/', '', $telefone)) < 8)) {
        throw new DomainException('Informe um telefone válido.');
    }
    if (!in_array($entrada['prioridade'] ?? null, ['normal', 'urgente'], true)) {
        throw new DomainException('Selecione uma prioridade válida.');
    }

    return [
        'nome' => $nome,
        'telefone' => $telefone,
        'endereco' => $endereco,
        'descricao' => $descricao,
        'id_tipo_servico' => $id('id_tipo_servico'),
        'id_disponibilidade' => $id('id_disponibilidade'),
        'id_tecnico' => $id('id_tecnico', true),
        'id_cliente' => $id('id_cliente', true),
        'urgente' => $entrada['prioridade'] === 'urgente',
    ];
}

if (defined('HVAC_SOLICITACOES_ADMIN_TESTE')) {
    return;
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
        if (($_GET['acao'] ?? '') === 'buscar_cliente') {
            $telefone = $_GET['telefone'] ?? '';
            if (!is_string($telefone)) {
                $conexao->close();
                responderSolicitacoesAdmin(422, ['sucesso' => false, 'mensagem' => 'Telefone inválido.']);
            }
            $telefone = preg_replace('/\\D/', '', $telefone);
            if (!preg_match('/^[1-9][0-9]{9,10}$/D', $telefone)) {
                $conexao->close();
                responderSolicitacoesAdmin(200, ['sucesso' => true, 'clientes' => []]);
            }
            $clientes = consultarSolicitacoesAdmin(
                $conexao,
                "SELECT id_usuario AS id, nome, email, telefone
                 FROM usuario
                 WHERE ativo = 1 AND perfil = 'cliente' AND telefone = ?
                 ORDER BY nome, id_usuario
                 LIMIT 10",
                [$telefone]
            )->get_result()->fetch_all(MYSQLI_ASSOC);
            foreach ($clientes as &$cliente) {
                $cliente['id'] = (string) $cliente['id'];
                $cliente['nome'] = (string) $cliente['nome'];
                $cliente['email'] = (string) $cliente['email'];
                $cliente['telefone'] = (string) $cliente['telefone'];
            }
            unset($cliente);
            $conexao->close();
            session_write_close();
            responderSolicitacoesAdmin(200, ['sucesso' => true, 'clientes' => $clientes]);
        }
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

        // Anexos
        $linhasAnexos = consultarSolicitacoesAdmin(
            $conexao,
            "SELECT a.id_solicitacao, f.id_arquivo, f.mime_type, a.legenda
            FROM anexo_solicitacao a
            JOIN arquivo f ON f.id_arquivo = a.id_arquivo
            JOIN solicitacao s ON s.id_solicitacao = a.id_solicitacao
            WHERE s.arquivada_em IS NULL
                AND f.mime_type IN ('image/jpeg', 'image/png', 'video/mp4')
            ORDER BY a.id_solicitacao, a.ordem, a.id_anexo"
        )->get_result()->fetch_all(MYSQLI_ASSOC);

        $anexosPorSolicitacao = [];
        foreach($linhasAnexos as $linha) {
            $anexosPorSolicitacao[(string) $linha['id_solicitacao']][] = [
                'id' => (string) $linha['id_arquivo'],
                'mime' => (string) $linha['mime_type'],
                'legenda' => (string) ($linha['legenda'] ?? ''),
            ];
        }

        foreach ($pedidos as &$pedido) {
            $pedido['anexos'] = $anexosPorSolicitacao[$pedido['id']] ?? [];
        }
        unset($pedido);

        $linhasTriagem = consultarSolicitacoesAdmin(
            $conexao,
            "SELECT tr.id_solicitacao, p.enunciado,
                    COALESCE(r.valor_texto, o.rotulo) AS resposta
             FROM triagem tr
             INNER JOIN resposta_triagem r ON r.id_triagem = tr.id_triagem
             INNER JOIN pergunta_triagem p ON p.id_pergunta = r.id_pergunta
             LEFT JOIN resposta_opcao ro ON ro.id_resposta = r.id_resposta
             LEFT JOIN opcao_pergunta o ON o.id_opcao = ro.id_opcao
             INNER JOIN solicitacao s ON s.id_solicitacao = tr.id_solicitacao
             WHERE s.arquivada_em IS NULL AND tr.status = 'ENVIADA'
             ORDER BY tr.id_solicitacao, p.ordem, p.id_pergunta"
        )->get_result()->fetch_all(MYSQLI_ASSOC);
        $triagemPorSolicitacao = [];
        foreach ($linhasTriagem as $linhaTriagem) {
            $triagemPorSolicitacao[(string) $linhaTriagem['id_solicitacao']][] = [
                'pergunta' => (string) $linhaTriagem['enunciado'],
                'resposta' => (string) ($linhaTriagem['resposta'] ?? ''),
            ];
        }
        foreach ($pedidos as &$pedido) {
            $pedido['triagem'] = $triagemPorSolicitacao[$pedido['id']] ?? [];
        }
        unset($pedido);
        $tecnicos = consultarSolicitacoesAdmin(
            $conexao,
            'SELECT id_tecnico AS id, nome FROM tecnico WHERE ativo = 1 ORDER BY nome, id_tecnico'
        )->get_result()->fetch_all(MYSQLI_ASSOC);
        foreach ($tecnicos as &$tecnico) {
            $tecnico['id'] = (string) $tecnico['id'];
        }
        unset($tecnico);
        $servicos = consultarSolicitacoesAdmin(
            $conexao,
            'SELECT id_tipo_servico AS id, nome FROM tipo_servico WHERE ativo = 1 ORDER BY ordem, nome'
        )->get_result()->fetch_all(MYSQLI_ASSOC);
        foreach ($servicos as &$servico) {
            $servico['id'] = (string) $servico['id'];
        }
        unset($servico);
        $conexao->close();
        responderSolicitacoesAdmin(200, [
            'sucesso' => true,
            'csrf' => $csrfSessao,
            'pedidos' => $pedidos,
            'tecnicos' => $tecnicos,
            'servicos' => $servicos
        ]);
    }

    $acao = $entrada['acao'] ?? null;
    if (!is_string($acao) || !in_array($acao, ['criar', 'status', 'tecnico'], true)) {
        responderSolicitacoesAdmin(422, ['sucesso' => false, 'mensagem' => 'Ação inválida.']);
    }

    if ($acao === 'criar') {
        $novoPedido = normalizarNovoPedidoAdmin($entrada);
        $conexao->begin_transaction();
        try {
            if ($novoPedido['id_cliente'] !== null) {
                $clienteExistente = consultarSolicitacoesAdmin(
                    $conexao,
                    "SELECT id_usuario, telefone
                     FROM usuario
                     WHERE id_usuario = ? AND perfil = 'cliente' AND ativo = 1
                     LIMIT 1 FOR UPDATE",
                    [$novoPedido['id_cliente']]
                )->get_result()->fetch_assoc();
                if (!$clienteExistente) {
                    throw new DomainException('A conta selecionada não está mais disponível. Busque o cliente novamente.');
                }
                $telefonePedido = preg_replace('/\D/', '', $novoPedido['telefone']);
                if ($telefonePedido === '' || !hash_equals((string) $clienteExistente['telefone'], $telefonePedido)) {
                    throw new DomainException('O telefone informado não corresponde à conta selecionada. Busque o cliente pelo telefone novamente.');
                }
            }

            $servico = consultarSolicitacoesAdmin(
                $conexao,
                'SELECT id_tipo_servico FROM tipo_servico WHERE id_tipo_servico = ? AND ativo = 1 LIMIT 1 FOR UPDATE',
                [$novoPedido['id_tipo_servico']]
            )->get_result()->fetch_assoc();
            if (!$servico) {
                throw new DomainException('O tipo de serviço selecionado está indisponível.');
            }

            $horario = consultarSolicitacoesAdmin(
                $conexao,
                "SELECT id_disponibilidade, capacidade
                 FROM disponibilidade
                 WHERE id_disponibilidade = ? AND situacao = 'LIVRE'
                   AND inicio_previsto > NOW()
                   AND inicio_previsto <= DATE_ADD(NOW(), INTERVAL 30 DAY)
                   AND fim_previsto > inicio_previsto
                 LIMIT 1 FOR UPDATE",
                [$novoPedido['id_disponibilidade']]
            )->get_result()->fetch_assoc();
            if (!$horario) {
                throw new DomainException('Esse horário não está mais disponível. Atualize a data e tente novamente.');
            }
            $reservas = (int) consultarSolicitacoesAdmin(
                $conexao,
                "SELECT COUNT(*) AS total
                 FROM agendamento a
                 INNER JOIN solicitacao s ON s.id_solicitacao = a.id_solicitacao
                 INNER JOIN status_solicitacao ss ON ss.id_status = s.id_status_atual
                 WHERE a.id_disponibilidade = ?
                   AND a.status NOT IN ('CANCELADO', 'REAGENDADO')
                   AND ss.terminal = 0",
                [$novoPedido['id_disponibilidade']]
            )->get_result()->fetch_assoc()['total'];
            if ($reservas >= (int) $horario['capacidade']) {
                throw new DomainException('Esse horário acabou de ficar cheio. Escolha outro horário.');
            }

            $status = consultarSolicitacoesAdmin(
                $conexao,
                "SELECT id_status FROM status_solicitacao WHERE codigo = 'AGENDADO' AND ativo = 1 LIMIT 1 FOR UPDATE"
            )->get_result()->fetch_assoc();
            if (!$status) {
                throw new RuntimeException('O status Agendado não está configurado no banco.');
            }

            $idAtribuicao = null;
            if ($novoPedido['id_tecnico'] !== null) {
                $tecnico = consultarSolicitacoesAdmin(
                    $conexao,
                    'SELECT id_tecnico FROM tecnico WHERE id_tecnico = ? AND ativo = 1 LIMIT 1 FOR UPDATE',
                    [$novoPedido['id_tecnico']]
                )->get_result()->fetch_assoc();
                if (!$tecnico) {
                    throw new DomainException('O técnico selecionado está indisponível.');
                }
            }

            $protocoloNovo = 'SRV-' . bin2hex(random_bytes(12));
            consultarSolicitacoesAdmin(
                $conexao,
                "INSERT INTO solicitacao
                 (protocolo, id_cliente, id_criador, id_tipo_servico, id_status_atual, nome_contato,
                  telefone_contato, descricao_problema, urgente, origem, criada_em, atualizada_em)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'ADMIN', NOW(), NOW())",
                [$protocoloNovo, $novoPedido['id_cliente'], $idAdministrador, $novoPedido['id_tipo_servico'], $status['id_status'],
                 $novoPedido['nome'], $novoPedido['telefone'] !== '' ? $novoPedido['telefone'] : null,
                 $novoPedido['descricao'] !== '' ? $novoPedido['descricao'] : null, (int) $novoPedido['urgente']]
            );
            $idSolicitacaoNova = (int) $conexao->insert_id;

            consultarSolicitacoesAdmin(
                $conexao,
                'INSERT INTO endereco_atendimento (id_solicitacao, endereco_informado) VALUES (?, ?)',
                [$idSolicitacaoNova, $novoPedido['endereco']]
            );

            if ($novoPedido['id_tecnico'] !== null) {
                consultarSolicitacoesAdmin(
                    $conexao,
                    'INSERT INTO atribuicao_tecnica (id_solicitacao, id_tecnico, id_responsavel, inicio_em)
                     VALUES (?, ?, ?, NOW())',
                    [$idSolicitacaoNova, $novoPedido['id_tecnico'], $idAdministrador]
                );
                $idAtribuicao = (int) $conexao->insert_id;
            }

            consultarSolicitacoesAdmin(
                $conexao,
                "INSERT INTO agendamento (id_solicitacao, id_disponibilidade, id_atribuicao, status)
                 VALUES (?, ?, ?, 'CONFIRMADO')",
                [$idSolicitacaoNova, $novoPedido['id_disponibilidade'], $idAtribuicao]
            );
            consultarSolicitacoesAdmin(
                $conexao,
                'INSERT INTO historico_status (id_solicitacao, id_status_anterior, id_status_novo, id_responsavel, observacao)
                 VALUES (?, NULL, ?, ?, ?)',
                [$idSolicitacaoNova, $status['id_status'], $idAdministrador, 'Pedido cadastrado manualmente pela equipe no painel administrativo.']
            );

            $conexao->commit();
            $conexao->close();
            session_write_close();
            responderSolicitacoesAdmin(201, [
                'sucesso' => true,
                'mensagem' => $novoPedido['id_cliente'] === null
                    ? 'Pedido cadastrado e horário confirmado. Não foi vinculado a uma conta de cliente.'
                    : 'Pedido cadastrado, horário confirmado e vinculado à conta do cliente.',
                'protocolo' => $protocoloNovo
            ]);
        } catch (DomainException $erro) {
            $conexao->rollback();
            $conexao->close();
            session_write_close();
            responderSolicitacoesAdmin(422, ['sucesso' => false, 'mensagem' => $erro->getMessage()]);
        } catch (Throwable $erro) {
            $conexao->rollback();
            throw $erro;
        }
    }

    $protocolo = validarProtocoloAdmin($entrada['protocolo'] ?? null);

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
                'SELECT a.id_agendamento, a.status, d.id_disponibilidade, d.inicio_previsto, (d.inicio_previsto > NOW()) AS horario_futuro
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
                // A disponibilidade só volta a abrir quando o administrador liberar manualmente.
                consultarSolicitacoesAdmin(
                    $conexao,
                    "UPDATE disponibilidade SET situacao = 'BLOQUEADO', id_editor = ?, atualizada_em = NOW()
                     WHERE id_disponibilidade = ? AND situacao = 'LIVRE'",
                    [$idAdministrador, $agendamento['id_disponibilidade']]
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
