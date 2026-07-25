<?php
/**
 * Histórico completo por cliente — OS, fotos, laudos, assinaturas e NPS.
 */

declare(strict_types=1);

$perfisPermitidosPagina = ['gestor', 'supervisor'];
$tituloPagina = 'Histórico do Cliente';
$paginaAtiva  = 'clientes';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/database.php';

$pdo    = obterConexao();
$busca  = trim($_GET['nome'] ?? '');
$pagina = max(1, (int) ($_GET['p'] ?? 1));
$limite = 15;
$offset = ($pagina - 1) * $limite;

$osLista   = [];
$total     = 0;
$nomeExato = '';

if ($busca !== '') {
    $like = '%' . $busca . '%';

    $stmtT = $pdo->prepare("SELECT COUNT(*) FROM ordens_servico WHERE cliente_nome LIKE :b");
    $stmtT->execute(['b' => $like]);
    $total = (int) $stmtT->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT os.id, os.codigo, os.cliente_nome, os.cliente_telefone,
                os.cliente_endereco, os.situacao_local, os.prioridade,
                os.data_agendamento, os.data_conclusao, os.observacoes,
                os.tempo_atendimento_segundos, os.nps_nota, os.nps_comentario, os.nps_respondido,
                (SELECT u2.nome FROM os_tecnicos ot2
                 INNER JOIN usuarios u2 ON u2.id = ot2.tecnico_id
                 WHERE ot2.os_id = os.id AND ot2.responsavel = 1
                 LIMIT 1) AS tecnico_nome,
                (SELECT COUNT(*) FROM midias_os m WHERE m.os_id = os.id AND m.tipo = 'foto') AS total_fotos,
                (SELECT COUNT(*) FROM midias_os m WHERE m.os_id = os.id AND m.tipo = 'assinatura') AS total_assinaturas
         FROM ordens_servico os
         WHERE os.cliente_nome LIKE :b
         ORDER BY os.criado_em DESC
         LIMIT :lim OFFSET :off"
    );
    $stmt->bindValue(':b',   $like,   PDO::PARAM_STR);
    $stmt->bindValue(':lim', $limite, PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $osLista = $stmt->fetchAll();

    if (!empty($osLista)) { $nomeExato = $osLista[0]['cliente_nome']; }
}

$totalPaginas = $total > 0 ? (int) ceil($total / $limite) : 1;

function formatSegundos(int $seg): string {
    $h = intdiv($seg, 3600); $m = intdiv($seg % 3600, 60);
    return $h > 0 ? "{$h}h{$m}min" : "{$m}min";
}

function rotuloStatus(string $s): string {
    return match($s) {
        'aberto' => 'Aberto', 'em_andamento' => 'Em andamento',
        'pausado' => 'Pausado', 'reagendado' => 'Reagendado',
        'concluido' => 'Concluído', 'cancelado' => 'Cancelado', default => $s,
    };
}

function estrelas(int $nota): string {
    return str_repeat('⭐', $nota) . str_repeat('☆', 5 - $nota);
}
?>

<div class="card" style="margin-bottom:16px;">
  <h2 style="margin:0 0 12px;font-size:1rem;">
    Histórico Completo por Cliente
    <?php if ($nomeExato): ?>
      — <span style="color:#1462b0;"><?= htmlspecialchars($nomeExato) ?></span>
    <?php endif; ?>
  </h2>
  <form method="get" style="display:flex;gap:8px;flex-wrap:wrap;">
    <input name="nome" value="<?= htmlspecialchars($busca) ?>"
           placeholder="Nome do cliente..." class="form-control" style="flex:1;min-width:200px;" required>
    <button type="submit" class="btn btn-primario">🔍 Buscar</button>
    <?php if ($busca): ?>
      <a href="historico.php" class="btn btn-secundario">Limpar</a>
    <?php endif; ?>
  </form>
</div>

<?php if ($busca && $total === 0): ?>
  <div class="card" style="text-align:center;padding:32px;color:#64748b;">
    Nenhuma OS encontrada para "<?= htmlspecialchars($busca) ?>".
  </div>

<?php elseif (!empty($osLista)): ?>

  <?php
    $totalOS         = $total;
    $concluidas      = array_filter($osLista, fn($o) => $o['situacao_local'] === 'concluido');
    $totalTempo      = array_sum(array_column($osLista, 'tempo_atendimento_segundos'));
    $totalFotos      = array_sum(array_column($osLista, 'total_fotos'));
    $npsRespondidos  = array_filter($osLista, fn($o) => $o['nps_respondido']);
    $mediaNps        = $npsRespondidos
        ? round(array_sum(array_column(array_values($npsRespondidos), 'nps_nota')) / count($npsRespondidos), 1)
        : null;
  ?>

  <!-- Resumo -->
  <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;">
    <div class="stat-card aberto" style="min-width:120px;">
      <div class="valor"><?= $totalOS ?></div>
      <div class="rotulo">Total de OS</div>
    </div>
    <div class="stat-card concluido" style="min-width:120px;">
      <div class="valor"><?= count($concluidas) ?></div>
      <div class="rotulo">Concluídas</div>
    </div>
    <?php if ($totalTempo > 0): ?>
    <div class="stat-card em_andamento" style="min-width:120px;">
      <div class="valor"><?= formatSegundos((int)$totalTempo) ?></div>
      <div class="rotulo">Tempo total</div>
    </div>
    <?php endif; ?>
    <div class="stat-card" style="min-width:120px;background:#fff;">
      <div class="valor" style="color:#1d4ed8;"><?= (int)$totalFotos ?></div>
      <div class="rotulo">📸 Fotos</div>
    </div>
    <?php if ($mediaNps !== null): ?>
    <div class="stat-card" style="min-width:120px;background:#fff;">
      <div class="valor" style="color:#ca8a04;"><?= number_format($mediaNps, 1) ?> ⭐</div>
      <div class="rotulo">Média NPS</div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Timeline de OS -->
  <?php foreach ($osLista as $os): ?>
  <div class="card" style="margin-bottom:14px;border-left:4px solid <?= $os['situacao_local']==='concluido' ? '#16803c' : ($os['situacao_local']==='em_andamento' ? '#1d4ed8' : '#94a3b8') ?>;">
    <div style="display:flex;justify-content:space-between;align-items:start;flex-wrap:wrap;gap:8px;margin-bottom:10px;">
      <div>
        <div style="font-size:14px;font-weight:800;color:#1c2430;">
          <?= $os['codigo'] ? htmlspecialchars($os['codigo']) : '#'.(int)$os['id'] ?>
          <span class="badge <?= $os['situacao_local'] ?>" style="font-size:11px;margin-left:6px;"><?= rotuloStatus($os['situacao_local']) ?></span>
          <?php if ($os['prioridade'] === 'urgente'): ?>
            <span style="font-size:11px;color:#dc2626;font-weight:700;margin-left:4px;">🔴 Urgente</span>
          <?php endif; ?>
        </div>
        <div style="font-size:12px;color:#64748b;margin-top:3px;">
          <?= $os['data_agendamento'] ? '📅 ' . date('d/m/Y', strtotime($os['data_agendamento'])) : '' ?>
          <?= $os['data_conclusao'] ? ' · ✓ ' . date('d/m/Y', strtotime($os['data_conclusao'])) : '' ?>
          <?= $os['tecnico_nome'] ? ' · 👤 ' . htmlspecialchars($os['tecnico_nome']) : '' ?>
          <?= $os['tempo_atendimento_segundos'] ? ' · ⏱ ' . formatSegundos((int)$os['tempo_atendimento_segundos']) : '' ?>
        </div>
        <?php if ($os['cliente_telefone']): ?>
          <div style="font-size:12px;color:#64748b;margin-top:2px;">
            📞 <a href="tel:<?= htmlspecialchars($os['cliente_telefone']) ?>" style="color:#1462b0;"><?= htmlspecialchars($os['cliente_telefone']) ?></a>
          </div>
        <?php endif; ?>
      </div>
      <div style="display:flex;gap:6px;align-items:center;">
        <?php if ($os['total_fotos'] > 0): ?>
          <span style="background:#dbeafe;color:#1d4ed8;border-radius:999px;padding:2px 10px;font-size:11px;font-weight:700;">
            📸 <?= (int)$os['total_fotos'] ?> foto<?= $os['total_fotos'] > 1 ? 's' : '' ?>
          </span>
        <?php endif; ?>
        <?php if ($os['total_assinaturas'] > 0): ?>
          <span style="background:#dcfce7;color:#16803c;border-radius:999px;padding:2px 10px;font-size:11px;font-weight:700;">
            ✍️ Assinado
          </span>
        <?php endif; ?>
        <a href="/app-tecnicos/admin/os/detalhe.php?id=<?= (int)$os['id'] ?>"
           class="btn btn-secundario btn-sm">Ver OS</a>
      </div>
    </div>

    <?php if ($os['observacoes']): ?>
      <div style="background:#f8fafc;border-radius:8px;padding:10px 12px;font-size:13px;color:#475569;margin-bottom:8px;white-space:pre-wrap;max-height:100px;overflow:hidden;border:1px solid #e2e8f0;">
        <?= htmlspecialchars(mb_substr($os['observacoes'], 0, 300)) ?><?= mb_strlen($os['observacoes']) > 300 ? '...' : '' ?>
      </div>
    <?php endif; ?>

    <?php if ($os['nps_respondido']): ?>
      <div style="background:#fefce8;border-radius:8px;padding:8px 12px;font-size:12px;color:#854d0e;border:1px solid #fef08a;">
        <b>Avaliação NPS:</b> <?= estrelas((int)$os['nps_nota']) ?>
        <?= $os['nps_comentario'] ? ' — "' . htmlspecialchars($os['nps_comentario']) . '"' : '' ?>
      </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>

  <!-- Paginação -->
  <?php if ($totalPaginas > 1): ?>
    <div style="display:flex;gap:6px;margin-top:12px;flex-wrap:wrap;align-items:center;">
      <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
        <a href="?nome=<?= urlencode($busca) ?>&p=<?= $i ?>"
           class="btn <?= $i === $pagina ? 'btn-primario' : 'btn-secundario' ?> btn-sm"><?= $i ?></a>
      <?php endfor; ?>
      <span style="font-size:12px;color:#64748b;margin-left:6px;">
        Mostrando <?= count($osLista) ?> de <?= $total ?>
      </span>
    </div>
  <?php endif; ?>

<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
