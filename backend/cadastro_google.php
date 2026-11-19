<?php

declare(strict_types=1);
require_once __DIR__ . '/google_auth.php';

function responderCadastroGoogle(int $status, array $dados): never
{
    $dados['sucesso'] = $status >= 200 && $status < 300;
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

try {
    iniciarSessaoGoogle();
    $pendente = $_SESSION['cadastro_google'] ?? null;
    if (!is_array($pendente) || (int) ($pendente['expira_em'] ?? 0) < time()) {
        unset($_SESSION['cadastro_google'], $_SESSION['csrf_cadastro_google']);
        responderCadastroGoogle(410, ['mensagem' => 'A etapa do Google expirou. Volte ao login e tente novamente.']);
    }

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
        responderCadastroGoogle(200, [
            'google' => [
                'nome' => (string) ($pendente['nome'] ?? ''),
                'email' => (string) $pendente['email'],
            ],
            'csrf' => (string) ($_SESSION['csrf_cadastro_google'] ?? ''),
        ]);
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: GET, POST');
        responderCadastroGoogle(405, ['mensagem' => 'Método não permitido.']);
    }
    if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 16384) {
        responderCadastroGoogle(413, ['mensagem' => 'Os dados enviados ultrapassam o limite do cadastro.']);
    }
    $csrfSessao = (string) ($_SESSION['csrf_cadastro_google'] ?? '');
    $csrfEnviado = $_POST['csrf'] ?? null;
    if ($csrfSessao === '' || !is_string($csrfEnviado) || !hash_equals($csrfSessao, $csrfEnviado)) {
        responderCadastroGoogle(403, ['mensagem' => 'A sessão expirou. Atualize a página e tente novamente.']);
    }

    require_once __DIR__ . '/validar_cadastro.php';
    require_once __DIR__ . '/validar_endereco.php';
    require_once __DIR__ . '/telefone_unico.php';
    $nomeGoogle = trim((string) ($pendente['nome'] ?? ''));
    $partesNome = preg_split('/\s+/u', $nomeGoogle, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $primeiroNome = trim((string) ($_POST['nome'] ?? ($partesNome[0] ?? '')));
    $sobrenome = trim((string) ($_POST['sobrenome'] ?? (count($partesNome) > 1 ? implode(' ', array_slice($partesNome, 1)) : '')));
    $senhaAleatoria = bin2hex(random_bytes(32));
    $entradaCadastro = [
        'nome' => $primeiroNome,
        'sobrenome' => $sobrenome,
        'email' => (string) $pendente['email'],
        'telefone' => $_POST['telefone'] ?? '',
        'data_nascimento' => $_POST['data_nascimento'] ?? '',
        'senha' => $senhaAleatoria,
        'confirmar_senha' => $senhaAleatoria,
    ];
    $resultadoCadastro = validarCadastro($entradaCadastro);
    $resultadoEndereco = validarEndereco($_POST);
    $erros = $resultadoCadastro['erros'] + $resultadoEndereco['erros'];
    if ($erros !== []) {
        responderCadastroGoogle(422, ['mensagem' => implode(' ', $erros), 'erros' => (object) $erros]);
    }
    if (($_POST['aceite_privacidade'] ?? null) !== '1') {
        responderCadastroGoogle(422, ['mensagem' => 'É necessário aceitar a Política de Privacidade.']);
    }

    $dados = $resultadoCadastro['dados'];
    $endereco = $resultadoEndereco['dados'];
    $senhaHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT, ['cost' => 12]);
    if (!is_string($senhaHash)) {
        throw new RuntimeException('Não foi possível proteger a conta.');
    }
    require_once __DIR__ . '/conexao.php';
    if (telefoneJaCadastrado($conexao, $dados['telefone'])) {
        responderCadastroGoogle(409, ['mensagem' => 'Este telefone já está cadastrado.', 'erros' => (object) ['telefone' => 'Use outro telefone.']]);
    }
    $ipHash = hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'desconhecido'));
    $userAgentHash = hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? 'desconhecido'));
    $aceitoEm = date('Y-m-d H:i:s');
    $versaoPolitica = '1.0';
    $perfil = 'cliente';

    $conexao->begin_transaction();
    try {
        $stmt = $conexao->prepare('SELECT 1 FROM usuario WHERE email = ? LIMIT 1 FOR UPDATE');
        $stmt->bind_param('s', $dados['email']);
        $stmt->execute();
        $emailExistente = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        if ($emailExistente) {
            throw new DomainException('Este e-mail já está associado a uma conta. Entre usando essa conta.');
        }
        if (telefoneJaCadastrado($conexao, $dados['telefone'])) {
            throw new LengthException('Este telefone já está cadastrado.');
        }

        $stmt = $conexao->prepare('INSERT INTO usuario (nome,email,senha_hash,telefone,data_nascimento,perfil,google_sub) VALUES (?,?,?,?,?,?,?)');
        $stmt->bind_param('sssssss', $dados['nome'], $dados['email'], $senhaHash, $dados['telefone'], $dados['data_nascimento'], $perfil, $pendente['sub']);
        $stmt->execute();
        $idUsuario = (int) $conexao->insert_id;
        $stmt->close();

        $enderecoInformado = montarEnderecoInformado($endereco);
        $complemento = $endereco['complemento'] === '' ? null : $endereco['complemento'];
        $principal = 1;
        $ativo = 1;
        $stmt = $conexao->prepare('INSERT INTO endereco_usuario (id_usuario,endereco_informado,cep,logradouro,numero,complemento,bairro,cidade,uf,principal,ativo) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->bind_param('issssssssii', $idUsuario, $enderecoInformado, $endereco['cep'], $endereco['logradouro'], $endereco['numero'], $complemento, $endereco['bairro'], $endereco['cidade'], $endereco['uf'], $principal, $ativo);
        $stmt->execute();
        $stmt->close();

        $stmt = $conexao->prepare('INSERT INTO aceite_politica (id_usuario,versao,ip_hash,user_agent_hash,aceito_em) VALUES (?,?,?,?,?)');
        $stmt->bind_param('issss', $idUsuario, $versaoPolitica, $ipHash, $userAgentHash, $aceitoEm);
        $stmt->execute();
        $stmt->close();
        $conexao->commit();
    } catch (Throwable $erro) {
        $conexao->rollback();
        throw $erro;
    }

    $usuario = ['id_usuario' => $idUsuario, 'nome' => $dados['nome'], 'email' => $dados['email']];
    unset($_SESSION['cadastro_google'], $_SESSION['csrf_cadastro_google'], $_SESSION['csrf_google'], $_SESSION['nonce_google']);
    estabelecerSessaoGoogle($usuario);
    session_write_close();
    responderCadastroGoogle(201, ['mensagem' => 'Cadastro concluído. Abrindo sua área de cliente...', 'area' => 'cliente', 'destino' => './dashboard.html']);
} catch (DomainException|LengthException $erro) {
    if (isset($conexao) && $conexao instanceof mysqli && $conexao->errno) {
        try { $conexao->rollback(); } catch (Throwable) {}
    }
    responderCadastroGoogle(409, ['mensagem' => $erro->getMessage()]);
} catch (mysqli_sql_exception $erro) {
    if (isset($conexao) && $conexao instanceof mysqli) {
        try { $conexao->rollback(); } catch (Throwable) {}
    }
    if ((int) $erro->getCode() === 1062) {
        responderCadastroGoogle(409, ['mensagem' => 'O e-mail ou telefone já está associado a uma conta.']);
    }
    error_log('Falha no banco durante cadastro Google: código ' . $erro->getCode());
    responderCadastroGoogle(503, ['mensagem' => 'Não foi possível acessar o banco. Tente novamente mais tarde.']);
} catch (Throwable $erro) {
    if (isset($conexao) && $conexao instanceof mysqli) {
        try { $conexao->rollback(); } catch (Throwable) {}
    }
    error_log('Falha no cadastro Google: ' . get_class($erro) . ' código ' . $erro->getCode());
    responderCadastroGoogle(500, ['mensagem' => 'Não foi possível concluir o cadastro Google.']);
}
