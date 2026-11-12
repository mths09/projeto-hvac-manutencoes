<?php

declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

const DOCUMENTO_TAMANHO_MAXIMO = 10 * 1024 * 1024; // 10 MB

function responderDocumentosAdmin(int $status, array $dados): never
{
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

function consultarDocumentosAdmin(mysqli $db, string $sql, array $parametros = []): mysqli_stmt
{
    $stmt = $db->prepare($sql);
    $stmt->execute($parametros);
    return $stmt;
}

try {
    $metodo = $_SERVER['REQUEST_METHOD'] ?? '';

    if (!in_array($metodo, ['GET', 'POST'], true)) {
        header('Allow: GET, POST');
        responderDocumentosAdmin(405, ['sucesso' => false, 'mensagem' => 'Método não permitido.']);
    }

    if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 11_000_000) {
        responderDocumentosAdmin(413, ['sucesso' => false, 'mensagem' => 'O PDF ultrapassa o limite de 10 MB.']);
    }

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

    $idAdministrador = filter_var(
        $_SESSION['usuario_id'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($idAdministrador === false || $idAdministrador === null) {
        responderDocumentosAdmin(401, ['sucesso' => false, 'mensagem' => 'Entre como administrador para continuar.']);
    }

    $_SESSION['csrf_solicitacoes_admin'] ??= bin2hex(random_bytes(32));
    $csrfSessao = (string) $_SESSION['csrf_solicitacoes_admin'];

    require_once __DIR__ . '/conexao.php';

    $administrador = consultarDocumentosAdmin(
        $conexao,
        'SELECT perfil, ativo FROM usuario WHERE id_usuario = ? LIMIT 1',
        [$idAdministrador]
    )->get_result()->fetch_assoc();

    $perfil = strtolower(trim((string) ($administrador['perfil'] ?? '')));

    if (!$administrador || 
        (int) $administrador['ativo'] !== 1 || 
        !in_array($perfil, ['admin', 'administrador'], true)
    ) {
        $conexao->close();
        responderDocumentosAdmin(403, ['sucesso' => false, 'mensagem' => 'Esta área é exclusiva do administrador.']);
    }

    // GET: lista os documentos de um pedido
    if ($metodo === 'GET') {
        $protocolo = $_GET['protocolo'] ?? '';

        if(
            !is_string($protocolo)
            || !preg_match('/^SRV-[A-Za-z0-9-]{1,26}$/D', $protocolo)
        ) {
            $conexao->close();
            responderDocumentosAdmin(422, ['sucesso' => false, 'mensagem' => 'Identificador do pedido inválido.']);
        }

        $pedido = consultarDocumentosAdmin(
            $conexao,
            'SELECT id_solicitacao
            FROM solicitacao
            WHERE protocolo = ?
            LIMIT 1',
            [$protocolo]
        )->get_result()->fetch_assoc();

        if(!$pedido) {
            $conexao->close();
            responderDocumentosAdmin(404, ['sucesso' => false, 'mensagem' => 'Pedido não encontrado.']);
        }

        $documentos = consultarDocumentosAdmin(
            $conexao,
            'SELECT d.id_documento AS id, d.titulo, d.tipo, d.numero,
                    d.emitido_em, d.disponivel_cliente
            FROM documento d
            WHERE d.id_solicitacao = ?
            ORDER BY d.emitido_em DESC, d.id_documento DESC',
            [(int) $pedido['id_solicitacao']]
        )->get_result()->fetch_all(MYSQLI_ASSOC);

        foreach ($documentos as &$documento) {
            $documento['id'] = (string) $documento['id'];
            $documento['disponivel_cliente'] =
                (bool) $documento['disponivel_cliente'];
            $documento['url'] = 
                '../../../backend/documento_download.php?id='
                . rawurlencode($documento['id']);
        }
        unset($documento);

        $conexao->close();
        session_write_close();

        responderDocumentosAdmin(200, [
            'sucesso' => true,
            'csrf' => $csrfSessao,
            'documentos' => $documentos
        ]);
    }

    // POST: valida CSRF e dados do formulário. Campos chegam em $_POST
    if(
        !is_string($_POST['csrf'] ?? null)
        || !hash_equals($csrfSessao, $_POST['csrf'])
    ) {
        $conexao->close();
        responderDocumentosAdmin(403, [
            'sucesso' => false,
            'mensagem' => 'Sessão expirada. Atualize o painel e tente novamente.'
        ]);
    }

    $protocolo = $_POST['protocolo'] ?? '';
    $titulo = trim((string) ($_POST['titulo'] ?? ''));
    $tipo = $_POST['tipo'] ?? '';
    $disponivelCliente = $_POST['disponivel_cliente'] ?? '0';

    if(
        !is_string($protocolo) 
        || !preg_match('/^SRV-[A-Za-z0-9-]{1,26}$/D', $protocolo)
    ) {
        $conexao-> close();
        responderDocumentosAdmin(422, [
            'sucesso' => false,
            'mensagem' => 'Identificador do pedido inválido.'
        ]);
    }

    if ($titulo === '' || mb_strlen($titulo, 'UTF-8') > 150) {
        $conexao->close();
        responderDocumentosAdmin(422, [
            'sucesso' => false,
            'mensagem' => 'Informe um título com até 150 caracteres.'
        ]);
    }

    $tiposPermitidos = [
        'ORCAMENTO',
        'RELATORIO',
        'RECIBO',
        'GARANTIA',
        'OUTRO'
    ];

    if(!is_string($tipo) || !in_array($tipo, $tiposPermitidos, true)) {
        $conexao->close();
        responderDocumentosAdmin(422, [
            'sucesso' => false,
            'mensagem' => 'Selecione um tipo de documento válido.'
        ]);
    }

    if(!in_array($disponivelCliente, ['0', '1'], true)) {
        $conexao->close();
        responderDocumentosAdmin(422, [
            'sucesso' => false,
            'mensagem' => 'Opção de visibilidade inválida.'
        ]);
    }

    $upload = $_FILES['documento'] ?? null;

    if (
        !is_array($upload)
        || (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
        || !is_uploaded_file((string) ($upload['tmp_name'] ?? '')) 
    ) {
        $conexao->close();
        responderDocumentosAdmin(422, [
            'sucesso' => false,
            'mensagem' => 'Selecione um PDF válido para enviar.'
        ]);
    }

    $tamanho = (int) ($upload['size'] ?? 0);

    if($tamanho <= 0 || $tamanho > DOCUMENTO_TAMANHO_MAXIMO) {
        $conexao->close();
        responderDocumentosAdmin(422, [
            'sucesso' => false,
            'mensagem' => 'O PDF deve ter até 10 MB.'
        ]);
    }

    $tmp = (string) $upload['tmp_name'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp);
    $assinatura = file_get_contents($tmp, false, null, 0, 5);
    $extensao = strtolower(pathinfo((string) ($upload['name'] ?? ''), PATHINFO_EXTENSION));

    if ($mime !== 'application/pdf' || $assinatura !== '%PDF-' || $extensao !== 'pdf') {
        $conexao->close();
        responderDocumentosAdmin(422, [
            'sucesso' => false,
            'mensagem' => 'O arquivo precisa ser um PDF válido.'
        ]);
    }

    $diretorioUpload =
        __DIR__ . DIRECTORY_SEPARATOR . 'uploads'
        . DIRECTORY_SEPARATOR . 'documentos';

    if (
        !is_dir($diretorioUpload)
        && !mkdir($diretorioUpload, 0750, true)
        && !is_dir($diretorioUpload)
    ) {
        $conexao->close();
        throw new RuntimeException('Não foi possível preparar a pasta de documentos.');
    }

    $nomeArquivo = bin2hex(random_bytes(16)) . '.pdf';
    $caminhoFisico = $diretorioUpload . DIRECTORY_SEPARATOR . $nomeArquivo;
    $arquivoMovido = false;

    $conexao->begin_transaction();

    try {
            $pedido = consultarDocumentosAdmin(
                $conexao,
                'SELECT id_solicitacao FROM solicitacao
                WHERE protocolo = ? LIMIT 1 FOR UPDATE',
                [$protocolo]
            )->get_result()->fetch_assoc();

            if(!$pedido) {
                throw new OutOfBoundsException('Pedido não encontrado.');
            }

            if(!move_uploaded_file($tmp, $caminhoFisico)) {
                throw new RuntimeException('Não foi possível salvar o PDF.');
            }
            $arquivoMovido = true;

            $chave = 'uploads/documentos/' . $nomeArquivo;
            $nomeOriginal = mb_substr(
                basename((string) $upload['name']),
                0,
                255,
                'UTF-8'
            );
            $sha256 = hash_file('sha256', $caminhoFisico);

            consultarDocumentosAdmin(
            $conexao,
            'INSERT INTO arquivo
                (nome_original, chave_armazenamento, mime_type,
                 tamanho_bytes, sha256, id_autor)
             VALUES (?, ?, ?, ?, ?, ?)',
            [
                $nomeOriginal,
                $chave,
                'application/pdf',
                $tamanho,
                $sha256,
                $idAdministrador
            ]
        );

        $idArquivo = (int) $conexao->insert_id;

        consultarDocumentosAdmin(
            $conexao,
            'INSERT INTO documento
                (id_solicitacao, id_arquivo, tipo, titulo, disponivel_cliente)
            VALUES (?, ?, ?, ?, ?)',
            [
                (int) $pedido['id_solicitacao'],
                $idArquivo,
                $tipo,
                $titulo,
                (int) $disponivelCliente
            ]
        );

        $idDocumento = (int) $conexao->insert_id;
        $conexao->commit();

        $conexao->close();
        session_write_close();

        responderDocumentosAdmin(201, [
            'sucesso' => true,
            'mensagem' => 'Documento cadastrado com sucesso.',
            'id' => (string) $idDocumento
        ]);

    } catch (DomainException $erro) {
        $conexao->rollback();

        if ($arquivoMovido && is_file($caminhoFisico)) {
            unlink($caminhoFisico);
        }

        $conexao->close();
        session_write_close();

        responderDocumentosAdmin(422, [
            'sucesso' => false,
            'mensagem' => $erro->getMessage()
        ]);
    } catch (OutOfBoundsException $erro) {
        $conexao->rollback();

        if ($arquivoMovido && is_file($caminhoFisico)) {
            unlink($caminhoFisico);
        }

        $conexao->close();
        session_write_close();

        responderDocumentosAdmin(404, [
            'sucesso' => false,
            'mensagem' => $erro->getMessage()
        ]);
    } catch (Throwable $erro) {
        $conexao->rollback();

        if ($arquivoMovido && is_file($caminhoFisico)) {
            unlink($caminhoFisico);
        }
        throw $erro;
    }
} catch (Throwable $erro) {
    if (isset($conexao) && $conexao instanceof mysqli) {
        $conexao->close();
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    error_log(
        'Falha no processamento de documentos administrativo: ' 
        . get_class($erro) . ', código ' . $erro->getCode()
    );

    responderDocumentosAdmin(500, [
        'sucesso' => false, 
        'mensagem' => 'Não foi possível processar o documento. Tente novamente.'
    ]);
}
