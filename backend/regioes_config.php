<?php

declare(strict_types=1);

const REGIOES_ATENDIDAS = [
    ['cidade' => 'Sorocaba', 'uf' => 'SP'],
    ['cidade' => 'Itu', 'uf' => 'SP'],
    ['cidade' => 'Votorantim', 'uf' => 'SP'],
];

function normalizarCidadeRegiao(string $cidade): string
{
    $cidade = mb_strtoupper(trim($cidade), 'UTF-8');

    $cidade = strtr($cidade, [
        'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A',
        'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
        'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
        'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
        'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
        'Ç' => 'C',
    ]);

    $normalizada = preg_replace('/[^A-Z0-9]+/', ' ', $cidade);

    return trim($normalizada ?? '');
}

function buscarRegiaoAtendida(string $cidade, string $uf): ?array
{
    $cidadeNormalizada = normalizarCidadeRegiao($cidade);
    $ufNormalizada = strtoupper(trim($uf));

    foreach (REGIOES_ATENDIDAS as $regiao) {
        if (
            normalizarCidadeRegiao($regiao['cidade']) === $cidadeNormalizada
            && $regiao['uf'] === $ufNormalizada
        ) {
            return $regiao;
        }
    }

    return null;
}