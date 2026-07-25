<?php
/**
 * Novo orçamento: autocomplete de clientes via GestãoClick,
 * busca de produtos/serviços GC por item (campo de descrição com dropdown).
 */

declare(strict_types=1);

$perfisPermitidosPagina = ['gestor', 'supervisor'];
$tituloPagina = 'Novo Orçamento';
$paginaAtiva  = 'orcamentos';
require_once __DIR__ . '/../../includes/header.php';
?>

<style>
/* ---- Autocomplete de clientes ---- */
.ac-wrap { position:relative; }
.ac-lista {
  position:absolute; top:100%; left:0; right:0; background:#fff;
  border:1.5px solid #1d4ed8; border-radius:0 0 10px 10px;
  box-shadow:0 6px 20px rgba(0,0,0,.1); z-index:900;
  max-height:260px; overflow-y:auto; display:none;
}
.ac-lista.aberto { display:block; }
.ac-item { padding:10px 14px; cursor:pointer; border-bottom:1px solid #f1f5f9; }
.ac-item:hover { background:#eff6ff; }
.ac-nome { font-weight:700; font-size:13px; }
.ac-detalhe { font-size:11px; color:#64748b; margin-top:2px; }
.ac-vazio { padding:10px 14px; font-size:13px; color:#94a3b8; }

/* ---- Autocomplete de produtos por linha ---- */
.prod-ac-wrap { position:relative; flex:1; }
.prod-ac-lista {
  position:absolute; top:100%; left:0; width:340px; background:#fff;
  border:1.5px solid #1d4ed8; border-radius:0 0 10px 10px;
  box-shadow:0 6px 20px rgba(0,0,0,.12); z-index:950;
  max-height:200px; overflow-y:auto; display:none;
}
.prod-ac-lista.aberto { display:block; }
.prod-ac-item { padding:8px 12px; cursor:pointer; border-bottom:1px solid #f1f5f9; display:flex; justify-content:space-between; align-items:center; }
.prod-ac-item:hover { background:#eff6ff; }
.prod-ac-nome { font-size:12px; font-weight:600; color:#1c2430; }
.prod-ac-valor { font-size:11px; color:#16803c; font-weight:700; }

/* ---- Tabela de itens ---- */
.itens-tabela { width:100%; border-collapse:collapse; font-size:13px; }
.itens-tabela th {
  text-align:left; padding:8px 6px; color:#64748b;
  font-weight:600; border-bottom:2px solid #e2e8f0;
}
.itens-tabela td { padding:6px; border-bottom:1px solid #f1f5f9; vertical-align:middle; }
.itens-tabela input, .itens-tabela select {
  border:1px solid #e2e8f0; border-radius:6px; padding:7px 10px;
  font-size:13px; width:100%; box-sizing:border-box;
}
.itens-tabela input:focus, .itens-tabela select:focus {
  outline:none; border-color:#1d4ed8;
}
.btn-rm {
  background:#fef2f2; color:#dc2626; border:none;
  border-radius:6px; padding:6px 12px; cursor:pointer; font-size:13px;
}
.btn-rm:hover { background:#fee2e2; }
#total-geral { font-size:20px; font-weight:800; color:#1d4ed8; }
</style>

<div class="card" style="max-width:820px;">
  <h2 style="margin:0 0 20px;font-size:15px;font-weight:700;">Novo Orçamento</h2>

  <!-- Cliente com autocomplete GC -->
  <div style="margin-bottom:14px;">
    <label class="form-label">Cliente *</label>
    <div class="ac-wrap">
      <input id="cliente-nome" class="form-control"
             placeholder="Digite para buscar no GestãoClick..." autocomplete="off" required>
      <div class="ac-lista" id="ac-lista-cliente"></div>
    </div>
    <input type="hidden" id="gc-cliente-id">
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px;">
    <div>
      <label class="form-label">Telefone</label>
      <input id="cliente-tel" class="form-control" placeholder="(11) 99999-9999">
    </div>
    <div>
      <label class="form-label">E-mail</label>
      <input id="cliente-email" class="form-control" type="email" placeholder="email@exemplo.com">
    </div>
    <div>
      <label class="form-label">Endereço</label>
      <input id="cliente-endereco" class="form-control" placeholder="Rua, número, bairro, cidade">
    </div>
    <div>
      <label class="form-label">Validade (dias)</label>
      <input id="validade" class="form-control" type="number" min="1" max="90" value="7">
    </div>
  </div>

  <div style="margin-bottom:16px;">
    <label class="form-label">Observações</label>
    <textarea id="observacoes" class="form-control" rows="3"
              placeholder="Condições, informações adicionais..."></textarea>
  </div>

  <!-- Itens do orçamento -->
  <div style="margin-bottom:8px;display:flex;justify-content:space-between;align-items:center;">
    <h3 style="margin:0;font-size:14px;font-weight:700;">Itens do Orçamento</h3>
    <button type="button" onclick="adicionarItem()" class="btn btn-secundario btn-sm">+ Adicionar item</button>
  </div>

  <div style="overflow-x:auto;margin-bottom:16px;">
    <table class="itens-tabela">
      <thead>
        <tr>
          <th style="width:90px;">Tipo</th>
          <th>Descrição <span style="font-size:10px;color:#94a3b8;font-weight:400;">(busca no GC ao digitar)</span></th>
          <th style="width:75px;">Qtd</th>
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
    <button type="button" onclick="salvar()" id="btn-salvar" class="btn btn-primario">Salvar Orçamento</button>
    <a href="/app-tecnicos/admin/orcamentos/lista.php" class="btn btn-neutro">Cancelar</a>
  </div>
</div>

<script>
// ============================================================
// Autocomplete de clientes (GestãoClick)
// ============================================================
(function () {
  let timer;
  const inputNome  = document.getElementById('cliente-nome');
  const listaDiv   = document.getElementById('ac-lista-cliente');
  const inputGcId  = document.getElementById('gc-cliente-id');
  const inputTel   = document.getElementById('cliente-tel');
  const inputEmail = document.getElementById('cliente-email');
  const inputEnd   = document.getElementById('cliente-endereco');

  inputNome.addEventListener('input', () => {
    clearTimeout(timer);
    const busca = inputNome.value.trim();
    if (busca.length < 2) { fecharAc(); return; }
    timer = setTimeout(() => buscarClientes(busca), 320);
  });
  inputNome.addEventListener('blur', () => setTimeout(fecharAc, 200));

  async function buscarClientes(busca) {
    listaDiv.innerHTML = '<div class="ac-vazio">Buscando...</div>';
    listaDiv.classList.add('aberto');
    try {
      const dados = await apiGet('/clientes/buscar.php?busca=' + encodeURIComponent(busca));
      renderAcClientes(dados.clientes || []);
    } catch (e) {
      listaDiv.innerHTML = '<div class="ac-vazio">Erro ao buscar clientes.</div>';
    }
  }

  function renderAcClientes(clientes) {
    if (!clientes.length) {
      listaDiv.innerHTML = '<div class="ac-vazio">Nenhum cliente encontrado no GestãoClick.</div>';
      listaDiv.classList.add('aberto');
      return;
    }
    listaDiv.innerHTML = clientes.map(c => {
      const end = (c.endereco || '').trim().replace(/^,\s*/, '').replace(/,\s*$/, '');
      return `<div class="ac-item" data-json='${JSON.stringify(c)}'>
        <div class="ac-nome">${escHtml(c.nome)}</div>
        <div class="ac-detalhe">${end ? escHtml(end) + '&nbsp;' : ''}${c.telefone ? escHtml(c.telefone) : ''}</div>
      </div>`;
    }).join('');
    listaDiv.classList.add('aberto');
    listaDiv.querySelectorAll('.ac-item').forEach(el => {
      el.addEventListener('click', () => {
        const c = JSON.parse(el.dataset.json);
        inputNome.value  = c.nome;
        inputGcId.value  = c.id || '';
        inputTel.value   = c.telefone || '';
        inputEmail.value = c.email || '';
        inputEnd.value   = (c.endereco || '').trim().replace(/^,\s*/, '').replace(/,\s*$/, '');
        fecharAc();
      });
    });
  }

  function fecharAc() {
    listaDiv.classList.remove('aberto');
    listaDiv.innerHTML = '';
  }
})();

// ============================================================
// Itens do orçamento com busca de produtos GC
// ============================================================
let itemId = 0;
const prodTimers = {};

function adicionarItem() {
  const id = ++itemId;
  const tr = document.createElement('tr');
  tr.id = 'item-' + id;
  tr.innerHTML = `
    <td>
      <select class="item-tipo" onchange="tipoAlterado(${id})">
        <option value="servico">Serviço</option>
        <option value="peca">Peça</option>
      </select>
    </td>
    <td>
      <div class="prod-ac-wrap">
        <input class="item-desc" type="text" placeholder="Buscar serviço/produto GC ou digitar..."
               autocomplete="off"
               oninput="agendarBuscaProd(${id}, this.value)">
        <div class="prod-ac-lista" id="prod-ac-${id}"></div>
      </div>
    </td>
    <td><input class="item-qtd" type="number" min="0.01" step="0.01" value="1"
               oninput="calcularSubtotal(${id})"></td>
    <td><input class="item-vl" type="number" min="0" step="0.01" value="0.00"
               oninput="calcularSubtotal(${id})"></td>
    <td><span class="item-sub" style="font-weight:600;color:#1d4ed8;">R$ 0,00</span></td>
    <td><button type="button" class="btn-rm" onclick="removerItem(${id})">&#x2715;</button></td>
  `;
  document.getElementById('itens-body').appendChild(tr);
}

function tipoAlterado(id) {
  // Limpa produto selecionado quando tipo muda
  const tr = document.getElementById('item-' + id);
  if (tr) {
    tr.querySelector('.item-desc').value = '';
    tr.querySelector('.item-vl').value   = '0.00';
    calcularSubtotal(id);
    fecharProdAc(id);
  }
}

function agendarBuscaProd(id, valor) {
  clearTimeout(prodTimers[id]);
  const busca = valor.trim();
  if (busca.length < 2) { fecharProdAc(id); return; }
  prodTimers[id] = setTimeout(() => buscarProd(id, busca), 350);
}

async function buscarProd(id, busca) {
  const tr = document.getElementById('item-' + id);
  if (!tr) { return; }
  const tipo = tr.querySelector('.item-tipo').value;
  const listaDiv = document.getElementById('prod-ac-' + id);
  listaDiv.innerHTML = '<div class="ac-vazio">Buscando...</div>';
  listaDiv.classList.add('aberto');
  try {
    const dados = await apiGet('/produtos/buscar-gc.php?busca=' + encodeURIComponent(busca) + '&tipo=' + tipo);
    renderProdAc(id, dados.produtos || []);
  } catch (e) {
    listaDiv.innerHTML = '<div class="ac-vazio">Erro ao buscar no GestãoClick.</div>';
  }
}

function renderProdAc(id, produtos) {
  const listaDiv = document.getElementById('prod-ac-' + id);
  if (!listaDiv) { return; }
  if (!produtos.length) {
    listaDiv.innerHTML = '<div class="ac-vazio">Nenhum resultado. Pode digitar manualmente.</div>';
    listaDiv.classList.add('aberto');
    return;
  }
  listaDiv.innerHTML = produtos.map(p => {
    const preco = p.valor_venda ? 'R$ ' + parseFloat(p.valor_venda).toLocaleString('pt-BR', {minimumFractionDigits:2}) : '';
    return `<div class="prod-ac-item" data-json='${JSON.stringify(p)}'>
      <span class="prod-ac-nome">${escHtml(p.nome)}</span>
      ${preco ? `<span class="prod-ac-valor">${escHtml(preco)}</span>` : ''}
    </div>`;
  }).join('');
  listaDiv.classList.add('aberto');
  listaDiv.querySelectorAll('.prod-ac-item').forEach(el => {
    el.addEventListener('click', () => {
      const p = JSON.parse(el.dataset.json);
      const tr = document.getElementById('item-' + id);
      if (!tr) { return; }
      tr.querySelector('.item-desc').value = p.nome;
      if (p.valor_venda) {
        tr.querySelector('.item-vl').value = parseFloat(p.valor_venda).toFixed(2);
      }
      calcularSubtotal(id);
      fecharProdAc(id);
    });
  });
}

function fecharProdAc(id) {
  const d = document.getElementById('prod-ac-' + id);
  if (d) { d.classList.remove('aberto'); d.innerHTML = ''; }
}

// Fecha todos os dropdowns ao clicar fora
document.addEventListener('click', (e) => {
  if (!e.target.closest('.prod-ac-wrap')) {
    document.querySelectorAll('.prod-ac-lista').forEach(d => {
      d.classList.remove('aberto'); d.innerHTML = '';
    });
  }
});

function calcularSubtotal(id) {
  const tr = document.getElementById('item-' + id);
  if (!tr) { return; }
  const qtd = parseFloat(tr.querySelector('.item-qtd').value) || 0;
  const vl  = parseFloat(tr.querySelector('.item-vl').value) || 0;
  tr.querySelector('.item-sub').textContent = 'R$ ' +
    (qtd * vl).toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2});
  calcularTotal();
}

function calcularTotal() {
  let total = 0;
  document.querySelectorAll('#itens-body tr').forEach(tr => {
    const qtd = parseFloat(tr.querySelector('.item-qtd')?.value) || 0;
    const vl  = parseFloat(tr.querySelector('.item-vl')?.value) || 0;
    total += qtd * vl;
  });
  document.getElementById('total-geral').textContent = 'R$ ' +
    total.toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2});
}

function removerItem(id) {
  document.getElementById('item-' + id)?.remove();
  calcularTotal();
}

function escHtml(s) {
  return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

// ============================================================
// Salvar
// ============================================================
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
      tipo:           tr.querySelector('.item-tipo').value,
      descricao:      desc,
      quantidade:     parseFloat(tr.querySelector('.item-qtd').value) || 1,
      valor_unitario: parseFloat(tr.querySelector('.item-vl').value) || 0,
    });
  }

  const btn = document.getElementById('btn-salvar');
  btn.disabled = true;
  btn.textContent = 'Salvando...';

  try {
    const r = await apiPost('/orcamentos/criar.php', {
      gc_cliente_id:    document.getElementById('gc-cliente-id').value || null,
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
