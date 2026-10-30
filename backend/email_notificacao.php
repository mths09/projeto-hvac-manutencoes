<?php

declare(strict_types=1);

function configEmailHvac(): array
{
    $config = [
        'host' => 'smtp.gmail.com',
        'port' => 587,
        'security' => 'tls',
        'username' => '',
        'app_password' => '',
        'from_email' => '',
        'from_name' => 'HVAC Lex Company',
    ];
    $local = __DIR__ . '/email_config.local.php';
    if (is_file($local)) {
        $personalizado = require $local;
        if (is_array($personalizado)) $config = array_merge($config, $personalizado);
    }
    foreach (['host' => 'HVAC_SMTP_HOST', 'port' => 'HVAC_SMTP_PORT', 'security' => 'HVAC_SMTP_SECURITY',
        'username' => 'HVAC_SMTP_USER', 'app_password' => 'HVAC_SMTP_APP_PASSWORD', 'from_email' => 'HVAC_EMAIL_FROM',
        'from_name' => 'HVAC_EMAIL_FROM_NAME'] as $campo => $variavel) {
        $valor = getenv($variavel);
        if ($valor !== false && $valor !== '') $config[$campo] = $campo === 'port' ? (int) $valor : $valor;
    }
    return $config;
}

function lerRespostaSmtp($socket, array $codigosAceitos): string
{
    $resposta = '';
    do {
        $linha = fgets($socket, 515);
        if ($linha === false) throw new RuntimeException('Conexão SMTP encerrada inesperadamente.');
        $resposta .= $linha;
    } while (isset($linha[3]) && $linha[3] === '-');
    $codigo = (int) substr($resposta, 0, 3);
    if (!in_array($codigo, $codigosAceitos, true)) {
        throw new RuntimeException('Servidor SMTP recusou uma etapa de envio (código ' . $codigo . ').');
    }
    return $resposta;
}

function comandoSmtp($socket, string $comando, array $codigosAceitos): string
{
    if (fwrite($socket, $comando . "\r\n") === false) throw new RuntimeException('Falha ao enviar comando SMTP.');
    return lerRespostaSmtp($socket, $codigosAceitos);
}

function enviarEmailHvac(string $destinatario, string $assunto, string $mensagem): array
{
    $config = configEmailHvac();
    $emailOrigem = (string) ($config['from_email'] ?: $config['username']);
    if (!filter_var($destinatario, FILTER_VALIDATE_EMAIL) || !filter_var($emailOrigem, FILTER_VALIDATE_EMAIL)
        || trim((string) $config['username']) === '' || trim((string) $config['app_password']) === '') {
        return ['enviado' => false, 'erro' => 'Configure a conta Gmail no arquivo backend/email_config.local.php.'];
    }
    $host = (string) ($config['host'] ?? 'smtp.gmail.com');
    $porta = (int) ($config['port'] ?? 587);
    $seguranca = strtolower((string) ($config['security'] ?? 'tls'));
    if (!in_array($seguranca, ['tls', 'ssl'], true) || $porta < 1 || $porta > 65535) {
        return ['enviado' => false, 'erro' => 'A configuração de segurança SMTP está inválida.'];
    }
    $transporte = $seguranca === 'ssl' ? 'ssl://' : 'tcp://';
    $socket = @stream_socket_client($transporte . $host . ':' . $porta, $errno, $errstr, 12, STREAM_CLIENT_CONNECT);
    if (!$socket) return ['enviado' => false, 'erro' => 'Não foi possível conectar ao SMTP do Gmail. Verifique a rede e a configuração.'];
    stream_set_timeout($socket, 12);
    try {
        lerRespostaSmtp($socket, [220]);
        comandoSmtp($socket, 'EHLO hvac.local', [250]);
        if ($seguranca === 'tls') {
            comandoSmtp($socket, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Não foi possível iniciar TLS com o Gmail.');
            }
            comandoSmtp($socket, 'EHLO hvac.local', [250]);
        }
        comandoSmtp($socket, 'AUTH LOGIN', [334]);
        comandoSmtp($socket, base64_encode((string) $config['username']), [334]);
        comandoSmtp($socket, base64_encode(str_replace(' ', '', (string) $config['app_password'])), [235]);
        comandoSmtp($socket, 'MAIL FROM:<' . $emailOrigem . '>', [250]);
        comandoSmtp($socket, 'RCPT TO:<' . $destinatario . '>', [250, 251]);
        comandoSmtp($socket, 'DATA', [354]);
        $nomeOrigem = '=?UTF-8?B?' . base64_encode((string) $config['from_name']) . '?=';
        $assuntoCodificado = '=?UTF-8?B?' . base64_encode($assunto) . '?=';
        $corpo = chunk_split(base64_encode($mensagem), 76, "\r\n");
        $email = 'Date: ' . gmdate('D, d M Y H:i:s') . " +0000\r\n"
            . 'From: ' . $nomeOrigem . ' <' . $emailOrigem . ">\r\n"
            . 'To: <' . $destinatario . ">\r\n"
            . 'Subject: ' . $assuntoCodificado . "\r\n"
            . "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . $corpo;
        $email = preg_replace('/^\./m', '..', $email);
        if (fwrite($socket, $email . ".\r\n") === false) throw new RuntimeException('Falha ao enviar o conteúdo do e-mail.');
        lerRespostaSmtp($socket, [250]);
        comandoSmtp($socket, 'QUIT', [221]);
        fclose($socket);
        return ['enviado' => true, 'erro' => null];
    } catch (Throwable $erro) {
        fclose($socket);
        error_log('Falha no envio de e-mail SMTP: ' . $erro->getMessage());
        return ['enviado' => false, 'erro' => 'O Gmail não aceitou o envio. Confira a senha de app e as configurações SMTP.'];
    }
}
