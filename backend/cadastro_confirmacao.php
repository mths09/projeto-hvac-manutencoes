<?php

declare(strict_types=1);

require_once __DIR__ . '/telefone_unico.php';
require_once __DIR__ . '/validar_endereco.php';

final class ErroCadastroConfirmacao extends RuntimeException
{
    public function __construct(
        public int $http,
        string $mensagem,
        public array $erros = []
    ) {
        parent::__construct($mensagem);
    }
}

function urlConfirmacaoCadastro(string $appUrl, string $token): string
{
    $partes = parse_url(trim($appUrl));
    if (
        !is_array($partes) ||
        !in_array(strtolower((string) ($partes['scheme'] ?? '')), ['http', 'https'], true) ||
        empty($partes['host'])
    ) {
        throw new ErroCadastroConfirmacao(503, 'A URL pública do sistema não está configurada.');
    }

    return rtrim($appUrl, '/')
        . '/frontend/src/paginas/confirmar-cadastro.html?token='
        . rawurlencode($token);
}

function armazenarCadastroPendenteEEnviar(
    mysqli $conexao,
    array $dados,
    array $endereco,
    string $senhaHash,
    string $ipHash,
    string $userAgentHash
): array {
    require_once __DIR__ . '/email_notificacao.php';

    $config = configEmailHvac();
    $appUrl = trim((string) ($config['app_url'] ?? ''));
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $dataAceite = date('Y-m-d H:i:s');
    $payload = [
        'nome' => $dados['nome'],
        'email' => $dados['email'],
        'telefone' => $dados['telefone'],
        'data_nascimento' => $dados['data_nascimento'],
        'senha_hash' => $senhaHash,
        'endereco' => $endereco,
        'ip_hash' => $ipHash,
        'user_agent_hash' => $userAgentHash,
        'aceito_em' => $dataAceite,
    ];
    $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

    $conexao->begin_transaction();
    try {
        $conexao->query('DELETE FROM cadastro_pendente WHERE expira_em <= NOW()');

        $stmt = $conexao->prepare(
            'SELECT enviado_em FROM cadastro_pendente
             WHERE email = ? AND expira_em > NOW() LIMIT 1 FOR UPDATE'
        );
        $stmt->bind_param('s', $dados['email']);
        $stmt->execute();
        $pendenteExistente = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!empty($pendenteExistente['enviado_em'])) {
            $stmt = $conexao->prepare(
                'SELECT enviado_em >= DATE_SUB(NOW(), INTERVAL 1 MINUTE) AS recente
                 FROM cadastro_pendente WHERE email = ? LIMIT 1'
            );
            $stmt->bind_param('s', $dados['email']);
            $stmt->execute();
            $recente = (int) ($stmt->get_result()->fetch_assoc()['recente'] ?? 0) === 1;
            $stmt->close();
            if ($recente) {
                $conexao->commit();
                return ['enviado' => true, 'ja_enviado' => true];
            }
        }

        $stmt = $conexao->prepare(
            'SELECT COUNT(*) AS total FROM cadastro_pendente
             WHERE ip_hash = ? AND criada_em >= DATE_SUB(NOW(), INTERVAL 1 HOUR)'
        );
        $stmt->bind_param('s', $ipHash);
        $stmt->execute();
        $tentativasIp = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
        $stmt->close();
        if ($tentativasIp >= 10) {
            throw new ErroCadastroConfirmacao(429, 'Muitas solicitações de cadastro. Tente novamente mais tarde.');
        }

        $stmt = $conexao->prepare(
            'SELECT 1 FROM cadastro_pendente
             WHERE telefone = ? AND email <> ? AND expira_em > NOW() LIMIT 1 FOR UPDATE'
        );
        $stmt->bind_param('ss', $dados['telefone'], $dados['email']);
        $stmt->execute();
        $telefonePendente = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        if ($telefonePendente) {
            throw new ErroCadastroConfirmacao(409, 'Este telefone já está em confirmação para outra conta.', [
                'telefone' => 'Use outro telefone para cadastrar uma nova conta.',
            ]);
        }

        $stmt = $conexao->prepare(
            'INSERT INTO cadastro_pendente
                (email, telefone, dados_json, token_hash, ip_hash, expira_em, enviado_em)
             VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR), NULL)
             ON DUPLICATE KEY UPDATE
                telefone = VALUES(telefone), dados_json = VALUES(dados_json),
                token_hash = VALUES(token_hash), ip_hash = VALUES(ip_hash),
                criada_em = NOW(), expira_em = VALUES(expira_em), enviado_em = NULL'
        );
        $stmt->bind_param(
            'sssss',
            $dados['email'],
            $dados['telefone'],
            $payloadJson,
            $tokenHash,
            $ipHash
        );
        $stmt->execute();
        $stmt->close();
        $conexao->commit();
    } catch (Throwable $erro) {
        try {
            $conexao->rollback();
        } catch (Throwable $erroRollback) {
            error_log('Falha no rollback do cadastro pendente: ' . get_class($erroRollback));
        }
        throw $erro;
    }

    $url = urlConfirmacaoCadastro($appUrl, $token);

    try {
        $conteudoEmail = montarEmailConfirmacaoCadastroHvac(
            (string) $dados['nome'],
            $url
        );

        $resultadoEmail = enviarEmailHvac(
            $dados['email'],
            'Confirme seu cadastro - HVAC Lex Company',
            $conteudoEmail['html'],
            true,
            $conteudoEmail['texto']
        );
    } catch (Throwable $erro) {
        error_log(
            'Falha ao enviar confirmação de cadastro: '
            . get_class($erro)
        );

        $resultadoEmail = ['enviado' => false];
    }

    if (empty($resultadoEmail['enviado'])) {
        throw new ErroCadastroConfirmacao(503, 'Não foi possível enviar o e-mail de confirmação. Confira a configuração SMTP e tente novamente.');
    }

    $stmt = $conexao->prepare(
        'UPDATE cadastro_pendente SET enviado_em = NOW()
         WHERE email = ? AND token_hash = ?'
    );
    $stmt->bind_param('ss', $dados['email'], $tokenHash);
    $stmt->execute();
    $stmt->close();

    return ['enviado' => true, 'ja_enviado' => false];
}

function confirmarCadastroPendente(mysqli $conexao, string $token): array
{
    if (!preg_match('/^[a-f0-9]{64}$/D', $token)) {
        return ['status' => 422, 'mensagem' => 'O link de confirmação é inválido.'];
    }

    $tokenHash = hash('sha256', $token);
    $conexao->begin_transaction();

    try {
        $stmt = $conexao->prepare(
            'SELECT id_cadastro_pendente, dados_json
             FROM cadastro_pendente
             WHERE token_hash = ? AND expira_em > NOW()
             LIMIT 1 FOR UPDATE'
        );
        $stmt->bind_param('s', $tokenHash);
        $stmt->execute();
        $pendente = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$pendente) {
            throw new ErroCadastroConfirmacao(410, 'Este link expirou ou já foi utilizado. Faça o cadastro novamente para receber outro link.');
        }

        $dados = json_decode((string) $pendente['dados_json'], true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($dados) || !is_array($dados['endereco'] ?? null)) {
            throw new RuntimeException('Dados temporários de cadastro inválidos.');
        }
        foreach (['nome', 'email', 'telefone', 'data_nascimento', 'senha_hash', 'ip_hash', 'user_agent_hash', 'aceito_em'] as $campo) {
            if (!is_string($dados[$campo] ?? null) || $dados[$campo] === '') {
                throw new RuntimeException('Campo de cadastro temporário ausente.');
            }
        }
        if (!filter_var($dados['email'], FILTER_VALIDATE_EMAIL) || !preg_match('/^[a-f0-9]{64}$/D', $dados['ip_hash']) || !preg_match('/^[a-f0-9]{64}$/D', $dados['user_agent_hash'])) {
            throw new RuntimeException('Dados de confirmação inválidos.');
        }

        $resultadoEndereco = validarEndereco($dados['endereco']);
        if ($resultadoEndereco['erros'] !== []) {
            throw new RuntimeException('Endereço temporário inválido.');
        }
        $endereco = $resultadoEndereco['dados'];

        $stmt = $conexao->prepare('SELECT 1 FROM usuario WHERE email = ? LIMIT 1 FOR UPDATE');
        $stmt->bind_param('s', $dados['email']);
        $stmt->execute();
        $emailCadastrado = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        if ($emailCadastrado) {
            throw new ErroCadastroConfirmacao(409, 'Este e-mail já está associado a uma conta.');
        }
        if (telefoneJaCadastrado($conexao, $dados['telefone'])) {
            throw new ErroCadastroConfirmacao(409, 'Este telefone já está cadastrado.', [
                'telefone' => 'Use outro telefone para criar uma conta.',
            ]);
        }

        $perfil = 'cliente';
        $stmt = $conexao->prepare(
            'INSERT INTO usuario (nome, email, telefone, data_nascimento, senha_hash, perfil)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param(
            'ssssss',
            $dados['nome'],
            $dados['email'],
            $dados['telefone'],
            $dados['data_nascimento'],
            $dados['senha_hash'],
            $perfil
        );
        $stmt->execute();
        $idUsuario = $conexao->insert_id;
        $stmt->close();

        $enderecoInformado = montarEnderecoInformado($endereco);
        $complemento = $endereco['complemento'] === '' ? null : $endereco['complemento'];
        $principal = 1;
        $ativo = 1;
        $stmt = $conexao->prepare(
            'INSERT INTO endereco_usuario
                (id_usuario, endereco_informado, cep, logradouro, numero, complemento,
                 bairro, cidade, uf, principal, ativo)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param(
            'issssssssii',
            $idUsuario,
            $enderecoInformado,
            $endereco['cep'],
            $endereco['logradouro'],
            $endereco['numero'],
            $complemento,
            $endereco['bairro'],
            $endereco['cidade'],
            $endereco['uf'],
            $principal,
            $ativo
        );
        $stmt->execute();
        $stmt->close();

        $versaoPolitica = '1.0';
        $stmt = $conexao->prepare(
            'INSERT INTO aceite_politica
                (id_usuario, versao, ip_hash, user_agent_hash, aceito_em)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->bind_param(
            'issss',
            $idUsuario,
            $versaoPolitica,
            $dados['ip_hash'],
            $dados['user_agent_hash'],
            $dados['aceito_em']
        );
        $stmt->execute();
        $stmt->close();

        $idPendente = (int) $pendente['id_cadastro_pendente'];
        $stmt = $conexao->prepare('DELETE FROM cadastro_pendente WHERE id_cadastro_pendente = ?');
        $stmt->bind_param('i', $idPendente);
        $stmt->execute();
        if ($stmt->affected_rows !== 1) {
            $stmt->close();
            throw new RuntimeException('O link de confirmação já foi utilizado.');
        }
        $stmt->close();

        $conexao->commit();
        return ['status' => 200, 'mensagem' => 'E-mail confirmado e cadastro concluído. Agora você já pode entrar na sua conta.'];
    } catch (ErroCadastroConfirmacao $erro) {
        try {
            $conexao->rollback();
        } catch (Throwable) {
        }
        return ['status' => $erro->http, 'mensagem' => $erro->getMessage(), 'erros' => $erro->erros];
    } catch (mysqli_sql_exception $erro) {
        try {
            $conexao->rollback();
        } catch (Throwable) {
        }
        if ((int) $erro->getCode() === 1062 && str_contains($erro->getMessage(), 'un_usuario_telefone_normalizado')) {
            return ['status' => 409, 'mensagem' => 'Este telefone já está cadastrado.', 'erros' => ['telefone' => 'Use outro telefone para criar uma conta.']];
        }
        if ((int) $erro->getCode() === 1062) {
            return ['status' => 409, 'mensagem' => 'Este e-mail já está associado a uma conta.'];
        }
        error_log('Falha no banco ao confirmar cadastro: código ' . $erro->getCode());
        return ['status' => 503, 'mensagem' => 'Não foi possível concluir o cadastro. Tente novamente.'];
    } catch (Throwable $erro) {
        try {
            $conexao->rollback();
        } catch (Throwable) {
        }
        error_log('Falha interna ao confirmar cadastro: class=' . get_class($erro) . ' code=' . $erro->getCode());
        return ['status' => 500, 'mensagem' => 'Não foi possível confirmar o cadastro. Tente novamente.'];
    }
}