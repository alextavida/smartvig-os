<?php
declare(strict_types=1);

$perfisPermitidosPagina = ['gestor'];
$tituloPagina = 'Novo Orçamento';
$paginaAtiva  = 'orcamentos';
require_once __DIR__ . '/../../includes/header.php';
?>

<style>
.itens-tabela { width:100%; border-collapse:collapse; font-size:13px; }
.itens-tabela th { text-align:left; padding:8px 6px; color:#64748b; font-weight:600; border-bottom:2px solid #e2e8f0; }
.itens-tabela td { padding:6px; border-bottom:1px solid #f1f5f9; vertical-align:middle; }
.itens-tabela input, .itens-tabela select { border:1px solid #e2e8f0; border-radius:6px; padding:7px 10px; font-size:13px; width:100%; }
.itens-tabela input:focus, .itens-tabela select:focus { outline:none; border-color:#1d4ed8; }
.btn-rm { background:#fef2f2; color:#dc2626; border:none; border-radius:6px; padding:6px 12px; cursor:pointer; font-size:13px; }
.btn-rm:hover { background:#fee2e2; }
#total-geral { font-size:20px; font-weight:800; color:#1d4ed8; }
</style>

<div class="card" style="max-width:760px;">
  <h2 style="margin:0 0 20px;font-size:15px;font-weight:700;">Novo Orçamento</h2>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px;">
    <div>
      <label class="form-label">Cliente *</label>
      <input id="cliente-nome" class="form-control" placeholder="Nome do cliente" required>
    </div>
    <div>
      <label class="form-label">Telefone</label>
      <input id="cliente-tel" class="form-control" placeholder="(11) 99999-9999">
    </div>
    <div>
      <label class="form-label">E-mail</label>
      <input id="cliente-email" class="form-control" type="email" placeholder="email@exemplo.com">
    </div>
    <div>
      <label class="form-label">Validade (dias)</label>
      <input id="validade" class="form-control" type="number" min="1" max="90" value="7">
    </div>
  </div>

  <div style="margin-bottom:16px;">
    <label class="form-label">Observações</label>
    <textarea id="observacoes" class="form-control" rows="3" placeholder="Condições, informações adicionais..."></textarea>
  </div>

  <!-- Itens -->
  <div style="margin-bottom:8px;display:flex;justify-content:space-between;align-items:center;">
    <h3 style="margin:0;font-size:14px;font-weight:700;">Itens do Orçamento</h3>
    <button onclick="adicionarItem()" class="btn btn-secundario btn-sm">+ Adicionar item</button>
  </div>

  <div style="overflow-x:auto;margin-bottom:16px;">
    <table class="itens-tabela">
      <thead>
        <tr>
          <th style="width:90px;">Tipo</th>
          <th>Descrição</th>
          <th style="width:80px;">Qtd</th>
          <th style="width:110px;">Valor Unit.</th>
          <th style="width:110px;">Subtotal</th>
          <th style="width:40px;"></th>
        </tr>
      </thead>
      <tbody id="itens-body"></tbody>
    </table>
  </div>

  <div style="text-align:right;margin-bottom:20px;font-size:14px;color:#64748b;">
    Total: <span id="total-geral">R$ 0,00</span>
  </div>

  <div style="display:flex;gap:10px;">
    <button onclick="salvar()" id="btn-salvar" class="btn btn-primario">Salvar Orçamento</button>
    <a href="/app-tecnicos/admin/orcamentos/lista.php" class="btn btn-neutro">Cancelar</a>
  </div>
</div>

<script>
let itemId = 0;

function adicionarItem() {
  const id = ++itemId;
  const tr = document.createElement('tr');
  tr.id = 'item-' + id;
  tr.innerHTML = `
    <td>
      <select class="item-tipo">
        <option value="servico">Serviço</option>
        <option value="peca">Peça</option>
      </select>
    </td>
    <td><input class="item-desc" type="text" placeholder="Descrição do item"></td>
    <td><input class="item-qtd" type="number" min="0.01" step="0.01" value="1" oninput="calcularSubtotal('${id}')"></td>
    <td><input class="item-vl" type="number" min="0" step="0.01" value="0" oninput="calcularSubtotal('${id}')"></td>
    <td><span class="item-sub" style="font-weight:600;color:#1d4ed8;">R$ 0,00</span></td>
    <td><button class="btn-rm" onclick="removerItem('${id}')">✕</button></td>
  `;
  document.getElementById('itens-body').appendChild(tr);
}

function calcularSubtotal(id) {
  const tr = document.getElementById('item-' + id);
  const qtd = parseFloat(tr.querySelector('.item-qtd').value) || 0;
  const vl  = parseFloat(tr.querySelector('.item-vl').value) || 0;
  tr.querySelector('.item-sub').textContent = 'R$ ' + (qtd * vl).toLocaleString('pt-BR', {minimumFractionDigits:2,maximumFractionDigits:2});
  calcularTotal();
}

function calcularTotal() {
  let total = 0;
  document.querySelectorAll('#itens-body tr').forEach(tr => {
    const qtd = parseFloat(tr.querySelector('.item-qtd')?.value) || 0;
    const vl  = parseFloat(tr.querySelector('.item-vl')?.value) || 0;
    total += qtd * vl;
  });
  document.getElementById('total-geral').textContent = 'R$ ' + total.toLocaleString('pt-BR', {minimumFractionDigits:2,maximumFractionDigits:2});
}

function removerItem(id) {
  document.getElementById('item-' + id)?.remove();
  calcularTotal();
}

async function salvar() {
  const nome = document.getElementById('cliente-nome').value.trim();
  if (!nome) { alert('Informe o nome do cliente.'); return; }

  const rows = document.querySelectorAll('#itens-body tr');
  if (!rows.length) { alert('Adicione pelo menos um item.'); return; }

  const itens = [];
  for (const tr of rows) {
    const desc = tr.querySelector('.item-desc').value.trim();
    if (!desc) { alert('Preencha a descrição de todos os itens.'); return; }
    itens.push({
      tipo:          tr.querySelector('.item-tipo').value,
      descricao:     desc,
      quantidade:    parseFloat(tr.querySelector('.item-qtd').value) || 1,
      valor_unitario: parseFloat(tr.querySelector('.item-vl').value) || 0,
    });
  }

  const btn = document.getElementById('btn-salvar');
  btn.disabled = true;
  btn.textContent = 'Salvando...';

  try {
    const r = await apiPost('/orcamentos/criar.php', {
      cliente_nome:     nome,
      cliente_email:    document.getElementById('cliente-email').value.trim() || null,
      cliente_telefone: document.getElementById('cliente-tel').value.trim() || null,
      observacoes:      document.getElementById('observacoes').value.trim() || null,
      validade_dias:    parseInt(document.getElementById('validade').value) || 7,
      itens,
    });
    if (r && r.id) {
      window.location.href = '/app-tecnicos/admin/orcamentos/detalhe.php?id=' + r.id;
    }
  } catch (e) {
    alert('Erro: ' + e.message);
    btn.disabled = false;
    btn.textContent = 'Salvar Orçamento';
  }
}

// Inicia com 1 item
adicionarItem();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
