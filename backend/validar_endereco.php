<?php

declare(strict_types=1);

const UFS_VALIDAS = [
    'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA',
    'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO',
];

// Valida e normaliza um endereço. Usado pelo cadastro e pela edição de perfil.
// Devolve ['dados' => [...], 'erros' => [campo => mensagem]].
function validarEndereco(array $entrada): array
{
    // campo => [rótulo, mínimo, máximo, obrigatório]
    $regras = [
        'logradouro'  => ['o logradouro', 2, 180, true],
        'numero'      => ['o número', 1, 20, true],
        'complemento' => ['o complemento', 1, 120, false],
        'bairro'      => ['o bairro', 2, 100, true],
        'cidade'      => ['a cidade', 2, 100, true],
    ];

    $dados = [];
    $erros = [];

    foreach (['cep', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'uf'] as $campo) {
        $valor = $entrada[$campo] ?? '';

        if (!is_string($valor) || !mb_check_encoding($valor, 'UTF-8')) {
            $erros[$campo] = 'Preencha este campo com um texto válido.';
            $valor = '';
        }

        // Tira espaços das pontas e junta os repetidos.
        $dados[$campo] = (string) preg_replace('/\s+/u', ' ', trim($valor));
    }

    // CEP: 12345678 ou 12345-678. Guardamos só os dígitos.
    if (!isset($erros['cep'])) {
        if (preg_match('/^[0-9]{5}-?[0-9]{3}$/D', $dados['cep'])) {
            $dados['cep'] = str_replace('-', '', $dados['cep']);
        } else {
            $erros['cep'] = 'Informe um CEP válido com 8 dígitos.';
        }
    }

    // Campos de texto: mesma regra aplicada em loop.
    foreach ($regras as $campo => [$rotulo, $min, $max, $obrigatorio]) {
        if (isset($erros[$campo])) {
            continue;
        }

        if ($dados[$campo] === '') {
            if ($obrigatorio) {
                $erros[$campo] = "Informe {$rotulo}.";
            }
            continue;
        }

        $tamanho = mb_strlen($dados[$campo], 'UTF-8');

        if (
            $tamanho < $min ||
            $tamanho > $max ||
            preg_match('/[\p{C}<>]/u', $dados[$campo])
        ) {
            $erros[$campo] = "Informe {$rotulo} com {$min} a {$max} caracteres, sem < ou >.";
        }
    }

    // UF: maiúscula e dentro da lista.
    $dados['uf'] = strtoupper($dados['uf']);

    if (!isset($erros['uf']) && !in_array($dados['uf'], UFS_VALIDAS, true)) {
        $erros['uf'] = 'Informe uma UF válida (ex.: SP).';
    }

    return ['dados' => $dados, 'erros' => $erros];
}

// Texto único que a coluna endereco_informado (NOT NULL) recebe.
// É montado a partir das partes, nunca digitado.
function montarEnderecoInformado(array $e): string
{
    $linha = $e['logradouro'] . ', ' . $e['numero'];

    if ($e['complemento'] !== '') {
        $linha .= ' - ' . $e['complemento'];
    }

    return $linha . ', ' . $e['bairro'] . ', ' . $e['cidade'] . '/' . $e['uf']
        . ', CEP ' . substr($e['cep'], 0, 5) . '-' . substr($e['cep'], 5);
}