<?php

declare(strict_types=1);

define('HVAC_LOGIN_TESTE', true);
require __DIR__ . '/../backend/login.php';
require __DIR__ . '/../backend/conexao.php';

$testes = 0;
function verificar(bool $condicao, string $nome): void
{
    global $testes;
    if (!$condicao) throw new RuntimeException('Falhou: ' . $nome);
    $testes++;
    echo "OK {$testes} - {$nome}\n";
}

// Tabela temporária: substitui somente nesta conexão a tabela real e desaparece ao fechar o script.
$conexao->query("CREATE TEMPORARY TABLE tentativa_login (
    id_tentativa BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    email_hash CHAR(64) NOT NULL,
    ip_hash CHAR(64) NOT NULL,
    tentada_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_email_data (email_hash, tentada_em),
    KEY idx_ip_data (ip_hash, tentada_em)
) ENGINE=InnoDB");

$emailA = hash('sha256', 'cliente-a@example.test');
$emailB = hash('sha256', 'cliente-b@example.test');
$ipA = hash('sha256', '192.0.2.10');
$ipB = hash('sha256', '192.0.2.11');
$ipAntigo = hash('sha256', '192.0.2.12');
$stmt = $conexao->prepare('INSERT INTO tentativa_login (email_hash, ip_hash, tentada_em) VALUES (?, ?, ?)');
$agora = date('Y-m-d H:i:s');
foreach ([$ipA, $ipA, $ipA, $ipB, $ipB] as $ip) {
    $stmt->bind_param('sss', $emailA, $ip, $agora);
    $stmt->execute();
}
$stmt->bind_param('sss', $emailB, $ipA, $agora);
$stmt->execute();
$antiga = date('Y-m-d H:i:s', time() - 360);
$stmt->bind_param('sss', $emailA, $ipAntigo, $antiga);
$stmt->execute();
$stmt->close();

verificar(contarTentativasPorEmail($conexao, $emailA) === 5, 'soma falhas do mesmo e-mail vindas de IPs diferentes');
verificar(contarTentativasPorEmail($conexao, $emailB) === 1, 'não mistura tentativas de contas diferentes');
verificar(loginBloqueadoPorTentativas($conexao, $emailA, $ipAntigo), 'bloqueia a conta ao somar falhas distribuídas em IPs diferentes');
verificar(!loginBloqueadoPorTentativas($conexao, $emailB, $ipB), 'falhas de uma conta não bloqueiam outra');
verificar(contarTentativasPorIp($conexao, $ipA) === 4, 'mantém a contagem independente por IP');
verificar(contarTentativasPorIp($conexao, $ipAntigo) === 0, 'ignora tentativas fora da janela de cinco minutos');
verificar(duracaoBloqueioEmail(4) === 0, 'não bloqueia antes de cinco falhas');
verificar(duracaoBloqueioEmail(5) === 20, 'primeiro bloqueio dura vinte segundos');
verificar(duracaoBloqueioEmail(6) === 40, 'segundo bloqueio dura quarenta segundos');
verificar(duracaoBloqueioEmail(7) === 60 && duracaoBloqueioEmail(20) === 60, 'bloqueios seguintes ficam limitados a um minuto');
verificar(segundosRestantesBloqueio(1000, 20, 1005) === 15, 'calcula o tempo restante com relógio de referência');
verificar(segundosRestantesBloqueio(1000, 20, 1020) === 0, 'libera quando o cooldown termina');
$ipEscalada = hash('sha256', '192.0.2.13');
$stmt = $conexao->prepare('INSERT INTO tentativa_login (email_hash, ip_hash, tentada_em) VALUES (?, ?, ?)');
$agoraEscalada = date('Y-m-d H:i:s');
$stmt->bind_param('sss', $emailA, $ipEscalada, $agoraEscalada);
$stmt->execute();
$esperaSegunda = segundosBloqueioLogin($conexao, $emailA, $ipEscalada);
verificar($esperaSegunda > 30 && $esperaSegunda <= 40, 'a sexta falha inicia o segundo cooldown de quarenta segundos');
$stmt->bind_param('sss', $emailA, $ipEscalada, $agoraEscalada);
$stmt->execute();
$stmt->close();
$esperaTerceira = segundosBloqueioLogin($conexao, $emailA, $ipEscalada);
verificar($esperaTerceira > 50 && $esperaTerceira <= 60, 'a sétima falha inicia o cooldown máximo de um minuto');
$emailC = hash('sha256', 'cliente-c@example.test');
$ipRate = $ipA;
$stmt = $conexao->prepare('INSERT INTO tentativa_login (email_hash, ip_hash, tentada_em) VALUES (?, ?, ?)');
for ($i = 0; $i < 26; $i++) {
    $emailDistribuido = hash('sha256', 'ip-test-account-' . $i);
    $stmt->bind_param('sss', $emailDistribuido, $ipRate, $agora);
    $stmt->execute();
}
$stmt->close();
verificar(contarTentativasPorEmail($conexao, $emailC) === 0, 'e-mail consultado permanece abaixo do limite');
verificar(loginBloqueadoPorTentativas($conexao, $emailC, $ipRate), 'bloqueia pelo limite global do IP mesmo com e-mail abaixo do limite');
$esperaIp = segundosBloqueioLogin($conexao, $emailC, $ipRate);
verificar($esperaIp > 0 && $esperaIp <= 300, 'retorna o tempo até o IP sair da janela de bloqueio');
limparTentativasLoginBemSucedidas($conexao, $emailA);
verificar(contarTentativasPorEmail($conexao, $emailA) === 0, 'login bem-sucedido limpa toda a sequência progressiva da conta');
verificar(contarTentativasPorEmail($conexao, $emailB) === 1, 'limpar uma conta preserva tentativas de outra conta');

$conexao->close();
echo "\n{$testes} testes aprovados.\n";
