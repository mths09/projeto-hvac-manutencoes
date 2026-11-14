<?php

declare(strict_types=1);
require __DIR__ . '/../backend/email_notificacao.php';

function conferirMime(bool $condicao, string $descricao): void
{
    if (!$condicao) throw new RuntimeException($descricao);
    echo "OK - {$descricao}\n";
}

$texto = "Olá, Ana & João!\nPedido recebido.\nhttps://example.test/painel?a=1&b=2";
$html = '<p>Olá, Ana &amp; João!</p><a href="https://example.test/painel?a=1&amp;b=2">Ver pedido</a>';
$simples = montarConteudoEmailHvac($texto);
conferirMime(str_starts_with($simples, 'Content-Type: text/plain;'), 'mantém e-mails antigos em texto simples');
conferirMime(base64_decode(explode("\r\n\r\n", $simples, 2)[1], true) === $texto, 'preserva acentos e links no texto');

$mime = montarConteudoEmailHvac($html, true, $texto);
preg_match('/boundary="([^"]+)"/', $mime, $match);
$limite = $match[1];
$partes = explode('--' . $limite, $mime);
conferirMime(count($partes) === 4 && $partes[3] === "--\r\n", 'separa duas partes e fecha o boundary');
foreach ([1 => ['text/plain', $texto], 2 => ['text/html', $html]] as $indice => [$tipo, $esperado]) {
    [$cabecalho, $corpo] = explode("\r\n\r\n", ltrim($partes[$indice], "\r\n"), 2);
    conferirMime(str_contains($cabecalho, "Content-Type: {$tipo}; charset=UTF-8"), "ordem e charset de {$tipo}");
    conferirMime(base64_decode($corpo, true) === $esperado, "conteúdo íntegro em {$tipo}");
    foreach (explode("\r\n", trim($corpo)) as $linha) {
        if (strlen($linha) > 76) throw new RuntimeException('Linha Base64 longa demais');
    }
}
conferirMime(!str_contains(explode("\r\n\r\n", $mime, 2)[0], 'Content-Transfer-Encoding'), 'não codifica o multipart inteiro em Base64');
conferirMime(!str_contains(montarConteudoEmailHvac($html, true, $texto), $limite), 'boundary exclusivo por mensagem');
try {
    montarConteudoEmailHvac($html, true);
    throw new RuntimeException('Aceitou HTML sem texto alternativo');
} catch (InvalidArgumentException) {
    echo "OK - exige texto alternativo para HTML\n";
}
$comLogo = montarConteudoEmailHvac('<img src="cid:logo.hvac@hvac.local" alt="HVAC">', true, $texto);
preg_match('/multipart\/related; type="text\/html"; boundary="([^"]+)"/', $comLogo, $relacionado);
conferirMime(isset($relacionado[1]), 'agrupa HTML e logo em multipart/related');
$recursos = explode('--' . $relacionado[1], $comLogo);
conferirMime(str_contains($recursos[1], 'Content-Type: text/html'), 'HTML é o primeiro recurso relacionado');
[$cabecalhoLogo, $corpoLogo] = explode("\r\n\r\n", ltrim($recursos[2], "\r\n"), 2);
conferirMime(str_contains($cabecalhoLogo, 'Content-ID: <logo.hvac@hvac.local>'), 'CID da imagem corresponde ao HTML');
conferirMime(str_contains($cabecalhoLogo, 'Content-Disposition: inline'), 'logo marcada para exibição no corpo');
conferirMime(base64_decode($corpoLogo, true) === file_get_contents(__DIR__ . '/../frontend/assets/logoSite.png'), 'preserva integralmente a logo do site');
echo "Nenhum e-mail foi enviado.\n";
