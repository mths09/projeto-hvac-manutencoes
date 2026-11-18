<?php

declare(strict_types=1);

/*
 * Provisiona o primeiro administrador em uma instalação vazia.
 * Execute somente dentro do contêiner/servidor: php backend/criar_admin_inicial.php
 * A execução pela web é bloqueada; a senha é lida sem eco no terminal.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

ini_set('display_errors', '0');
ini_set('log_errors', '1');

function lerLinhaObrigatoria(string $rotulo): string
{
    fwrite(STDOUT, $rotulo);
    $valor = fgets(STDIN);
    if ($valor === false) {
        throw new RuntimeException('Não foi possível ler a entrada do terminal.');
    }
    return trim($valor);
}

function lerSenhaOculta(string $rotulo): string
{
    if (DIRECTORY_SEPARATOR !== '/' || !function_exists('exec')) {
        throw new RuntimeException('Execute este provisionamento em um terminal Linux interativo do Railway.');
    }

    fwrite(STDOUT, $rotulo);
    exec('stty -echo 2>/dev/null', $saida, $codigo);
    if ($codigo !== 0) {
        throw new RuntimeException('O terminal não permite leitura segura da senha.');
    }

    try {
        $senha = fgets(STDIN);
    } finally {
        exec('stty echo 2>/dev/null');
        fwrite(STDOUT, PHP_EOL);
    }

    if ($senha === false) {
        throw new RuntimeException('Não foi possível ler a senha do terminal.');
    }
    return rtrim($senha, "\r\n");
}

try {
    require_once __DIR__ . '/conexao.php';

    $nome = lerLinhaObrigatoria('Nome do administrador: ');
    $email = strtolower(lerLinhaObrigatoria('E-mail do administrador: '));
    $telefone = lerLinhaObrigatoria('Telefone com DDD: ');
    $telefoneNormalizado = preg_replace('/\D+/', '', $telefone) ?? '';
    $senha = lerSenhaOculta('Nova senha (mínimo 12 caracteres): ');
    $confirmacao = lerSenhaOculta('Confirme a senha: ');

    if ($nome === '' || mb_strlen($nome, 'UTF-8') > 150) {
        throw new InvalidArgumentException('Informe um nome de até 150 caracteres.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
        throw new InvalidArgumentException('Informe um e-mail válido.');
    }
    if (strlen($telefoneNormalizado) < 10 || strlen($telefoneNormalizado) > 15) {
        throw new InvalidArgumentException('Informe um telefone válido com DDD.');
    }
    if (
        strlen($senha) < 12
        || strlen($senha) > 72
        || !preg_match('/[a-z]/', $senha)
        || !preg_match('/[A-Z]/', $senha)
        || !preg_match('/[0-9]/', $senha)
    ) {
        throw new InvalidArgumentException('A senha deve ter 12 a 72 bytes e conter maiúscula, minúscula e número.');
    }
    if (!hash_equals($senha, $confirmacao)) {
        throw new InvalidArgumentException('As senhas não conferem.');
    }

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $resultadoLock = $conexao->query("SELECT GET_LOCK('hvac_provision_initial_admin', 10) AS adquirido")->fetch_assoc();
    if ((int) ($resultadoLock['adquirido'] ?? 0) !== 1) {
        throw new RuntimeException('Não foi possível reservar o provisionamento do administrador. Tente novamente.');
    }

    try {
        $totalAdmins = $conexao->query(
            "SELECT COUNT(*) AS total FROM usuario WHERE LOWER(TRIM(perfil)) IN ('admin', 'administrador')"
        )->fetch_assoc();
        if ((int) ($totalAdmins['total'] ?? 0) > 0) {
            throw new RuntimeException('Já existe uma conta administrativa. Nenhum dado foi alterado.');
        }

        $stmt = $conexao->prepare('SELECT 1 FROM usuario WHERE email = ? OR telefone_normalizado = ? LIMIT 1');
        $stmt->bind_param('ss', $email, $telefoneNormalizado);
        $stmt->execute();
        $duplicado = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        if ($duplicado) {
            throw new RuntimeException('O e-mail ou telefone já pertence a uma conta. Nenhum dado foi alterado.');
        }

        $hash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);
        if (!is_string($hash)) {
            throw new RuntimeException('Não foi possível proteger a senha.');
        }

        $stmt = $conexao->prepare(
            "INSERT INTO usuario (nome, email, senha_hash, telefone, perfil, ativo)
             VALUES (?, ?, ?, ?, 'admin', 1)"
        );
        $stmt->bind_param('ssss', $nome, $email, $hash, $telefone);
        $stmt->execute();
        $stmt->close();

        fwrite(STDOUT, "Administrador criado com sucesso. Entre pela tela de login do site.\n");
    } finally {
        $conexao->query("SELECT RELEASE_LOCK('hvac_provision_initial_admin')");
    }

    $conexao->close();
} catch (Throwable $erro) {
    fwrite(STDERR, 'Falha: ' . $erro->getMessage() . PHP_EOL);
    exit(1);
}
