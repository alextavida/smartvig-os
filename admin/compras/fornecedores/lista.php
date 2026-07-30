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

<script>
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
