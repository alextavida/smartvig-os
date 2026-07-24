<?php
/**
 * Histórico de OS por cliente — filtra pelo nome do cliente (busca parcial).
 * URL: /admin/clientes/historico.php?nome=Joao%20Silva
 */

declare(strict_types=1);

$perfisPermitidosPagina = ['gestor'];
$tituloPagina = 'Histórico do Cliente';
$paginaAtiva  = 'os';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/database.php';

$pdo    = obterConexao();
$busca  = trim($_GET['nome'] ?? '');
$pagina = max(1, (int) ($_GET['p'] ?? 1));
$limite = 20;
$offset = ($pagina - 1) * $limite;

$osLista  = [];
$total    = 0;
$nomeExato = '';

if ($busca !== '') {
    $like = '%' . $busca . '%';

    $stmtTotal = $pdo->prepare(
        "SELECT COUNT(*) FROM ordens_servico WHERE cliente_nome LIKE :b"
    );
    $stmtTotal->execute(['b' => $like]);
    $total = (int) $stmtTotal->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT os.id, os.gc_os_id, os.codigo, os.cliente_nome, os.cliente_telefone,
                os.situacao_local, os.prioridade, os.data_agendamento, os.data_conclusao,
                os.tempo_atendimento_segundos, resp.nome AS tecnico_nome,
                (SELECT COUNT(*) FROM midias_os m WHERE m.os_id = os.id) AS total_midias
         FROM ordens_servico os
         LEFT JOIN usuarios resp ON resp.id = os.tecnico_id
         WHERE os.cliente_nome LIKE :b
         ORDER BY os.criado_em DESC
         LIMIT :lim OFFSET :off"
    );
    $stmt->bindValue(':b',   $like,   PDO::PARAM_STR);
    $stmt->bindValue(':lim', $limite, PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $osLista = $stmt->fetchAll();

    if (!empty($osLista)) {
        $nomeExato = $osLista[0]['cliente_nome'];
    }
}

$totalPaginas = $total > 0 ? (int) ceil($total / $limite) : 1;

function formatSegundos(int $seg): string
{
    $h = intdiv($seg, 3600);
    $m = intdiv($seg % 3600, 60);
    return $h > 0 ? "{$h}h{$m}min" : "{$m}min";
}

function rotuloStatus(string $s): string
{
    return match ($s) {
        'aberto'       => 'Aberto',
        'em_andamento' => 'Em andamento',
        'pausado'      => 'Pausado',
        'reagendado'   => 'Reagendado',
        'concluido'    => 'Concluído',
        'cancelado'    => 'Cancelado',
        default        => $s,
    };
}
?>

<!-- Barra de busca -->
<div class="card" style="margin-bottom:16px;">
  <h2 style="margin:0 0 12px;font-size:1rem;">
    Histórico por Cliente
    <?php if ($nomeExato): ?>
      — <span style="color:#1462b0;"><?= htmlspecialchars($nomeExato) ?></span>
    <?php endif; ?>
  </h2>
  <form method="get" style="display:flex;gap:8px;flex-wrap:wrap;">
    <input
      name="nome"
      value="<?= htmlspecialchars($busca) ?>"
      placeholder="Nome do cliente..."
      class="form-control"
      style="flex:1;min-width:200px;"
      required
    >
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

  <!-- Resumo do cliente -->
  <?php
    $totalOS        = $total;
    $totalConcluidas = array_sum(array_map(fn($o) => $o['situacao_local'] === 'concluido' ? 1 : 0, $osLista));
    $totalTempo     = array_sum(array_column($osLista, 'tempo_atendimento_segundos'));
  ?>
  <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px;">
    <div class="stat-card aberto" style="min-width:130px;">
      <div class="valor"><?= $totalOS ?></div>
      <div class="rotulo">Total de OS</div>
    </div>
    <div class="stat-card concluido" style="min-width:130px;">
      <div class="valor"><?= $totalConcluidas ?></div>
      <div class="rotulo">Concluídas</div>
    </div>
    <?php if ($totalTempo > 0): ?>
    <div class="stat-card em_andamento" style="min-width:130px;">
      <div class="valor"><?= formatSegundos((int) $totalTempo) ?></div>
      <div class="rotulo">Tempo total</div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Tabela de OS -->
  <div class="card">
    <div style="overflow-x:auto;">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Cliente</th>
            <th>Técnico</th>
            <th>Agendamento</th>
            <th>Conclusão</th>
            <th>Tempo</th>
            <th>Status</th>
            <th>Fotos</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($osLista as $os): ?>
            <tr>
              <td style="font-size:12px;color:#64748b;">
                <?= $os['codigo'] ? htmlspecialchars($os['codigo']) : '#' . (int) $os['id'] ?>
                <?php if ($os['gc_os_id']): ?>
                  <br><span style="font-size:10px;color:#94a3b8;">GC:<?= (int) $os['gc_os_id'] ?></span>
                <?php endif; ?>
              </td>
              <td>
                <?= htmlspecialchars($os['cliente_nome'] ?? '-') ?>
                <?php if ($os['cliente_telefone']): ?>
                  <br><a href="tel:<?= htmlspecialchars($os['cliente_telefone']) ?>"
                     style="font-size:11px;color:#1462b0;"><?= htmlspecialchars($os['cliente_telefone']) ?></a>
                <?php endif; ?>
              </td>
              <td><?= htmlspecialchars($os['tecnico_nome'] ?? '-') ?></td>
              <td><?= $os['data_agendamento'] ? date('d/m/Y', strtotime($os['data_agendamento'])) : '-' ?></td>
              <td><?= $os['data_conclusao'] ? date('d/m/Y', strtotime($os['data_conclusao'])) : '-' ?></td>
              <td style="font-size:12px;color:#475569;">
                <?= $os['tempo_atendimento_segundos'] ? formatSegundos((int) $os['tempo_atendimento_segundos']) : '-' ?>
              </td>
              <td><span class="badge <?= $os['situacao_local'] ?>"><?= rotuloStatus($os['situacao_local']) ?></span></td>
              <td style="text-align:center;"><?= (int) $os['total_midias'] ?></td>
              <td>
                <a href="/app-tecnicos/admin/os/detalhe.php?id=<?= (int) $os['id'] ?>"
                   class="btn btn-secundario btn-sm">Ver OS</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Paginação -->
    <?php if ($totalPaginas > 1): ?>
      <div style="display:flex;gap:6px;margin-top:12px;flex-wrap:wrap;align-items:center;">
        <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
          <a href="?nome=<?= urlencode($busca) ?>&p=<?= $i ?>"
             class="btn <?= $i === $pagina ? 'btn-primario' : 'btn-secundario' ?> btn-sm"><?= $i ?></a>
        <?php endfor; ?>
        <span style="font-size:12px;color:#64748b;margin-left:6px;">
          Mostrando <?= count($osLista) ?> de <?= $total ?> registros
        </span>
      </div>
    <?php endif; ?>
  </div>

<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
