<?php

declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Método não permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    require_once __DIR__ . '/conexao.php';
    $stmt = $conexao->prepare(
        'SELECT i.id_item_portfolio AS id, i.titulo, i.descricao,
                i.localidade_publica AS localidade, c.nome AS categoria,
                c.cor AS cor_categoria, i.id_arquivo_capa
         FROM item_portfolio i
         INNER JOIN categoria_portfolio c ON c.id_categoria = i.id_categoria AND c.ativo = 1
         LEFT JOIN arquivo f ON f.id_arquivo = i.id_arquivo_capa
         WHERE i.publicado = 1 AND i.id_arquivo_capa IS NOT NULL AND f.id_arquivo IS NOT NULL
         ORDER BY i.ordem, i.id_item_portfolio DESC'
    );
    $stmt->execute();
    $resultado = $stmt->get_result();
    $itens = [];
    while ($linha = $resultado->fetch_assoc()) {
        $itens[] = [
            'id' => (string) $linha['id'],
            'titulo' => (string) $linha['titulo'],
            'descricao' => (string) ($linha['descricao'] ?? ''),
            'localidade' => (string) ($linha['localidade'] ?? ''),
            'categoria' => (string) $linha['categoria'],
            'cor_categoria' => (string) ($linha['cor_categoria'] ?? '#00d8f3'),
            'imagem' => './backend/portfolio_imagem.php?id=' . (string) $linha['id_arquivo_capa']
        ];
    }
    $conexao->close();
    echo json_encode(['sucesso' => true, 'itens' => $itens], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (Throwable $erro) {
    error_log('Falha ao carregar portfólio público: ' . get_class($erro) . ', código ' . $erro->getCode());
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Não foi possível carregar o portfólio.'], JSON_UNESCAPED_UNICODE);
}
