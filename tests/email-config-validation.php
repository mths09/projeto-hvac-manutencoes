<?php

declare(strict_types=1);
require __DIR__ . '/../backend/email_notificacao.php';

$variaveis = [
    'HVAC_SMTP_HOST' => 'smtp.example.test',
    'HVAC_SMTP_PORT' => '587',
    'HVAC_SMTP_SECURITY' => 'tls',
    'HVAC_SMTP_USER' => 'hvac@example.test',
    'HVAC_SMTP_APP_PASSWORD' => 'senha-de-teste-nao-real',
    'HVAC_EMAIL_FROM' => 'hvac@example.test',
    'HVAC_EMAIL_FROM_NAME' => 'HVAC Teste',
    'HVAC_APP_URL' => 'https://hvac.example.test',
];
$anteriores = [];
foreach ($variaveis as $nome => $valor) {
    $anterior = getenv($nome);
    $anteriores[$nome] = $anterior === false ? null : $anterior;
    putenv($nome . '=' . $valor);
}

$config = configEmailHvac();
$testes = 0;
function verificar(bool $condicao, string $nome): void
{
    global $testes;
    if (!$condicao) throw new RuntimeException('Falhou: ' . $nome);
    $testes++;
    echo "OK {$testes} - {$nome}\n";
}

foreach ($variaveis as $nome => $valor) {
    $chave = match ($nome) {
        'HVAC_SMTP_HOST' => 'host', 'HVAC_SMTP_PORT' => 'port',
        'HVAC_SMTP_SECURITY' => 'security', 'HVAC_SMTP_USER' => 'username',
        'HVAC_SMTP_APP_PASSWORD' => 'app_password', 'HVAC_EMAIL_FROM' => 'from_email',
        'HVAC_EMAIL_FROM_NAME' => 'from_name', 'HVAC_APP_URL' => 'app_url',
    };
    $esperado = $chave === 'port' ? (int) $valor : $valor;
    verificar($config[$chave] === $esperado, "carrega {$nome} do ambiente");
}

foreach ($anteriores as $nome => $valor) {
    if ($valor === null) putenv($nome);
    else putenv($nome . '=' . $valor);
}

echo "\n{$testes} testes aprovados. Nenhum e-mail foi enviado.\n";
