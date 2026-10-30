<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function responderRecuperacao(int $status, array $dados): never
{
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    responderRecuperacao(405, ['sucesso' => false, 'mensagem' => 'Método não permitido.']);
}

$email = $_POST['email'] ?? null;
if (!is_string($email)) {
    responderRecuperacao(422, ['sucesso' => false, 'mensagem' => 'Informe um e-mail válido.']);
}
$email = strtolower(trim($email));
if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    responderRecuperacao(422, ['sucesso' => false, 'mensagem' => 'Informe um e-mail válido.']);
}

$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'desconhecido');
$emailHash = hash('sha256', $email);
$ipHash = hash('sha256', $ip);
$respostaGenerica = [
    'sucesso' => true,
    'mensagem' => 'Se houver uma conta ativa com esse e-mail, enviaremos um link para redefinir a senha.',
];

try {
    require_once __DIR__ . '/conexao.php';
    require_once __DIR__ . '/email_notificacao.php';

    $stmt = $conexao->prepare(
        'SELECT COUNT(*) AS total FROM recuperacao_senha
         WHERE email_hash = ? AND criada_em >= DATE_SUB(NOW(), INTERVAL 1 HOUR)'
    );
    $stmt->bind_param('s', $emailHash);
    $stmt->execute();
    $porEmail = (int) $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    $stmt = $conexao->prepare(
        'SELECT COUNT(*) AS total FROM recuperacao_senha
         WHERE ip_hash = ? AND criada_em >= DATE_SUB(NOW(), INTERVAL 1 HOUR)'
    );
    $stmt->bind_param('s', $ipHash);
    $stmt->execute();
    $porIp = (int) $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    if ($porEmail >= 3 || $porIp >= 10) {
        responderRecuperacao(200, $respostaGenerica);
    }

    $stmt = $conexao->prepare(
        'SELECT id_usuario, nome, email FROM usuario
         WHERE email = ? AND ativo = 1 LIMIT 1'
    );
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $usuario = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $idUsuario = $usuario ? (int) $usuario['id_usuario'] : null;

    $conexao->begin_transaction();
    if ($idUsuario !== null) {
        $stmt = $conexao->prepare(
            'UPDATE recuperacao_senha SET utilizada_em = NOW()
             WHERE id_usuario = ? AND utilizada_em IS NULL'
        );
        $stmt->bind_param('i', $idUsuario);
        $stmt->execute();
        $stmt->close();
    }

    $stmt = $conexao->prepare(
        'INSERT INTO recuperacao_senha
         (id_usuario, email_hash, ip_hash, token_hash, criada_em, expira_em)
         VALUES (?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 30 MINUTE))'
    );
    $stmt->bind_param('isss', $idUsuario, $emailHash, $ipHash, $tokenHash);
    $stmt->execute();
    $stmt->close();
    $conexao->commit();

    if ($usuario) {
        $config = configEmailHvac();
        $baseUrl = rtrim((string) ($config['app_url'] ?? ''), '/');
        if ($baseUrl === '') {
            $stmt = $conexao->prepare(
                'UPDATE recuperacao_senha SET utilizada_em = NOW() WHERE token_hash = ?'
            );
            $stmt->bind_param('s', $tokenHash);
            $stmt->execute();
            $stmt->close();
            error_log('HVAC_APP_URL não configurada; o link de recuperação não foi enviado.');
            responderRecuperacao(200, $respostaGenerica);
        }
        $link = $baseUrl . '/frontend/src/paginas/redefinir-senha.html?token=' . rawurlencode($token);
        $nome = trim((string) $usuario['nome']);
        $corpo = "Olá, {$nome}.\n\n"
            . "Recebemos um pedido para redefinir a senha da sua conta HVAC.\n"
            . "Use o link abaixo em até 30 minutos:\n{$link}\n\n"
            . "Se você não solicitou a alteração, ignore esta mensagem.";
        $resultadoEmail = enviarEmailHvac((string) $usuario['email'], 'Redefinição de senha - HVAC', $corpo);
        if (!$resultadoEmail['enviado']) {
            $stmt = $conexao->prepare(
                'UPDATE recuperacao_senha SET utilizada_em = NOW() WHERE token_hash = ?'
            );
            $stmt->bind_param('s', $tokenHash);
            $stmt->execute();
            $stmt->close();
            error_log('Falha ao enviar e-mail de recuperação HVAC: ' . (string) ($resultadoEmail['erro'] ?? 'erro desconhecido'));
        }
    }

    responderRecuperacao(200, $respostaGenerica);
} catch (Throwable $erro) {
    if (isset($conexao) && $conexao instanceof mysqli) {
        try {
            $conexao->rollback();
        } catch (Throwable) {
        }
    }
    error_log('Falha na solicitação de recuperação HVAC: ' . get_class($erro) . ' - código ' . $erro->getCode());
    responderRecuperacao(500, [
        'sucesso' => false,
        'mensagem' => 'Não foi possível processar agora. Tente novamente mais tarde.',
    ]);
}

