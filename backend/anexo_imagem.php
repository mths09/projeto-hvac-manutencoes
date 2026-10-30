<?php

declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('X-Content-Type-Options: nosniff');

if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD');
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

    $tamanho = filesize($caminho);
    if ($tamanho === false || $tamanho <= 0) {
        http_response_code(404);
        exit;
    }

    $inicio = 0;
    $fim = $tamanho - 1;
    $status = 200;
    $range = trim((string) ($_SERVER['HTTP_RANGE'] ?? ''));

    if ($range !== '') {
        if (!preg_match('/^bytes=(\d*)-(\d*)$/D', $range, $partes)
            || ($partes[1] === '' && $partes[2] === '')) {
            http_response_code(416);
            header("Content-Range: bytes */{$tamanho}");
            exit;
        }

        if($partes[1] === '' && $partes[2] !== '') {
            $sufixo = (int) $partes[2];
            if ($sufixo <= 0) {
                http_response_code(416);
                header("Content-Range: bytes */{$tamanho}");
                exit;
            }
            $inicio = max(0, $tamanho - $sufixo);
        } else {
            $inicio = (int) $partes[1];
            $fim = $partes[2] === '' ? $tamanho - 1 : min((int) $partes[2], $tamanho - 1);
        }

        if ($inicio >= $tamanho || $fim < $inicio) {
            http_response_code(416);
            header("Content-Range: bytes */{$tamanho}");
            exit;
        }

        $status = 206;
    }

    $comprimento = $fim - $inicio + 1;

    http_response_code($status);
    header('Content-Type: ' . $arquivo['mime_type']);
    header('Content-Disposition: inline');
    header('Accept-Ranges: bytes');
    header('Content-Length: ' . $comprimento);
    // O navegador deve repetir a autorização em cada acesso ao arquivo.
    header('Cache-Control: private, no-store');

    if ($status === 206) {
        header("Content-Range: bytes {$inicio}-{$fim}/{$tamanho}");
    }

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'HEAD') {
        exit;
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $stream = fopen($caminho, 'rb');
    if ($stream === false) {
        http_response_code(404);
        exit;
    }

    fseek($stream, $inicio);
    $restante = $comprimento;

    while ($restante > 0 && !feof($stream)) {
        $bloco = fread($stream, min(8192, $restante));
        if ($bloco === false || $bloco === '') {
            break;
        }

        echo $bloco;
        $restante -= strlen($bloco);
    }

    fclose($stream);

} catch (Throwable $erro) {
    error_log('Falha ao carregar anexo da solicitação: ' . get_class($erro) . ', código ' . $erro->getCode());
    http_response_code(404);
}
