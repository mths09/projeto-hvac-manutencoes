<?php

declare(strict_types=1);

define('HVAC_AGENDAMENTO_CLIENTE_TESTE', true);
require __DIR__ . '/../backend/agendamento_cliente.php';

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
    } catch (ErroAgendamentoCliente) {
        verificar(true, $nome);
        return;
    }
    verificar(false, $nome);
}

$protocolo = 'SRV-' . str_repeat('a', 24);
verificar(validarProtocoloAgendamentoCliente($protocolo) === $protocolo, 'aceita protocolo de pedido válido');
deveFalhar(fn() => validarProtocoloAgendamentoCliente('SRV-ABC'), 'rejeita protocolo fora do formato');
deveFalhar(fn() => validarProtocoloAgendamentoCliente('SRV-' . str_repeat('A', 24)), 'rejeita protocolo com caracteres inesperados');

$pedidoSolicitado = ['status_codigo' => 'SOLICITADO'];
$agendamentoPendenteFuturo = ['status' => 'PENDENTE', 'horario_futuro' => 1];
$agendamentoConfirmadoFuturo = ['status' => 'CONFIRMADO', 'horario_futuro' => 1];
$agendamentoPassado = ['status' => 'CONFIRMADO', 'horario_futuro' => 0];
$agendamentoCancelado = ['status' => 'CANCELADO', 'horario_futuro' => 1];

verificar(pedidoClientePodeAlterar($pedidoSolicitado, null), 'permite cancelar pedido solicitado ainda sem horário');
verificar(pedidoClientePodeAlterar($pedidoSolicitado, $agendamentoPendenteFuturo), 'permite alterar horário pendente no futuro');
verificar(pedidoClientePodeAlterar(['status_codigo' => 'AGENDADO'], $agendamentoConfirmadoFuturo), 'permite alterar horário confirmado no futuro');
verificar(!pedidoClientePodeAlterar(['status_codigo' => 'EM_ANDAMENTO'], $agendamentoConfirmadoFuturo), 'impede alterar pedido em andamento');
verificar(!pedidoClientePodeAlterar($pedidoSolicitado, $agendamentoPassado), 'impede alterar horário que já passou');
verificar(!pedidoClientePodeAlterar($pedidoSolicitado, $agendamentoCancelado), 'impede alterar agendamento cancelado');
verificar(!pedidoClientePodeAlterar(['status_codigo' => 'CONCLUIDO'], null), 'impede cancelar pedido concluído');

 echo "\n{$testes} testes aprovados.\n";
