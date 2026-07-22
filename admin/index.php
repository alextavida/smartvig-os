<?php
/**
 * Dashboard do gestor: cards de totais por status + ultimas OS.
 */

declare(strict_types=1);

$perfisPermitidosPagina = ['gestor'];
$tituloPagina = 'Dashboard';
$paginaAtiva = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/icons.php';
require_once __DIR__ . '/../config/database.php';

$pdo = obterConexao();

$statusLista = ['aberto', 'em_andamento', 'pausado', 'reagendado', 'concluido', 'cancelado'];
$rotulos = [
    'aberto' => 'Abertas',
    'em_andamento' => 'Em andamento',
    'pausado' => 'Pausadas',
    'reagendado' => 'Reagendadas',
    'concluido' => 'Concluidas hoje',
    'cancelado' => 'Canceladas',
];

$contagens = array_fill_keys($statusLista, 0);
$stmt = $pdo->query('SELECT situacao_local, COUNT(*) AS total FROM ordens_servico GROUP BY situacao_local');
foreach ($stmt->fetchAll() as $linha) {
    $contagens[$linha['situacao_local']] = (int) $linha['total'];
}

$stmtConcluidasHoje = $pdo->query("SELECT COUNT(*) FROM ordens_servico WHERE situacao_local = 'concluido' AND DATE(data_conclusao) = CURDATE()");
$concluidasHoje = (int) $stmtConcluidasHoje->fetchColumn();

$stmtTecnicosAtivos = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE perfil = 'tecnico' AND ativo = 1");
$tecnicosAtivos = (int) $stmtTecnicosAtivos->fetchColumn();

$stmtUltimas = $pdo->query(
    "SELECT os.id, os.cliente_nome, os.situacao_local, os.prioridade, os.data_agendamento, resp.nome AS tecnico_nome
     FROM ordens_servico os
     LEFT JOIN usuarios resp ON resp.id = os.tecnico_id
     ORDER BY os.criado_em DESC
     LIMIT 8"
);
$ultimasOs = $stmtUltimas->fetchAll();

function rotuloStatus(string $s): string
{
    $mapa = [
        'aberto' => 'Aberto', 'em_andamento' => 'Em andamento', 'pausado' => 'Pausado',
        'reagendado' => 'Reagendado', 'concluido' => 'Concluido', 'cancelado' => 'Cancelado',
    ];
    return $mapa[$s] ?? $s;
}
?>

<div class="grid-cards">
  <?php foreach ($statusLista as $s): ?>
    <div class="stat-card <?= $s ?>">
      <div class="valor"><?= $s === 'concluido' ? $concluidasHoje : $contagens[$s] ?></div>
      <div class="rotulo"><?= $rotulos[$s] ?></div>
    </div>
  <?php endforeach; ?>
  <div class="stat-card" style="border-top-color:#1a56a0;">
    <div class="valor"><?= $tecnicosAtivos ?></div>
    <div class="rotulo">Tecnicos ativos</div>
  </div>
</div>

<div class="card">
  <h3 style="margin-top:0;">Ultimas ordens de servico</h3>
  <?php if (empty($ultimasOs)): ?>
    <div class="vazio"><?= ic('os_vazio') ?><br>Nenhuma OS cadastrada ainda. <a href="/app-tecnicos/admin/os/criar.php">Criar a primeira OS</a></div>
  <?php else: ?>
  <table>
    <thead>
      <tr><th>#</th><th>Cliente</th><th>Tecnico</th><th>Prioridade</th><th>Agendamento</th><th>Status</th><th></th></tr>
    </thead>
    <tbody>
      <?php foreach ($ultimasOs as $os): ?>
        <tr>
          <td>#<?= (int) $os['id'] ?></td>
          <td><?= htmlspecialchars($os['cliente_nome'] ?? '-') ?></td>
          <td><?= htmlspecialchars($os['tecnico_nome'] ?? '-') ?></td>
          <td><span class="chip-prioridade <?= $os['prioridade'] ?? 'baixo' ?>"><?= ic('flag', 10) ?> <?= ucfirst($os['prioridade'] ?? 'baixo') ?></span></td>
          <td><?= $os['data_agendamento'] ? date('d/m/Y', strtotime($os['data_agendamento'])) : '-' ?></td>
          <td><span class="badge <?= $os['situacao_local'] ?>"><?= rotuloStatus($os['situacao_local']) ?></span></td>
          <td>
            <div class="acoes-tabela">
              <a href="/app-tecnicos/admin/os/detalhe.php?id=<?= (int) $os['id'] ?>" class="btn btn-secundario btn-sm">Ver</a>
              <a href="/app-tecnicos/admin/os/imprimir.php?id=<?= (int) $os['id'] ?>" target="_blank" class="btn-icone" title="Imprimir"><?= ic('imprimir', 14) ?></a>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
