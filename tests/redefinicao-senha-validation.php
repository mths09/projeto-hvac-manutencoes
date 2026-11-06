<?php

declare(strict_types=1);
require __DIR__ . '/../backend/validar_redefinicao_senha.php';

$testes = 0;
function verificar(bool $condicao, string $nome): void
{
    global $testes;
    if (!$condicao) throw new RuntimeException('Falhou: ' . $nome);
    $testes++;
    echo "OK {$testes} - {$nome}\n";
}

verificar(tokenRedefinicaoValido(str_repeat('a', 64)), 'aceita token hexadecimal de 64 caracteres');
verificar(!tokenRedefinicaoValido(str_repeat('A', 64)), 'rejeita token com maiúsculas');
verificar(!tokenRedefinicaoValido(str_repeat('a', 63)), 'rejeita token curto');
verificar(!tokenRedefinicaoValido(['token']), 'rejeita token de tipo incorreto');
verificar(senhaRedefinicaoValida('Senha123'), 'aceita senha no tamanho mínimo');
verificar(senhaRedefinicaoValida(str_repeat('a', 72)), 'aceita senha no tamanho máximo');
verificar(!senhaRedefinicaoValida('curta'), 'rejeita senha curta');
verificar(!senhaRedefinicaoValida(str_repeat('a', 73)), 'rejeita senha acima do limite');
verificar(!senhaRedefinicaoValida("Senha123\0invalida"), 'rejeita senha com byte nulo');
verificar(!senhaRedefinicaoValida(['senha']), 'rejeita senha de tipo incorreto');

echo "\n{$testes} testes aprovados.\n";
