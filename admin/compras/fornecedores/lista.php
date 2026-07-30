<?php
declare(strict_types=1);

$perfisPermitidosPagina = ['gestor', 'supervisor'];
$tituloPagina = 'Fornecedores';
$paginaAtiva  = 'fornecedores';
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/compras_helpers.php';

$pdo = obterConexao();
$fornecedores = $pdo->query(
    "SELECT f.*, gc_id,
            (SELECT COUNT(*) FROM solicitacoes_compra sc WHERE sc.fornecedor_id=f.id) AS total_compras,
            (SELECT COALESCE(AVG(sc.valor_final),0) FROM solicitacoes_compra sc WHERE sc.fornecedor_id=f.id AND sc.valor_final IS NOT NULL) AS ticket_medio
     FROM fornecedores f ORDER BY f.nome"
)->fetchAll();

$ultimaSync = obterConfiguracao('fornecedores_gc_sync_em');
?>

<!-- Barra de ações -->
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:18px;">
  <div>
    <h2 style="margin:0;font-size:18px;">Fornecedores</h2>
    <?php if ($ultimaSync): ?>
      <span style="font-size:12px;color:#6b7789;">
        Última sincronização com GestãoClick: <strong><?= htmlspecialchars(date('d/m/Y H:i', strtotime($ultimaSync))) ?></strong>
      </span>
    <?php else: ?>
      <span style="font-size:12px;color:#94a3b8;">Ainda não sincronizado com GestãoClick.</span>
    <?php endif; ?>
  </div>
  <button onclick="sincronizarGC()" id="btnSync" class="btn btn-primario" style="display:flex;align-items:center;gap:6px;">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
      <path d="M21 2v6h-6"/><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/>
      <path d="M3 22v-6h6"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/>
    </svg>
    Sincronizar com GestãoClick
  </button>
</div>

<div id="alertaSync" style="margin-bottom:12px;"></div>

<!-- Barra de filtro -->
<div class="card" style="padding:12px 16px;margin-bottom:14px;">
  <input type="text" id="filtroForn" placeholder="Filtrar por nome, CNPJ ou contato…" style="width:100%;max-width:400px;"
    oninput="filtrarTabela(this.value)">
</div>

<!-- Tabela de fornecedores -->
<div class="card" style="margin-bottom:16px;">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
    <h3 style="margin:0;">Fornecedores cadastrados (<span id="contadorForn"><?= count($fornecedores) ?></span>)</h3>
    <button onclick="document.getElementById('modalNovoForn').style.display='flex'" class="btn btn-secundario btn-sm">+ Cadastrar manual</button>
  </div>
  <?php if (empty($fornecedores)): ?>
    <div style="text-align:center;color:#94a3b8;padding:30px;">
      Nenhum fornecedor cadastrado. Clique em <strong>Sincronizar com GestãoClick</strong> para importar automaticamente.
    </div>
  <?php else: ?>
  <div style="overflow-x:auto;">
  <table id="tabelaForn">
    <thead>
      <tr>
        <th>Nome</th>
        <th>CNPJ</th>
        <th>Contato</th>
        <th>E-mail / Telefone</th>
        <th style="text-align:right;">Compras</th>
        <th style="text-align:right;">Ticket Médio</th>
        <th>Status</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($fornecedores as $f): ?>
      <tr class="forn-row" data-busca="<?= htmlspecialchars(strtolower($f['nome'] . ' ' . ($f['cnpj'] ?? '') . ' ' . ($f['contato'] ?? ''))) ?>">
        <td>
          <strong><?= htmlspecialchars($f['nome']) ?></strong>
          <?php if (!empty($f['razao_social'])): ?>
            <br><small style="color:#6b7789;"><?= htmlspecialchars($f['razao_social']) ?></small>
          <?php endif; ?>
          <?php if ($f['gc_id']): ?>
            <span style="font-size:10px;background:#e0f0ff;color:#0055b3;border-radius:4px;padding:1px 5px;margin-left:4px;">GC</span>
          <?php endif; ?>
        </td>
        <td style="font-size:12px;"><?= htmlspecialchars($f['cnpj'] ?? '—') ?></td>
        <td style="font-size:12px;"><?= htmlspecialchars($f['contato'] ?? '—') ?></td>
        <td style="font-size:12px;">
          <?= htmlspecialchars($f['email'] ?? '') ?>
          <?php if (!empty($f['email']) && !empty($f['telefone'])): ?><br><?php endif; ?>
          <?= htmlspecialchars($f['telefone'] ?? '') ?>
          <?php if (empty($f['email']) && empty($f['telefone'])): ?>—<?php endif; ?>
        </td>
        <td style="text-align:right;"><?= (int)$f['total_compras'] ?></td>
        <td style="text-align:right;"><?= formatarMoeda((float)$f['ticket_medio']) ?></td>
        <td><span class="badge <?= $f['ativo'] ? 'concluido' : 'cancelado' ?>"><?= $f['ativo'] ? 'Ativo' : 'Inativo' ?></span></td>
        <td>
          <button onclick="editarForn(<?= htmlspecialchars(json_encode($f), ENT_QUOTES) ?>)" class="btn btn-secundario btn-sm">Editar</button>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</div>

<!-- Modal: Novo fornecedor manual -->
<div id="modalNovoForn" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:14px;padding:24px;width:min(580px,94vw);max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.3);">
    <h3 style="margin-top:0;">Cadastrar Fornecedor</h3>
    <div id="alertaForn"></div>
    <div class="linha-form">
      <div class="campo"><label>Nome fantasia *</label><input type="text" id="fNome" required placeholder="Nome do fornecedor"></div>
      <div class="campo"><label>Razão Social</label><input type="text" id="fRazao" placeholder="Razão social completa"></div>
    </div>
    <div class="linha-form">
      <div class="campo"><label>CNPJ</label><input type="text" id="fCnpj" placeholder="00.000.000/0000-00"></div>
      <div class="campo"><label>Telefone</label><input type="tel" id="fTel" placeholder="(00) 00000-0000"></div>
    </div>
    <div class="linha-form">
      <div class="campo"><label>E-mail</label><input type="email" id="fEmail"></div>
      <div class="campo"><label>Contato (pessoa)</label><input type="text" id="fContato" placeholder="Nome do contato"></div>
    </div>
    <div class="campo"><label>Observações</label><textarea id="fObs" rows="2"></textarea></div>
    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:8px;">
      <button onclick="document.getElementById('modalNovoForn').style.display='none'" class="btn btn-neutro btn-sm">Cancelar</button>
      <button onclick="cadastrarFornecedor()" class="btn btn-primario btn-sm">Cadastrar</button>
    </div>
  </div>
</div>

<!-- Modal edição -->
<div id="modalForn" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:14px;padding:24px;width:min(560px,94vw);max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.3);">
    <h3 style="margin-top:0;">Editar Fornecedor</h3>
    <input type="hidden" id="editId">
    <div class="linha-form">
      <div class="campo"><label>Nome *</label><input type="text" id="editNome" required></div>
      <div class="campo"><label>Razão Social</label><input type="text" id="editRazao"></div>
    </div>
    <div class="linha-form">
      <div class="campo"><label>CNPJ</label><input type="text" id="editCnpj"></div>
      <div class="campo"><label>Telefone</label><input type="tel" id="editTel"></div>
    </div>
    <div class="linha-form">
      <div class="campo"><label>E-mail</label><input type="email" id="editEmail"></div>
      <div class="campo"><label>Contato</label><input type="text" id="editContato"></div>
    </div>
    <div class="campo"><label>Observações</label><textarea id="editObs" rows="2"></textarea></div>
    <div class="campo"><label style="display:flex;align-items:center;gap:8px;font-weight:400;"><input type="checkbox" id="editAtivo" checked> Ativo</label></div>
    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:8px;">
      <button onclick="document.getElementById('modalForn').style.display='none'" class="btn btn-neutro btn-sm">Cancelar</button>
      <button onclick="salvarEdicao()" class="btn btn-primario btn-sm">Salvar</button>
    </div>
  </div>
</div>

<script>
// ---------- Sincronizar GC ----------
async function sincronizarGC() {
  const btn   = document.getElementById('btnSync');
  const alerta = document.getElementById('alertaSync');
  btn.disabled = true;
  btn.innerHTML = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="animation:spin 1s linear infinite"><path d="M21 2v6h-6"/><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M3 22v-6h6"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/></svg> Sincronizando…';
  alerta.innerHTML = '<div style="background:#f0f7ff;border:1px solid #bfdbfe;border-radius:8px;padding:12px 16px;font-size:13px;color:#1e40af;">Importando fornecedores do GestãoClick, aguarde…</div>';
  try {
    const r = await fetch('/app-tecnicos/api/fornecedores/sincronizar-gc.php', {
      method: 'POST',
      headers: {'Authorization': 'Bearer ' + (window.APP_JWT || '')},
    });
    const d = await r.json();
    if (d.sucesso) {
      const stats = d.dados;
      alerta.innerHTML = `<div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:12px 16px;font-size:13px;color:#166534;">
        ✓ Sincronização concluída! <strong>${stats.inseridos}</strong> novos · <strong>${stats.atualizados}</strong> atualizados · <strong>${stats.total}</strong> total
      </div>`;
      setTimeout(() => location.reload(), 1500);
    } else {
      alerta.innerHTML = `<div style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:12px 16px;font-size:13px;color:#991b1b;">Erro: ${d.erro || 'Falha na sincronização.'}</div>`;
      btn.disabled = false;
      btn.innerHTML = '↺ Sincronizar com GestãoClick';
    }
  } catch (e) {
    alerta.innerHTML = `<div style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:12px 16px;font-size:13px;color:#991b1b;">Erro de comunicação: ${e.message}</div>`;
    btn.disabled = false;
    btn.innerHTML = '↺ Sincronizar com GestãoClick';
  }
}

// ---------- Filtro local ----------
function filtrarTabela(q) {
  const termo = q.toLowerCase();
  let visiveis = 0;
  document.querySelectorAll('.forn-row').forEach(tr => {
    const match = !termo || tr.dataset.busca.includes(termo);
    tr.style.display = match ? '' : 'none';
    if (match) visiveis++;
  });
  document.getElementById('contadorForn').textContent = visiveis;
}

// ---------- CRUD local ----------
async function cadastrarFornecedor() {
  const r = await fetch('/app-tecnicos/api/fornecedores/criar.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json', 'Authorization': 'Bearer ' + (window.APP_JWT || '')},
    body: JSON.stringify({
      nome: document.getElementById('fNome').value,
      razao_social: document.getElementById('fRazao').value || null,
      cnpj: document.getElementById('fCnpj').value || null,
      telefone: document.getElementById('fTel').value || null,
      email: document.getElementById('fEmail').value || null,
      contato: document.getElementById('fContato').value || null,
      observacoes: document.getElementById('fObs').value || null,
    }),
  });
  const d = await r.json();
  if (d.sucesso) { location.reload(); }
  else { document.getElementById('alertaForn').innerHTML = '<div style="color:#c62f2f;margin-bottom:10px;">' + (d.erro || 'Erro.') + '</div>'; }
}

function editarForn(f) {
  document.getElementById('editId').value      = f.id;
  document.getElementById('editNome').value    = f.nome || '';
  document.getElementById('editRazao').value   = f.razao_social || '';
  document.getElementById('editCnpj').value    = f.cnpj || '';
  document.getElementById('editTel').value     = f.telefone || '';
  document.getElementById('editEmail').value   = f.email || '';
  document.getElementById('editContato').value = f.contato || '';
  document.getElementById('editObs').value     = f.observacoes || '';
  document.getElementById('editAtivo').checked = !!parseInt(f.ativo);
  document.getElementById('modalForn').style.display = 'flex';
}

async function salvarEdicao() {
  const r = await fetch('/app-tecnicos/api/fornecedores/atualizar.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json', 'Authorization': 'Bearer ' + (window.APP_JWT || '')},
    body: JSON.stringify({
      id: parseInt(document.getElementById('editId').value),
      nome: document.getElementById('editNome').value,
      razao_social: document.getElementById('editRazao').value || null,
      cnpj: document.getElementById('editCnpj').value || null,
      telefone: document.getElementById('editTel').value || null,
      email: document.getElementById('editEmail').value || null,
      contato: document.getElementById('editContato').value || null,
      observacoes: document.getElementById('editObs').value || null,
      ativo: document.getElementById('editAtivo').checked ? 1 : 0,
    }),
  });
  const d = await r.json();
  if (d.sucesso) { location.reload(); } else { alert(d.erro || 'Erro.'); }
}

// Animação do ícone de sync
const style = document.createElement('style');
style.textContent = '@keyframes spin { from{transform:rotate(0deg)} to{transform:rotate(360deg)} }';
document.head.appendChild(style);
</script>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
