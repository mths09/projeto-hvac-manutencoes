<?php

declare(strict_types=1);

require __DIR__ . '/../backend/telefone_unico.php';
require __DIR__ . '/../backend/conexao.php';

$conexao->query(
    'CREATE TEMPORARY TABLE usuario (
        id_usuario BIGINT NOT NULL PRIMARY KEY,
        telefone VARCHAR(25) NOT NULL
    ) ENGINE=InnoDB'
);
$conexao->query(
    "INSERT INTO usuario (id_usuario, telefone) VALUES
        (1, '15999991234'),
        (2, '1533331234')"
);

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
    telefoneJaCadastrado($conexao, '(15) 99999-1234'),
    'detecta duplicata quando a máscara é diferente'
);
verificar(
    !telefoneJaCadastrado($conexao, '(15) 99999-1234', 1),
    'ignora a própria conta ao editar o perfil'
);
verificar(
    !telefoneJaCadastrado($conexao, '15988887777'),
    'permite telefone ainda não cadastrado'
);

$conexao->query(
    "ALTER TABLE usuario
       ADD COLUMN telefone_normalizado VARCHAR(25)
         GENERATED ALWAYS AS (NULLIF(REGEXP_REPLACE(telefone, '[^0-9]', ''), '')) VIRTUAL,
       ADD UNIQUE KEY un_usuario_telefone_normalizado (telefone_normalizado)"
);

$duplicataBloqueada = false;
try {
    $conexao->query(
        "INSERT INTO usuario (id_usuario, telefone) VALUES (3, '(15) 99999-1234')"
    );
} catch (mysqli_sql_exception $erro) {
    $duplicataBloqueada =
        $erro->getCode() === 1062 &&
        str_contains($erro->getMessage(), 'un_usuario_telefone_normalizado');
}

verificar($duplicataBloqueada, 'índice único bloqueia duplicata com máscara diferente');

echo "\n{$testes} testes aprovados.\n";
