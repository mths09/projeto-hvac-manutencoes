<?php

declare(strict_types=1);

define('HVAC_AGENDA_ADMIN_TESTE', true);
require __DIR__ . '/../backend/agenda_admin.php';

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

function deveFalhar(callable $acao, string $nome): void
{
    try {
        $acao();
    } catch (InvalidArgumentException) {
        verificar(true, $nome);
        return;
    }
    verificar(false, $nome);
}

$fuso = new DateTimeZone('America/Sao_Paulo');
$dataFutura = (new DateTimeImmutable('tomorrow', $fuso))->modify('+3 days')->format('Y-m-d');
$dataPassada = (new DateTimeImmutable('yesterday', $fuso))->format('Y-m-d');
$dataDistante = (new DateTimeImmutable('today', $fuso))->modify('+31 days')->format('Y-m-d');

verificar(validarDataAgenda($dataFutura) === $dataFutura, 'aceita data de calendário válida');
deveFalhar(fn() => validarDataAgenda('2026-02-30'), 'rejeita data inexistente');

$horario = validarHorarioAgenda('09:00', '10:30', '3', $dataFutura);
verificar($horario[0] === "$dataFutura 09:00:00" && $horario[1] === "$dataFutura 10:30:00" && $horario[2] === 3, 'aceita intervalo futuro e capacidade configurada');
deveFalhar(fn() => validarHorarioAgenda('10:00', '10:00', 1, $dataFutura), 'rejeita fim igual ao início');
deveFalhar(fn() => validarHorarioAgenda('11:00', '10:00', 1, $dataFutura), 'rejeita fim anterior ao início');
deveFalhar(fn() => validarHorarioAgenda('09:00', '10:00', 0, $dataFutura), 'rejeita capacidade zero');
deveFalhar(fn() => validarHorarioAgenda('09:00', '10:00', 101, $dataFutura), 'rejeita capacidade acima do limite');
deveFalhar(fn() => validarHorarioAgenda('09:00', '10:00', 1, $dataPassada), 'rejeita horário no passado');
deveFalhar(fn() => validarHorarioAgenda('09:00', '10:00', 1, $dataDistante), 'rejeita horário além de 30 dias');

echo "\n{$testes} testes aprovados.\n";
