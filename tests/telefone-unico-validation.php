<?php

declare(strict_types=1);

require __DIR__ . '/../backend/telefone_unico.php';

$testes = 0;

function verificar(bool $condicao, string $nome): void
{
    global $testes;
    if (!$condicao) {
        throw new RuntimeException('Falhou: ' . $nome);
    }
    $testes++;
    echo "OK {$testes} - {$nome}\n";
}

verificar(
    normalizarTelefoneUnico('(15) 99999-1234') === '15999991234',
    'normaliza telefone com máscara'
);
verificar(
    normalizarTelefoneUnico('15 3333.1234') === '1533331234',
    'normaliza telefone fixo com pontuação'
);
verificar(
    normalizarTelefoneUnico('15999991234') === '15999991234',
    'mantém telefone já normalizado'
);

echo "\n{$testes} testes aprovados.\n";
