<?php

declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('X-Content-Type-Options: nosniff');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    exit;
}

$idArquivo = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($idArquivo === false || $idArquivo === null) {
    http_response_code(404);
    exit;
}

try {
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    if (!session_start([
        'use_strict_mode' => true,
        'use_only_cookies' => true,
        'cookie_httponly' => true,
        'cookie_secure' => $https,
        'cookie_samesite' => 'Lax',
        'cookie_path' => '/',
        'read_and_close' => true
    ])) {
        throw new RuntimeException('Falha ao recuperar sessão.');
    }

    // Só usuários autenticados podem ver anexos de solicitações.
    $idSessao = filter_var($_SESSION['usuario_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($idSessao === false || $idSessao === null) {
        http_response_code(401);
        exit;
    }

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    require_once __DIR__ . '/conexao.php';

    $stmtUsuario = $conexao->prepare('SELECT perfil, ativo FROM usuario WHERE id_usuario = ? LIMIT 1');
    $stmtUsuario->execute([$idSessao]);
    $usuario = $stmtUsuario->get_result()->fetch_assoc();
    $perfil = strtolower(trim((string) ($usuario['perfil'] ?? '')));
    $ativo = (bool) $usuario && (int) $usuario['ativo'] === 1;
    $ehAdmin = $ativo && in_array($perfil, ['admin', 'administrador'], true);

    // O anexo só pertence a UMA solicitação (restrição un_anexo_solicitacao_arquivo).
    $stmt = $conexao->prepare(
        'SELECT f.chave_armazenamento, f.mime_type, s.id_cliente
         FROM arquivo f
         INNER JOIN anexo_solicitacao an ON an.id_arquivo = f.id_arquivo
         INNER JOIN solicitacao s ON s.id_solicitacao = an.id_solicitacao
         WHERE f.id_arquivo = ?
         LIMIT 1'
    );
    $stmt->execute([$idArquivo]);
    $arquivo = $stmt->get_result()->fetch_assoc();
    $conexao->close();

    if (!$arquivo) {
        http_response_code(404);
        exit;
    }

    // Só o cliente dono do pedido ou um administrador ativo podem ver o anexo.
    $ehDono = $ativo && (int) $arquivo['id_cliente'] === $idSessao;
    if (!$ehAdmin && !$ehDono) {
        http_response_code(403);
        exit;
    }

    $mimesPermitidos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'video/mp4' => 'mp4'];
    if (!isset($mimesPermitidos[$arquivo['mime_type']])) {
        http_response_code(404);
        exit;
    }

    $chave = (string) $arquivo['chave_armazenamento'];
    if (!preg_match('#^uploads/solicitacoes/[a-f0-9]{32}\.(?:jpg|png|mp4)$#D', $chave)) {
        http_response_code(404);
        exit;
    }
    $caminho = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $chave);
    if (!is_file($caminho)) {
        http_response_code(404);
        exit;
    }

    header('Content-Type: ' . $arquivo['mime_type']);
    header('Content-Length: ' . (string) filesize($caminho));
    header('Content-Disposition: inline');
    // Privado: cada anexo só é visível para o dono do pedido e para admins, então não deve ir a um cache compartilhado.
    header('Cache-Control: private, max-age=86400');
    readfile($caminho);
} catch (Throwable $erro) {
    error_log('Falha ao carregar anexo da solicitação: ' . get_class($erro) . ', código ' . $erro->getCode());
    http_response_code(404);
}