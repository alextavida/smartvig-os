<?php
/**
 * Helpers compartilhados do módulo de Solicitação de Compras.
 */

declare(strict_types=1);

require_once __DIR__ . '/push.php';

function gerarNumeroSolicitacao(PDO $pdo): string
{
    $ano  = date('Y');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM solicitacoes_compra WHERE YEAR(criado_em) = :ano");
    $stmt->execute(['ano' => $ano]);
    $seq  = (int) $stmt->fetchColumn() + 1;
    return 'SC-' . $ano . '-' . str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
}

function registrarHistoricoCompra(
    PDO     $pdo,
    int     $solicitacaoId,
    ?int    $usuarioId,
    ?string $usuarioNome,
    string  $acao,
    ?string $detalhe = null
): void {
    $pdo->prepare(
        'INSERT INTO solicitacao_historico
         (solicitacao_id, usuario_id, usuario_nome, acao, detalhe)
         VALUES (:sid, :uid, :unome, :acao, :detalhe)'
    )->execute([
        'sid'    => $solicitacaoId,
        'uid'    => $usuarioId,
        'unome'  => $usuarioNome,
        'acao'   => $acao,
        'detalhe'=> $detalhe,
    ]);
}

function recalcularValorEstimado(PDO $pdo, int $solicitacaoId): void
{
    $stmt = $pdo->prepare(
        'SELECT SUM(quantidade * COALESCE(valor_estimado, 0)) FROM solicitacao_itens WHERE solicitacao_id = :id'
    );
    $stmt->execute(['id' => $solicitacaoId]);
    $total = $stmt->fetchColumn();
    $pdo->prepare('UPDATE solicitacoes_compra SET valor_estimado = :v WHERE id = :id')
        ->execute(['v' => $total !== false ? (float) $total : null, 'id' => $solicitacaoId]);
}

/**
 * Verifica se o usuário tem determinado papel no módulo de compras.
 * Gestor sempre tem acesso a tudo.
 */
function temRoleCompras(array $usuarioAtual, string ...$roles): bool
{
    if (($usuarioAtual['perfil'] ?? '') === 'gestor') {
        return true;
    }
    $todosRoles = array_merge(
        [$usuarioAtual['perfil'] ?? ''],
        $usuarioAtual['roles']   ?? []
    );
    foreach ($roles as $role) {
        if (in_array($role, $todosRoles, true)) {
            return true;
        }
    }
    return false;
}

function rotuloStatusCompra(string $status): string
{
    return match ($status) {
        'rascunho'             => 'Rascunho',
        'aguardando_aprovacao' => 'Aguard. Aprovação',
        'aprovado'             => 'Aprovado',
        'reprovado'            => 'Reprovado',
        'devolvido'            => 'Devolvido p/ Ajuste',
        'em_compra'            => 'Em Compra',
        'recebido'             => 'Recebido',
        'concluido'            => 'Concluído',
        'cancelado'            => 'Cancelado',
        default                => $status,
    };
}

function cssStatusCompra(string $status): string
{
    return match ($status) {
        'rascunho'             => 'pausado',
        'aguardando_aprovacao' => 'em_andamento',
        'aprovado'             => 'aberto',
        'reprovado'            => 'cancelado',
        'devolvido'            => 'reagendado',
        'em_compra'            => 'sc-compra',
        'recebido'             => 'concluido',
        'concluido'            => 'concluido',
        'cancelado'            => 'cancelado',
        default                => 'pausado',
    };
}

function cssPrioridadeCompra(string $p): string
{
    return match ($p) {
        'urgente' => 'cancelado',
        'alta'    => 'reagendado',
        'media'   => 'em_andamento',
        'baixa'   => 'pausado',
        default   => 'pausado',
    };
}

function rotuloDestinoCompra(string $d): string
{
    return match ($d) {
        'cliente'    => 'Cliente',
        'condominio' => 'Condomínio',
        'obra'       => 'Obra',
        'estoque'    => 'Estoque',
        'manutencao' => 'Manutenção',
        'veiculo'    => 'Veículo',
        'outro'      => 'Outro',
        default      => $d,
    };
}

function rotulosPrioridadeCompra(): array
{
    return ['baixa' => 'Baixa', 'media' => 'Média', 'alta' => 'Alta', 'urgente' => 'Urgente'];
}

function formatarMoeda(float|null $valor): string
{
    if ($valor === null) { return '—'; }
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

function notificarCompradores(PDO $pdo, int $solicitacaoId, string $tipo, string $titulo, string $mensagem): void
{
    $stmt = $pdo->query(
        "SELECT DISTINCT u.id
         FROM usuarios u
         WHERE u.ativo = 1
           AND (
               u.perfil = 'gestor'
               OR EXISTS (SELECT 1 FROM usuario_roles ur WHERE ur.usuario_id = u.id AND ur.role = 'comprador')
           )"
    );
    $ids = [];
    foreach ($stmt->fetchAll() as $row) {
        $id = (int) $row['id'];
        $pdo->prepare(
            'INSERT INTO notificacoes (usuario_id, os_id, tipo, titulo, mensagem)
             VALUES (:uid, NULL, :tipo, :titulo, :msg)'
        )->execute(['uid' => $id, 'tipo' => $tipo, 'titulo' => $titulo, 'msg' => $mensagem]);
        $ids[] = $id;
    }
    enviarPushParaUsuarios($pdo, $ids, $titulo, $mensagem, [
        'tipo'          => 'compra',
        'solicitacao_id' => (string) $solicitacaoId,
    ]);
}

function notificarAprovadores(PDO $pdo, int $solicitacaoId, string $tipo, string $titulo, string $mensagem): void
{
    $stmt = $pdo->query(
        "SELECT DISTINCT u.id
         FROM usuarios u
         WHERE u.ativo = 1
           AND (
               u.perfil IN ('gestor','supervisor')
               OR EXISTS (SELECT 1 FROM usuario_roles ur WHERE ur.usuario_id = u.id AND ur.role = 'aprovador')
           )"
    );
    $ids = [];
    foreach ($stmt->fetchAll() as $row) {
        $id = (int) $row['id'];
        $pdo->prepare(
            'INSERT INTO notificacoes (usuario_id, os_id, tipo, titulo, mensagem)
             VALUES (:uid, NULL, :tipo, :titulo, :msg)'
        )->execute(['uid' => $id, 'tipo' => $tipo, 'titulo' => $titulo, 'msg' => $mensagem]);
        $ids[] = $id;
    }
    enviarPushParaUsuarios($pdo, $ids, $titulo, $mensagem, [
        'tipo'          => 'compra',
        'solicitacao_id' => (string) $solicitacaoId,
    ]);
}
