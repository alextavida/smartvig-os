<?php
declare(strict_types=1);

$perfisPermitidosPagina = ['gestor', 'supervisor', 'solicitante', 'comprador', 'aprovador'];
$tituloPagina = 'Detalhe da Solicitação';
$paginaAtiva  = 'compras_lista';
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/compras_helpers.php';

$pdo = obterConexao();
$id  = (int) ($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: lista.php'); exit; }

$stmt = $pdo->prepare(
    "SELECT sc.*,
            sol.nome  AS solicitante_nome,  sol.email  AS solicitante_email,
            comp.nome AS comprador_nome,
            apr.nome  AS aprovador_nome,
            rec.nome  AS recebedor_nome,
            f.nome    AS fornecedor_nome, f.cnpj AS fornecedor_cnpj,
            f.telefone AS fornecedor_telefone, f.contato AS fornecedor_contato,
            cc.nome   AS centro_custo_nome,
            cat.nome  AS categoria_nome
     FROM solicitacoes_compra sc
     LEFT JOIN usuarios sol  ON sol.id  = sc.solicitante_id
     LEFT JOIN usuarios comp ON comp.id = sc.comprador_id
     LEFT JOIN usuarios apr  ON apr.id  = sc.aprovador_id
     LEFT JOIN usuarios rec  ON rec.id  = sc.recebido_por_id
     LEFT JOIN fornecedores f   ON f.id  = sc.fornecedor_id
     LEFT JOIN centros_custo cc ON cc.id = sc.centro_custo_id
     LEFT JOIN categorias_compra cat ON cat.id = sc.categoria_id
     WHERE sc.id = :id LIMIT 1"
);
$stmt->execute(['id' => $id]);
$sc = $stmt->fetch();
if (!$sc) { header('Location: lista.php'); exit; }

$itens    = $pdo->prepare('SELECT * FROM solicitacao_itens WHERE solicitacao_id=:id ORDER BY id')->execute(['id'=>$id]) ? [] : [];
$stmtI    = $pdo->prepare('SELECT * FROM solicitacao_itens WHERE solicitacao_id=:id ORDER BY id');
$stmtI->execute(['id' => $id]);
$itens    = $stmtI->fetchAll();

$stmtH    = $pdo->prepare('SELECT * FROM solicitacao_historico WHERE solicitacao_id=:id ORDER BY criado_em ASC');
$stmtH->execute(['id' => $id]);
$historico = $stmtH->fetchAll();

$stmtA    = $pdo->prepare('SELECT * FROM solicitacao_anexos WHERE solicitacao_id=:id ORDER BY criado_em');
$stmtA->execute(['id' => $id]);
$anexos   = $stmtA->fetchAll();

$fornecedores = $pdo->query('SELECT id, nome FROM fornecedores WHERE ativo=1 ORDER BY nome')->fetchAll();

$uid       = (int) $usuarioAtual['usuario_id'];
$isGestor  = temRole($usuarioAtual, 'aprovador', 'supervisor');
$podeComprar = temRole($usuarioAtual, 'comprador');
$ehSolicitante = (int) $sc['solicitante_id'] === $uid;
$status    = $sc['status'];
$tituloPagina = 'Solicitação ' . htmlspecialchars($sc['numero']);

// Mensagem de feedback
$msg = $_GET['msg'] ?? '';
?>

<div style="margin-bottom:14px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
  <a href="/app-tecnicos/admin/compras/solicitacoes/lista.php" style="font-size:13px;color:#6b7789;">← Voltar</a>
  <span style="color:#cbd3dd;">›</span>
  <span style="font-size:13px;font-weight:600;"><?= htmlspecialchars($sc['numero']) ?></span>
  <span class="badge <?= cssStatusCompra($status) ?>" style="margin-left:4px;"><?= rotuloStatusCompra($status) ?></span>
  <span class="badge <?= cssPrioridadeCompra($sc['prioridade']) ?>" style="font-size:10px;"><?= ucfirst($sc['prioridade']) ?></span>
</div>

<?php if ($msg === 'criado'): ?>
  <div class="card" style="background:#e5f5ec;border:1px solid #16803c;color:#16803c;padding:10px 16px;margin-bottom:14px;">
    ✓ Solicitação criada com sucesso.
  </div>
<?php endif; ?>

<div id="alerta"></div>

<!-- Barra de ação rápida para aprovador -->
<?php if ($isGestor && $status === 'aguardando_aprovacao'): ?>
<div class="aprovacao-bar">
  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#b8860b" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
  <span style="font-size:13px;font-weight:600;color:#7a5800;flex:1;">Aguardando sua aprovação</span>
  <button onclick="acao('aprovar')" class="btn btn-sucesso btn-sm">✓ Aprovar</button>
  <button onclick="mostrarModal('devolver')" class="btn btn-neutro btn-sm">↩ Devolver</button>
  <button onclick="mostrarModal('reprovar')" class="btn btn-perigo btn-sm">✗ Reprovar</button>
</div>
<?php endif; ?>

<!-- Barra do comprador -->
<?php if ($podeComprar && $status === 'aprovado'): ?>
<div class="aprovacao-bar" style="background:linear-gradient(135deg,#e0f0ff,#fff);border-color:#93c5fd;">
  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#1462b0" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/></svg>
  <span style="font-size:13px;font-weight:600;color:#1a2d5a;flex:1;">Aprovada — registrar compra</span>
  <button onclick="document.getElementById('secaoCompra').scrollIntoView({behavior:'smooth'})" class="btn btn-primario btn-sm">Registrar Compra →</button>
</div>
<?php endif; ?>

<!-- Barra de recebimento -->
<?php if ($status === 'em_compra'): ?>
<div class="aprovacao-bar" style="background:linear-gradient(135deg,#e5f5ec,#fff);border-color:#86efac;">
  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#1e8e5a" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
  <span style="font-size:13px;font-weight:600;color:#155724;flex:1;">Em compra — registrar recebimento</span>
  <button onclick="document.getElementById('secaoRecebimento').scrollIntoView({behavior:'smooth'})" class="btn btn-sucesso btn-sm">Confirmar Recebimento →</button>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:18px;" class="responsive-2col">
  <!-- Coluna principal -->
  <div>

    <!-- Dados da solicitação -->
    <div class="card" style="margin-bottom:16px;">
      <h3 style="margin-top:0;">Dados da Solicitação</h3>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
        <div><span style="font-size:12px;color:#6b7789;display:block;">Solicitante</span><strong><?= htmlspecialchars($sc['solicitante_nome'] ?? '—') ?></strong></div>
        <div><span style="font-size:12px;color:#6b7789;display:block;">Data</span><strong><?= date('d/m/Y H:i', strtotime($sc['criado_em'])) ?></strong></div>
        <div><span style="font-size:12px;color:#6b7789;display:block;">Destino</span><strong><?= rotuloDestinoCompra($sc['destino']) ?><?= $sc['destino_referencia'] ? ': ' . htmlspecialchars($sc['destino_referencia']) : '' ?></strong></div>
        <div><span style="font-size:12px;color:#6b7789;display:block;">Centro de Custo</span><strong><?= htmlspecialchars($sc['centro_custo_nome'] ?? '—') ?></strong></div>
        <div><span style="font-size:12px;color:#6b7789;display:block;">Categoria</span><strong><?= htmlspecialchars($sc['categoria_nome'] ?? '—') ?></strong></div>
        <div><span style="font-size:12px;color:#6b7789;display:block;">Valor Estimado</span><strong><?= formatarMoeda($sc['valor_estimado'] !== null ? (float)$sc['valor_estimado'] : null) ?></strong></div>
      </div>
      <div style="background:#f4f9fe;border-radius:8px;padding:12px 14px;margin-bottom:12px;">
        <div style="font-size:12px;font-weight:700;color:#6b7789;margin-bottom:4px;">Justificativa</div>
        <div style="font-size:14px;"><?= nl2br(htmlspecialchars($sc['justificativa'])) ?></div>
      </div>
      <?php if ($sc['observacoes']): ?>
      <div style="font-size:13px;color:#475569;"><?= nl2br(htmlspecialchars($sc['observacoes'])) ?></div>
      <?php endif; ?>
      <?php if ($sc['motivo_reprovacao'] && in_array($status, ['reprovado','devolvido'], true)): ?>
      <div style="background:#fbe4e4;border-left:4px solid #c62f2f;border-radius:8px;padding:10px 14px;margin-top:12px;">
        <strong style="color:#c62f2f;font-size:12px;">Motivo:</strong>
        <div style="font-size:13px;margin-top:4px;"><?= nl2br(htmlspecialchars($sc['motivo_reprovacao'])) ?></div>
      </div>
      <?php endif; ?>
    </div>

    <!-- Itens -->
    <div class="card" style="margin-bottom:16px;">
      <h3 style="margin-top:0;">Itens (<?= count($itens) ?>)</h3>
      <div style="overflow-x:auto;">
      <table>
        <thead>
          <tr><th>Produto</th><th>Código</th><th>Unid.</th><th style="text-align:right;">Qtd</th><th style="text-align:right;">Val. Est.</th><th style="text-align:right;">Val. Final</th><th style="text-align:right;">Qtd Recebida</th></tr>
        </thead>
        <tbody>
          <?php foreach ($itens as $it): ?>
          <tr>
            <td><strong style="font-size:13px;"><?= htmlspecialchars($it['produto_nome']) ?></strong><?php if ($it['observacao']): ?><br><small style="color:#6b7789;"><?= htmlspecialchars($it['observacao']) ?></small><?php endif; ?></td>
            <td style="font-size:12px;color:#6b7789;"><?= htmlspecialchars($it['produto_codigo'] ?? '—') ?></td>
            <td><?= htmlspecialchars($it['produto_unidade'] ?? 'UN') ?></td>
            <td style="text-align:right;"><?= number_format((float)$it['quantidade'], 3, ',', '.') ?></td>
            <td style="text-align:right;"><?= formatarMoeda($it['valor_estimado'] !== null ? (float)$it['valor_estimado'] : null) ?></td>
            <td style="text-align:right;"><?= formatarMoeda($it['valor_final'] !== null ? (float)$it['valor_final'] : null) ?></td>
            <td style="text-align:right;"><?= $it['quantidade_recebida'] !== null ? number_format((float)$it['quantidade_recebida'],3,',','.') : '—' ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr style="font-weight:700;">
            <td colspan="4">Total</td>
            <td style="text-align:right;"><?= formatarMoeda($sc['valor_estimado'] !== null ? (float)$sc['valor_estimado'] : null) ?></td>
            <td style="text-align:right;"><?= formatarMoeda($sc['valor_final'] !== null ? (float)$sc['valor_final'] : null) ?></td>
            <td></td>
          </tr>
        </tfoot>
      </table>
      </div>
    </div>

    <!-- Seção Compra (comprador) -->
    <?php if (($podeComprar || $isGestor) && in_array($status, ['aprovado','em_compra'], true)): ?>
    <div class="card" id="secaoCompra" style="margin-bottom:16px;">
      <h3 style="margin-top:0;">Registrar Compra</h3>
      <div class="linha-form">
        <div class="campo">
          <label>Fornecedor</label>
          <select id="fornecedor_id">
            <option value="">— Selecionar —</option>
            <?php foreach ($fornecedores as $f): ?>
              <option value="<?= (int)$f['id'] ?>" <?= (int)($sc['fornecedor_id']??0)===(int)$f['id']?'selected':'' ?>><?= htmlspecialchars($f['nome']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="campo">
          <label>Nº do Pedido</label>
          <input type="text" id="numero_pedido" value="<?= htmlspecialchars($sc['numero_pedido']??'') ?>" placeholder="Número do pedido/orçamento">
        </div>
      </div>
      <div class="linha-form">
        <div class="campo">
          <label>Valor Negociado (R$)</label>
          <input type="number" id="valor_negociado" value="<?= $sc['valor_negociado']??'' ?>" min="0" step="0.01" placeholder="0,00">
        </div>
        <div class="campo">
          <label>Frete (R$)</label>
          <div style="display:flex;align-items:center;gap:10px;">
            <input type="number" id="valor_frete" value="<?= $sc['valor_frete']??'0.00' ?>" min="0" step="0.01" placeholder="0,00" style="flex:1;">
            <label style="display:flex;align-items:center;gap:6px;font-weight:400;white-space:nowrap;"><input type="checkbox" id="frete_gratis" <?= $sc['frete_gratis']?'checked':'' ?> onchange="document.getElementById('valor_frete').disabled=this.checked;"> Frete grátis</label>
          </div>
        </div>
      </div>
      <div class="linha-form">
        <div class="campo">
          <label>Data da Compra</label>
          <input type="date" id="data_compra" value="<?= $sc['data_compra'] ?? date('Y-m-d') ?>">
        </div>
        <div class="campo">
          <label>Prazo de Entrega</label>
          <input type="date" id="prazo_entrega" value="<?= $sc['prazo_entrega']??'' ?>">
        </div>
      </div>
      <div class="linha-form">
        <div class="campo">
          <label>Nota Fiscal Nº</label>
          <input type="text" id="nota_fiscal_numero" value="<?= htmlspecialchars($sc['nota_fiscal_numero']??'') ?>">
        </div>
        <div class="campo">
          <label>Data da NF</label>
          <input type="date" id="nota_fiscal_data" value="<?= $sc['nota_fiscal_data']??'' ?>">
        </div>
      </div>
      <div class="campo">
        <label>Observações da compra</label>
        <textarea id="observacoes_compra" rows="2"><?= htmlspecialchars($sc['observacoes_compra']??'') ?></textarea>
      </div>
      <button onclick="registrarCompra()" class="btn btn-primario">Salvar Compra</button>
    </div>
    <?php elseif ($sc['status'] === 'em_compra' || $sc['status'] === 'recebido' || $sc['status'] === 'concluido'): ?>
    <div class="card" style="margin-bottom:16px;">
      <h3 style="margin-top:0;">Dados da Compra</h3>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div><span style="font-size:12px;color:#6b7789;display:block;">Fornecedor</span><strong><?= htmlspecialchars($sc['fornecedor_nome']??'—') ?></strong></div>
        <div><span style="font-size:12px;color:#6b7789;display:block;">Comprador</span><strong><?= htmlspecialchars($sc['comprador_nome']??'—') ?></strong></div>
        <div><span style="font-size:12px;color:#6b7789;display:block;">Valor Negociado</span><strong><?= formatarMoeda($sc['valor_negociado']!==null?(float)$sc['valor_negociado']:null) ?></strong></div>
        <div><span style="font-size:12px;color:#6b7789;display:block;">Frete</span><strong><?= $sc['frete_gratis'] ? 'Grátis' : formatarMoeda((float)($sc['valor_frete']??0)) ?></strong></div>
        <div><span style="font-size:12px;color:#6b7789;display:block;">Valor Final</span><strong style="color:#1e8e5a;"><?= formatarMoeda($sc['valor_final']!==null?(float)$sc['valor_final']:null) ?></strong></div>
        <div><span style="font-size:12px;color:#6b7789;display:block;">Prazo de Entrega</span><strong><?= $sc['prazo_entrega'] ? date('d/m/Y', strtotime($sc['prazo_entrega'])) : '—' ?></strong></div>
        <?php if ($sc['nota_fiscal_numero']): ?>
        <div><span style="font-size:12px;color:#6b7789;display:block;">Nota Fiscal</span><strong>NF <?= htmlspecialchars($sc['nota_fiscal_numero']) ?><?= $sc['nota_fiscal_data'] ? ' · ' . date('d/m/Y', strtotime($sc['nota_fiscal_data'])) : '' ?></strong></div>
        <?php endif; ?>
        <?php if ($sc['numero_pedido']): ?>
        <div><span style="font-size:12px;color:#6b7789;display:block;">Nº Pedido</span><strong><?= htmlspecialchars($sc['numero_pedido']) ?></strong></div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Seção Recebimento -->
    <?php if (in_array($status, ['em_compra'], true)): ?>
    <div class="card" id="secaoRecebimento" style="margin-bottom:16px;">
      <h3 style="margin-top:0;">Registrar Recebimento</h3>
      <div class="campo">
        <label>Quantidades Recebidas</label>
        <?php foreach ($itens as $it): ?>
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
          <span style="flex:1;font-size:13px;"><?= htmlspecialchars($it['produto_nome']) ?></span>
          <span style="font-size:12px;color:#6b7789;">Pedido: <?= number_format((float)$it['quantidade'],3,',','.') ?></span>
          <input type="number" class="qtd-recebida" data-id="<?= (int)$it['id'] ?>" value="<?= $it['quantidade'] ?>" min="0" step="0.001" style="width:100px;">
        </div>
        <?php endforeach; ?>
      </div>
      <div class="campo">
        <label>Observações do Recebimento</label>
        <textarea id="obs_recebimento" rows="2"></textarea>
      </div>
      <button onclick="registrarRecebimento()" class="btn btn-sucesso">Confirmar Recebimento</button>
    </div>
    <?php elseif (in_array($status, ['recebido','concluido'], true)): ?>
    <div class="card" style="margin-bottom:16px;">
      <h3 style="margin-top:0;">Recebimento</h3>
      <div style="display:flex;gap:16px;flex-wrap:wrap;">
        <div><span style="font-size:12px;color:#6b7789;display:block;">Recebido por</span><strong><?= htmlspecialchars($sc['recebedor_nome']??'—') ?></strong></div>
        <div><span style="font-size:12px;color:#6b7789;display:block;">Data</span><strong><?= $sc['recebido_em'] ? date('d/m/Y H:i', strtotime($sc['recebido_em'])) : '—' ?></strong></div>
      </div>
      <?php if ($sc['observacoes_recebimento']): ?>
      <div style="margin-top:10px;font-size:13px;"><?= nl2br(htmlspecialchars($sc['observacoes_recebimento'])) ?></div>
      <?php endif; ?>
      <?php if ($status === 'recebido' && ($podeComprar || $isGestor)): ?>
      <button onclick="concluir()" class="btn btn-sucesso btn-sm" style="margin-top:10px;">Marcar como Concluído</button>
      <?php endif; ?>
    </div>
    <?php endif; ?>

  </div>

  <!-- Coluna lateral -->
  <div>
    <!-- Histórico -->
    <div class="card" style="margin-bottom:16px;">
      <h3 style="margin-top:0;font-size:.95rem;">Histórico</h3>
      <div class="timeline">
        <?php foreach (array_reverse($historico) as $h): ?>
        <div class="timeline-item">
          <div class="timeline-dot" style="font-size:10px;"><?= mb_strtoupper(mb_substr($h['usuario_nome']??'?', 0, 2)) ?></div>
          <div class="timeline-corpo">
            <div class="timeline-acao"><?= htmlspecialchars($h['acao']) ?></div>
            <div class="timeline-meta"><?= htmlspecialchars($h['usuario_nome']??'Sistema') ?> · <?= date('d/m H:i', strtotime($h['criado_em'])) ?></div>
            <?php if ($h['detalhe']): ?><div class="timeline-detalhe"><?= nl2br(htmlspecialchars($h['detalhe'])) ?></div><?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Ações -->
    <div class="card">
      <h3 style="margin-top:0;font-size:.95rem;">Ações</h3>
      <div style="display:flex;flex-direction:column;gap:8px;">
        <?php if (in_array($status, ['rascunho','devolvido'], true) && ($ehSolicitante || $isGestor)): ?>
          <button onclick="enviarAprovacao()" class="btn btn-primario btn-sm">Enviar para Aprovação</button>
        <?php endif; ?>
        <?php if (($ehSolicitante || $isGestor) && in_array($status, ['rascunho','devolvido','reprovado'], true)): ?>
          <button onclick="cancelar()" class="btn btn-perigo btn-sm">Cancelar Solicitação</button>
        <?php endif; ?>
        <?php if ($isGestor && $status === 'aguardando_aprovacao'): ?>
          <button onclick="acao('aprovar')" class="btn btn-sucesso btn-sm">✓ Aprovar</button>
          <button onclick="mostrarModal('devolver')" class="btn btn-neutro btn-sm">↩ Devolver</button>
          <button onclick="mostrarModal('reprovar')" class="btn btn-perigo btn-sm">✗ Reprovar</button>
        <?php endif; ?>
      </div>
    </div>

    <!-- Anexos -->
    <div class="card" style="margin-top:16px;">
      <h3 style="margin-top:0;font-size:.95rem;">Anexos (<?= count($anexos) ?>)</h3>
      <?php foreach ($anexos as $anx): ?>
        <div style="display:flex;align-items:center;gap:8px;padding:6px 0;border-bottom:1px solid #eef1f5;font-size:12px;">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/></svg>
          <a href="/app-tecnicos/<?= htmlspecialchars($anx['caminho']) ?>" target="_blank"><?= htmlspecialchars($anx['nome_original']) ?></a>
        </div>
      <?php endforeach; ?>
      <div style="margin-top:10px;">
        <input type="file" id="uploadAnexo" multiple style="font-size:12px;">
        <button onclick="uploadAnexo()" class="btn btn-neutro btn-sm" style="margin-top:6px;">Fazer upload</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal de reprovação/devolução -->
<div id="modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:14px;padding:24px;width:min(480px,94vw);box-shadow:0 20px 60px rgba(0,0,0,.3);">
    <h3 id="modalTitulo" style="margin-top:0;"></h3>
    <div class="campo">
      <label id="modalLabel">Motivo *</label>
      <textarea id="modalMotivo" rows="3" required placeholder="Descreva o motivo…"></textarea>
    </div>
    <div style="display:flex;gap:8px;justify-content:flex-end;">
      <button onclick="fecharModal()" class="btn btn-neutro btn-sm">Cancelar</button>
      <button id="modalBtn" class="btn btn-perigo btn-sm">Confirmar</button>
    </div>
  </div>
</div>

<script>
const SC_ID = <?= $id ?>;

async function acao(tipo, extra = {}) {
  const endpoints = {
    aprovar: '/app-tecnicos/api/compras/aprovar.php',
    reprovar: '/app-tecnicos/api/compras/reprovar.php',
    devolver: '/app-tecnicos/api/compras/devolver.php',
    cancelar: '/app-tecnicos/api/compras/cancelar.php',
  };
  const r = await fetch(endpoints[tipo], {
    method:'POST',
    headers:{'Content-Type':'application/json','Authorization':'Bearer '+(window.APP_JWT||'')},
    body: JSON.stringify({id: SC_ID, ...extra}),
  });
  const d = await r.json();
  if (d.sucesso) { location.reload(); }
  else { alert(d.erro||'Erro.'); }
}

let modalAcao = '';
function mostrarModal(tipo) {
  modalAcao = tipo;
  document.getElementById('modalTitulo').textContent = tipo==='reprovar' ? 'Reprovar Solicitação' : 'Devolver para Ajuste';
  document.getElementById('modalLabel').textContent  = tipo==='reprovar' ? 'Motivo da reprovação *' : 'Motivo da devolução *';
  document.getElementById('modalBtn').textContent    = tipo==='reprovar' ? '✗ Reprovar' : '↩ Devolver';
  document.getElementById('modalMotivo').value       = '';
  document.getElementById('modal').style.display     = 'flex';
}
function fecharModal() { document.getElementById('modal').style.display='none'; }
document.getElementById('modalBtn').onclick = () => {
  const motivo = document.getElementById('modalMotivo').value.trim();
  if (!motivo) { alert('Informe o motivo.'); return; }
  fecharModal();
  acao(modalAcao, {motivo});
};

async function enviarAprovacao() {
  if (!confirm('Enviar para aprovação?')) return;
  const r = await fetch('/app-tecnicos/api/compras/enviar_aprovacao.php', {
    method:'POST',
    headers:{'Content-Type':'application/json','Authorization':'Bearer '+(window.APP_JWT||'')},
    body: JSON.stringify({id: SC_ID}),
  });
  const d = await r.json();
  if (d.sucesso) { location.reload(); } else { alert(d.erro||'Erro.'); }
}

async function cancelar() {
  if (!confirm('Cancelar esta solicitação? Esta ação não pode ser desfeita.')) return;
  acao('cancelar');
}

async function registrarCompra() {
  const dados = {
    id:                  SC_ID,
    fornecedor_id:       parseInt(document.getElementById('fornecedor_id').value)||null,
    numero_pedido:       document.getElementById('numero_pedido').value||null,
    valor_negociado:     parseFloat(document.getElementById('valor_negociado').value)||null,
    valor_frete:         parseFloat(document.getElementById('valor_frete').value)||0,
    frete_gratis:        document.getElementById('frete_gratis').checked,
    data_compra:         document.getElementById('data_compra').value||null,
    prazo_entrega:       document.getElementById('prazo_entrega').value||null,
    observacoes_compra:  document.getElementById('observacoes_compra').value||null,
    nota_fiscal_numero:  document.getElementById('nota_fiscal_numero').value||null,
    nota_fiscal_data:    document.getElementById('nota_fiscal_data').value||null,
  };
  const r = await fetch('/app-tecnicos/api/compras/comprador_atualizar.php', {
    method:'POST',
    headers:{'Content-Type':'application/json','Authorization':'Bearer '+(window.APP_JWT||'')},
    body: JSON.stringify(dados),
  });
  const d = await r.json();
  if (d.sucesso) { location.reload(); } else { alert(d.erro||'Erro.'); }
}

async function registrarRecebimento() {
  const itens = [...document.querySelectorAll('.qtd-recebida')].map(e => ({
    id: parseInt(e.dataset.id), quantidade_recebida: parseFloat(e.value)||0,
  }));
  const r = await fetch('/app-tecnicos/api/compras/receber.php', {
    method:'POST',
    headers:{'Content-Type':'application/json','Authorization':'Bearer '+(window.APP_JWT||'')},
    body: JSON.stringify({id: SC_ID, itens, observacoes: document.getElementById('obs_recebimento').value||null}),
  });
  const d = await r.json();
  if (d.sucesso) { location.reload(); } else { alert(d.erro||'Erro.'); }
}

async function concluir() {
  if (!confirm('Marcar como concluído?')) return;
  const r = await fetch('/app-tecnicos/api/compras/concluir.php', {
    method:'POST',
    headers:{'Content-Type':'application/json','Authorization':'Bearer '+(window.APP_JWT||'')},
    body: JSON.stringify({id: SC_ID}),
  });
  const d = await r.json();
  if (d.sucesso) { location.reload(); } else { alert(d.erro||'Erro.'); }
}

async function uploadAnexo() {
  const files = document.getElementById('uploadAnexo').files;
  if (!files.length) return;
  const fd = new FormData();
  fd.append('solicitacao_id', SC_ID);
  for (const f of files) { fd.append('files[]', f); }
  const r = await fetch('/app-tecnicos/api/compras/upload_anexo.php', {
    method:'POST',
    headers:{'Authorization':'Bearer '+(window.APP_JWT||'')},
    body: fd,
  });
  const d = await r.json();
  if (d.sucesso) { location.reload(); } else { alert(d.erro||'Erro no upload.'); }
}
</script>
<style>
@media(max-width:768px) { .responsive-2col { grid-template-columns:1fr !important; } }
</style>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
