<?php

declare(strict_types=1);

function areaPermitidaLogin(string $perfil): ?string
{
    $perfil = strtolower(trim($perfil));
    if (in_array($perfil, ['admin', 'administrador'], true)) {
        return 'admin';
    }
    if ($perfil === 'cliente') {
        return 'cliente';
    }

    // O sistema não oferece uma área de login para técnicos.
    return null;
}
