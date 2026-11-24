<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/perfil_acesso.php';

header('Cache-Control: no-store');

try {
    iniciarSessaoSegura();
    $id = (int) ($_SESSION['usuario_id'] ?? 0);
    session_write_close();

    $area = null;

    if ($id > 0) {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        require __DIR__ . '/conexao.php';

        $stmt = $conexao->prepare(
            'SELECT perfil FROM usuario WHERE id_usuario = ? AND ativo = 1 LIMIT 1'
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $usuario = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($usuario) {
            $area = areaPermitidaLogin((string) $usuario['perfil']);
        }
    }

    if (isset($_GET['estado'])) {
        responderJson(200, ['sucesso' => true, 'area' => $area]);
    }

    $pagina = match ($area) {
        'admin'   => 'tela-adm.html',
        'cliente' => 'dashboard.html',
        default   => 'login.html',
    };

    header('Location: ../frontend/src/paginas/' . $pagina, true, 302);
    exit;
} catch (Throwable $erro) {
    error_log('Falha ao consultar a área da conta: ' . get_class($erro));
    responderJson(500, [
        'sucesso' => false,
        'mensagem' => 'Não foi possível verificar sua conta agora.',
    ]);
}