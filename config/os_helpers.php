<?php
/**
 * Funcoes auxiliares compartilhadas pelos endpoints de OS:
 * historico (timeline), notificacoes (substitui push/FCM) e controle de acesso.
 */

declare(strict_types=1);

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/push.php';

function registrarHistorico(PDO $pdo, int $osId, ?int $usuarioId, string $acao, ?string $detalhe = null): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO historico_os (os_id, usuario_id, acao, detalhe) VALUES (:os_id, :usuario_id, :acao, :detalhe)'
    );
    $stmt->execute([
        'os_id' => $osId,
        'usuario_id' => $usuarioId,
        'acao' => $acao,
        'detalhe' => $detalhe,
    ]);
}

function criarNotificacao(PDO $pdo, int $usuarioId, ?int $osId, string $tipo, string $titulo, string $mensagem): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO notificacoes (usuario_id, os_id, tipo, titulo, mensagem) VALUES (:usuario_id, :os_id, :tipo, :titulo, :mensagem)'
    );
    $stmt->execute([
        'usuario_id' => $usuarioId,
        'os_id' => $osId,
        'tipo' => $tipo,
        'titulo' => $titulo,
        'mensagem' => $mensagem,
    ]);
}

/**
 * Envia notificacao para todos os gestores ativos (in-app + push FCM).
 */
function notificarGestores(PDO $pdo, ?int $osId, string $tipo, string $titulo, string $mensagem): void
{
    $stmt = $pdo->query("SELECT id FROM usuarios WHERE perfil = 'gestor' AND ativo = 1");
    $ids = [];
    foreach ($stmt->fetchAll() as $gestor) {
        $id = (int) $gestor['id'];
        criarNotificacao($pdo, $id, $osId, $tipo, $titulo, $mensagem);
        $ids[] = $id;
    }
    $dadosPush = ['tipo' => 'os'];
    if ($osId) {
        $dadosPush['os_id'] = (string) $osId;
    }
    enviarPushParaUsuarios($pdo, $ids, $titulo, $mensagem, $dadosPush);
}

/**
 * Envia notificacao para todos os tecnicos atribuidos a uma OS (in-app + push FCM).
 */
function notificarTecnicosDaOs(PDO $pdo, int $osId, string $tipo, string $titulo, string $mensagem): void
{
    $stmt = $pdo->prepare('SELECT tecnico_id FROM os_tecnicos WHERE os_id = :os_id');
    $stmt->execute(['os_id' => $osId]);
    $ids = [];
    foreach ($stmt->fetchAll() as $linha) {
        $id = (int) $linha['tecnico_id'];
        criarNotificacao($pdo, $id, $osId, $tipo, $titulo, $mensagem);
        $ids[] = $id;
    }
    enviarPushParaUsuarios($pdo, $ids, $titulo, $mensagem, [
        'tipo'  => 'os',
        'os_id' => (string) $osId,
    ]);
}

/**
 * Envia notificacao para gestores E supervisores (in-app + push FCM).
 * Usada quando o técnico muda status e é importante notificar ambos.
 */
function notificarGestoresESupervisores(PDO $pdo, ?int $osId, string $tipo, string $titulo, string $mensagem): void
{
    $stmt = $pdo->query("SELECT id FROM usuarios WHERE perfil IN ('gestor','supervisor') AND ativo = 1");
    $ids = [];
    foreach ($stmt->fetchAll() as $u) {
        $id = (int) $u['id'];
        criarNotificacao($pdo, $id, $osId, $tipo, $titulo, $mensagem);
        $ids[] = $id;
    }
    $dadosPush = ['tipo' => 'os'];
    if ($osId) {
        $dadosPush['os_id'] = (string) $osId;
    }
    enviarPushParaUsuarios($pdo, $ids, $titulo, $mensagem, $dadosPush);
}

/**
 * Retorna o gc_situacao_id configurado para uma situacao local, ou null se nao configurado.
 */
function obterSituacaoGcId(string $situacaoLocal): ?int
{
    $mapa = [
        'aberto' => 'gc_situacao_aberto',
        'em_andamento' => 'gc_situacao_em_andamento',
        'pausado' => 'gc_situacao_pausado',
        'reagendado' => 'gc_situacao_reagendado',
        'concluido' => 'gc_situacao_concluido',
        'cancelado' => 'gc_situacao_cancelado',
    ];

    if (!isset($mapa[$situacaoLocal])) {
        return null;
    }

    $valor = obterConfiguracao($mapa[$situacaoLocal]);

    return ($valor === null || $valor === '') ? null : (int) $valor;
}

/**
 * Busca uma OS pelo id local, aplicando controle de acesso:
 * - gestor: acessa qualquer OS
 * - tecnico: so acessa OS as quais esteja atribuido
 * Responde com erro 403/404 e encerra a execucao se nao encontrar/nao autorizado.
 */
function buscarOsOuFalhar(PDO $pdo, int $osId, array $payloadJwt): array
{
    $stmt = $pdo->prepare('SELECT * FROM ordens_servico WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $osId]);
    $os = $stmt->fetch();

    if (!$os) {
        responderErro('Ordem de servico nao encontrada.', 404);
    }

    if ($payloadJwt['perfil'] === 'tecnico') {
        $tid = (int) $payloadJwt['usuario_id'];
        // Verifica acesso via os_tecnicos OU via tecnico_id direto na OS (GC sync)
        $stmtAcesso = $pdo->prepare(
            'SELECT 1 FROM os_tecnicos WHERE os_id = :oid AND tecnico_id = :tid
             UNION ALL
             SELECT 1 FROM ordens_servico WHERE id = :oid2 AND tecnico_id = :tid2
             LIMIT 1'
        );
        $stmtAcesso->execute(['oid' => $osId, 'tid' => $tid, 'oid2' => $osId, 'tid2' => $tid]);
        if (!$stmtAcesso->fetch()) {
            responderErro('Voce nao tem acesso a esta ordem de servico.', 403);
        }
    }

    return $os;
}

/**
 * Lista os tecnicos atribuidos a uma OS, indicando quem e o responsavel.
 */
function listarTecnicosDaOs(PDO $pdo, int $osId): array
{
    $stmt = $pdo->prepare(
        'SELECT u.id, u.nome, u.email, u.foto_perfil, ot.responsavel
         FROM os_tecnicos ot
         INNER JOIN usuarios u ON u.id = ot.tecnico_id
         WHERE ot.os_id = :os_id
         ORDER BY ot.responsavel DESC, u.nome ASC'
    );
    $stmt->execute(['os_id' => $osId]);

    return $stmt->fetchAll();
}
