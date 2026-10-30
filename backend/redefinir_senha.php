<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function responderRedefinicao(int $status, array $dados): never
{
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    responderRedefinicao(405, ['sucesso' => false, 'mensagem' => 'Método não permitido.']);
}

$corpo = file_get_contents('php://input');
try {
    $dados = is_string($corpo) ? json_decode($corpo, true, 512, JSON_THROW_ON_ERROR) : null;
} catch (JsonException) {
    $dados = null;
}
if (!is_array($dados)) {
    responderRedefinicao(400, ['sucesso' => false, 'mensagem' => 'Não foi possível ler os dados enviados.']);
}

$token = $dados['token'] ?? null;
$senha = $dados['senha'] ?? null;
if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/D', $token)) {
    responderRedefinicao(422, ['sucesso' => false, 'mensagem' => 'Este link é inválido ou expirou. Solicite outro.']);
}
if (!is_string($senha) || strlen($senha) < 8 || strlen($senha) > 72 || str_contains($senha, "\0")) {
    responderRedefinicao(422, ['sucesso' => false, 'mensagem' => 'A senha precisa ter entre 8 e 72 caracteres.']);
}

try {
    require_once __DIR__ . '/conexao.php';
    $tokenHash = hash('sha256', $token);
    $conexao->begin_transaction();

    $stmt = $conexao->prepare(
        'SELECT id_recuperacao, id_usuario
         FROM recuperacao_senha
         WHERE token_hash = ? AND utilizada_em IS NULL AND expira_em > NOW()
         LIMIT 1 FOR UPDATE'
    );
    $stmt->bind_param('s', $tokenHash);
    $stmt->execute();
    $recuperacao = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$recuperacao || $recuperacao['id_usuario'] === null) {
        $conexao->rollback();
        responderRedefinicao(422, ['sucesso' => false, 'mensagem' => 'Este link é inválido ou expirou. Solicite outro.']);
    }

    $idRecuperacao = (int) $recuperacao['id_recuperacao'];
    $idUsuario = (int) $recuperacao['id_usuario'];
    $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

    $stmt = $conexao->prepare(
        'UPDATE usuario SET senha_hash = ? WHERE id_usuario = ? AND ativo = 1'
    );
    $stmt->bind_param('si', $senhaHash, $idUsuario);
    $stmt->execute();
    $usuarioAtualizado = $stmt->affected_rows > 0;
    $stmt->close();
    if (!$usuarioAtualizado) {
        $conexao->rollback();
        responderRedefinicao(422, ['sucesso' => false, 'mensagem' => 'Não foi possível redefinir a senha desta conta.']);
    }

    $stmt = $conexao->prepare(
        'UPDATE recuperacao_senha SET utilizada_em = NOW()
         WHERE id_recuperacao = ?'
    );
    $stmt->bind_param('i', $idRecuperacao);
    $stmt->execute();
    $stmt->close();

    $stmt = $conexao->prepare(
        'UPDATE recuperacao_senha SET utilizada_em = NOW()
         WHERE id_usuario = ? AND utilizada_em IS NULL'
    );
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $stmt->close();

    $conexao->commit();
    responderRedefinicao(200, [
        'sucesso' => true,
        'mensagem' => 'Senha redefinida. Entre com a nova senha.',
    ]);
} catch (Throwable $erro) {
    if (isset($conexao) && $conexao instanceof mysqli) {
        try {
            $conexao->rollback();
        } catch (Throwable) {
        }
    }
    error_log('Falha ao redefinir senha HVAC: ' . get_class($erro) . ' - código ' . $erro->getCode());
    responderRedefinicao(500, [
        'sucesso' => false,
        'mensagem' => 'Não foi possível redefinir a senha agora. Tente novamente.',
    ]);
}
