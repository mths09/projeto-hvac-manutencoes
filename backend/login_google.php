<?php

declare(strict_types=1);
require_once __DIR__ . '/google_auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    responderGoogle(405, ['sucesso' => false, 'mensagem' => 'Método não permitido.']);
}
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 16384) {
    responderGoogle(413, ['sucesso' => false, 'mensagem' => 'A solicitação é muito grande.']);
}

try {
    iniciarSessaoGoogle();
    $entrada = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($entrada)) {
        responderGoogle(400, ['sucesso' => false, 'mensagem' => 'Solicitação inválida.']);
    }
    $csrfSessao = (string) ($_SESSION['csrf_google'] ?? '');
    $csrfEnviado = $entrada['csrf'] ?? null;
    if ($csrfSessao === '' || !is_string($csrfEnviado) || !hash_equals($csrfSessao, $csrfEnviado)) {
        responderGoogle(403, ['sucesso' => false, 'mensagem' => 'A sessão expirou. Atualize a página e tente novamente.']);
    }
    $clientId = clientIdGoogle();
    $credential = $entrada['credential'] ?? null;
    if ($clientId === '' || !is_string($credential) || strlen($credential) > 12000) {
        responderGoogle(422, ['sucesso' => false, 'mensagem' => 'Credencial Google inválida.']);
    }
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        throw new RuntimeException('Biblioteca Google ausente.');
    }
    require_once $autoload;
    $client = new Google_Client(['client_id' => $clientId]);
    $payload = $client->verifyIdToken($credential);
    $nonceSessao = (string) ($_SESSION['nonce_google'] ?? '');
    if (!is_array($payload)
        || !is_string($payload['sub'] ?? null)
        || !is_string($payload['email'] ?? null)
        || !is_string($payload['nonce'] ?? null)
        || $nonceSessao === ''
        || !hash_equals($nonceSessao, $payload['nonce'])) {
        responderGoogle(401, ['sucesso' => false, 'mensagem' => 'Não foi possível validar sua conta Google. Atualize a página e tente novamente.']);
    }
    unset($_SESSION['nonce_google'], $_SESSION['csrf_google']);

    $email = strtolower(trim($payload['email']));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        responderGoogle(422, ['sucesso' => false, 'mensagem' => 'O Google não retornou um endereço de e-mail válido.']);
    }

    require_once __DIR__ . '/conexao.php';
    $stmt = $conexao->prepare('SELECT id_usuario, nome, email, perfil, ativo FROM usuario WHERE google_sub = ? LIMIT 1');
    $stmt->bind_param('s', $payload['sub']);
    $stmt->execute();
    $usuario = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($usuario) {
        if (strtolower((string) $usuario['perfil']) !== 'cliente' || (int) $usuario['ativo'] !== 1) {
            responderGoogle(403, ['sucesso' => false, 'mensagem' => 'Esta conta não pode entrar com Google.']);
        }
        estabelecerSessaoGoogle($usuario);
        session_write_close();
        responderGoogle(200, ['sucesso' => true, 'area' => 'cliente', 'destino' => './dashboard.html']);
    }

    if (!emailGoogleAutoritativo($email, $payload)) {
        responderGoogle(422, ['sucesso' => false, 'mensagem' => 'Use uma conta Gmail ou Google Workspace com e-mail verificado para continuar.']);
    }

    if (!$usuario) {
        $stmt = $conexao->prepare('SELECT id_usuario, nome, email, perfil, ativo, google_sub FROM usuario WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $usuario = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($usuario) {
            if (strtolower((string) $usuario['perfil']) !== 'cliente' || (int) $usuario['ativo'] !== 1) {
                responderGoogle(403, ['sucesso' => false, 'mensagem' => 'Esta conta não pode entrar com Google. Use o acesso por e-mail e senha.']);
            }
            if (!empty($usuario['google_sub']) && !hash_equals((string) $usuario['google_sub'], $payload['sub'])) {
                responderGoogle(409, ['sucesso' => false, 'mensagem' => 'Esta conta já está vinculada a outro acesso Google. Entre usando e-mail e senha.']);
            }
            if (empty($usuario['google_sub'])) {
                $stmt = $conexao->prepare('UPDATE usuario SET google_sub = ? WHERE id_usuario = ? AND google_sub IS NULL');
                $idUsuario = (int) $usuario['id_usuario'];
                $stmt->bind_param('si', $payload['sub'], $idUsuario);
                $stmt->execute();
                $vinculoFeito = $stmt->affected_rows === 1;
                $stmt->close();
                if (!$vinculoFeito) {
                    responderGoogle(409, ['sucesso' => false, 'mensagem' => 'Não foi possível vincular esta conta Google. Entre usando e-mail e senha.']);
                }
            }
        }
    }

    if ($usuario) {
        if (strtolower((string) $usuario['perfil']) !== 'cliente' || (int) $usuario['ativo'] !== 1) {
            responderGoogle(403, ['sucesso' => false, 'mensagem' => 'Esta conta não pode entrar com Google.']);
        }
        estabelecerSessaoGoogle($usuario);
        session_write_close();
        responderGoogle(200, ['sucesso' => true, 'area' => 'cliente', 'destino' => './dashboard.html']);
    }

    $nome = trim((string) ($payload['name'] ?? ''));
    $_SESSION['cadastro_google'] = [
        'sub' => $payload['sub'],
        'email' => $email,
        'nome' => $nome,
        'expira_em' => time() + 600,
    ];
    $_SESSION['csrf_cadastro_google'] = bin2hex(random_bytes(32));
    session_write_close();
    responderGoogle(200, ['sucesso' => true, 'cadastro_google' => true, 'destino' => './cadastro.html?google=1']);
} catch (mysqli_sql_exception $erro) {
    error_log('Falha no banco durante login Google: código ' . $erro->getCode());
    responderGoogle(503, ['sucesso' => false, 'mensagem' => 'Não foi possível acessar a conta. Tente novamente mais tarde.']);
} catch (Throwable $erro) {
    error_log('Falha no login Google: ' . get_class($erro) . ' código ' . $erro->getCode());
    responderGoogle(500, ['sucesso' => false, 'mensagem' => 'Não foi possível realizar o login Google.']);
}
