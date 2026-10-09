<?php

declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function responderDashboardCliente(int $status, array $dados): void
{
    http_response_code($status);

    echo json_encode(
        $dados,
        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );

    exit;
}

// Este endpoint apenas consulta os dados.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');

    responderDashboardCliente(405, [
        'sucesso' => false,
        'mensagem' => 'Método não permitido.'
    ]);
}

try {
    $https = !empty($_SERVER['HTTPS'])
        && $_SERVER['HTTPS'] !== 'off';

    // Recupera a mesma sessão criada no login.
    $sessaoIniciada = session_start([
        'use_strict_mode' => true,
        'use_only_cookies' => true,
        'cookie_httponly' => true,
        'cookie_secure' => $https,
        'cookie_samesite' => 'Lax',
        'cookie_path' => '/',
        'read_and_close' => true
    ]);

    if (!$sessaoIniciada) {
        throw new RuntimeException('Falha ao recuperar a sessão.');
    }

    $idNaSessao = $_SESSION['usuario_id'] ?? null;

    $idUsuario = is_int($idNaSessao) || is_string($idNaSessao)
        ? (string) $idNaSessao
        : '';

    if (!preg_match('/^[1-9][0-9]{0,18}$/D', $idUsuario)) {
        responderDashboardCliente(401, [
            'sucesso' => false,
            'mensagem' => 'Entre na sua conta para continuar.'
        ]);
    }

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    require_once __DIR__ . '/conexao.php';

    // Confere se a conta da sessão continua ativa.
    $stmt = $conexao->prepare(
        'SELECT id_usuario
         FROM usuario
         WHERE id_usuario = ?
           AND ativo = 1
         LIMIT 1'
    );

    $stmt->bind_param('s', $idUsuario);
    $stmt->execute();

    $usuario = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    if (!$usuario) {
        $conexao->close();

        responderDashboardCliente(401, [
            'sucesso' => false,
            'mensagem' => 'Sua conta não está disponível. Faça login novamente.'
        ]);
    }

    // Busca somente as solicitações do cliente conectado.
    // Cada solicitação aparece uma única vez.
    $stmt = $conexao->prepare(
        'SELECT
            s.id_solicitacao AS id,
            s.protocolo,
            ts.nome AS tipo,
            ss.codigo AS status,
            ss.nome AS status_nome,
            s.descricao_problema AS descricao,
            s.criada_em AS criado_em
         FROM solicitacao AS s
         INNER JOIN tipo_servico AS ts
            ON ts.id_tipo_servico = s.id_tipo_servico
         INNER JOIN status_solicitacao AS ss
            ON ss.id_status = s.id_status_atual
         WHERE s.id_cliente = ?
         ORDER BY s.criada_em DESC, s.id_solicitacao DESC'
    );

    $stmt->bind_param('s', $idUsuario);
    $stmt->execute();

    $resultado = $stmt->get_result();

    $servicos = [];

    $resumo = [
        'total' => 0,
        'andamento' => 0,
        'concluidos' => 0,
        'agendados' => 0
    ];

    while ($linha = $resultado->fetch_assoc()) {
        $servicos[] = [
            'id' => (string) $linha['id'],
            'protocolo' => (string) $linha['protocolo'],
            'tipo' => (string) $linha['tipo'],
            'status' => (string) $linha['status'],
            'status_nome' => (string) $linha['status_nome'],
            'descricao' => (string) ($linha['descricao'] ?? ''),
            'criado_em' => (string) $linha['criado_em']
        ];

        // Todo pedido entra no total.
        $resumo['total']++;

        // Os outros contadores dependem do status atual.
        switch ($linha['status']) {
            case 'EM_ANDAMENTO':
                $resumo['andamento']++;
                break;

            case 'CONCLUIDO':
                $resumo['concluidos']++;
                break;

            case 'AGENDADO':
                $resumo['agendados']++;
                break;
        }
    }

    $stmt->close();

    // Documentos liberados para este cliente.
    $stmt = $conexao->prepare(
        'SELECT
            d.id_documento AS id,
            d.titulo,
            d.tipo,
            s.protocolo,
            d.emitido_em
         FROM documento AS d
         INNER JOIN solicitacao AS s
            ON s.id_solicitacao = d.id_solicitacao
         WHERE s.id_cliente = ?
           AND d.disponivel_cliente = 1
         ORDER BY d.emitido_em DESC, d.id_documento DESC'
    );

    $stmt->bind_param('s', $idUsuario);
    $stmt->execute();

    $resultado = $stmt->get_result();

    $documentos = [];

    while ($linha = $resultado->fetch_assoc()) {
        $documentos[] = [
            'id' => (string) $linha['id'],
            'titulo' => (string) $linha['titulo'],
            'tipo' => (string) $linha['tipo'],
            'protocolo' => (string) $linha['protocolo'],
            'emitido_em' => (string) $linha['emitido_em']
        ];
    }

    $stmt->close();

    // Notificações destinadas ao cliente conectado.
    $stmt = $conexao->prepare(
        'SELECT
            id_notificacao AS id,
            titulo,
            mensagem,
            lida_em,
            criada_em
         FROM notificacao
         WHERE id_destinatario = ?
         ORDER BY criada_em DESC, id_notificacao DESC'
    );

    $stmt->bind_param('s', $idUsuario);
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

    responderDashboardCliente(200, [
        'sucesso' => true,
        'resumo' => $resumo,
        'servicos' => $servicos,
        'documentos' => $documentos,
        'notificacoes' => $notificacoes
    ]);
} catch (Throwable $erro) {
    error_log(
        'Falha ao carregar dashboard do cliente: '
        . get_class($erro)
        . ' - código '
        . $erro->getCode()
    );

    responderDashboardCliente(500, [
        'sucesso' => false,
        'mensagem' => 'Não foi possível carregar seus serviços e notificações. Tente novamente.'
    ]);
}
