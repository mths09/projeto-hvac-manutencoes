<?php

declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function responderPerfil(int $status, array $dados): void
{
    http_response_code($status);

    echo json_encode(
        $dados,
        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );

    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');

    responderPerfil(405, [
        'mensagem' => 'Método não permitido.'
    ]);
}

try {
    $https = !empty($_SERVER['HTTPS'])
        && $_SERVER['HTTPS'] !== 'off';

    // Recuperar a mesma sessão criada pelo login.
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

    $idUsuario = filter_var(
        $_SESSION['usuario_id'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($idUsuario === false) {
        responderPerfil(401, [
            'mensagem' => 'Entre na sua conta para continuar.'
        ]);
    }

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    require_once __DIR__ . '/conexao.php';

    // O ID vem da sessão, não de um parâmetro enviado pelo navegador.
    $sql = "
        SELECT
            u.id_usuario,
            u.nome,
            u.email,
            u.telefone,
            u.data_nascimento,
            u.criado_em,
            COALESCE(
                (
                    SELECT e.endereco_informado
                    FROM endereco_usuario AS e
                    WHERE e.id_usuario = u.id_usuario
                      AND e.ativo = 1
                    ORDER BY
                        e.principal DESC,
                        e.id_endereco_usuario DESC
                    LIMIT 1
                ),
                'Endereço não cadastrado'
            ) AS endereco
        FROM usuario AS u
        WHERE u.id_usuario = ?
          AND u.ativo = 1
        LIMIT 1
    ";

    $stmt = $conexao->prepare($sql);
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();

    $usuario = $stmt->get_result()->fetch_assoc();

    $stmt->close();
    $conexao->close();

    if (!$usuario) {
        responderPerfil(401, [
            'mensagem' => 'Sua conta não está disponível. Faça login novamente.'
        ]);
    }

    responderPerfil(200, [
        'sucesso' => true,
        'usuario' => $usuario
    ]);

} catch (Throwable $erro) {
    error_log(
        'Falha ao carregar perfil: '
        . get_class($erro)
        . ' - código '
        . $erro->getCode()
    );

    responderPerfil(500, [
        'mensagem' => 'Não foi possível carregar seu perfil.'
    ]);
}