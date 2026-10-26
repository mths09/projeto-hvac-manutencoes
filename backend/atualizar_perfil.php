<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function responder(int $status, array $dados) {
    http_response_code($status);

    echo json_encode(
        $dados,
        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );

    exit;
}

require_once __DIR__ . '/validar_endereco.php';

// so aceita o metodo PATCH
if(($_SERVER['REQUEST_METHOD'] ?? '') !== 'PATCH') {
    header('Allow: PATCH');

    responder(405, [
        'sucesso' => false,
        'mensagem' => 'Método não permitido.'
    ]);
}

// descobrir o perfil logado
$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

session_start([
    'use_strict_mode' => true,
    'use_only_cookies' => true,
    'cookie_httponly' => true,
    'cookie_secure' => $https,
    'cookie_samesite' => 'Lax',
    'cookie_path' => '/',
    'read_and_close' => true
]);

$idUsuario = filter_var(
    $_SESSION['usuario_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if($idUsuario === false) {
    responder(401, [
        'sucesso' => false,
        'mensagem' => 'Entre na sua conta para continuar.'    
    ]);
}

// leitura do JSON
$corpo = file_get_contents('php://input');

// erro interno (servidor)
if($corpo === false) {
    responder(500, [
        'sucesso' => false,
        'mensagem' => 'Ocorreu um erro no servidor. Tente novamente mais tarde.'
    ]);
}

// erro do cliente (corpo vazio)
if(trim($corpo) === '') {
    responder(400, [
        'sucesso' => false,
        'mensagem' => 'Preencha todas as informações corretamente.'
    ]);
}

// verificar se é JSON
try {
    $dados = json_decode(
        $corpo,
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    error_log('[debug-perfil] chaves: ' . implode(',', array_keys($dados)));
} catch(JsonException $erro) {
    // JSON malformado
    responder(400, [
        'sucesso' => false,
        'mensagem' => 'JSON inválido'
    ]);
}
// confirmar que dados é array
if(!is_array($dados)) {
    responder(422, [
        'sucesso' => false,
        'mensagem' => 'O corpo deve ser um objeto JSON.',
        'erros' => ['corpo' => 'O corpo deve ser um objeto JSON.'],
    ]);
}

// NOTA: o token é criado em perfil_cliente.php (GET) com esta mesma
// chave de sessão. Se renomear 'csrf_dashboard' aqui, renomeie lá também.
$csrfSessao = $_SESSION['csrf_dashboard'] ?? '';
$csrfEnviado = $dados['csrf'] ?? null;

if (
    !is_string($csrfSessao) ||
    $csrfSessao === '' ||
    !is_string($csrfEnviado) ||
    !hash_equals($csrfSessao, $csrfEnviado)
) {
    responder(403, [
        'sucesso' => false,
        'mensagem' => 'Sessão expirada ou token inválido. Recarregue a página.'
    ]);
}

// conferindo se o objeto tem o formato
$campos = ['nome', 'email', 'telefone', 'endereco'];
$erros = [];

// endereco
foreach ($campos as $campo) {
    if (!array_key_exists($campo, $dados)) {
        $erros[$campo] = 'Este campo é obrigatório.';
    }
}
// validação tipo
foreach(['nome', 'email', 'telefone'] as $campo) {
    if(array_key_exists($campo, $dados) && !is_string($dados[$campo])) {
        $erros[$campo] = 'Este campo deve ser um texto.';
    }
}

$endereco = null;

if(!array_key_exists('endereco', $dados)) {
    // erro ja registrado acima
} elseif (!is_array($dados['endereco'])) {
    $erros['endereco'] = 'O endereço deve ser informado como objeto.';
} else {
    $resultadoEndereco = validarEndereco($dados['endereco']);

    $endereco = $resultadoEndereco['dados'];
    $erros = array_merge($erros, $resultadoEndereco['erros']);
}

$email = null;

if(is_string($dados['email'] ?? null)) {
    $email = strtolower(trim($dados['email']));

    if(strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erros['email'] = 'Informe um e-mail válido.';
    }
}

$nome = null;

if(is_string($dados['nome'] ?? null)) {
    $nome = preg_replace('/\s+/u', ' ', trim($dados['nome']));

    if(
        mb_strlen($nome, 'UTF-8') < 2 ||
        mb_strlen($nome, 'UTF-8') > 150 ||
        preg_match('/[\p{C}<>]/u', $nome)
    ) {
        $erros['nome'] = 'Informe um nome válido.';
    }
}

$telefone = null;

if(is_string($dados['telefone'] ?? null)) {
    $telefoneEntrada = trim($dados['telefone']);
    $telefone = preg_replace('/\D/', '', $telefoneEntrada);

    if(
        !preg_match('/^[0-9()\s.-]+$/D', $telefoneEntrada) ||
        !preg_match('/^[1-9][0-9]{9,10}$/D', $telefone)
    ) {
        $erros['telefone'] = 'Informe um telefone válido com DDD';
    }
}

if ($erros !== []) {
    responder(422, [
        'sucesso' => false,
        'mensagem' => 'Confira os campos informados.',
        'erros' => $erros,
    ]);
}

$endereco = $resultadoEndereco['dados'];
$enderecoInformado = montarEnderecoInformado($endereco);

$complemento = $endereco['complemento'] !== ''
    ? $endereco['complemento']
    : null;

$emTransacao = false; // flag de segurança

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    require_once __DIR__ . '/conexao.php';  

    // verificar usuario
    $sql = "SELECT perfil FROM usuario WHERE id_usuario = ? AND ativo = 1 LIMIT 1";
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $linhaUsuario = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if(!$linhaUsuario || strtolower((string) $linhaUsuario['perfil']) !== 'cliente') {
        responder(401, [
            'sucesso' => false,
            'mensagem' => 'Entre na conta de cliente para continuar.'
        ]);
    }

    // se alguem ja usa o email
    $sql = "SELECT 1 FROM usuario WHERE email = ? AND id_usuario <> ? LIMIT 1";
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param('si', $email, $idUsuario);
    $stmt->execute();

    $emailEmUso = $stmt->get_result()->num_rows > 0;
    $stmt->close();



    if($emailEmUso) {
        responder(409, [
            'sucesso' => false,
            'mensagem' => 'Este email já está em uso.',
            'erros' => [
                'email' => 'Este email já está em uso.',
            ],
        ]);
    }

    // iniciando a transação
    $conexao->begin_transaction();
    $emTransacao = true;

    // atualizar dados do usuario
    $sql = "UPDATE usuario SET nome = ?, email = ?, telefone = ? WHERE id_usuario = ? AND ativo = 1";
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param('sssi', $nome, $email, $telefone, $idUsuario);
    $stmt->execute();


    $linhasAfetadas = $stmt->affected_rows;
    $stmt->close();

    // verificar se o usuário ainda esta ativo, se nada foi afetado
    if($linhasAfetadas === 0) {
        $sql = "SELECT 1 FROM usuario WHERE id_usuario = ? AND ativo = 1 LIMIT 1";
        $stmt = $conexao->prepare($sql);
        $stmt->bind_param('i', $idUsuario);
        $stmt->execute();

        $usuarioContinuaAtivo = $stmt->get_result()->num_rows > 0;
        $stmt->close();
            
        if(!$usuarioContinuaAtivo) {
            throw new RuntimeException(
                'Usuário inexistente ou inativo durante a atualização'
            );
        }
    }


    // endereço
    $sql = "SELECT id_endereco_usuario
            FROM endereco_usuario 
            WHERE id_usuario = ? AND ativo = 1
            ORDER BY principal DESC, id_endereco_usuario DESC
            LIMIT 1";

    $stmt = $conexao->prepare($sql);
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $linhaEndereco = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    //id encontrado
    $idEnderecoAtual = $linhaEndereco
        ? (int) $linhaEndereco['id_endereco_usuario']
        : null; 

    // verificação endereço
        if($idEnderecoAtual === null) {
            $stmt = $conexao->prepare(
                'INSERT INTO endereco_usuario (id_usuario, endereco_informado, cep, logradouro, numero,
                complemento, bairro, cidade, uf, principal, ativo)
                VALUES (?,?,?,?,?,?,?,?,?,1,1)'
            );

    $stmt->bind_param(
        'issssssss',
        $idUsuario,
        $enderecoInformado,
        $endereco['cep'],
        $endereco['logradouro'],
        $endereco['numero'],
        $complemento,
        $endereco['bairro'],
        $endereco['cidade'],
        $endereco['uf']
    );
        } else {
            $stmt = $conexao->prepare(
                'UPDATE endereco_usuario
                SET endereco_informado = ?, cep = ?, logradouro = ?, numero = ?,
                    complemento = ?, bairro = ?, cidade = ?, uf = ?,
                    principal = 1, ativo = 1
                WHERE id_endereco_usuario = ? AND id_usuario = ?'
            );
            $stmt->bind_param(
                'ssssssssii',
                $enderecoInformado,
                $endereco['cep'],
                $endereco['logradouro'],
                $endereco['numero'],
                $complemento,
                $endereco['bairro'],
                $endereco['cidade'],
                $endereco['uf'],
                $idEnderecoAtual,
                $idUsuario
            );
        }

    $stmt->execute();
    $stmt->close(); 

    // confirmar commit e responder 200
    $conexao->commit();
    $emTransacao = false;

    responder(200, [
        'sucesso' => true,
        'mensagem' => 'Perfil atualizado com sucesso.',
        'usuario' => [
            'nome' => $nome,
            'email' => $email,
            'telefone' => $telefone,
            'endereco' => $endereco,
        ],
    ]);

} catch(Throwable $erro) {

    // ID de correlação
    $errorId = bin2hex(random_bytes(8));

    // 1062 com 'un_usuario_email' = race condition entre o SELECT e o UPDATE.
    // Se renomear o índice un_usuario_email no banco, atualizar a string abaixo.
    $ehEmailDuplicado = $erro instanceof mysqli_sql_exception 
        && $erro->getCode() === 1062 
        && str_contains($erro->getMessage(), 'un_usuario_email');
    
    // inclua o rótulo "race-email" ou "erro" para filtrar depois
    error_log(sprintf(
        '[atualizar_perfil][%s][%s] class=%s code=%s file=%s line=%d',
        $errorId,
        $ehEmailDuplicado ? 'race-email' : 'erro',
        get_class($erro),
        (string) $erro->getCode(),
        $erro->getFile(),
        $erro->getLine()
    ));

    if($emTransacao) {
        try {
            $conexao->rollback();
        } catch (Throwable $erroRollback) {
            error_log(sprintf(
                '[atualizar_perfil][%s][rollback-falhou] class=%s code=%s',
                $errorId,
                get_class($erroRollback),
                (string) $erroRollback->getCode()
            ));
        }
        $emTransacao = false;
    }

    if($ehEmailDuplicado) {
        responder(409, [
            'sucesso' => false,
            'mensagem' => 'Este e-mail já existe.',
            'erros' => [
                'email' => 'Este e-mail já existe.',
            ]
        ]);
    }

    responder(500, [
        'sucesso' => false,
        'mensagem' => 'Ops! Algo deu errado com o nosso servidor. Por favor, tente novamente em alguns minutos.',
        'referencia' => $errorId
    ]);
} 
