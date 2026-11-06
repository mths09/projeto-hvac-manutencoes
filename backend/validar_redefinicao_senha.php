<?php

declare(strict_types=1);

function tokenRedefinicaoValido(mixed $token): bool
{
    return is_string($token) && preg_match('/^[a-f0-9]{64}$/D', $token) === 1;
}

function senhaRedefinicaoValida(mixed $senha): bool
{
    return is_string($senha)
        && strlen($senha) >= 8
        && strlen($senha) <= 72
        && !str_contains($senha, "\0");
}
