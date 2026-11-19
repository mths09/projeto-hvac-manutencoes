<?php

declare(strict_types=1);

$arquivosDeTeste = [
    'agenda-admin-validation.php',
    'agendamento-cliente-validation.php',
    'email-config-validation.php',
    'email-mime-validation.php',
    'novo-pedido-admin-validation.php',
    'perfil-login-validation.php',
    'recuperacao-schema-validation.php',
    'redefinicao-senha-validation.php',
    'telefone-unico-validation.php',
    'test_regioes_atendidas.php',
    'triagem-validation.php'
];

$totalFalhas = 0;

foreach($arquivosDeTeste as $arquivo) {
    $caminho = __DIR__ . DIRECTORY_SEPARATOR . $arquivo;

    if(!is_file($caminho)) {
        fwrite(STDERR, "FALHOU: arquivo não encontrado: {$arquivo}\n");
        $totalFalhas++;
        continue;
    }

    echo "\n=== {$arquivo} ===\n";

    $comando = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($caminho);
    passthru($comando, $codigoSaida); // executa cada script separadamente e percebe se houve falha

    if($codigoSaida !== 0) {
        fwrite(STDERR, "FALHOU: {$arquivo} terminou com código {$codigoSaida}.\n");
        $totalFalhas++;
    }
}

if($totalFalhas > 0) {
    fwrite(STDERR, "\nResultado: {$totalFalhas} arquivo(s) de teste falharam.\n");
    exit(1); // retorna 1 pois sera utilizado futuramente na automação do github
}

echo "\nTodos os 11 scripts incluídos no runner passaram.\n";
echo "Os 2 testes que precisam de banco ficam para a etapa de isolamento do banco de testes.\n";