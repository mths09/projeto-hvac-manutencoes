<?php

declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/bootstrap.php';

// Avaliação do atendimento: o cliente dá uma nota de 1 a 5 (e um comentário opcional)
// para um serviço concluído. Cada atendimento só pode ser avaliado uma vez.

const AVALIACAO_COMENTARIO_MAX = 1000;

final class ErroAvaliacao extends RuntimeException
{
    public function __construct(string $mensagem, public int $http)
    {
        parent::__construct($mensagem);
    }
}

function validarEntradaAvaliacao(array $entrada): array
{
    $protocolo = $entrada['protocolo'] ?? null;
    if (!is_string($protocolo) || !preg_match('/^SRV-[a-f0-9]{24}$/D', $protocolo)) {
        throw new ErroAvaliacao('Identificador do pedido inválido.', 422);
    }

    $nota = $entrada['nota'] ?? null;
    if (is_string($nota) && ctype_digit($nota)) {
        $nota = (int) $nota;
    }
    if (!is_int($nota) || $nota < 1 || $nota > 5) {
        throw new ErroAvaliacao('Escolha uma nota de 1 a 5 estrelas.', 422);
    }

    $comentario = $entrada['comentario'] ?? '';
    if (!is_string($comentario)) {
        throw new ErroAvaliacao('Comentário inválido.', 422);
    }
    $comentario = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $comentario) ?? '');
    if (mb_strlen($comentario) > AVALIACAO_COMENTARIO_MAX) {
        throw new ErroAvaliacao('O comentário pode ter até ' . AVALIACAO_COMENTARIO_MAX . ' caracteres.', 422);
    }

    return [$protocolo, $nota, $comentario === '' ? null : $comentario];
}

if (defined('HVAC_AVALIACAO_TESTE')) {
    return;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    responderJson(405, ['sucesso' => false, 'mensagem' => 'Método não permitido.']);
}
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 8192) {
    responderJson(413, ['sucesso' => false, 'mensagem' => 'Os dados enviados ultrapassam o limite permitido.']);
}

$conexao = null;
$transacaoAberta = false;

try {
    iniciarSessaoSegura();

    $idCliente = filter_var($_SESSION['usuario_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($idCliente === false || $idCliente === null) {
        throw new ErroAvaliacao('Entre na sua conta para continuar.', 401);
    }

    try {
        $entrada = json_decode((string) file_get_contents('php://input'), true, 8, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        throw new ErroAvaliacao('Os dados enviados são inválidos.', 400);
    }
    if (!is_array($entrada)) {
        throw new ErroAvaliacao('Os dados enviados são inválidos.', 400);
    }

    exigirCsrf('csrf_dashboard', $entrada['csrf'] ?? null);
    session_write_close();

    [$protocolo, $nota, $comentario] = validarEntradaAvaliacao($entrada);

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    require_once __DIR__ . '/conexao.php';

    $stmt = $conexao->prepare('SELECT nome, perfil, ativo FROM usuario WHERE id_usuario = ? LIMIT 1');
    $stmt->execute([$idCliente]);
    $usuario = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$usuario || (int) $usuario['ativo'] !== 1 || strtolower((string) $usuario['perfil']) !== 'cliente') {
        throw new ErroAvaliacao('Entre na sua conta de cliente para continuar.', 401);
    }

    $conexao->begin_transaction();
    $transacaoAberta = true;

    // Só o dono do pedido encontra o pedido; para outro cliente ele "não existe" (404).
    $stmt = $conexao->prepare(
        "SELECT s.id_solicitacao, s.nome_contato, st.codigo AS status_codigo,
                (SELECT MAX(a.id_agendamento) FROM agendamento a
                 WHERE a.id_solicitacao = s.id_solicitacao AND a.status = 'CONCLUIDO') AS id_agendamento
         FROM solicitacao s
         JOIN status_solicitacao st ON st.id_status = s.id_status_atual
         WHERE s.protocolo = ? AND s.id_cliente = ? AND s.arquivada_em IS NULL
         LIMIT 1 FOR UPDATE"
    );
    $stmt->execute([$protocolo, $idCliente]);
    $pedido = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$pedido) {
        throw new ErroAvaliacao('Pedido não encontrado.', 404);
    }
    if ($pedido['status_codigo'] !== 'CONCLUIDO' || $pedido['id_agendamento'] === null) {
        throw new ErroAvaliacao('Só é possível avaliar um atendimento concluído.', 409);
    }

    $idAgendamento = (int) $pedido['id_agendamento'];

    $stmt = $conexao->prepare('SELECT 1 FROM avaliacao_atendimento WHERE id_agendamento = ? LIMIT 1');
    $stmt->execute([$idAgendamento]);
    $jaAvaliado = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    if ($jaAvaliado) {
        throw new ErroAvaliacao('Este atendimento já foi avaliado. Obrigado!', 409);
    }

    $stmt = $conexao->prepare(
        'INSERT INTO avaliacao_atendimento (id_agendamento, id_autor, nota, comentario) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$idAgendamento, $idCliente, $nota, $comentario]);
    $stmt->close();

    // Avisa a equipe no painel (sino de notificações do admin).
    $mensagemEquipe = 'O cliente ' . $pedido['nome_contato'] . ' avaliou o pedido ' . $protocolo
        . ' com ' . $nota . ($nota === 1 ? ' estrela.' : ' estrelas.');
    $stmt = $conexao->prepare(
        "INSERT INTO notificacao
            (id_destinatario, id_solicitacao, id_agendamento, tipo_evento, titulo, mensagem, canal, status_envio, enviada_em)
         SELECT id_usuario, ?, ?, 'AVALIACAO_RECEBIDA', 'Nova avaliação de atendimento', ?, 'SITE', 'ENVIADO', NOW()
         FROM usuario
         WHERE ativo = 1 AND LOWER(perfil) IN ('admin', 'administrador')"
    );
    $stmt->execute([(int) $pedido['id_solicitacao'], $idAgendamento, $mensagemEquipe]);
    $stmt->close();

    $conexao->commit();
    $transacaoAberta = false;
    $conexao->close();

    responderJson(201, [
        'sucesso' => true,
        'mensagem' => 'Obrigado pela sua avaliação!',
        'avaliacao' => [
            'nota' => $nota,
            'comentario' => (string) ($comentario ?? ''),
            'avaliada_em' => date('Y-m-d H:i:s'),
        ],
    ]);
} catch (Throwable $erro) {
    if ($transacaoAberta && $conexao instanceof mysqli) {
        try {
            $conexao->rollback();
        } catch (Throwable) {
        }
    }
    if ($conexao instanceof mysqli) {
        try {
            $conexao->close();
        } catch (Throwable) {
        }
    }
    if ($erro instanceof ErroAvaliacao) {
        responderJson($erro->http, ['sucesso' => false, 'mensagem' => $erro->getMessage()]);
    }
    // Dois envios ao mesmo tempo: a chave única do banco barra o segundo.
    if ($erro instanceof mysqli_sql_exception && $erro->getCode() === 1062) {
        responderJson(409, ['sucesso' => false, 'mensagem' => 'Este atendimento já foi avaliado. Obrigado!']);
    }
    error_log('Falha ao registrar avaliação: ' . get_class($erro) . ', código ' . $erro->getCode());
    responderJson(500, ['sucesso' => false, 'mensagem' => 'Não foi possível enviar a avaliação agora. Tente novamente.']);
}
