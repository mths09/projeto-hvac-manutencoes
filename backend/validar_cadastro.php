<?php

declare(strict_types=1);

function validarCadastro(array $entrada): array
{
    $campos = [
        'nome',
        'email',
        'telefone',
        'data_nascimento',
        'senha',
        'confirmar_senha'
    ];

    $dados = [];
    $erros = [];

    foreach ($campos as $campo) {
        $valor = $entrada[$campo] ?? null;

        if (!is_string($valor) || !mb_check_encoding($valor, 'UTF-8')) {
            $erros[$campo] = 'Preencha este campo com um texto válido.';
            $valor = '';
        }

        // Não remover espaços das senhas.
        if (in_array($campo, ['senha', 'confirmar_senha'], true)) {
            $dados[$campo] = $valor;
        } else {
            $dados[$campo] = trim($valor);
        }
    }

    // Nome
    if (
        mb_strlen($dados['nome'], 'UTF-8') < 2 ||
        mb_strlen($dados['nome'], 'UTF-8') > 150 ||
        preg_match('/[\p{C}<>]/u', $dados['nome'])
    ) {
        $erros['nome'] =
            'Informe um nome de 2 a 150 caracteres, sem marcação ou caracteres de controle.';
    }

    // E-mail
    $dados['email'] = strtolower($dados['email']);

    if (
        strlen($dados['email']) > 254 ||
        !filter_var($dados['email'], FILTER_VALIDATE_EMAIL)
    ) {
        $erros['email'] = 'Informe um e-mail válido de até 254 caracteres.';
    }

    // Telefone: aceita a máscara, mas salva somente os números.
    $telefone = preg_replace('/[^0-9]/', '', $dados['telefone']);

    if (
        !preg_match('/^[0-9()\s-]+$/D', $dados['telefone']) ||
        !preg_match('/^[1-9][0-9]{9,10}$/D', $telefone)
    ) {
        $erros['telefone'] =
            'Informe o telefone com DDD: 10 ou 11 dígitos, sem o código do país.';
    }

    $dados['telefone'] = $telefone;

    // Data de nascimento
    $fuso = new DateTimeZone('America/Sao_Paulo');
    $data = false;

    if (
        preg_match(
            '/^[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}$/D',
            $dados['data_nascimento']
        )
    ) {
        $data = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $dados['data_nascimento'],
            $fuso
        );
    }

    if (
        !$data ||
        $data->format('Y-m-d') !== $dados['data_nascimento'] ||
        $data > new DateTimeImmutable('today', $fuso)
    ) {
        $erros['data_nascimento'] =
            'Informe uma data de nascimento válida, que não esteja no futuro.';
    }

    // Senha: o limite de 72 bytes evita truncamento pelo bcrypt.
    if (
        mb_strlen($dados['senha'], 'UTF-8') < 8 ||
        strlen($dados['senha']) > 72 ||
        str_contains($dados['senha'], "\0")
    ) {
        $erros['senha'] =
            'Use uma senha de pelo menos 8 caracteres e no máximo 72 bytes.';
    }

    // Confirmação
    if (
        $dados['confirmar_senha'] === '' ||
        $dados['confirmar_senha'] !== $dados['senha']
    ) {
        $erros['confirmar_senha'] =
            'A confirmação precisa ser igual à senha.';
    }

    // A confirmação não precisa ser salva.
    unset($dados['confirmar_senha']);

    return [
        'dados' => $dados,
        'erros' => $erros
    ];
}