<?php

declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function responderCadastro(int $status, string $mensagem, array $erros = []): never
{
    http_response_code($status);
    echo json_encode([
        'sucesso' => $status === 202,
        'mensagem' => $mensagem,
        'erros' => (object) $erros,
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    responderCadastro(405, 'Método não permitido. Envie o formulário de cadastro.');
}
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 16384) {
    responderCadastro(413, 'Os dados enviados ultrapassam o limite do cadastro.');
}

try {
    require_once __DIR__ . '/validar_cadastro.php';
    require_once __DIR__ . '/validar_endereco.php';
    require_once __DIR__ . '/cadastro_confirmacao.php';

    $resultadoCadastro = validarCadastro($_POST);
    $resultadoEndereco = validarEndereco($_POST);
    $erros = $resultadoCadastro['erros'] + $resultadoEndereco['erros'];
    if ($erros !== []) {
        responderCadastro(422, implode(' ', $erros), $erros);
    }
    if (($_POST['aceite_privacidade'] ?? null) !== '1') {
        responderCadastro(422, 'É necessário aceitar a Política de Privacidade.');
    }

    $dados = $resultadoCadastro['dados'];
    $endereco = $resultadoEndereco['dados'];
    $senhaHash = password_hash($dados['senha'], PASSWORD_BCRYPT, ['cost' => 12]);
    if (!is_string($senhaHash)) {
        throw new RuntimeException('Não foi possível proteger a senha.');
    }

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    require_once __DIR__ . '/conexao.php';

    $stmt = $conexao->prepare('SELECT 1 FROM usuario WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $dados['email']);
    $stmt->execute();
    $emailCadastrado = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    if ($emailCadastrado) {
        responderCadastro(409, 'Este e-mail já está cadastrado.', [
            'email' => 'Use outro e-mail para cadastrar uma nova conta.',
        ]);
    }

    if (telefoneJaCadastrado($conexao, $dados['telefone'])) {
        responderCadastro(409, 'Este telefone já está cadastrado.', [
            'telefone' => 'Use outro telefone para cadastrar uma nova conta.',
        ]);
    }

    $ipHash = hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'desconhecido'));
    $userAgentHash = hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? 'desconhecido'));
    $resultado = armazenarCadastroPendenteEEnviar(
        $conexao,
        $dados,
        $endereco,
        $senhaHash,
        $ipHash,
        $userAgentHash
    );

    $mensagem = !empty($resultado['ja_enviado'])
        ? 'Se o endereço puder ser confirmado, já enviamos um link. Confira sua caixa de entrada e o spam.'
        : 'Enviamos um link de confirmação para seu e-mail. Abra-o em até 24 horas para concluir o cadastro.';
    responderCadastro(202, $mensagem);
} catch (ErroCadastroConfirmacao $erro) {
    responderCadastro($erro->http, $erro->getMessage(), $erro->erros);
} catch (mysqli_sql_exception $erro) {
    if ((int) $erro->getCode() === 1062) {
        if (str_contains($erro->getMessage(), 'un_cadastro_pendente_telefone')) {
            responderCadastro(409, 'Este telefone já está em confirmação para outra conta.', [
                'telefone' => 'Use outro telefone para cadastrar uma nova conta.',
            ]);
        }
        if (str_contains($erro->getMessage(), 'un_usuario_telefone_normalizado')) {
            responderCadastro(409, 'Este telefone já está cadastrado.', [
                'telefone' => 'Use outro telefone para cadastrar uma nova conta.',
            ]);
        }
        responderCadastro(409, 'Este e-mail já está cadastrado.', [
            'email' => 'Use outro e-mail para cadastrar uma nova conta.',
        ]);
    }
    error_log('Falha no banco durante solicitação de cadastro: código ' . $erro->getCode());
    responderCadastro(503, 'Não foi possível acessar o banco. Tente novamente mais tarde.');
} catch (Throwable $erro) {
    error_log(sprintf(
        'Falha interna na solicitação de cadastro: class=%s code=%s file=%s line=%d',
        get_class($erro), (string) $erro->getCode(), $erro->getFile(), $erro->getLine()
    ));
    responderCadastro(500, 'Não foi possível iniciar o cadastro. Tente novamente.');
}