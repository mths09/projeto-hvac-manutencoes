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

// Esta rota só LÊ o perfil. A escrita é feita por atualizar_perfil.php.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
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

    // ATENÇÃO: esta chave de sessão é conferida em atualizar_perfil.php.
    // Se renomear aqui, renomeie lá também.
    $_SESSION['csrf_dashboard'] ??= bin2hex(random_bytes(32));
    $csrf = $_SESSION['csrf_dashboard'];

    // Libera a sessão antes de consultar o banco.
    session_write_close();
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    require_once __DIR__ . '/conexao.php';

    $stmt = $conexao->prepare(
        'SELECT nome, email, telefone, perfil, ativo, criado_em
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
        'csrf' => $csrf,
        'usuario' => [
            'nome' => (string) $usuario['nome'],
            'email' => (string) $usuario['email'],
            'telefone' => (string) $usuario['telefone'],
            'endereco' => (string) $enderecoPerfil,
            'criado_em' => (string) $usuario['criado_em']
        ]
    ]);
} catch (Throwable $erro) {
    if (isset($conexao) && $conexao instanceof mysqli) {
        $conexao->close();
    }
    error_log('Falha ao carregar perfil do cliente: ' . get_class($erro) . ', código ' . $erro->getCode());
    responderPerfilCliente(500, ['sucesso' => false, 'mensagem' => 'Não foi possível carregar seu perfil agora.']);
}