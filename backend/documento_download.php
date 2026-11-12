<?php

declare(strict_types=1);

ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');

function responderErroDownload(int $status): never {
    http_response_code($status);
    exit;
}

if(($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    responderErroDownload(405);
}

$idDocumento = filter_var(
    $_GET['id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if($idDocumento === false || $idDocumento === null) {
    responderErroDownload(404);
}

$conexao = null;

try {
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    if(!session_start([
        'use_strict_mode' => true,
        'use_only_cookies' => true,
        'cookie_httponly' => true,
        'cookie_secure' => $https,
        'cookie_samesite' => 'Lax',
        'cookie_path' => '/'
    ])) {
        throw new RuntimeException(('Não foi possível recuperar a sessão.'));
    }

    $idUsuario = filter_var(
        $_SESSION['usuario_id'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if($idUsuario === false || $idUsuario === null) {
        responderErroDownload(401);
    }

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    require_once __DIR__ . '/conexao.php';

    // confirma se conta existe, se ta ativa e qual o perfil
    $stmtUsuario = $conexao->prepare(
        'SELECT perfil, ativo FROM usuario
        WHERE id_usuario = ? LIMIT 1'
    );
    $stmtUsuario->execute([$idUsuario]);
    $usuario = $stmtUsuario->get_result()->fetch_assoc();
    $stmtUsuario->close();

    $ativo = $usuario && (int) $usuario['ativo'] === 1;
    $perfil = strtolower(trim((string) ($usuario['perfil'] ?? '')));

    if(!$ativo) {
        responderErroDownload(403);
    }

    $ehAdmin = in_array($perfil, ['admin', 'administrador'], true);
    $ehCliente = $perfil === 'cliente';

    if(!$ehAdmin && !$ehCliente) {
        responderErroDownload(403);
    }

    // busca documento e cliente dono do pedido numa consulta
    $stmtDocumento = $conexao->prepare(
        'SELECT d.id_documento, d.titulo, d.disponivel_cliente,
            f.chave_armazenamento, f.mime_type, f.tamanho_bytes,
            s.id_cliente
        FROM documento d
        INNER JOIN arquivo f ON f.id_arquivo = d.id_arquivo
        INNER JOIN solicitacao s ON s.id_solicitacao = d.id_solicitacao
        WHERE d.id_documento = ?
        LIMIT 1'
    );
    $stmtDocumento->execute([$idDocumento]);
    $documento = $stmtDocumento->get_result()->fetch_assoc();
    $stmtDocumento->close();

    if(!$documento) {
        responderErroDownload(404);
    }

    $ehDonoDoPedido =
        $documento['id_cliente'] !== null
        && (int) $documento['id_cliente'] === (int) $idUsuario;
    
    $documentoLiberado = (int) $documento['disponivel_cliente'] === 1;

    // Admin baixa qualquer documento, Cliente baixa documento do seu pedido (apenas se liberado)
    if(!$ehAdmin && (!$ehDonoDoPedido || !$documentoLiberado)) {
        responderErroDownload((403));
    }

    // Serve para PDFs criados pelo fluxo de documentos
    if($documento['mime_type'] !== 'application/pdf') {
        responderErroDownload(404);
    }

    $chave = (string) $documento['chave_armazenamento'];

    if(!preg_match(
        '#^uploads/documentos/[a-f0-9]{32}\.pdf$#D',
        $chave
    )) {
        responderErroDownload(404);
    }

    $diretorioDocumentos = realpath(
         __DIR__ . DIRECTORY_SEPARATOR . 'uploads'
        . DIRECTORY_SEPARATOR . 'documentos'
    );

    $caminhoReal = realpath(
        __DIR__ . DIRECTORY_SEPARATOR
        . str_replace('/', DIRECTORY_SEPARATOR, $chave)
    );

    // comparação mantem arquivo na pasta privada de documentos
    if(
        $diretorioDocumentos === false
        || $caminhoReal === false
        || !is_file($caminhoReal)
        || !str_starts_with(
            $caminhoReal,
            $diretorioDocumentos . DIRECTORY_SEPARATOR
    )) {
        responderErroDownload(404);
    }

    $tamanhoReal = filesize($caminhoReal);

    if($tamanhoReal === false || $tamanhoReal <= 0) {
        responderErroDownload(404);
    }

    // libera bloqueio de sessão e fecha o banco antes de transmitir o PDF
    session_write_close();
    $conexao->close();
    $conexao = null;
    
    // nome generico evita inserir conteudo do banco no cabeçalho
    $nomeDownload = 'documento-'
        . (int) $documento['id_documento']
        . '.pdf';

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $nomeDownload . '"');
    header('Content-Length: ' . $tamanhoReal);

    while(ob_get_level() > 0) {
        ob_end_clean();
    }

    readfile($caminhoReal);
    exit;
} catch (Throwable $erro) {
    if(session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    if($conexao instanceof mysqli) {
        $conexao->close();
    }

    error_log(
        'Falha no dowload de documentos: '
        . get_class($erro) . ', código ' . $erro->getCode()
    );

    responderErroDownload(404);
}