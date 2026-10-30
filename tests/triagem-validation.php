<?php

declare(strict_types=1);

define('HVAC_AGENDAMENTO_TESTE', true);
require __DIR__ . '/../backend/agendamento.php';

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

function deveFalhar(callable $acao, int $httpEsperado, string $nome): void
{
    try {
        $acao();
    } catch (ErroAgendamento $erro) {
        verificar($erro->getCode() === $httpEsperado, $nome);
        return;
    }
    verificar(false, $nome);
}

$questionario = [
    'perguntas' => [
        ['codigo' => 'SERVICO', 'enunciado' => 'O que você precisa?', 'tipo_resposta' => 'OPCAO_UNICA', 'obrigatoria' => true,
            'opcoes' => [['codigo' => 'INSTALACAO'], ['codigo' => 'CORRETIVA']]],
        ['codigo' => 'EQUIPAMENTO', 'enunciado' => 'Qual equipamento?', 'tipo_resposta' => 'OPCAO_UNICA', 'obrigatoria' => true,
            'opcoes' => [['codigo' => 'SPLIT'], ['codigo' => 'OUTRO']]],
        ['codigo' => 'SITUACAO', 'enunciado' => 'Qual situação?', 'tipo_resposta' => 'OPCAO_UNICA', 'obrigatoria' => true,
            'opcoes' => [['codigo' => 'NAO_RESFRIA'], ['codigo' => 'NAO_LIGA']]],
        ['codigo' => 'INICIO', 'enunciado' => 'Quando começou?', 'tipo_resposta' => 'OPCAO_UNICA', 'obrigatoria' => true,
            'opcoes' => [['codigo' => 'HOJE'], ['codigo' => 'ATE_SEMANA']]],
    ],
];

$respostas = [
    'SERVICO' => 'INSTALACAO',
    'EQUIPAMENTO' => 'SPLIT',
    'SITUACAO' => 'NAO_RESFRIA',
    'INICIO' => 'HOJE',
];
$json = json_encode($respostas, JSON_THROW_ON_ERROR);

verificar(normalizarRespostasTriagem($json, $questionario) === $respostas,
    'aceita respostas completas para as opções do questionário');

$incompletas = $respostas;
unset($incompletas['INICIO']);
deveFalhar(fn() => normalizarRespostasTriagem($incompletas, $questionario), 422,
    'rejeita triagem com pergunta obrigatória sem resposta');

$opcaoInvalida = $respostas;
$opcaoInvalida['EQUIPAMENTO'] = 'CAMARA_FRIA';
deveFalhar(fn() => normalizarRespostasTriagem($opcaoInvalida, $questionario), 422,
    'rejeita opção que não pertence à pergunta');

$perguntaInvalida = $respostas;
$perguntaInvalida['PERGUNTA_INVENTADA'] = 'OUTRO';
deveFalhar(fn() => normalizarRespostasTriagem($perguntaInvalida, $questionario), 422,
    'rejeita pergunta enviada pelo navegador que não existe no banco');

deveFalhar(fn() => normalizarRespostasTriagem('{resposta inválida', $questionario), 422,
    'rejeita JSON de respostas malformado');

$questionarioIncompleto = $questionario;
array_pop($questionarioIncompleto['perguntas']);
deveFalhar(fn() => normalizarRespostasTriagem($respostas, $questionarioIncompleto), 503,
    'detecta questionário com menos de quatro perguntas');

echo "\n{$testes} testes aprovados.\n";
