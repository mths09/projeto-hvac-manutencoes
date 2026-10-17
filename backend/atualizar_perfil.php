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

try {
    $dados = json_decode(
        $corpo,
        false,
        512,
        JSON_THROW_ON_ERROR
    );
} catch(JsonException $erro) {
    // JSON malformado
    responder(400, [
        'sucesso' => false,
        'mensagem' => 'JSON inválido'
    ]);
}

// conferindo se o objeto tem o formato
$campos = ['nome', 'email', 'telefone', 'endereco'];

if (!is_object($dados)) {
    responder(422, [
        'sucesso' => false,
        'mensagem' => 'O corpo deve ser um objeto JSON.'
    ]);
}

foreach ($campos as $campo) {
    if (!property_exists($dados, $campo)) {
        responder(422, [
            'sucesso' => false,
            'mensagem' => 'Faltam campos obrigatórios.'
        ]);
    }
}


// validando token csrf ja usado
$csrfSessao = $_SESSION['csrf_dashboard'] ?? '';
$csrfEnviado = $dados->csrf ?? null;

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

// validação dos valores e tipos do perfil
// verificar se são strings
// email
if(!is_string($dados->email)) {
    responder(422, [
        'sucesso' => false,
        'mensagem' => 'O e-mail deve ser um texto.'
    ]);
}

// nome
if(!is_string($dados->nome)) {
    responder(422, [
        'sucesso' => false,
        'mensagem' => 'O nome deve ser um texto.'
    ]);
}

// telefone
if (!is_string($dados->telefone)) {
    responder(422, [
        'sucesso' => false,
        'mensagem' => 'O telefone deve ser um texto.'
    ]);
}

// endereço
if(!is_string($dados->endereco)) {
    responder(422, [
        'sucesso' => false,
        'mensagem' => 'O endereço deve ser um texto.' 
    ]);
}

// validando formatos e limites
$email = trim($dados->email);

if(strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    responder(422, [
        'sucesso' => false,
        'mensagem' => 'Informe um e-mail válido.'
    ]);
}

$nome = trim($dados->nome);

if(mb_strlen($nome, 'UTF-8') < 2 || mb_strlen($nome, 'UTF-8') > 150) {
    responder(422, [
        'sucesso' => false,
        'mensagem' => 'O nome deve ter de 2 a 150 caracteres.'
    ]);
}

$telefoneEntrada = trim($dados->telefone);
$telefone = preg_replace('/\D/', '', $telefoneEntrada);

if (
    !preg_match('/^[0-9()\s.-]+$/D', $telefoneEntrada) ||
    !preg_match('/^[1-9][0-9]{9,10}$/D', $telefone)
) {
    responder(422, [
        'sucesso' => false,
        'mensagem' => 'Informe um telefone válido com DDD.'
    ]);
}

$endereco = trim($dados->endereco);

if(
    $endereco !== '' &&
    (mb_strlen($endereco, 'UTF-8') < 10 ||
    mb_strlen($endereco, 'UTF-8') > 500)
) {
    responder(422, [
        'sucesso' => false,
        'mensagem' => 'O endereço deve ter de 10 a 500 caracteres ou ficar vazio.'
    ]);
}


// CONEXÃO COM O BANCO (puxar do arquivo 'perfil_cliente.php', linha 82)
$emTransacao = false; // flag de segurança

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    require_once __DIR__ . '/conexao.php';  

    // verificar usuario
    $sql = "SELECT 1 FROM usuario WHERE id_usuario = ? AND ativo = 1 LIMIT 1";
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();

    $usuarioAtivo = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    if(!$usuarioAtivo) {
        responder(403, [
            'sucesso' => false,
            'mensagem' => 'Não foi possível atualizar este perfil.'
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
            'mensagem' => 'Este email já está em uso.'
        ]);
    }

    // iniciando a transação
    $conexao->begin_transaction();
    $emTransacao = true;

    // atualizar dados do usuario
    $sql = "UPDATE usuario SET nome = ?, email = ?, telefone_x = ? WHERE id_usuario = ? AND ativo = 1";
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
    if($endereco === '') {
        // só desativa o principal, se existir
        if($idEnderecoAtual !== null) {
            $stmt = $conexao->prepare(
                'UPDATE endereco_usuario
                SET ativo = 0, principal = 0
                WHERE id_endereco_usuario = ? AND id_usuario = ?'
            );
            $stmt->bind_param('ii', $idEnderecoAtual, $idUsuario);
            $stmt->execute();
            $stmt->close();    
        }
    } elseif ($idEnderecoAtual === null) {
            $stmt = $conexao->prepare(
                'INSERT INTO endereco_usuario
                    (id_usuario, endereco_informado, principal, ativo)
                VALUES (?,?,1,1'
            );
            $stmt->bind_param('is', $idUsuario, $endereco);
            $stmt->execute();
            $stmt->close();
        } else {
            // se tem endereço ativo, força atualização
            $stmt = $conexao->prepare(
                'UPDATE endereco_usuario
                SET endereco_informado = ?, principal = 1, ativo = 1,
                    cep = NULL, logradouro = NULL, numero = NULL,
                    complemento = NULL, bairro = NULL, cidade = NULL, uf = NULL
                    WHERE id_endereco_usuario = ? AND id_usuario = ?'
                );
            $stmt->bind_param('sii', $endereco, $idEnderecoAtual, $idUsuario);
            $stmt->execute();
            $stmt->close();
        }

    // confirmar commit e responder 200
    $conexao->commit();
    $emTransacao = false;

    responder(200, [
        'sucesso' => true,
        'mensagem' => 'Perfil atualizado.'
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
            'mensagem' => 'Este e-mail já existe.'
        ]);
    }

    responder(500, [
        'sucesso' => false,
        'mensagem' => 'Ops! Algo deu errado com o nosso servidor. Por favor, tente novamente em alguns minutos.',
        'referencia' => $errorId
    ]);
} 
