<?php

declare(strict_types=1);

const CONFIRMACAO_AUTOMATICA = false;

final class ErroAgendamento extends RuntimeException
{
    public function __construct(
        string $mensagem,
        int $http,
        public string $codigoApi = ''
    ) {
        parent::__construct($mensagem, $http);
    }
}

function consultarAgendamento(
    mysqli $db,
    string $sql,
    array $parametros = []
): mysqli_stmt {
    $stmt = $db->prepare($sql);
    $stmt->execute($parametros);

    return $stmt;
}

function configurarFusoAgendamento(mysqli $db): void
{
    date_default_timezone_set('America/Sao_Paulo');

    consultarAgendamento(
        $db,
        'SET time_zone = ?',
        [(new DateTimeImmutable())->format('P')]
    );
}

function conferirClienteAgendamento(?array $usuario): void
{
    if (!$usuario || (int) $usuario['ativo'] !== 1) {
        throw new ErroAgendamento(
            'Entre na sua conta para continuar.',
            401
        );
    }

    if ($usuario['perfil'] !== 'cliente') {
        throw new ErroAgendamento(
            'Este formulário é exclusivo da área do cliente.',
            403
        );
    }
}

function validarDadosAgendamento(array $entrada): array
{
    $dados = [];

    $campos = [
        'protocolo',
        'tipo_servico',
        'disponibilidade',
        'nome',
        'telefone',
        'endereco',
        'descricao',
        'urgente'
    ];

    foreach ($campos as $campo) {
        $valor = $entrada[$campo] ?? null;

        if (
            !is_string($valor)
            || !mb_check_encoding($valor, 'UTF-8')
            || str_contains($valor, "\0")
        ) {
            throw new ErroAgendamento(
                'Preencha todos os campos com valores válidos.',
                422
            );
        }

        $dados[$campo] = trim($valor);
    }

    if (!preg_match('/^SRV-[a-f0-9]{24}$/D', $dados['protocolo'])) {
        throw new ErroAgendamento(
            'Identificador inválido. Consulte Meus Pedidos antes de tentar novamente.',
            403
        );
    }

    if (!preg_match('/^[A-Z][A-Z0-9_]{0,59}$/D', $dados['tipo_servico'])) {
        throw new ErroAgendamento(
            'Selecione um serviço válido.',
            422
        );
    }

    $id = filter_var(
        $dados['disponibilidade'],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($id === false) {
        throw new ErroAgendamento(
            'Selecione um horário disponível.',
            422
        );
    }

    $dados['disponibilidade'] = (string) $id;

    $dados['nome'] = preg_replace(
        '/\s+/u',
        ' ',
        $dados['nome']
    );

    if (
        mb_strlen($dados['nome']) > 150
        || !preg_match('/^\S+(?: +\S+)+$/uD', $dados['nome'])
        || preg_match('/[\p{C}<>]/u', $dados['nome'])
    ) {
        throw new ErroAgendamento(
            'Informe nome e sobrenome, com no máximo 150 caracteres.',
            422
        );
    }

    if (!preg_match('/^[0-9() .-]+$/D', $dados['telefone'])) {
        throw new ErroAgendamento(
            'Informe o telefone com DDD, sem código do país.',
            422
        );
    }

    $dados['telefone'] = preg_replace(
        '/[^0-9]/',
        '',
        $dados['telefone']
    );

    if (!preg_match('/^[1-9][0-9]{9,10}$/D', $dados['telefone'])) {
        throw new ErroAgendamento(
            'Informe um telefone com DDD, com 10 ou 11 dígitos.',
            422
        );
    }

    foreach (['endereco' => 500, 'descricao' => 2000] as $campo => $limite) {
        if (
            mb_strlen($dados[$campo]) < 10
            || mb_strlen($dados[$campo]) > $limite
        ) {
            throw new ErroAgendamento(
                "O campo {$campo} deve ter de 10 a {$limite} caracteres.",
                422
            );
        }
    }

    if (!in_array($dados['urgente'], ['0', '1'], true)) {
        throw new ErroAgendamento(
            'Informe uma opção de urgência válida.',
            422
        );
    }

    if (
        isset($entrada['tecnico'])
        && (
            !is_string($entrada['tecnico'])
            || trim($entrada['tecnico']) !== ''
        )
    ) {
        throw new ErroAgendamento(
            'O técnico será atribuído pela equipe após analisar o pedido.',
            422
        );
    }

    return $dados;
}

function resultadoAgendamento(
    string $protocolo,
    bool $repetido
): array {
    return [
        'sucesso' => true,
        'protocolo' => $protocolo,
        'status' => CONFIRMACAO_AUTOMATICA
            ? 'AGENDADO'
            : 'SOLICITADO',
        'mensagem' => CONFIRMACAO_AUTOMATICA
            ? 'Agendamento confirmado com sucesso.'
            : 'Pedido recebido. O horário está pendente de confirmação da equipe.',
        'repetido' => $repetido
    ];
}

// Grava todos os dados juntos ou desfaz tudo se ocorrer uma falha.
function executarAgendamento(
    mysqli $conexao,
    array $usuario,
    array $dados
): array {
    $dados = validarDadosAgendamento($dados);

    configurarFusoAgendamento($conexao);
    $conexao->begin_transaction();

    try {
        $cliente = consultarAgendamento(
            $conexao,
            'SELECT id_usuario, nome, email, telefone, perfil, ativo
             FROM usuario
             WHERE id_usuario = ?
             FOR UPDATE',
            [$usuario['id_usuario']]
        )->get_result()->fetch_assoc();

        conferirClienteAgendamento($cliente);

        $idCliente = $cliente['id_usuario'];

        // Evita criar outro pedido quando o mesmo envio é repetido.
        $existente = consultarAgendamento(
            $conexao,
            'SELECT
                s.*,
                t.codigo AS tipo,
                e.endereco_informado,
                (
                    SELECT a.id_disponibilidade
                    FROM agendamento a
                    WHERE a.id_solicitacao = s.id_solicitacao
                    ORDER BY a.id_agendamento
                    LIMIT 1
                ) AS horario_original
             FROM solicitacao s
             JOIN tipo_servico t
                ON t.id_tipo_servico = s.id_tipo_servico
             LEFT JOIN endereco_atendimento e
                ON e.id_solicitacao = s.id_solicitacao
             WHERE s.protocolo = ?
             FOR UPDATE',
            [$dados['protocolo']]
        )->get_result()->fetch_assoc();

        if ($existente) {
            if ((string) $existente['id_cliente'] !== (string) $idCliente) {
                throw new ErroAgendamento(
                    'Este identificador não pertence à sua conta.',
                    403
                );
            }

            $comparacoes = [
                'tipo_servico' => 'tipo',
                'disponibilidade' => 'horario_original',
                'nome' => 'nome_contato',
                'telefone' => 'telefone_contato',
                'endereco' => 'endereco_informado',
                'descricao' => 'descricao_problema',
                'urgente' => 'urgente'
            ];

            foreach ($comparacoes as $campo => $coluna) {
                if ($dados[$campo] !== (string) $existente[$coluna]) {
                    throw new ErroAgendamento(
                        'Este pedido já foi enviado com outros dados. Consulte Meus Pedidos.',
                        409
                    );
                }
            }

            $conexao->commit();

            return resultadoAgendamento($dados['protocolo'], true);
        }

        $tipo = consultarAgendamento(
            $conexao,
            'SELECT id_tipo_servico
             FROM tipo_servico
             WHERE codigo = ? AND ativo = 1
             FOR UPDATE',
            [$dados['tipo_servico']]
        )->get_result()->fetch_assoc();

        $status = consultarAgendamento(
            $conexao,
            'SELECT id_status
             FROM status_solicitacao
             WHERE codigo = ? AND ativo = 1 AND terminal = 0
             FOR UPDATE',
            [
                CONFIRMACAO_AUTOMATICA
                    ? 'AGENDADO'
                    : 'SOLICITADO'
            ]
        )->get_result()->fetch_assoc();

        if (!$tipo) {
            throw new ErroAgendamento(
                'O serviço selecionado não está disponível.',
                422
            );
        }

        if (!$status) {
            throw new RuntimeException(
                'Status inicial não configurado.'
            );
        }

        // O horário precisa ter sido disponibilizado previamente.
        $horario = consultarAgendamento(
            $conexao,
            "SELECT id_disponibilidade, capacidade
             FROM disponibilidade
             WHERE id_disponibilidade = ?
               AND situacao = 'LIVRE'
               AND capacidade > 0
               AND inicio_previsto > NOW()
               AND inicio_previsto <= DATE_ADD(NOW(), INTERVAL 60 DAY)
               AND fim_previsto > inicio_previsto
             FOR UPDATE",
            [$dados['disponibilidade']]
        )->get_result()->fetch_assoc();

        if (!$horario) {
            throw new ErroAgendamento(
                'Este horário não está mais disponível. Escolha outro.',
                409,
                'HORARIO_INDISPONIVEL'
            );
        }

        // Reservas pendentes também ocupam a capacidade do horário.
        $reservas = consultarAgendamento(
            $conexao,
            "SELECT a.id_agendamento
             FROM agendamento a
             JOIN solicitacao s
                ON s.id_solicitacao = a.id_solicitacao
             JOIN status_solicitacao st
                ON st.id_status = s.id_status_atual
             WHERE a.id_disponibilidade = ?
               AND a.status NOT IN ('CANCELADO', 'REAGENDADO')
               AND st.terminal = 0
             FOR UPDATE",
            [$dados['disponibilidade']]
        )->get_result()->num_rows;

        if ($reservas >= (int) $horario['capacidade']) {
            throw new ErroAgendamento(
                'Este horário acabou de ser reservado. Escolha outro.',
                409,
                'HORARIO_INDISPONIVEL'
            );
        }

        consultarAgendamento(
            $conexao,
            "INSERT INTO solicitacao (
                protocolo,
                id_cliente,
                id_criador,
                id_tipo_servico,
                id_status_atual,
                nome_contato,
                telefone_contato,
                email_contato,
                descricao_problema,
                urgente,
                origem
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'SITE')",
            [
                $dados['protocolo'],
                $idCliente,
                $idCliente,
                $tipo['id_tipo_servico'],
                $status['id_status'],
                $dados['nome'],
                $dados['telefone'],
                $cliente['email'],
                $dados['descricao'],
                $dados['urgente']
            ]
        );

        $idSolicitacao = $conexao->insert_id;

        consultarAgendamento(
            $conexao,
            'INSERT INTO endereco_atendimento (
                id_solicitacao,
                endereco_informado
             ) VALUES (?, ?)',
            [
                $idSolicitacao,
                $dados['endereco']
            ]
        );

        consultarAgendamento(
            $conexao,
            'INSERT INTO agendamento (
                id_solicitacao,
                id_disponibilidade,
                status
             ) VALUES (?, ?, ?)',
            [
                $idSolicitacao,
                $dados['disponibilidade'],
                CONFIRMACAO_AUTOMATICA
                    ? 'CONFIRMADO'
                    : 'PENDENTE'
            ]
        );

        $idAgendamento = $conexao->insert_id;

        consultarAgendamento(
            $conexao,
            'INSERT INTO historico_status (
                id_solicitacao,
                id_status_anterior,
                id_status_novo,
                id_responsavel,
                observacao
             ) VALUES (?, NULL, ?, ?, ?)',
            [
                $idSolicitacao,
                $status['id_status'],
                $idCliente,
                'Pedido criado pelo cliente no site.'
            ]
        );

        $resposta = resultadoAgendamento(
            $dados['protocolo'],
            false
        );

        consultarAgendamento(
            $conexao,
            "INSERT INTO notificacao (
                id_destinatario,
                id_solicitacao,
                id_agendamento,
                tipo_evento,
                titulo,
                mensagem,
                canal,
                status_envio,
                enviada_em
             ) VALUES (
                ?, ?, ?,
                'PEDIDO_RECEBIDO',
                'Pedido recebido',
                ?,
                'SITE',
                'ENVIADO',
                NOW()
             )",
            [
                $idCliente,
                $idSolicitacao,
                $idAgendamento,
                $resposta['mensagem']
                    . ' Protocolo: '
                    . $dados['protocolo']
            ]
        );

        $conexao->commit();

        return $resposta;
    } catch (Throwable $erro) {
        $conexao->rollback();

        throw $erro;
    }
}

function responderAgendamento(int $http, array $dados): never
{
    http_response_code($http);

    echo json_encode(
        $dados,
        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );

    exit;
}

if (!defined('HVAC_AGENDAMENTO_TESTE')) {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');

    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');

    try {
        $metodo = $_SERVER['REQUEST_METHOD'] ?? '';

        if (!in_array($metodo, ['GET', 'POST'], true)) {
            header('Allow: GET, POST');

            throw new ErroAgendamento(
                'Método não permitido.',
                405
            );
        }

        if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 32768) {
            throw new ErroAgendamento(
                'Os dados enviados ultrapassam o limite permitido.',
                413
            );
        }

        $sessaoIniciada = session_start([
            'use_strict_mode' => true,
            'use_only_cookies' => true,
            'cookie_httponly' => true,
            'cookie_secure' => !empty($_SERVER['HTTPS'])
                && $_SERVER['HTTPS'] !== 'off',
            'cookie_samesite' => 'Lax',
            'cookie_path' => '/'
        ]);

        if (!$sessaoIniciada) {
            throw new RuntimeException(
                'Não foi possível iniciar a sessão.'
            );
        }

        $id = filter_var(
            $_SESSION['usuario_id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if (!$id) {
            throw new ErroAgendamento(
                'Entre na sua conta para continuar.',
                401
            );
        }

        mysqli_report(
            MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT
        );

        require __DIR__ . '/conexao.php';

        configurarFusoAgendamento($conexao);

        $usuario = consultarAgendamento(
            $conexao,
            'SELECT id_usuario, nome, email, telefone, perfil, ativo
             FROM usuario
             WHERE id_usuario = ?',
            [$id]
        )->get_result()->fetch_assoc();

        conferirClienteAgendamento($usuario);

        $_SESSION['csrf_agendamento'] ??= bin2hex(
            random_bytes(32)
        );

        $rascunhos = $_SESSION['agendamento_rascunhos'] ?? [];

        $rascunhos = array_filter(
            $rascunhos,
            fn(array $item): bool =>
                $item['criado'] > time() - 7200
        );

        if ($metodo === 'GET') {
            $protocolo = 'SRV-' . bin2hex(random_bytes(12));

            $rascunhos[$protocolo] = [
                'criado' => time(),
                'fingerprint' => null,
                'usuario' => $id
            ];

            $_SESSION['agendamento_rascunhos'] = array_slice(
                $rascunhos,
                -20,
                null,
                true
            );

            $csrf = $_SESSION['csrf_agendamento'];

            if (!session_write_close()) {
                throw new RuntimeException(
                    'Falha ao salvar a sessão.'
                );
            }

            $servicos = consultarAgendamento(
                $conexao,
                'SELECT codigo, nome
                 FROM tipo_servico
                 WHERE ativo = 1
                 ORDER BY ordem, nome'
            )->get_result()->fetch_all(MYSQLI_ASSOC);

            $horarios = consultarAgendamento(
                $conexao,
                "SELECT
                    d.id_disponibilidade AS id,
                    d.inicio_previsto AS inicio,
                    d.fim_previsto AS fim
                 FROM disponibilidade d
                 WHERE d.situacao = 'LIVRE'
                   AND d.inicio_previsto > NOW()
                   AND d.inicio_previsto <= DATE_ADD(NOW(), INTERVAL 60 DAY)
                   AND d.fim_previsto > d.inicio_previsto
                   AND d.capacidade > (
                        SELECT COUNT(*)
                        FROM agendamento a
                        JOIN solicitacao s
                            ON s.id_solicitacao = a.id_solicitacao
                        JOIN status_solicitacao st
                            ON st.id_status = s.id_status_atual
                        WHERE a.id_disponibilidade = d.id_disponibilidade
                          AND a.status NOT IN ('CANCELADO', 'REAGENDADO')
                          AND st.terminal = 0
                   )
                 ORDER BY d.inicio_previsto, d.id_disponibilidade"
            )->get_result()->fetch_all(MYSQLI_ASSOC);

            foreach ($horarios as &$horario) {
                $horario['id'] = (string) $horario['id'];
            }

            unset($horario);

            responderAgendamento(200, [
                'sucesso' => true,
                'csrf' => $csrf,
                'protocolo' => $protocolo,
                'hoje' => date('Y-m-d'),
                'usuario' => [
                    'id' => (string) $id,
                    'nome' => $usuario['nome'],
                    'telefone' => $usuario['telefone']
                ],
                'servicos' => $servicos,
                'horarios' => $horarios,
                'confirmacao_automatica' => CONFIRMACAO_AUTOMATICA
            ]);
        }

        if (
            !is_string($_POST['csrf'] ?? null)
            || !hash_equals(
                $_SESSION['csrf_agendamento'],
                $_POST['csrf']
            )
        ) {
            throw new ErroAgendamento(
                'Sessão expirada. Entre novamente e consulte Meus Pedidos antes de reenviar.',
                403
            );
        }

        foreach ($_FILES as $arquivo) {
            if (($arquivo['error'] ?? null) !== UPLOAD_ERR_NO_FILE) {
                throw new ErroAgendamento(
                    'Anexos ainda não estão disponíveis nesta etapa. Envie o pedido sem arquivos.',
                    422
                );
            }
        }

        $dados = validarDadosAgendamento($_POST);

        $rascunho = $rascunhos[$dados['protocolo']] ?? null;

        if (
            !$rascunho
            || (int) $rascunho['usuario'] !== $id
        ) {
            throw new ErroAgendamento(
                'Este envio expirou ou não pertence à sua sessão. Consulte Meus Pedidos antes de iniciar outro.',
                403
            );
        }

        $fingerprint = hash(
            'sha256',
            json_encode(
                $dados,
                JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            )
        );

        if (
            $rascunho['fingerprint'] !== null
            && !hash_equals(
                $rascunho['fingerprint'],
                $fingerprint
            )
        ) {
            throw new ErroAgendamento(
                'Os dados deste envio foram alterados. Consulte Meus Pedidos antes de iniciar outro.',
                409
            );
        }

        $rascunhos[$dados['protocolo']]['fingerprint'] = $fingerprint;

        $_SESSION['agendamento_rascunhos'] = $rascunhos;

        // Salvar antes da gravação permite repetir o envio sem duplicar.
        if (!session_write_close()) {
            throw new RuntimeException(
                'Falha ao salvar a intenção de envio.'
            );
        }

        $resultado = executarAgendamento(
            $conexao,
            $usuario,
            $dados
        );

        responderAgendamento(
            $resultado['repetido'] ? 200 : 201,
            $resultado
        );
    } catch (Throwable $erro) {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        if ($erro instanceof ErroAgendamento) {
            $resposta = [
                'sucesso' => false,
                'mensagem' => $erro->getMessage()
            ];

            if ($erro->codigoApi !== '') {
                $resposta['codigo'] = $erro->codigoApi;
            }

            responderAgendamento(
                $erro->getCode(),
                $resposta
            );
        }

        error_log(
            'Falha no agendamento: '
            . get_class($erro)
            . ', código '
            . $erro->getCode()
        );

        responderAgendamento(500, [
            'sucesso' => false,
            'mensagem' =>
                'Não foi possível confirmar o resultado. Consulte Meus Pedidos ou repita o mesmo envio.'
        ]);
    }
}