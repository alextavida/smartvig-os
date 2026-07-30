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
    "SELECT f.*, (SELECT COUNT(*) FROM solicitacoes_compra sc WHERE sc.fornecedor_id=f.id) AS total_compras,
            (SELECT COALESCE(AVG(sc.valor_final),0) FROM solicitacoes_compra sc WHERE sc.fornecedor_id=f.id AND sc.valor_final IS NOT NULL) AS ticket_medio
     FROM fornecedores f ORDER BY f.nome"
)->fetchAll();
?>

<!-- Abas -->
<div style="display:flex;gap:0;border-bottom:2px solid #e2e8f0;margin-bottom:18px;">
  <button onclick="mostrarAba('local')" id="abaLocal" class="aba-forn aba-forn-ativa" style="padding:10px 22px;border:none;background:none;font-weight:700;font-size:13px;cursor:pointer;border-bottom:3px solid var(--azul-700);color:var(--azul-700);margin-bottom:-2px;">
    Cadastrados (<?= count($fornecedores) ?>)
  </button>
  <button onclick="mostrarAba('gc')" id="abaGC" class="aba-forn" style="padding:10px 22px;border:none;background:none;font-weight:600;font-size:13px;cursor:pointer;color:#6b7789;border-bottom:3px solid transparent;margin-bottom:-2px;">
    Buscar no GestãoClick
  </button>
</div>

<!-- Painel GestãoClick -->
<div id="painelGC" style="display:none;">
  <div class="card" style="margin-bottom:16px;">
    <h3 style="margin-top:0;display:flex;align-items:center;gap:8px;">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--azul-700)" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
      Buscar fornecedores no GestãoClick
    </h3>
    <div style="display:flex;gap:8px;margin-bottom:12px;">
      <input type="text" id="buscaGC" placeholder="Nome do fornecedor…" style="flex:1;" onkeydown="if(event.key==='Enter')buscarGC()">
      <button onclick="buscarGC()" class="btn btn-primario">Buscar</button>
    </div>
    <div id="alertaGC"></div>
    <div id="resultadosGC"></div>
  </div>
</div>

<!-- Painel Local -->
<div id="painelLocal">

<div class="card" style="margin-bottom:16px;padding:14px 20px;">
  <h3 style="margin-top:0;">Cadastrar Fornecedor</h3>
  <div id="alertaForn"></div>
  <form id="formForn" onsubmit="return false;">
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
    <button type="submit" onclick="cadastrarFornecedor()" class="btn btn-primario">Cadastrar Fornecedor</button>
  </form>
</div>

<div class="card">
  <h3 style="margin-top:0;">Fornecedores cadastrados (<?= count($fornecedores) ?>)</h3>
  <?php if (empty($fornecedores)): ?>
    <div style="text-align:center;color:#94a3b8;padding:30px;">Nenhum fornecedor cadastrado ainda.</div>
  <?php else: ?>
  <div style="overflow-x:auto;">
  <table>
    <thead><tr><th>Nome</th><th>CNPJ</th><th>Contato</th><th>E-mail</th><th>Telefone</th><th style="text-align:right;">Compras</th><th style="text-align:right;">Ticket Médio</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($fornecedores as $f): ?>
      <tr>
        <td><strong><?= htmlspecialchars($f['nome']) ?></strong><?php if ($f['razao_social']): ?><br><small style="color:#6b7789;"><?= htmlspecialchars($f['razao_social']) ?></small><?php endif; ?></td>
        <td style="font-size:12px;"><?= htmlspecialchars($f['cnpj'] ?? '—') ?></td>
        <td style="font-size:12px;"><?= htmlspecialchars($f['contato'] ?? '—') ?></td>
        <td style="font-size:12px;"><?= htmlspecialchars($f['email'] ?? '—') ?></td>
        <td style="font-size:12px;"><?= htmlspecialchars($f['telefone'] ?? '—') ?></td>
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

<!-- Modal edição -->
<div id="modalForn" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:14px;padding:24px;width:min(560px,94vw);max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.3);">
    <h3 id="modalFornTitulo" style="margin-top:0;">Editar Fornecedor</h3>
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

</div><!-- /painelLocal -->

<script>
// ---------- Abas ----------
function mostrarAba(aba) {
  document.getElementById('painelLocal').style.display = aba === 'local' ? '' : 'none';
  document.getElementById('painelGC').style.display    = aba === 'gc'    ? '' : 'none';
  document.getElementById('abaLocal').style.borderBottomColor = aba === 'local' ? 'var(--azul-700)' : 'transparent';
  document.getElementById('abaLocal').style.color = aba === 'local' ? 'var(--azul-700)' : '#6b7789';
  document.getElementById('abaGC').style.borderBottomColor = aba === 'gc' ? 'var(--azul-700)' : 'transparent';
  document.getElementById('abaGC').style.color = aba === 'gc' ? 'var(--azul-700)' : '#6b7789';
}

// ---------- Busca GestãoClick ----------
async function buscarGC() {
  const q = document.getElementById('buscaGC').value.trim();
  const alerta = document.getElementById('alertaGC');
  const res    = document.getElementById('resultadosGC');
  if (q.length < 2) { alerta.innerHTML = '<div style="color:#c62f2f;font-size:13px;margin-bottom:8px;">Digite ao menos 2 caracteres.</div>'; return; }
  alerta.innerHTML = '<div style="color:#6b7789;font-size:13px;">Buscando…</div>';
  res.innerHTML = '';
  try {
    const r = await fetch('/app-tecnicos/api/fornecedores/buscar-gc.php?busca=' + encodeURIComponent(q), {
      headers: {'Authorization': 'Bearer ' + (window.APP_JWT || '')}
    });
    const d = await r.json();
    if (!d.sucesso) { alerta.innerHTML = '<div style="color:#c62f2f;font-size:13px;margin-bottom:8px;">' + (d.erro || 'Erro na busca.') + '</div>'; return; }
    alerta.innerHTML = '';
    const lista = d.dados?.fornecedores ?? [];
    if (!lista.length) { res.innerHTML = '<div style="color:#6b7789;font-size:13px;">Nenhum fornecedor encontrado no GestãoClick.</div>'; return; }
    res.innerHTML = lista.map((f, i) => `
      <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 12px;border-radius:8px;background:#f8fafc;margin-bottom:6px;gap:12px;">
        <div style="flex:1;min-width:0;">
          <div style="font-weight:700;font-size:13px;">${esc(f.nome)}</div>
          <div style="font-size:11px;color:#6b7789;margin-top:2px;">
            ${f.cnpj ? 'CNPJ: ' + esc(f.cnpj) : ''}
            ${f.telefone ? ' · ' + esc(f.telefone) : ''}
            ${f.email ? ' · ' + esc(f.email) : ''}
          </div>
        </div>
        <div style="flex-shrink:0;">
          ${f.ja_cadastrado
            ? '<span class="badge concluido" style="font-size:11px;">Já cadastrado</span>'
            : `<button onclick='importarGC(${JSON.stringify(f)})' class="btn btn-primario btn-sm">Importar</button>`
          }
        </div>
      </div>
    `).join('');
  } catch (e) {
    alerta.innerHTML = '<div style="color:#c62f2f;font-size:13px;margin-bottom:8px;">Erro de comunicação: ' + e.message + '</div>';
  }
}

async function importarGC(f) {
  const alerta = document.getElementById('alertaGC');
  try {
    const r = await fetch('/app-tecnicos/api/fornecedores/importar-gc.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json', 'Authorization': 'Bearer ' + (window.APP_JWT || '')},
      body: JSON.stringify(f),
    });
    const d = await r.json();
    if (d.sucesso) {
      alerta.innerHTML = '<div style="color:#1e8e5a;margin-bottom:8px;font-size:13px;">✓ Fornecedor ' + (d.dados.ja_existia ? 'já existia e foi encontrado.' : 'importado com sucesso!') + '</div>';
      buscarGC(); // recarrega para atualizar badge
    } else {
      alerta.innerHTML = '<div style="color:#c62f2f;margin-bottom:8px;font-size:13px;">' + (d.erro || 'Erro.') + '</div>';
    }
  } catch (e) {
    alerta.innerHTML = '<div style="color:#c62f2f;margin-bottom:8px;font-size:13px;">' + e.message + '</div>';
  }
}

function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

// ---------- CRUD local ----------
async function cadastrarFornecedor() {
  const r = await fetch('/app-tecnicos/api/fornecedores/criar.php', {
    method:'POST',
    headers:{'Content-Type':'application/json','Authorization':'Bearer '+(window.APP_JWT||'')},
    body: JSON.stringify({
      nome: document.getElementById('fNome').value,
      razao_social: document.getElementById('fRazao').value||null,
      cnpj: document.getElementById('fCnpj').value||null,
      telefone: document.getElementById('fTel').value||null,
      email: document.getElementById('fEmail').value||null,
      contato: document.getElementById('fContato').value||null,
      observacoes: document.getElementById('fObs').value||null,
    }),
  });
  const d = await r.json();
  if (d.sucesso) { location.reload(); }
  else { document.getElementById('alertaForn').innerHTML='<div style="color:#c62f2f;margin-bottom:10px;">'+d.erro+'</div>'; }
}

function editarForn(f) {
  document.getElementById('editId').value      = f.id;
  document.getElementById('editNome').value    = f.nome||'';
  document.getElementById('editRazao').value   = f.razao_social||'';
  document.getElementById('editCnpj').value    = f.cnpj||'';
  document.getElementById('editTel').value     = f.telefone||'';
  document.getElementById('editEmail').value   = f.email||'';
  document.getElementById('editContato').value = f.contato||'';
  document.getElementById('editObs').value     = f.observacoes||'';
  document.getElementById('editAtivo').checked = !!parseInt(f.ativo);
  document.getElementById('modalForn').style.display='flex';
}

async function salvarEdicao() {
  const r = await fetch('/app-tecnicos/api/fornecedores/atualizar.php', {
    method:'POST',
    headers:{'Content-Type':'application/json','Authorization':'Bearer '+(window.APP_JWT||'')},
    body: JSON.stringify({
      id: parseInt(document.getElementById('editId').value),
      nome: document.getElementById('editNome').value,
      razao_social: document.getElementById('editRazao').value||null,
      cnpj: document.getElementById('editCnpj').value||null,
      telefone: document.getElementById('editTel').value||null,
      email: document.getElementById('editEmail').value||null,
      contato: document.getElementById('editContato').value||null,
      observacoes: document.getElementById('editObs').value||null,
      ativo: document.getElementById('editAtivo').checked ? 1 : 0,
    }),
  });
  const d = await r.json();
  if (d.sucesso) { location.reload(); } else { alert(d.erro||'Erro.'); }
}
</script>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
