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
    $h = '<span style="display:inline-flex;gap:2px;vertical-align:middle;">';
    for ($i = 1; $i <= 5; $i++) {
        $fill   = $i <= $nota ? '#f59e0b' : '#e2e8f0';
        $stroke = $i <= $nota ? '#d97706' : '#cbd5e1';
        $h .= '<svg width="14" height="14" viewBox="0 0 24 24" fill="'.$fill.'" stroke="'.$stroke.'" stroke-width="1.5">'
            . '<polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26"/>'
            . '</svg>';
    }
    return $h . '</span>';
}

function svgMeta(string $nome, string $cor = '#94a3b8'): string {
    $paths = [
        'calendar' => 'M8 2v3M16 2v3M3.5 9.09h17M21 8.5V17c0 3-1.5 4-4 4H7c-2.5 0-4-1-4-4V8.5c0-3 1.5-4 4-4h10c2.5 0 4 1 4 4z',
        'check'    => 'M20 6L9 17l-5-5',
        'person'   => 'M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z',
        'clock'    => 'M12 2a10 10 0 1 0 0 20A10 10 0 0 0 12 2zM12 6v6l4 2',
        'camera'   => 'M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2zM12 17a4 4 0 1 0 0-8 4 4 0 0 0 0 8z',
        'pen'      => 'M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z',
    ];
    return '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="'.$cor.'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;">'
         . '<path d="' . ($paths[$nome] ?? '') . '"/>'
         . '</svg>';
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
    <button type="submit" class="btn btn-primario">Buscar</button>
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
      <div class="rotulo">Fotos</div>
    </div>
    <?php if ($mediaNps !== null): ?>
    <div class="stat-card" style="min-width:120px;background:#fff;">
      <div class="valor" style="color:#ca8a04;"><?= number_format($mediaNps, 1) ?> ★</div>
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
            <span class="badge" style="font-size:11px;color:#dc2626;background:#fee2e2;margin-left:4px;">Urgente</span>
          <?php endif; ?>
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:6px;">
          <?php if ($os['data_agendamento']): ?>
            <span style="display:inline-flex;align-items:center;gap:4px;background:#f1f5f9;border-radius:6px;padding:2px 8px;font-size:11px;color:#475569;font-weight:500;">
              <?= svgMeta('calendar') ?> <?= date('d/m/Y', strtotime($os['data_agendamento'])) ?>
            </span>
          <?php endif; ?>
          <?php if ($os['data_conclusao']): ?>
            <span style="display:inline-flex;align-items:center;gap:4px;background:#f0fdf4;border-radius:6px;padding:2px 8px;font-size:11px;color:#16803c;font-weight:500;">
              <?= svgMeta('check', '#16803c') ?> <?= date('d/m/Y', strtotime($os['data_conclusao'])) ?>
            </span>
          <?php endif; ?>
          <?php if ($os['tecnico_nome']): ?>
            <span style="display:inline-flex;align-items:center;gap:4px;background:#f1f5f9;border-radius:6px;padding:2px 8px;font-size:11px;color:#475569;font-weight:500;">
              <?= svgMeta('person') ?> <?= htmlspecialchars($os['tecnico_nome']) ?>
            </span>
          <?php endif; ?>
          <?php if ($os['tempo_atendimento_segundos']): ?>
            <span style="display:inline-flex;align-items:center;gap:4px;background:#f1f5f9;border-radius:6px;padding:2px 8px;font-size:11px;color:#475569;font-weight:500;">
              <?= svgMeta('clock') ?> <?= formatSegundos((int)$os['tempo_atendimento_segundos']) ?>
            </span>
          <?php endif; ?>
          <?php if ($os['cliente_telefone']): ?>
            <a href="tel:<?= htmlspecialchars($os['cliente_telefone']) ?>"
               style="display:inline-flex;align-items:center;gap:4px;background:#eff6ff;border-radius:6px;padding:2px 8px;font-size:11px;color:#1462b0;font-weight:500;text-decoration:none;">
              <?= svgMeta('person', '#1462b0') ?> <?= htmlspecialchars($os['cliente_telefone']) ?>
            </a>
          <?php endif; ?>
        </div>
      </div>
      <div style="display:flex;gap:6px;align-items:center;">
        <?php if ($os['total_fotos'] > 0): ?>
          <span style="display:inline-flex;align-items:center;gap:4px;background:#dbeafe;color:#1d4ed8;border-radius:6px;padding:3px 10px;font-size:11px;font-weight:700;">
            <?= svgMeta('camera', '#1d4ed8') ?> <?= (int)$os['total_fotos'] ?> foto<?= $os['total_fotos'] > 1 ? 's' : '' ?>
          </span>
        <?php endif; ?>
        <?php if ($os['total_assinaturas'] > 0): ?>
          <span style="display:inline-flex;align-items:center;gap:4px;background:#dcfce7;color:#16803c;border-radius:6px;padding:3px 10px;font-size:11px;font-weight:700;">
            <?= svgMeta('pen', '#16803c') ?> Assinado
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
      <div style="display:flex;align-items:flex-start;gap:10px;background:#fefce8;border-radius:8px;padding:10px 14px;border:1px solid #fef08a;margin-top:6px;">
        <div>
          <?= estrelas((int)$os['nps_nota']) ?>
          <span style="font-size:11px;font-weight:700;color:#92400e;margin-left:6px;"><?= (int)$os['nps_nota'] ?>/5</span>
        </div>
        <?php if ($os['nps_comentario']): ?>
          <span style="font-size:12px;color:#78350f;border-left:2px solid #fcd34d;padding-left:10px;line-height:1.5;">
            "<?= htmlspecialchars($os['nps_comentario']) ?>"
          </span>
        <?php endif; ?>
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
