<?php

declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function responderDashboard(int $status, array $dados): never
{
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    responderDashboard(405, ['sucesso' => false, 'mensagem' => 'Método não permitido.']);
}

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

    $idUsuario = filter_var(
        $_SESSION['usuario_id'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($idUsuario === false || $idUsuario === null) {
        responderDashboard(401, ['sucesso' => false, 'mensagem' => 'Entre na sua conta para continuar.']);
    }

    $_SESSION['csrf_dashboard'] ??= bin2hex(random_bytes(32));
    $csrf = $_SESSION['csrf_dashboard'];
    session_write_close();

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    require_once __DIR__ . '/conexao.php';

    $stmt = $conexao->prepare(
        'SELECT id_usuario, nome, email, telefone, perfil, ativo, criado_em
         FROM usuario
         WHERE id_usuario = ?
         LIMIT 1'
    );
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $usuario = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$usuario || (int) $usuario['ativo'] !== 1 || strtolower((string) $usuario['perfil']) !== 'cliente') {
        $conexao->close();
        responderDashboard(401, ['sucesso' => false, 'mensagem' => 'Entre na sua conta de cliente para continuar.']);
    }

    // -----------------------------------------
    // ENDEREÇO SEPARADO (CEP, logradouro, bairro, cidade, UF)
    // -----------------------------------------
    $stmt = $conexao->prepare(
        'SELECT endereco_informado, cep, logradouro, bairro, cidade, uf
         FROM endereco_usuario
         WHERE id_usuario = ? AND ativo = 1
         ORDER BY principal DESC, id_endereco_usuario DESC
         LIMIT 1'
    );
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $registroEndereco = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $endereco = null;

    if ($registroEndereco) {
        $logradouro = trim((string) ($registroEndereco['logradouro'] ?? ''));

        $endereco = [
            'cep'        => (string) ($registroEndereco['cep'] ?? ''),
            // Se o logradouro estiver vazio (cadastro antigo), usa o texto completo
            'logradouro' => $logradouro !== ''
                ? $logradouro
                : (string) ($registroEndereco['endereco_informado'] ?? ''),
            'bairro'     => (string) ($registroEndereco['bairro'] ?? ''),
            'cidade'     => (string) ($registroEndereco['cidade'] ?? ''),
            'uf'         => (string) ($registroEndereco['uf'] ?? ''),
        ];
    }

    $stmt = $conexao->prepare(
        "SELECT
            s.id_solicitacao AS id,
            s.protocolo,
            s.urgente,
            s.nome_contato,
            s.telefone_contato,
            s.descricao_problema AS descricao,
            s.criada_em AS criado_em,
            ts.nome AS tipo,
            ss.codigo AS status,
            ss.nome AS status_nome,
            ea.endereco_informado AS endereco,
            d.inicio_previsto,
            t.nome AS tecnico,
            t.telefone AS telefone_tecnico
         FROM solicitacao AS s
         INNER JOIN tipo_servico AS ts ON ts.id_tipo_servico = s.id_tipo_servico
         INNER JOIN status_solicitacao AS ss ON ss.id_status = s.id_status_atual
         LEFT JOIN endereco_atendimento AS ea ON ea.id_solicitacao = s.id_solicitacao
         LEFT JOIN agendamento AS a ON a.id_agendamento = (
            SELECT a2.id_agendamento
            FROM agendamento AS a2
            WHERE a2.id_solicitacao = s.id_solicitacao
              AND NOT EXISTS (
                SELECT 1 FROM agendamento AS a3 WHERE a3.id_anterior = a2.id_agendamento
              )
            ORDER BY a2.id_agendamento DESC
            LIMIT 1
         )
         LEFT JOIN disponibilidade AS d ON d.id_disponibilidade = a.id_disponibilidade
         LEFT JOIN atribuicao_tecnica AS at ON at.id_atribuicao = (
            SELECT at2.id_atribuicao
            FROM atribuicao_tecnica AS at2
            WHERE at2.id_solicitacao = s.id_solicitacao AND at2.fim_em IS NULL
            ORDER BY at2.inicio_em DESC, at2.id_atribuicao DESC
            LIMIT 1
         )
         LEFT JOIN tecnico AS t ON t.id_tecnico = at.id_tecnico AND t.ativo = 1
         WHERE s.id_cliente = ?
         ORDER BY s.criada_em DESC, s.id_solicitacao DESC"
    );
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $resultado = $stmt->get_result();

    $servicos = [];
    $resumo = ['total' => 0, 'andamento' => 0, 'concluidos' => 0, 'agendados' => 0];

    while ($linha = $resultado->fetch_assoc()) {
        $servicos[] = [
            'id' => (string) $linha['id'],
            'protocolo' => (string) $linha['protocolo'],
            'tipo' => (string) $linha['tipo'],
            'status' => (string) $linha['status'],
            'status_nome' => (string) $linha['status_nome'],
            'urgente' => (bool) $linha['urgente'],
            'nome_contato' => (string) $linha['nome_contato'],
            'telefone_contato' => (string) ($linha['telefone_contato'] ?? ''),
            'descricao' => (string) ($linha['descricao'] ?? ''),
            'criado_em' => (string) $linha['criado_em'],
            'endereco' => (string) ($linha['endereco'] ?? ''),
            'inicio_previsto' => (string) ($linha['inicio_previsto'] ?? ''),
            'tecnico' => (string) ($linha['tecnico'] ?? ''),
            'telefone_tecnico' => (string) ($linha['telefone_tecnico'] ?? '')
        ];

        $resumo['total']++;

        if ($linha['status'] === 'EM_ANDAMENTO') $resumo['andamento']++;
        if ($linha['status'] === 'CONCLUIDO') $resumo['concluidos']++;
        if ($linha['status'] === 'AGENDADO') $resumo['agendados']++;
    }
    $stmt->close();

    $stmt = $conexao->prepare(
    "SELECT a.id_solicitacao, f.id_arquivo, f.mime_type, a.legenda
     FROM anexo_solicitacao a
     JOIN arquivo f ON f.id_arquivo = a.id_arquivo
     JOIN solicitacao s ON s.id_solicitacao = a.id_solicitacao
     WHERE s.id_cliente = ?
       AND f.mime_type IN ('image/jpeg', 'image/png', 'video/mp4')
     ORDER BY a.id_solicitacao, a.ordem, a.id_anexo"
    );
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $linhasAnexos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $anexosPorSolicitacao = [];
    foreach ($linhasAnexos as $linha) {
        $anexosPorSolicitacao[(string) $linha['id_solicitacao']][] = [
            'id' => (string) $linha['id_arquivo'],
            'mime' => (string) $linha['mime_type'],
            'legenda' => (string) ($linha['legenda'] ?? ''),
        ];
    }

    foreach ($servicos as &$servico) {
        $servico['anexos'] = $anexosPorSolicitacao[$servico['id']] ?? [];
    }
    unset($servico);

    $stmt = $conexao->prepare(
        'SELECT d.id_documento AS id, d.titulo, d.tipo, d.emitido_em, s.protocolo
         FROM documento AS d
         INNER JOIN solicitacao AS s ON s.id_solicitacao = d.id_solicitacao
         WHERE s.id_cliente = ? AND d.disponivel_cliente = 1
         ORDER BY d.emitido_em DESC, d.id_documento DESC
         LIMIT 50'
    );
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $resultado = $stmt->get_result();

    $documentos = [];

    while ($linha = $resultado->fetch_assoc()) {
        $documentos[] = [
            'id' => (string) $linha['id'],
            'titulo' => (string) $linha['titulo'],
            'tipo' => (string) $linha['tipo'],
            'emitido_em' => (string) $linha['emitido_em'],
            'protocolo' => (string) $linha['protocolo']
        ];
    }

    $stmt->close();

    $stmt = $conexao->prepare(
        "SELECT id_notificacao AS id, titulo, mensagem, lida_em, criada_em
         FROM notificacao
         WHERE id_destinatario = ?
           AND canal IN ('SITE', 'APP')
           AND status_envio IN ('ENVIADO', 'ENVIADA')
         ORDER BY criada_em DESC, id_notificacao DESC
         LIMIT 50"
    );
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $resultado = $stmt->get_result();

    $notificacoes = [];

    while ($linha = $resultado->fetch_assoc()) {
        $notificacoes[] = [
            'id' => (string) $linha['id'],
            'titulo' => (string) $linha['titulo'],
            'mensagem' => (string) $linha['mensagem'],
            'lida' => $linha['lida_em'] !== null,
            'criada_em' => (string) $linha['criada_em']
        ];
    }

    $stmt->close();
    $conexao->close();

    responderDashboard(200, [
        'sucesso' => true,
        'csrf' => $csrf,
        'usuario' => [
            'nome' => (string) $usuario['nome'],
            'email' => (string) $usuario['email'],
            'telefone' => (string) $usuario['telefone'],
            'endereco' => $endereco,
            'criado_em' => (string) $usuario['criado_em']
        ],
        'resumo' => $resumo,
        'servicos' => $servicos,
        'documentos' => $documentos,
        'notificacoes' => $notificacoes
    ]);
} catch (Throwable $erro) {
    if (isset($conexao) && $conexao instanceof mysqli) $conexao->close();

    error_log('Falha ao carregar dashboard do cliente: ' . get_class($erro) . ', código ' . $erro->getCode());
    responderDashboard(500, ['sucesso' => false, 'mensagem' => 'Não foi possível carregar o painel agora. Tente novamente.']);
}