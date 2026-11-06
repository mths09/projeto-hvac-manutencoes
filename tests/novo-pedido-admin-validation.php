<?php

declare(strict_types=1);

define('HVAC_SOLICITACOES_ADMIN_TESTE', true);
require __DIR__ . '/../backend/solicitacoes_admin.php';

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
    } catch (DomainException) {
        verificar(true, $nome);
        return;
    }
    verificar(false, $nome);
}

$pedido = [
    'nome' => '  Ana Silva  ',
    'telefone' => '(15) 99999-1234',
    'endereco' => 'Rua das Flores, 123 — Centro',
    'descricao' => 'Ar condicionado não está gelando.',
    'id_tipo_servico' => '1',
    'id_disponibilidade' => '22',
    'prioridade' => 'urgente',
];

$normalizado = normalizarNovoPedidoAdmin($pedido);
verificar($normalizado['nome'] === 'Ana Silva', 'remove espaços extras do nome');
verificar($normalizado['id_tipo_servico'] === 1 && $normalizado['id_disponibilidade'] === 22, 'converte serviço e horário em IDs inteiros');
verificar(!array_key_exists('id_tecnico', $normalizado) && $normalizado['urgente'] === true, 'não inclui atribuição de técnico e mantém prioridade urgente');

$prioridadeNormal = $pedido;
$prioridadeNormal['prioridade'] = 'normal';
$prioridadeNormal['telefone'] = '';
$normalizadoSemTecnico = normalizarNovoPedidoAdmin($prioridadeNormal);
verificar(!array_key_exists('id_tecnico', $normalizadoSemTecnico) && $normalizadoSemTecnico['urgente'] === false, 'mantém pedido manual sem técnico e prioridade normal');

$invalido = $pedido;
$invalido['nome'] = '   ';
deveFalhar(fn() => normalizarNovoPedidoAdmin($invalido), 'exige nome do cliente');

$invalido = $pedido;
$invalido['telefone'] = 'telefone-invalido';
deveFalhar(fn() => normalizarNovoPedidoAdmin($invalido), 'rejeita telefone inválido');

$invalido = $pedido;
$invalido['id_disponibilidade'] = '0';
deveFalhar(fn() => normalizarNovoPedidoAdmin($invalido), 'rejeita ID de horário inválido');

$invalido = $pedido;
$invalido['prioridade'] = 'critica';
deveFalhar(fn() => normalizarNovoPedidoAdmin($invalido), 'rejeita prioridade fora das opções');

$invalido = $pedido;
$invalido['endereco'] = '';
deveFalhar(fn() => normalizarNovoPedidoAdmin($invalido), 'exige endereço do atendimento');

echo "\n{$testes} testes aprovados.\n";
