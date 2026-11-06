<?php

declare(strict_types=1);

$schema = file_get_contents(__DIR__ . '/../banco/hvac_db.sql');
$migracao = file_get_contents(__DIR__ . '/../banco/migrations/2026_11_05_create_recuperacao_senha.sql');
if (!is_string($schema) || !is_string($migracao)) {
    throw new RuntimeException('Não foi possível ler os arquivos SQL.');
}

$testes = 0;
function verificar(bool $condicao, string $nome): void
{
    global $testes;
    if (!$condicao) throw new RuntimeException('Falhou: ' . $nome);
    $testes++;
    echo "OK {$testes} - {$nome}\n";
}

foreach (['id_recuperacao', 'email_hash', 'ip_hash', 'token_hash', 'expira_em', 'utilizada_em', 'fk_recuperacao_usuario'] as $campo) {
    verificar(str_contains($schema, $campo), "schema de instalação inclui {$campo}");
    verificar(str_contains($migracao, $campo), "migração incremental inclui {$campo}");
}
verificar(str_contains($migracao, 'CREATE TABLE IF NOT EXISTS'), 'migração é idempotente e não recria a tabela');
verificar(str_contains($migracao, 'ON DELETE CASCADE'), 'migração preserva integridade referencial do usuário');

echo "\n{$testes} testes aprovados.\n";
