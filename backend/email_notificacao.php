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
        'app_url' => '',
        'notify_to' => '',
    ];
    $local = __DIR__ . '/email_config.local.php';
    if (is_file($local)) {
        $personalizado = require $local;
        if (is_array($personalizado)) $config = array_merge($config, $personalizado);
    }
    foreach (['host' => 'HVAC_SMTP_HOST', 'port' => 'HVAC_SMTP_PORT', 'security' => 'HVAC_SMTP_SECURITY',
        'username' => 'HVAC_SMTP_USER', 'app_password' => 'HVAC_SMTP_APP_PASSWORD', 'from_email' => 'HVAC_EMAIL_FROM',
        'from_name' => 'HVAC_EMAIL_FROM_NAME', 'app_url' => 'HVAC_APP_URL', 'notify_to' => 'HVAC_EMAIL_NOTIFY_TO',] as $campo => $variavel) {
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

function montarEmailConfirmacaoCadastroHvac(
    string $nome,
    string $url
): array {
    $nomeHtml = htmlspecialchars(
        $nome,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );

    $urlHtml = htmlspecialchars(
        $url,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );

    $html = <<<HTML
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Confirme seu cadastro</title>
    </head>

    <body style="margin:0;padding:0;background-color:#f3f4f6;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
            style="background-color:#f3f4f6;">

            <tr>
                <td align="center" style="padding:24px 12px;">

                    <table role="presentation" width="100%"
                        cellpadding="0" cellspacing="0"
                            style="max-width:600px;background-color:#ffffff;
                            font-family:Arial,Helvetica,sans-serif;
                            color:#334155;">

                        <!-- Logo -->
                        <tr>
                            <td style="padding:24px;background-color:#08112e;
                                    border-bottom:3px solid #00e5ff;">
                                <img
                                    src="cid:logo.hvac@hvac.local"
                                    width="320"
                                    alt="HVAC Lex Company"
                                    style="display:block;width:320px;
                                        max-width:100%;height:auto;
                                        border:0;color:#ffffff;
                                        font-size:18px;">
                            </td>
                        </tr>

                        <!-- Apresentação -->
                        <tr>
                            <td style="padding:28px 24px 24px;">
                                <h1 style="margin:0 0 16px;font-size:24px;
                                        line-height:1.3;color:#0d1b4b;">
                                    Confirme seu cadastro
                                </h1>

                                <p style="margin:0 0 12px;font-size:16px;
                                        line-height:1.6;">
                                    Olá, {$nomeHtml}!
                                </p>

                                <p style="margin:0;font-size:15px;
                                        line-height:1.6;">
                                    Falta apenas confirmar seu endereço de
                                    e-mail para concluir o cadastro na
                                    HVAC Lex Company.
                                </p>
                            </td>
                        </tr>

                        <!-- Prazo -->
                        <tr>
                            <td style="padding:0 24px 24px;">
                                <table role="presentation" width="100%"
                                        cellpadding="0" cellspacing="0"
                                        style="background-color:#e0faff;
                                            border:1px solid #c4e8ef;">
                                    <tr>
                                        <td style="padding:20px;">
                                            <h2 style="margin:0 0 8px;
                                                    font-size:18px;
                                                    color:#0d1b4b;">
                                                Link válido por 24 horas
                                            </h2>

                                            <p style="margin:0;font-size:15px;
                                                    line-height:1.6;">
                                                Use o botão abaixo dentro
                                                desse prazo para confirmar
                                                seu e-mail.
                                            </p>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>

                        <!-- Ação principal -->
                        <tr>
                            <td style="padding:0 24px 28px;">
                                <a href="{$urlHtml}"
                                    style="display:inline-block;
                                        background-color:#00e5ff;
                                        color:#08112e;
                                        text-decoration:none;
                                        padding:14px 22px;
                                        border-radius:6px;
                                        font-size:15px;
                                        font-weight:bold;">
                                    Confirmar meu cadastro
                                </a>
                            </td>
                        </tr>

                        <!-- Link alternativo -->
                        <tr>
                            <td style="padding:0 24px 24px;">
                                <p style="margin:0 0 8px;font-size:13px;
                                        line-height:1.6;color:#475569;">
                                    Se o botão não funcionar, copie e cole
                                    este endereço no navegador:
                                </p>

                                <p style="margin:0;font-size:12px;
                                        line-height:1.6;
                                        overflow-wrap:anywhere;
                                        word-break:break-all;">
                                    <a href="{$urlHtml}"
                                        style="color:#1565c0;
                                            text-decoration:underline;">
                                        {$urlHtml}
                                    </a>
                                </p>
                            </td>
                        </tr>

                        <!-- Orientação -->
                        <tr>
                            <td style="padding:0 24px 28px;">
                                <p style="margin:0;font-size:13px;
                                        line-height:1.6;color:#475569;">
                                    Se você não solicitou este cadastro,
                                    ignore esta mensagem.
                                </p>
                            </td>
                        </tr>

                        <!-- Rodapé -->
                        <tr>
                            <td style="padding:20px 24px;
                                    border-top:1px solid #e2e8f0;
                                    font-size:12px;color:#64748b;
                                    line-height:1.6;">
                                HVAC Lex Company<br>
                                Mensagem automática de confirmação de cadastro.
                            </td>
                        </tr>

                    </table>
                </td>
            </tr>
        </table>
    </body>
    HTML;

    $texto = "Olá, {$nome}!\n\n"
        . "Falta apenas confirmar seu endereço de e-mail "
        . "para concluir o cadastro na HVAC Lex Company.\n\n"
        . "O link é válido por 24 horas.\n\n"
        . "Confirmar meu cadastro:\n{$url}\n\n"
        . "Se você não solicitou este cadastro, ignore esta mensagem.\n\n"
        . "HVAC Lex Company";

    return [
        'html' => $html,
        'texto' => $texto
    ];
}

function montarEmailAtualizacaoHvac(
    string $protocolo,
    string $status
): array {
    $config = configEmailHvac();
    $baseUrl = rtrim((string) ($config['app_url'] ?? ''), '/');

    $linkPainel = $baseUrl !== ''
        ? $baseUrl . '/frontend/src/paginas/dashboard.html'
        : '';

    $protocoloHtml = htmlspecialchars(
        $protocolo,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );

    $statusHtml = htmlspecialchars(
        $status,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );

    $botaoHtml = '';

    if($linkPainel !== '') {
        $linkHtml = htmlspecialchars(
            $linkPainel,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );

        $botaoHtml = <<<HTML
            <p style="margin:24px 0 0;">
                <a href="{$linkHtml}"
                style="display:inline-block;background-color:#00e5ff;
                        color:#08112e;text-decoration:none;
                        padding:14px 22px;border-radius:6px;
                        font-size:15px;font-weight:bold;">
                    Acompanhar pedido
                </a>
            </p>
        HTML;
    }

    $html = <<<HTML
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Atualização do seu pedido</title>
    </head>

    <body style="margin:0;padding:0;background-color:#f3f4f6;">
        <table role="presentation" width="100%"
                cellpadding="0" cellspacing="0"
                style="background-color: #f3f4f6;">
            <tr>

                <td align="center" style="padding: 24px 12px;">

                    <table role="presentation" width="100%"
                            cellpadding="0" cellspacing="0"
                            style="max-width:600px;background-color:#ffffff;
                                font-family:Arial,Helvetica,sans-serif;
                                color:#334155;">

                        <!-- Identidade da empresa -->
                        <tr>
                            <td style="padding:24px;background-color:#08112e;
                                    border-bottom:3px solid #00e5ff;">
                                <img
                                    src="cid:logo.hvac@hvac.local"
                                    width="320"
                                    alt="HVAC Lex Company"
                                    style="display:block;width:320px;
                                        max-width:100%;height:auto;
                                        border:0;color:#ffffff;
                                        font-size:18px;">
                            </td>
                        </tr>

                        <!-- Introdução -->
                        <tr>
                            <td style="padding:28px 24px 24px;">
                                <h1 style="margin:0 0 16px;font-size:24px;
                                        line-height:1.3;color:#0d1b4b;">
                                    Seu pedido foi atualizado
                                </h1>

                                <p style="margin:0 0 12px;font-size:16px;">
                                    Olá!
                                </p>

                                <p style="margin:0;font-size:15px;
                                        line-height:1.6;">
                                    A situação do seu atendimento mudou.
                                    Confira a atualização abaixo.
                                </p>
                            </td>
                        </tr>

                        <!-- Informações da atualização -->
                        <tr>
                            <td style="padding:0 24px 24px;">
                                <table role="presentation" width="100%"
                                        cellpadding="0" cellspacing="0"
                                        style="background-color:#e0faff;
                                            border:1px solid #c4e8ef;">
                                    <tr>
                                        <td style="padding:20px;">
                                            <p style="margin:0 0 6px;
                                                    font-size:12px;
                                                    color:#475569;">
                                                PROTOCOLO
                                            </p>

                                            <p style="margin:0 0 20px;
                                                    font-size:18px;
                                                    font-weight:bold;
                                                    line-height:1.5;
                                                    color:#0d1b4b;
                                                    overflow-wrap:anywhere;
                                                    word-break:break-all;">
                                                {$protocoloHtml}
                                            </p>

                                            <p style="margin:0 0 6px;
                                                    font-size:12px;
                                                    color:#475569;">
                                                NOVA SITUAÇÃO
                                            </p>

                                            <p style="margin:0;font-size:20px;
                                                    font-weight:bold;
                                                    line-height:1.4;
                                                    color:#0d1b4b;">
                                                {$statusHtml}
                                            </p>
                                        </td>
                                    </tr>

                        <!-- Acesso ao painel -->
                        <tr>
                            <td style="padding:0 24px 28px;">
                                <h2 style="margin:0 0 12px;font-size:18px;
                                    color:#0d1b4b;">
                                    Acompanhe seu atendimento
                                </h2>

                                <p style="margin:0;font-size:15px;
                                    line-height:1.6;">
                                    Acesse sua conta para consultar os
                                    detalhes e as atualizações do pedido.
                                </p>

                                {$botaoHtml}
                            </td>
                        </tr>

                        <tr>
                            <td style="padding:20px 24px;
                                border-top:1px solid #e2e8f0;
                                font-size:12px;color:#64748b;
                                line-height:1.6;">
                                HVAC Lex Company<br>
                                Mensagem automática referente à
                                atualização da sua solicitação.
                            </td>
                        </tr>

                    </table>
                </td>
            </tr>
        </table>
    </body>
    HTML;

    $texto = "Olá! \n\n"
    . "Seu pedido foi atualizado.\n\n"
    . "Protocolo: {$protocolo}\n"
    . "Nova situação: {$status}\n\n"
    . "Acesse sua conta para consultar os detalhes "
    . "e as atualizações do pedido.\n";

    if($linkPainel !== '') {
        $texto .= "\nAcompanhar pedido: {$linkPainel}\n";
    }

    $texto .= "/nHVAC Lex Company\n"
        . "Mensagem Automática referente à atualização da sua solicitação.";

    return [
        'html' => $html,
        'texto' => $texto,
    ];
}



// Mantém o corpo MIME separado do SMTP para permitir testes sem enviar e-mails.
function montarConteudoEmailHvac(string $mensagem, bool $html = false, ?string $textoAlternativo = null): string
{
    $codificar = static fn(string $texto): string => chunk_split(base64_encode($texto), 76, "\r\n");
    if (!$html) {
        return "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n" . $codificar($mensagem);
    }
    if ($textoAlternativo === null || trim($textoAlternativo) === '') {
        throw new InvalidArgumentException('Informe a versão em texto para o e-mail HTML.');
    }

    $parteHtml = "Content-Type: text/html; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: base64\r\n\r\n" . $codificar($mensagem);

    // A logo via CID acompanha o HTML, inclusive quando o site está no localhost.
    if (str_contains($mensagem, 'cid:logo.hvac@hvac.local')) {
        $logo = file_get_contents(__DIR__ . '/../frontend/assets/logoSite.png');
        if ($logo === false) {
            throw new RuntimeException('Não foi possível ler a logo do e-mail.');
        }
        $relacionado = 'hvac_logo_' . bin2hex(random_bytes(18));
        $parteHtml = 'Content-Type: multipart/related; type="text/html"; boundary="'
            . $relacionado . "\"\r\n\r\n"
            . '--' . $relacionado . "\r\n" . $parteHtml
            . '--' . $relacionado . "\r\n"
            . "Content-Type: image/png; name=\"logo-hvac.png\"\r\n"
            . "Content-Transfer-Encoding: base64\r\n"
            . "Content-ID: <logo.hvac@hvac.local>\r\n"
            . "Content-Disposition: inline; filename=\"logo-hvac.png\"\r\n\r\n"
            . $codificar($logo)
            . '--' . $relacionado . "--\r\n";
    }

    $limite = 'hvac_' . bin2hex(random_bytes(18));
    return 'Content-Type: multipart/alternative; boundary="' . $limite . "\"\r\n\r\n"
        . '--' . $limite . "\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: base64\r\n\r\n" . $codificar($textoAlternativo)
        . '--' . $limite . "\r\n"
        . $parteHtml
        . '--' . $limite . "--\r\n";
}

function enviarEmailHvac(string $destinatario, string $assunto, string $mensagem, bool $html = false, ?string $textoAlternativo = null): array
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
        $conteudo = montarConteudoEmailHvac($mensagem, $html, $textoAlternativo);
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
        $email = 'Date: ' . gmdate('D, d M Y H:i:s') . " +0000\r\n"
            . 'From: ' . $nomeOrigem . ' <' . $emailOrigem . ">\r\n"
            . 'To: <' . $destinatario . ">\r\n"
            . 'Subject: ' . $assuntoCodificado . "\r\n"
            . "MIME-Version: 1.0\r\n"
            . $conteudo;
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

