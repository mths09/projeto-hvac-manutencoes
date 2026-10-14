<?php

declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function responderPerfilCliente(int $status, array $dados): never
{
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

$metodo = $_SERVER['REQUEST_METHOD'] ?? '';
if (!in_array($metodo, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    responderPerfilCliente(405, ['sucesso' => false, 'mensagem' => 'Método não permitido.']);
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

    $idUsuario = filter_var($_SESSION['usuario_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($idUsuario === false || $idUsuario === null) {
        responderPerfilCliente(401, ['sucesso' => false, 'mensagem' => 'Entre na sua conta para continuar.']);
    }

    if ($metodo === 'GET') {
        $_SESSION['csrf_dashboard'] ??= bin2hex(random_bytes(32));
        $csrf = $_SESSION['csrf_dashboard'];
    } else {
        $csrfSessao = $_SESSION['csrf_dashboard'] ?? '';
        $csrfEnviado = $_POST['csrf'] ?? '';
        if (!is_string($csrfSessao) || $csrfSessao === '' || !is_string($csrfEnviado) || !hash_equals($csrfSessao, $csrfEnviado)) {
            responderPerfilCliente(403, ['sucesso' => false, 'mensagem' => 'Sessão expirada. Recarregue a página e tente novamente.']);
        }
    }

    if ($metodo === 'POST') {
        $nome = $_POST['nome'] ?? null;
        $email = $_POST['email'] ?? null;
        $telefoneEntrada = $_POST['telefone'] ?? null;
        $enderecoEntrada = $_POST['endereco'] ?? null;
        if (!is_string($nome) || !is_string($email) || !is_string($telefoneEntrada) || !is_string($enderecoEntrada)) {
            responderPerfilCliente(422, ['sucesso' => false, 'mensagem' => 'Preencha todos os campos com dados válidos.']);
        }

        $nome = preg_replace('/\s+/u', ' ', trim($nome));
        $email = strtolower(trim($email));
        $telefone = preg_replace('/\D/', '', $telefoneEntrada);
        $endereco = trim($enderecoEntrada);
        if (mb_strlen($nome, 'UTF-8') < 2 || mb_strlen($nome, 'UTF-8') > 150 || preg_match('/[\p{C}<>]/u', $nome)) {
            responderPerfilCliente(422, ['sucesso' => false, 'mensagem' => 'Informe um nome válido de até 150 caracteres.']);
        }
        if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            responderPerfilCliente(422, ['sucesso' => false, 'mensagem' => 'Informe um e-mail válido.']);
        }
        if (!preg_match('/^[0-9()\s.-]+$/D', $telefoneEntrada) || !preg_match('/^[1-9][0-9]{9,10}$/D', $telefone)) {
            responderPerfilCliente(422, ['sucesso' => false, 'mensagem' => 'Informe um telefone com DDD, com 10 ou 11 dígitos.']);
        }
        if ($endereco !== '' && (mb_strlen($endereco, 'UTF-8') < 10 || mb_strlen($endereco, 'UTF-8') > 500 || preg_match('/[\p{C}<>]/u', $endereco))) {
            responderPerfilCliente(422, ['sucesso' => false, 'mensagem' => 'O endereço deve ter de 10 a 500 caracteres ou ficar vazio.']);
        }
    }

    session_write_close();
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    require_once __DIR__ . '/conexao.php';

    $stmt = $conexao->prepare(
        'SELECT id_usuario, nome, email, telefone, perfil, ativo, criado_em
         FROM usuario WHERE id_usuario = ? LIMIT 1'
    );
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $usuario = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$usuario || (int) $usuario['ativo'] !== 1 || strtolower((string) $usuario['perfil']) !== 'cliente') {
        $conexao->close();
        responderPerfilCliente(401, ['sucesso' => false, 'mensagem' => 'Entre na sua conta de cliente para continuar.']);
    }

    if ($metodo === 'POST') {
        $conexao->begin_transaction();
        try {
            $stmt = $conexao->prepare('UPDATE usuario SET nome = ?, email = ?, telefone = ? WHERE id_usuario = ?');
            $stmt->bind_param('sssi', $nome, $email, $telefone, $idUsuario);
            $stmt->execute();
            $stmt->close();

            $stmt = $conexao->prepare(
                'SELECT id_endereco_usuario FROM endereco_usuario
                 WHERE id_usuario = ? AND principal = 1 AND ativo = 1
                 ORDER BY id_endereco_usuario DESC LIMIT 1 FOR UPDATE'
            );
            $stmt->bind_param('i', $idUsuario);
            $stmt->execute();
            $enderecoExistente = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($endereco === '') {
                $stmt = $conexao->prepare('UPDATE endereco_usuario SET ativo = 0, principal = 0 WHERE id_usuario = ? AND principal = 1');
                $stmt->bind_param('i', $idUsuario);
            } elseif ($enderecoExistente) {
                $idEndereco = (int) $enderecoExistente['id_endereco_usuario'];
                $stmt = $conexao->prepare('UPDATE endereco_usuario SET endereco_informado = ? WHERE id_endereco_usuario = ? AND id_usuario = ?');
                $stmt->bind_param('sii', $endereco, $idEndereco, $idUsuario);
            } else {
                $stmt = $conexao->prepare('INSERT INTO endereco_usuario (id_usuario, endereco_informado, principal, ativo) VALUES (?, ?, 1, 1)');
                $stmt->bind_param('is', $idUsuario, $endereco);
            }
            $stmt->execute();
            $stmt->close();
            $conexao->commit();

            if (session_start()) {
                $_SESSION['usuario_nome'] = $nome;
                $_SESSION['usuario_email'] = $email;
                session_write_close();
            }
        } catch (Throwable $erro) {
            $conexao->rollback();
            throw $erro;
        }

        $usuario['nome'] = $nome;
        $usuario['email'] = $email;
        $usuario['telefone'] = $telefone;
    }

    $stmt = $conexao->prepare(
        'SELECT endereco_informado FROM endereco_usuario
         WHERE id_usuario = ? AND ativo = 1
         ORDER BY principal DESC, id_endereco_usuario DESC LIMIT 1'
    );
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $enderecoPerfil = $stmt->get_result()->fetch_assoc()['endereco_informado'] ?? '';
    $stmt->close();
    $conexao->close();

    responderPerfilCliente(200, [
        'sucesso' => true,
        'csrf' => $csrf ?? null,
        'usuario' => [
            'nome' => (string) $usuario['nome'],
            'email' => (string) $usuario['email'],
            'telefone' => (string) $usuario['telefone'],
            'endereco' => (string) $enderecoPerfil,
            'criado_em' => (string) $usuario['criado_em']
        ],
        'mensagem' => $metodo === 'POST' ? 'Perfil atualizado com sucesso.' : null
    ]);
} catch (mysqli_sql_exception $erro) {
    if (isset($conexao) && $conexao instanceof mysqli) $conexao->close();
    if ((int) $erro->getCode() === 1062) {
        responderPerfilCliente(409, ['sucesso' => false, 'mensagem' => 'Este e-mail já está sendo usado por outra conta.']);
    }
    error_log('Falha de banco ao carregar perfil: código ' . $erro->getCode());
    responderPerfilCliente(503, ['sucesso' => false, 'mensagem' => 'Não foi possível salvar seus dados agora.']);
} catch (Throwable $erro) {
    if (isset($conexao) && $conexao instanceof mysqli) $conexao->close();
    error_log('Falha ao carregar perfil do cliente: ' . get_class($erro) . ', código ' . $erro->getCode());
    responderPerfilCliente(500, ['sucesso' => false, 'mensagem' => 'Não foi possível carregar seu perfil agora.']);
}
