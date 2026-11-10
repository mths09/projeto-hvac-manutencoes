<?php

require_once __DIR__ . '/../backend/regioes_config.php';

$casos = [
    ['Sorocaba', 'SP', true],
    ['sorócaba', 'sp', true],
    [' Itu ', 'SP', true],
    ['VOTORANTIM', 'SP', true],
    ['São Paulo', 'SP', false],
    ['Itu', 'MG', false],
];

foreach ($casos as [$cidade, $uf, $esperado]) {
    $resultado = buscarRegiaoAtendida($cidade, $uf) !== null;

    if ($resultado !== $esperado) {
        fwrite(
            STDERR,
            "Falhou: {$cidade}/{$uf}; esperado "
            . ($esperado ? 'atendida' : 'fora da área')
            . PHP_EOL
        );
        exit(1);
    }
}

echo count($casos) . " verificações de região passaram." . PHP_EOL;