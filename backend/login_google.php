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

    $stmt = $conexao->prepare('SELECT id_usuario, nome, email, telefone, perfil, ativo FROM usuario WHERE google_sub = ? LIMIT 1');
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
        $stmt = $conexao->prepare('SELECT id_usuario, nome, email, telefone, perfil, ativo, google_sub FROM usuario WHERE email = ? LIMIT 1');
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

    // Primeiro acesso: cria a conta só com nome e e-mail do Google. Telefone,
    // endereço e nascimento o cliente completa depois, pelo aviso no dashboard.
    // Telefone vazio vira NULL em telefone_normalizado, sem violar o índice único.
    $nome = preg_replace('/\s+/u', ' ', trim((string) ($payload['name'] ?? ''))) ?? '';
    if (mb_strlen($nome, 'UTF-8') < 2 || preg_match('/[\p{C}<>]/u', $nome)) {
        $nome = (string) strstr($email, '@', true);
    }
    $nome = mb_substr($nome, 0, 150, 'UTF-8');
    $senhaHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT, ['cost' => 12]);
    if (!is_string($senhaHash)) {
        throw new RuntimeException('Não foi possível proteger a conta.');
    }
    $telefoneVazio = '';
    $perfil = 'cliente';
    $versaoPolitica = '1.0';
    $ipHash = hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'desconhecido'));
    $userAgentHash = hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? 'desconhecido'));
    $aceitoEm = date('Y-m-d H:i:s');

    $conexao->begin_transaction();
    try {
        $stmt = $conexao->prepare('INSERT INTO usuario (nome,email,senha_hash,telefone,data_nascimento,perfil,google_sub) VALUES (?,?,?,?,NULL,?,?)');
        $stmt->bind_param('ssssss', $nome, $email, $senhaHash, $telefoneVazio, $perfil, $payload['sub']);
        $stmt->execute();
        $idUsuario = (int) $conexao->insert_id;
        $stmt->close();

        // O aviso da política fica junto ao botão do Google no login.
        $stmt = $conexao->prepare('INSERT INTO aceite_politica (id_usuario,versao,ip_hash,user_agent_hash,aceito_em) VALUES (?,?,?,?,?)');
        $stmt->bind_param('issss', $idUsuario, $versaoPolitica, $ipHash, $userAgentHash, $aceitoEm);
        $stmt->execute();
        $stmt->close();
        $conexao->commit();
    } catch (Throwable $erro) {
        $conexao->rollback();
        if ($erro instanceof mysqli_sql_exception && (int) $erro->getCode() === 1062) {
            // Outro envio simultâneo criou a conta: basta entrar de novo.
            responderGoogle(409, ['sucesso' => false, 'mensagem' => 'Sua conta acabou de ser criada. Clique em continuar com Google novamente.']);
        }
        throw $erro;
    }

    estabelecerSessaoGoogle(['id_usuario' => $idUsuario, 'nome' => $nome, 'email' => $email]);
    session_write_close();
    responderGoogle(200, ['sucesso' => true, 'area' => 'cliente', 'destino' => './dashboard.html']);
} catch (mysqli_sql_exception $erro) {
    error_log('Falha no banco durante login Google: código ' . $erro->getCode());
    responderGoogle(503, ['sucesso' => false, 'mensagem' => 'Não foi possível acessar a conta. Tente novamente mais tarde.']);
} catch (Throwable $erro) {
    error_log('Falha no login Google: ' . get_class($erro) . ' código ' . $erro->getCode());
    responderGoogle(500, ['sucesso' => false, 'mensagem' => 'Não foi possível realizar o login Google.']);
}
