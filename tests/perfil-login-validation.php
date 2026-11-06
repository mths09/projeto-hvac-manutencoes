<?php

declare(strict_types=1);
require __DIR__ . '/../backend/perfil_acesso.php';

$testes = 0;
function verificar(bool $condicao, string $nome): void
{
    global $testes;
    if (!$condicao) throw new RuntimeException('Falhou: ' . $nome);
    $testes++;
    echo "OK {$testes} - {$nome}\n";
}

verificar(areaPermitidaLogin('cliente') === 'cliente', 'mantém acesso do cliente');
verificar(areaPermitidaLogin('admin') === 'admin', 'mantém acesso do administrador');
verificar(areaPermitidaLogin(' Administrador ') === 'admin', 'normaliza o perfil administrador');
verificar(areaPermitidaLogin('tecnico') === null, 'bloqueia login de técnico');
verificar(areaPermitidaLogin('desconhecido') === null, 'bloqueia perfil não reconhecido');

echo "\n{$testes} testes aprovados.\n";
