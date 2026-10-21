<?php

declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function responderPortfolioAdmin(int $status, array $dados): never
{
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

function consultarPortfolioAdmin(mysqli $db, string $sql, array $parametros = []): mysqli_stmt
{
    $stmt = $db->prepare($sql);
    $stmt->execute($parametros);
    return $stmt;
}

function categoriasPadraoPortfolio(mysqli $db): void
{
    $categorias = [
        ['ar-condicionado', 'Ar Condicionado', '#00d8f3', 1],
        ['refrigeracao', 'Refrigeração', '#4ea1ff', 2],
        ['eletrica', 'Elétrica', '#ffab00', 3],
        ['iluminacao', 'Iluminação', '#dc34ed', 4]
    ];
    foreach ($categorias as [$codigo, $nome, $cor, $ordem]) {
        consultarPortfolioAdmin(
            $db,
            'INSERT INTO categoria_portfolio (codigo, nome, cor, ordem, ativo)
             VALUES (?, ?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE nome = VALUES(nome), cor = VALUES(cor), ordem = VALUES(ordem), ativo = 1',
            [$codigo, $nome, $cor, $ordem]
        );
    }
}

function limparArquivoPortfolio(mysqli $db, int $idArquivo): void
{
    $arquivo = consultarPortfolioAdmin(
        $db,
        'SELECT chave_armazenamento FROM arquivo WHERE id_arquivo = ? LIMIT 1',
        [$idArquivo]
    )->get_result()->fetch_assoc();
    if (!$arquivo) {
        return;
    }
    $usos = (int) consultarPortfolioAdmin(
        $db,
        'SELECT
           (SELECT COUNT(*) FROM item_portfolio WHERE id_arquivo_capa = ?) +
           (SELECT COUNT(*) FROM documento WHERE id_arquivo = ?) +
           (SELECT COUNT(*) FROM anexo_solicitacao WHERE id_arquivo = ?) AS total',
        [$idArquivo, $idArquivo, $idArquivo]
    )->get_result()->fetch_assoc()['total'];
    if ($usos > 0) {
        return;
    }

    $chave = (string) $arquivo['chave_armazenamento'];
    consultarPortfolioAdmin($db, 'DELETE FROM arquivo WHERE id_arquivo = ?', [$idArquivo]);
    if (preg_match('#^uploads/portfolio/[a-f0-9]{32}\.(?:jpg|png|webp)$#D', $chave)) {
        $caminho = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $chave);
        if (is_file($caminho)) {
            unlink($caminho);
        }
    }
}

try {
    $metodo = $_SERVER['REQUEST_METHOD'] ?? '';
    if (!in_array($metodo, ['GET', 'POST'], true)) {
        header('Allow: GET, POST');
        responderPortfolioAdmin(405, ['sucesso' => false, 'mensagem' => 'Método não permitido.']);
    }
    if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 2_300_000) {
        responderPortfolioAdmin(413, ['sucesso' => false, 'mensagem' => 'A imagem enviada ultrapassa o limite de 2 MB.']);
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
        responderPortfolioAdmin(401, ['sucesso' => false, 'mensagem' => 'Entre como administrador para continuar.']);
    }
    $_SESSION['csrf_solicitacoes_admin'] ??= bin2hex(random_bytes(32));
    $csrfSessao = (string) $_SESSION['csrf_solicitacoes_admin'];

    $entrada = [];
    if ($metodo === 'POST') {
        $entrada = $_POST;
        if (!is_string($entrada['csrf'] ?? null) || !hash_equals($csrfSessao, $entrada['csrf'])) {
            responderPortfolioAdmin(403, ['sucesso' => false, 'mensagem' => 'Sessão expirada. Atualize o painel e tente novamente.']);
        }
    }

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    require_once __DIR__ . '/conexao.php';
    $administrador = consultarPortfolioAdmin(
        $conexao,
        'SELECT perfil, ativo FROM usuario WHERE id_usuario = ? LIMIT 1',
        [$idAdministrador]
    )->get_result()->fetch_assoc();
    $perfil = strtolower(trim((string) ($administrador['perfil'] ?? '')));
    if (!$administrador || (int) $administrador['ativo'] !== 1 || !in_array($perfil, ['admin', 'administrador'], true)) {
        $conexao->close();
        responderPortfolioAdmin(403, ['sucesso' => false, 'mensagem' => 'Esta área é exclusiva do administrador.']);
    }

    if ($metodo === 'GET') {
        categoriasPadraoPortfolio($conexao);
        $categorias = consultarPortfolioAdmin(
            $conexao,
            'SELECT id_categoria AS id, codigo, nome, cor
             FROM categoria_portfolio WHERE ativo = 1 ORDER BY ordem, nome'
        )->get_result()->fetch_all(MYSQLI_ASSOC);
        $itens = consultarPortfolioAdmin(
            $conexao,
            'SELECT i.id_item_portfolio AS id, i.id_categoria, i.id_arquivo_capa,
                    i.titulo, i.descricao, i.localidade_publica, i.publicado, i.criado_em,
                    c.nome AS categoria, f.nome_original AS imagem_nome
             FROM item_portfolio i
             INNER JOIN categoria_portfolio c ON c.id_categoria = i.id_categoria
             LEFT JOIN arquivo f ON f.id_arquivo = i.id_arquivo_capa
             ORDER BY i.ordem, i.id_item_portfolio DESC'
        )->get_result()->fetch_all(MYSQLI_ASSOC);
        foreach ($categorias as &$categoria) {
            $categoria['id'] = (string) $categoria['id'];
        }
        unset($categoria);
        foreach ($itens as &$item) {
            $item['id'] = (string) $item['id'];
            $item['id_categoria'] = (string) $item['id_categoria'];
            $item['imagem_url'] = $item['id_arquivo_capa'] === null
                ? ''
                : '../../../backend/portfolio_imagem.php?id=' . (string) $item['id_arquivo_capa'];
            $item['publicado'] = (bool) $item['publicado'];
        }
        unset($item);
        $conexao->close();
        session_write_close();
        responderPortfolioAdmin(200, [
            'sucesso' => true,
            'csrf' => $csrfSessao,
            'categorias' => $categorias,
            'itens' => $itens
        ]);
    }

    $acao = $entrada['acao'] ?? null;
    if (!is_string($acao) || !in_array($acao, ['salvar', 'remover'], true)) {
        $conexao->close();
        responderPortfolioAdmin(422, ['sucesso' => false, 'mensagem' => 'Ação de portfólio inválida.']);
    }
    $conexao->begin_transaction();
    $novoArquivoFisico = '';
    $imagemAntiga = null;
    try {
        $idItem = null;
        if (isset($entrada['id']) && $entrada['id'] !== '') {
            $idItem = filter_var($entrada['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($idItem === false || $idItem === null) {
                throw new DomainException('Selecione um item válido.');
            }
        }

        if ($acao === 'remover') {
            if ($idItem === null) {
                throw new DomainException('Selecione um item para remover.');
            }
            $itemAtual = consultarPortfolioAdmin(
                $conexao,
                'SELECT id_arquivo_capa FROM item_portfolio WHERE id_item_portfolio = ? LIMIT 1 FOR UPDATE',
                [$idItem]
            )->get_result()->fetch_assoc();
            if (!$itemAtual) {
                throw new OutOfBoundsException('O item não foi encontrado. Atualize o portfólio.');
            }
            $imagemAntiga = $itemAtual['id_arquivo_capa'] === null ? null : (int) $itemAtual['id_arquivo_capa'];
            consultarPortfolioAdmin($conexao, 'DELETE FROM item_portfolio WHERE id_item_portfolio = ?', [$idItem]);
            $conexao->commit();
            if ($imagemAntiga !== null) {
                try {
                    limparArquivoPortfolio($conexao, $imagemAntiga);
                } catch (Throwable $erroLimpeza) {
                    error_log('Falha ao limpar imagem removida do portfólio: ' . get_class($erroLimpeza));
                }
            }
            $conexao->close();
            session_write_close();
            responderPortfolioAdmin(200, ['sucesso' => true, 'mensagem' => 'Item removido do portfólio.']);
        }

        $titulo = trim((string) ($entrada['titulo'] ?? ''));
        $descricao = trim((string) ($entrada['descricao'] ?? ''));
        $localidade = trim((string) ($entrada['localidade'] ?? ''));
        $idCategoria = filter_var($entrada['id_categoria'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($titulo === '' || mb_strlen($titulo, 'UTF-8') > 150) {
            throw new DomainException('Informe um título com até 150 caracteres.');
        }
        if (mb_strlen($descricao, 'UTF-8') > 3000 || mb_strlen($localidade, 'UTF-8') > 150) {
            throw new DomainException('A descrição ou a localidade ultrapassa o limite permitido.');
        }
        if ($idCategoria === false || $idCategoria === null) {
            throw new DomainException('Selecione uma categoria válida.');
        }
        $categoria = consultarPortfolioAdmin(
            $conexao,
            'SELECT id_categoria FROM categoria_portfolio WHERE id_categoria = ? AND ativo = 1 LIMIT 1',
            [$idCategoria]
        )->get_result()->fetch_assoc();
        if (!$categoria) {
            throw new DomainException('A categoria selecionada não está disponível.');
        }
        $publicado = isset($entrada['publicado']) && $entrada['publicado'] === '1' ? 1 : 0;

        $imagem = $_FILES['imagem'] ?? null;
        $idNovaImagem = null;
        if (is_array($imagem) && (int) ($imagem['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            if ((int) ($imagem['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($imagem['tmp_name'] ?? ''))) {
                throw new DomainException('Não foi possível receber a imagem. Tente outro arquivo.');
            }
            $tamanho = (int) ($imagem['size'] ?? 0);
            if ($tamanho <= 0 || $tamanho > 2_097_152) {
                throw new DomainException('A imagem deve ter até 2 MB.');
            }
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file((string) $imagem['tmp_name']);
            $extensoes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            if (!is_string($mime) || !isset($extensoes[$mime])) {
                throw new DomainException('Use uma imagem JPG, PNG ou WebP.');
            }
            $diretorioUpload = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'portfolio';
            if (!is_dir($diretorioUpload) && !mkdir($diretorioUpload, 0750, true) && !is_dir($diretorioUpload)) {
                throw new RuntimeException('Não foi possível preparar o armazenamento de imagens.');
            }
            $nomeArquivo = bin2hex(random_bytes(16)) . '.' . $extensoes[$mime];
            $novoArquivoFisico = $diretorioUpload . DIRECTORY_SEPARATOR . $nomeArquivo;
            if (!move_uploaded_file((string) $imagem['tmp_name'], $novoArquivoFisico)) {
                throw new RuntimeException('Não foi possível salvar a imagem enviada.');
            }
            $chave = 'uploads/portfolio/' . $nomeArquivo;
            $nomeOriginal = basename((string) ($imagem['name'] ?? 'imagem'));
            $sha256 = hash_file('sha256', $novoArquivoFisico);
            consultarPortfolioAdmin(
                $conexao,
                'INSERT INTO arquivo (nome_original, chave_armazenamento, mime_type, tamanho_bytes, sha256, id_autor)
                 VALUES (?, ?, ?, ?, ?, ?)',
                [$nomeOriginal, $chave, $mime, $tamanho, $sha256, $idAdministrador]
            );
            $idNovaImagem = (int) $conexao->insert_id;
        }

        if ($idItem !== null) {
            $itemAtual = consultarPortfolioAdmin(
                $conexao,
                'SELECT id_arquivo_capa, publicado_em FROM item_portfolio WHERE id_item_portfolio = ? LIMIT 1 FOR UPDATE',
                [$idItem]
            )->get_result()->fetch_assoc();
            if (!$itemAtual) {
                throw new OutOfBoundsException('O item não foi encontrado. Atualize o portfólio.');
            }
            $imagemAntiga = $itemAtual['id_arquivo_capa'] === null ? null : (int) $itemAtual['id_arquivo_capa'];
            if ($idNovaImagem === null && $imagemAntiga === null) {
                throw new DomainException('Escolha uma imagem de capa para este item.');
            }
            $idImagemFinal = $idNovaImagem ?? $imagemAntiga;
            consultarPortfolioAdmin(
                $conexao,
                'UPDATE item_portfolio
                 SET id_categoria = ?, id_arquivo_capa = ?, titulo = ?, descricao = ?,
                     localidade_publica = ?, publicado = ?,
                     publicado_em = CASE WHEN ? = 1 THEN COALESCE(publicado_em, NOW()) ELSE NULL END
                 WHERE id_item_portfolio = ?',
                [$idCategoria, $idImagemFinal, $titulo, $descricao, $localidade, $publicado, $publicado, $idItem]
            );
            $mensagem = 'Item atualizado no portfólio.';
        } else {
            if ($idNovaImagem === null) {
                throw new DomainException('Escolha uma imagem de capa para o item.');
            }
            $ordem = (int) consultarPortfolioAdmin(
                $conexao,
                'SELECT COALESCE(MAX(ordem), 0) + 1 AS proxima FROM item_portfolio'
            )->get_result()->fetch_assoc()['proxima'];
            consultarPortfolioAdmin(
                $conexao,
                'INSERT INTO item_portfolio
                 (id_categoria, id_arquivo_capa, titulo, descricao, localidade_publica,
                  ordem, publicado, publicado_em)
                 VALUES (?, ?, ?, ?, ?, ?, ?, IF(? = 1, NOW(), NULL))',
                [$idCategoria, $idNovaImagem, $titulo, $descricao, $localidade, $ordem, $publicado, $publicado]
            );
            $mensagem = 'Item adicionado ao portfólio.';
        }

        $conexao->commit();
        if ($idNovaImagem !== null && $imagemAntiga !== null && $idNovaImagem !== $imagemAntiga) {
            try {
                limparArquivoPortfolio($conexao, $imagemAntiga);
            } catch (Throwable $erroLimpeza) {
                error_log('Falha ao limpar imagem antiga do portfólio: ' . get_class($erroLimpeza));
            }
        }
        $conexao->close();
        session_write_close();
        responderPortfolioAdmin(200, ['sucesso' => true, 'mensagem' => $mensagem]);
    } catch (DomainException $erro) {
        $conexao->rollback();
        if ($novoArquivoFisico !== '' && is_file($novoArquivoFisico)) {
            unlink($novoArquivoFisico);
        }
        $conexao->close();
        session_write_close();
        responderPortfolioAdmin(422, ['sucesso' => false, 'mensagem' => $erro->getMessage()]);
    } catch (OutOfBoundsException $erro) {
        $conexao->rollback();
        if ($novoArquivoFisico !== '' && is_file($novoArquivoFisico)) {
            unlink($novoArquivoFisico);
        }
        $conexao->close();
        session_write_close();
        responderPortfolioAdmin(404, ['sucesso' => false, 'mensagem' => $erro->getMessage()]);
    } catch (Throwable $erro) {
        $conexao->rollback();
        if ($novoArquivoFisico !== '' && is_file($novoArquivoFisico)) {
            unlink($novoArquivoFisico);
        }
        throw $erro;
    }
} catch (Throwable $erro) {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    error_log('Falha ao processar portfólio administrativo: ' . get_class($erro) . ', código ' . $erro->getCode());
    responderPortfolioAdmin(500, ['sucesso' => false, 'mensagem' => 'Não foi possível atualizar o portfólio. Tente novamente.']);
}
