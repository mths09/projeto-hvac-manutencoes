<?php

declare(strict_types=1);

function normalizarTelefoneUnico(string $telefone): string
{
    return (string) preg_replace('/\D/', '', $telefone);
}

/** Verifica duplicidade após remover máscara, excluindo a conta em edição. */
function telefoneJaCadastrado(
    mysqli $conexao,
    string $telefone,
    ?int $idUsuarioIgnorado = null
): bool {
    $telefoneNormalizado = normalizarTelefoneUnico($telefone);

    if ($idUsuarioIgnorado === null) {
        $stmt = $conexao->prepare(
            "SELECT 1 FROM usuario
             WHERE REGEXP_REPLACE(telefone, '[^0-9]', '') = ?
             LIMIT 1"
        );
        $stmt->bind_param('s', $telefoneNormalizado);
    } else {
        $stmt = $conexao->prepare(
            "SELECT 1 FROM usuario
             WHERE REGEXP_REPLACE(telefone, '[^0-9]', '') = ?
               AND id_usuario <> ?
             LIMIT 1"
        );
        $stmt->bind_param('si', $telefoneNormalizado, $idUsuarioIgnorado);
    }

    $stmt->execute();
    $duplicado = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    return $duplicado;
}
