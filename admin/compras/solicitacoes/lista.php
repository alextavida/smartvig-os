<?php
declare(strict_types=1);

$perfisPermitidosPagina = ['gestor', 'supervisor', 'solicitante', 'comprador', 'aprovador'];
$tituloPagina = 'Solicitações de Compra';
$paginaAtiva  = 'compras_lista';
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/compras_helpers.php';

$pdo       = obterConexao();
$isGestor  = temRole($usuarioAtual, 'aprovador', 'supervisor');
$podeComprar = temRole($usuarioAtual, 'comprador');
$uid       = (int) $usuarioAtual['usuario_id'];
$perfil    = $usuarioAtual['perfil'];

// Filtros
$filtroStatus     = $_GET['status']     ?? '';
$filtroPrioridade = $_GET['prioridade'] ?? '';
$filtroBusca      = $_GET['busca']      ?? '';
$pagina           = max(1, (int) ($_GET['pagina'] ?? 1));
$porPagina        = 20;
$offset           = ($pagina - 1) * $porPagina;

$where  = ['1=1'];
$params = [];

// Controle de acesso
if (!$isGestor && !$podeComprar) {
    // Solicitante vê só as próprias
    $where[] = 'sc.solicitante_id = :uid';
    $params['uid'] = $uid;
} elseif ($podeComprar && !$isGestor) {
    // Comprador vê aprovadas+
    $where[] = "sc.status IN ('aprovado','em_compra','recebido','concluido')";
}

if ($filtroStatus !== '') {
    $where[]          = 'sc.status = :status';
    $params['status'] = $filtroStatus;
}
if ($filtroPrioridade !== '') {
    $where[]                = 'sc.prioridade = :prioridade';
    $params['prioridade']   = $filtroPrioridade;
}
if ($filtroBusca !== '') {
    $where[]          = '(sc.numero LIKE :busca OR sc.justificativa LIKE :busca OR sol.nome LIKE :busca)';
    $params['busca']  = '%' . $filtroBusca . '%';
}

$whereStr = implode(' AND ', $where);

$stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM solicitacoes_compra sc LEFT JOIN usuarios sol ON sol.id=sc.solicitante_id WHERE $whereStr");
$stmtTotal->execute($params);
$total  = (int) $stmtTotal->fetchColumn();
$paginas = max(1, (int) ceil($total / $porPagina));

$sql = "SELECT sc.id, sc.numero, sc.status, sc.prioridade, sc.destino, sc.destino_referencia,
               sc.justificativa, sc.valor_estimado, sc.valor_final, sc.criado_em,
               sol.nome AS solicitante_nome, f.nome AS fornecedor_nome,
               (SELECT COUNT(*) FROM solicitacao_itens si WHERE si.solicitacao_id=sc.id) AS total_itens
        FROM solicitacoes_compra sc
        LEFT JOIN usuarios sol ON sol.id = sc.solicitante_id
        LEFT JOIN fornecedores f ON f.id = sc.fornecedor_id
        WHERE $whereStr
        ORDER BY FIELD(sc.prioridade,'urgente','alta','media','baixa'), sc.criado_em DESC
        LIMIT :lim OFFSET :off";

$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) { $stmt->bindValue(':' . $k, $v); }
$stmt->bindValue(':lim', $porPagina, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset,    PDO::PARAM_INT);
$stmt->execute();
$lista = $stmt->fetchAll();

$statusOpcoes = [''=>'Todos os status','rascunho'=>'Rascunho','aguardando_aprovacao'=>'Aguard. Aprovação','aprovado'=>'Aprovado','reprovado'=>'Reprovado','devolvido'=>'Devolvido','em_compra'=>'Em Compra','recebido'=>'Recebido','concluido'=>'Concluído','cancelado'=>'Cancelado'];
?>

<!-- Filtros -->
<div class="card" style="margin-bottom:16px;padding:14px 20px;">
  <form method="get" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
    <div class="campo" style="margin:0;flex:1;min-width:160px;">
      <label style="margin-bottom:4px;">Buscar</label>
      <input type="text" name="busca" value="<?= htmlspecialchars($filtroBusca) ?>" placeholder="Número, descrição, solicitante…">
    </div>
    <div class="campo" style="margin:0;min-width:160px;">
      <label style="margin-bottom:4px;">Status</label>
      <select name="status">
        <?php foreach ($statusOpcoes as $v => $l): ?>
          <option value="<?= $v ?>" <?= $filtroStatus === $v ? 'selected' : '' ?>><?= $l ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="campo" style="margin:0;min-width:130px;">
      <label style="margin-bottom:4px;">Prioridade</label>
      <select name="prioridade">
        <option value="">Todas</option>
        <?php foreach (['urgente'=>'Urgente','alta'=>'Alta','media'=>'Média','baixa'=>'Baixa'] as $v => $l): ?>
          <option value="<?= $v ?>" <?= $filtroPrioridade === $v ? 'selected' : '' ?>><?= $l ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn btn-primario btn-sm">Filtrar</button>
    <a href="/app-tecnicos/admin/compras/solicitacoes/lista.php" class="btn btn-neutro btn-sm">Limpar</a>
    <a href="/app-tecnicos/admin/compras/solicitacoes/nova.php" class="btn btn-primario btn-sm" style="margin-left:auto;">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Nova Solicitação
    </a>
  </form>
</div>

<div id="alertaLista"></div>

<!-- Tabela -->
<div class="card">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
    <span style="font-size:13px;color:#6b7789;"><?= $total ?> resultado<?= $total !== 1 ? 's' : '' ?></span>
  </div>

  <?php if (empty($lista)): ?>
    <div style="text-align:center;padding:40px;color:#94a3b8;">
      <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom:12px;opacity:.4;"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="2" ry="2"/></svg>
      <br>Nenhuma solicitação encontrada.
    </div>
  <?php else: ?>
  <div style="overflow-x:auto;">
  <table>
    <thead>
      <tr>
        <th>Número</th>
        <th>Justificativa</th>
        <th>Solicitante</th>
        <th>Prioridade</th>
        <th>Itens</th>
        <th>Valor Est.</th>
        <th>Status</th>
        <th>Data</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($lista as $sc): ?>
        <tr>
          <td style="font-weight:700;font-size:12px;white-space:nowrap;">
            <a href="/app-tecnicos/admin/compras/solicitacoes/detalhe.php?id=<?= (int)$sc['id'] ?>" style="color:#1462b0;"><?= htmlspecialchars($sc['numero']) ?></a>
          </td>
          <td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:13px;">
            <?= htmlspecialchars($sc['justificativa']) ?>
          </td>
          <td style="font-size:12px;"><?= htmlspecialchars($sc['solicitante_nome'] ?? '—') ?></td>
          <td>
            <span class="badge <?= cssPrioridadeCompra($sc['prioridade']) ?>" style="font-size:11px;">
              <?= ucfirst($sc['prioridade']) ?>
            </span>
          </td>
          <td style="text-align:center;font-size:13px;"><?= (int)$sc['total_itens'] ?></td>
          <td style="font-size:13px;"><?= formatarMoeda($sc['valor_estimado'] !== null ? (float)$sc['valor_estimado'] : null) ?></td>
          <td><span class="badge <?= cssStatusCompra($sc['status']) ?>"><?= rotuloStatusCompra($sc['status']) ?></span></td>
          <td style="font-size:12px;white-space:nowrap;"><?= date('d/m/Y', strtotime($sc['criado_em'])) ?></td>
          <td>
            <div class="acoes-tabela">
              <a href="/app-tecnicos/admin/compras/solicitacoes/detalhe.php?id=<?= (int)$sc['id'] ?>" class="btn btn-secundario btn-sm">Ver</a>
              <?php if ($isGestor && $sc['status'] === 'aguardando_aprovacao'): ?>
                <button onclick="aprovarRapido(<?= (int)$sc['id'] ?>, '<?= htmlspecialchars($sc['numero']) ?>')" class="btn btn-sucesso btn-sm">✓ Aprovar</button>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>

  <!-- Paginação -->
  <?php if ($paginas > 1): ?>
  <div style="display:flex;justify-content:center;gap:6px;margin-top:16px;flex-wrap:wrap;">
    <?php for ($i = 1; $i <= $paginas; $i++): ?>
      <a href="?<?= http_build_query(array_merge($_GET, ['pagina' => $i])) ?>"
         style="padding:6px 12px;border-radius:6px;border:1px solid <?= $i === $pagina ? '#1462b0' : '#e2e8f0' ?>;background:<?= $i === $pagina ? '#1462b0' : '#fff' ?>;color:<?= $i === $pagina ? '#fff' : '#1c2430' ?>;font-size:13px;font-weight:600;text-decoration:none;">
        <?= $i ?>
      </a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>

<script>
async function aprovarRapido(id, numero) {
  if (!confirm('Aprovar a solicitação '+numero+'?')) return;
  const r = await fetch('/app-tecnicos/api/compras/aprovar.php', {
    method:'POST',
    headers:{'Content-Type':'application/json','Authorization':'Bearer '+(window.APP_JWT||'')},
    body: JSON.stringify({id}),
  });
  const d = await r.json();
  if (d.sucesso) { location.reload(); }
  else { alert(d.erro||'Erro ao aprovar.'); }
}
</script>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
