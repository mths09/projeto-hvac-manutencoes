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
    $idSessao = null;
    if (session_start([
        'use_strict_mode' => true,
        'use_only_cookies' => true,
        'cookie_httponly' => true,
        'cookie_secure' => $https,
        'cookie_samesite' => 'Lax',
        'cookie_path' => '/',
        'read_and_close' => true
    ])) {
        $idSessao = filter_var($_SESSION['usuario_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    }

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    require_once __DIR__ . '/conexao.php';
    $stmtAdmin = $conexao->prepare('SELECT perfil, ativo FROM usuario WHERE id_usuario = ? LIMIT 1');
    $isAdmin = false;
    if ($idSessao !== false && $idSessao !== null) {
        $stmtAdmin->execute([$idSessao]);
        $usuario = $stmtAdmin->get_result()->fetch_assoc();
        $perfil = strtolower(trim((string) ($usuario['perfil'] ?? '')));
        $isAdmin = $usuario && (int) $usuario['ativo'] === 1 && in_array($perfil, ['admin', 'administrador'], true);
    }

    $sql = 'SELECT f.chave_armazenamento, f.mime_type
            FROM arquivo f
            INNER JOIN item_portfolio i ON i.id_arquivo_capa = f.id_arquivo
            WHERE f.id_arquivo = ?';
    if (!$isAdmin) {
        $sql .= ' AND i.publicado = 1';
    }
    $sql .= ' LIMIT 1';
    $stmt = $conexao->prepare($sql);
    $stmt->execute([$idArquivo]);
    $arquivo = $stmt->get_result()->fetch_assoc();
    $conexao->close();

    if (!$arquivo || !in_array($arquivo['mime_type'], ['image/jpeg', 'image/png', 'image/webp'], true)) {
        http_response_code(404);
        exit;
    }
    $chave = (string) $arquivo['chave_armazenamento'];
    if (!preg_match('#^uploads/portfolio/[a-f0-9]{32}\.(?:jpg|png|webp)$#D', $chave)) {
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
    header('Cache-Control: public, max-age=86400');
    readfile($caminho);
} catch (Throwable $erro) {
    error_log('Falha ao carregar imagem do portfólio: ' . get_class($erro) . ', código ' . $erro->getCode());
    http_response_code(404);
}
