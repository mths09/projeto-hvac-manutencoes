<?php

declare(strict_types=1);

// Os erros internos ficam no log do PHP.
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// O cadastro.js espera uma resposta JSON.
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function responderCadastro(
    int $status,
    string $mensagem,
    array $erros = []
): void {
    http_response_code($status);

    echo json_encode(
        [
            'sucesso' => $status === 201,
            'mensagem' => $mensagem,
            'erros' => (object) $erros
        ],
        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );

    exit;
}

// Aceitar somente o envio por POST.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');

    responderCadastro(
        405,
        'Método não permitido. Envie o formulário de cadastro.'
    );
}

// Limitar o tamanho dos dados recebidos.
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 16384) {
    responderCadastro(
        413,
        'Os dados enviados ultrapassam o limite do cadastro.'
    );
}

try {
    require_once __DIR__ . '/validar_cadastro.php';
    require_once __DIR__. '/validar_endereco.php';

    $resultadoCadastro = validarCadastro($_POST);
    $resultadoEndereco = validarEndereco($_POST);

    $erros = $resultadoCadastro['erros'] + $resultadoEndereco['erros'];

    if($erros) {
        responderCadastro(
            422,
            implode(' ', $erros),
            $erros
        );
    }

    $dados = $resultadoCadastro['dados']; 
    $endereco = $resultadoEndereco['dados']; 

    // O perfil é definido no servidor.
    // Um visitante nunca pode se cadastrar como administrador.
    $perfil = 'cliente';

    // salva somente hash da senha
    $senhaHash = password_hash(
        $dados['senha'],
        PASSWORD_BCRYPT,
        ['cost' => 12]
    );

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    require_once __DIR__ . '/conexao.php';
    $conexao->begin_transaction();

try {
    // ------ usuario ------
    $sql = '
        INSERT INTO usuario (
            nome,
            email,
            telefone,
            data_nascimento,
            senha_hash,
            perfil
        )
        VALUES (?, ?, ?, ?, ?, ?)
    ';
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param(
        'ssssss',
        $dados['nome'],
        $dados['email'],
        $dados['telefone'],
        $dados['data_nascimento'],
        $senhaHash,
        $perfil
    );
    $stmt->execute();
    $usuarioId = $conexao->insert_id;
    $stmt->close();

    // ------ endereco -------
    $complemento = $endereco['complemento'] === '' ? null : $endereco['complemento']; // adicionar "null" ao complemento
    $enderecoInformado = montarEnderecoInformado($endereco);

    $principal = 1;
    $ativo = 1;

    $sql = '
        INSERT INTO endereco_usuario (
            id_usuario,
            endereco_informado,
            cep,
            logradouro,
            numero,
            complemento,
            bairro,
            cidade,
            uf,
            principal,
            ativo
        )
        VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ';
        $stmt = $conexao->prepare($sql);
    $stmt->bind_param(
        'issssssssii',
        $usuarioId,
        $enderecoInformado, 
        $endereco['cep'],
        $endereco['logradouro'],
        $endereco['numero'],
        $complemento,
        $endereco['bairro'],
        $endereco['cidade'],
        $endereco['uf'],
        $principal,
        $ativo,
    );
    $stmt->execute();
    $stmt->close();

    $conexao->commit();

} catch (Throwable $erroInterno) {
    try {
        $conexao->rollback();
    } catch (Throwable $erroRollBack) {
        error_log('Falha no rollback do cadastro: ' . get_class($erroRollBack));
    }
    throw $erroInterno;
}

    responderCadastro(
        201,
        'Cadastro realizado com sucesso!'
    );

} catch (mysqli_sql_exception $erro) {
    // O banco já possui uma restrição UNIQUE para o e-mail.
    if ((int) $erro->getCode() === 1062) {
        responderCadastro(
            409,
            'Este e-mail já está cadastrado.',
            [
                'email' => 'Use outro e-mail para cadastrar uma nova conta.'
            ]
        );
    }
    // Não registrar senha ou valores enviados no formulário.
    error_log(
        'Falha no banco durante cadastro. Código: ' . $erro->getCode()
    );
    responderCadastro(
        503,
        'Não foi possível acessar o banco. Tente novamente mais tarde.'
    );

} catch (Throwable $erro) {
    error_log(sprintf(
        'Falha interna no cadastro: class=%s code=%s file=%s line=%d',
        get_class($erro),
        (string) $erro->getCode(),
        $erro->getFile(),
        $erro->getLine()
    ));

    responderCadastro(
        500,
        'Não foi possível concluir o cadastro. Tente novamente.'
    );
}