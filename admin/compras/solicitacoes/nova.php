<?php
declare(strict_types=1);

$perfisPermitidosPagina = ['gestor', 'supervisor', 'solicitante', 'comprador', 'aprovador'];
$tituloPagina = 'Nova Solicitação de Compra';
$paginaAtiva  = 'compras_nova';
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/compras_helpers.php';

$pdo        = obterConexao();
$categorias = $pdo->query('SELECT id, nome FROM categorias_compra WHERE ativo=1 ORDER BY nome')->fetchAll();
$centros    = $pdo->query('SELECT id, nome, codigo FROM centros_custo WHERE ativo=1 ORDER BY nome')->fetchAll();
?>

<div style="max-width:900px;margin:0 auto;">

<div style="margin-bottom:16px;">
  <a href="/app-tecnicos/admin/compras/solicitacoes/lista.php" style="font-size:13px;color:#6b7789;">← Voltar para Solicitações</a>
</div>

<div id="alerta"></div>

<form id="formSolicitacao">
  <!-- Cabeçalho da solicitação -->
  <div class="card" style="margin-bottom:16px;">
    <h3 style="margin-top:0;display:flex;align-items:center;gap:8px;">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--azul-700)" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      Dados da Solicitação
    </h3>
    <div class="linha-form">
      <div class="campo">
        <label>Prioridade *</label>
        <select name="prioridade" id="prioridade" required>
          <option value="baixa">Baixa</option>
          <option value="media" selected>Média</option>
          <option value="alta">Alta</option>
          <option value="urgente">Urgente</option>
        </select>
      </div>
      <div class="campo">
        <label>Destino *</label>
        <select name="destino" id="destino" required onchange="toggleReferencia()">
          <option value="estoque">Estoque</option>
          <option value="cliente">Cliente</option>
          <option value="condominio">Condomínio</option>
          <option value="obra">Obra</option>
          <option value="manutencao">Manutenção</option>
          <option value="veiculo">Veículo</option>
          <option value="outro">Outro</option>
        </select>
      </div>
    </div>
    <div class="campo" id="campoReferencia" style="display:none;">
      <label>Referência do Destino (nome do cliente/obra/etc.)</label>
      <input type="text" name="destino_referencia" placeholder="Nome do cliente, obra, condomínio…">
    </div>
    <div class="linha-form">
      <div class="campo">
        <label>Centro de Custo</label>
        <select name="centro_custo_id">
          <option value="">— Selecionar —</option>
          <?php foreach ($centros as $cc): ?>
            <option value="<?= (int)$cc['id'] ?>"><?= htmlspecialchars($cc['nome']) ?><?= $cc['codigo'] ? ' (' . $cc['codigo'] . ')' : '' ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="campo">
        <label>Categoria</label>
        <select name="categoria_id">
          <option value="">— Selecionar —</option>
          <?php foreach ($categorias as $cat): ?>
            <option value="<?= (int)$cat['id'] ?>"><?= htmlspecialchars($cat['nome']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="campo">
      <label>Justificativa / Descrição da necessidade *</label>
      <textarea name="justificativa" rows="3" required placeholder="Descreva por que esse material/serviço é necessário…"></textarea>
    </div>
    <div class="campo">
      <label>Observações adicionais</label>
      <textarea name="observacoes" rows="2" placeholder="Especificações técnicas, urgência, contexto…"></textarea>
    </div>
  </div>

  <!-- Itens -->
  <div class="card" style="margin-bottom:16px;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
      <h3 style="margin:0;display:flex;align-items:center;gap:8px;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--azul-700)" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
        Itens / Produtos
      </h3>
      <button type="button" onclick="adicionarItem()" class="btn btn-secundario btn-sm">
        + Adicionar Item
      </button>
    </div>

    <!-- Busca rápida de produto GC -->
    <div style="margin-bottom:14px;background:#f4f9fe;border-radius:8px;padding:12px;">
      <label style="font-size:12px;color:#6b7789;">Buscar produto no GestãoClick</label>
      <div style="display:flex;gap:8px;margin-top:4px;">
        <input type="text" id="buscaProduto" placeholder="Digite o nome do produto…" style="flex:1;">
        <button type="button" onclick="buscarProdutoGC()" class="btn btn-secundario btn-sm">Buscar</button>
      </div>
      <div id="resultadosBusca" style="margin-top:6px;"></div>
    </div>

    <!-- Cabeçalho da grid de itens -->
    <div style="display:grid;grid-template-columns:1fr 70px 80px 110px 34px;gap:8px;padding:6px 0;font-size:12px;font-weight:700;color:#6b7789;border-bottom:1px solid #eef1f5;">
      <span>Produto / Descrição</span>
      <span style="text-align:center;">Qtd</span>
      <span>Unidade</span>
      <span style="text-align:right;">Valor Est.</span>
      <span></span>
    </div>
    <div id="listaItens"></div>
    <div id="totalEstimado" style="text-align:right;font-size:13px;font-weight:700;margin-top:10px;color:#1462b0;"></div>
  </div>

  <!-- Ações -->
  <div style="display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap;">
    <button type="button" onclick="salvar(false)" class="btn btn-neutro">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
      Salvar Rascunho
    </button>
    <button type="button" onclick="salvar(true)" class="btn btn-primario">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
      Enviar para Aprovação
    </button>
  </div>
</form>

</div>

<script>
let contadorItens = 0;

function adicionarItem(dados = {}) {
  contadorItens++;
  const div = document.createElement('div');
  div.className = 'item-row';
  div.id = 'item_' + contadorItens;
  div.innerHTML = `
    <div>
      <input type="text" name="produto_nome[]" value="${escape_html(dados.nome||'')}" placeholder="Nome do produto" required style="margin-bottom:4px;">
      <input type="text" name="produto_codigo[]" value="${escape_html(dados.codigo||'')}" placeholder="Código (opcional)" style="font-size:12px;padding:6px 10px;">
    </div>
    <input type="number" name="quantidade[]" value="${dados.qtd||1}" min="0.001" step="0.001" required oninput="atualizarTotal()">
    <input type="text" name="produto_unidade[]" value="${dados.un||'UN'}" placeholder="UN">
    <input type="number" name="valor_estimado[]" value="${dados.valor||''}" min="0" step="0.01" placeholder="0,00" oninput="atualizarTotal()">
    <button type="button" onclick="document.getElementById('item_${contadorItens}').remove(); atualizarTotal();" style="background:none;border:none;cursor:pointer;color:#c62f2f;font-size:18px;padding:0;line-height:1;">×</button>
  `;
  document.getElementById('listaItens').appendChild(div);
  atualizarTotal();
}

function escape_html(s) { const d=document.createElement('div'); d.textContent=s; return d.innerHTML; }

function atualizarTotal() {
  const qtds   = [...document.querySelectorAll('[name="quantidade[]"]')].map(e => parseFloat(e.value)||0);
  const vals   = [...document.querySelectorAll('[name="valor_estimado[]"]')].map(e => parseFloat(e.value)||0);
  const total  = qtds.reduce((s, q, i) => s + q * vals[i], 0);
  const el     = document.getElementById('totalEstimado');
  el.textContent = total > 0 ? 'Total estimado: R$ ' + total.toLocaleString('pt-BR', {minimumFractionDigits: 2}) : '';
}

function toggleReferencia() {
  const dest = document.getElementById('destino').value;
  const mostra = ['cliente','condominio','obra','veiculo','outro'].includes(dest);
  document.getElementById('campoReferencia').style.display = mostra ? '' : 'none';
}

async function buscarProdutoGC() {
  const q = document.getElementById('buscaProduto').value.trim();
  if (q.length < 2) return;
  const r = await fetch('/app-tecnicos/api/produtos/buscar-gc.php?q='+encodeURIComponent(q), {
    headers: {'Authorization': 'Bearer ' + (window.APP_JWT||'')}
  });
  const d = await r.json();
  const itens = d.sucesso ? (d.dados || []) : [];
  const div = document.getElementById('resultadosBusca');
  if (!itens.length) { div.innerHTML = '<span style="font-size:12px;color:#6b7789;">Nenhum produto encontrado. Adicione manualmente.</span>'; return; }
  div.innerHTML = itens.slice(0,8).map(p =>
    `<div onclick="adicionarItem({nome:'${p.nome.replace(/'/g,"\\'")}',valor:${p.valor_venda||0},un:'UN',gcid:${p.id||0}})" style="cursor:pointer;padding:6px 8px;border-radius:6px;font-size:13px;border-bottom:1px solid #eef1f5;hover:background:#f4f9fe;">
      <strong>${p.nome}</strong>
      ${p.valor_venda ? ' · R$ '+parseFloat(p.valor_venda).toLocaleString('pt-BR',{minimumFractionDigits:2}) : ''}
    </div>`
  ).join('');
}

// Coleta dados e salva
async function salvar(enviar) {
  const form = document.getElementById('formSolicitacao');
  if (!form.reportValidity()) return;

  const nomes  = [...document.querySelectorAll('[name="produto_nome[]"]')].map(e=>e.value.trim());
  const codigos= [...document.querySelectorAll('[name="produto_codigo[]"]')].map(e=>e.value.trim());
  const qtds   = [...document.querySelectorAll('[name="quantidade[]"]')].map(e=>parseFloat(e.value)||1);
  const uns    = [...document.querySelectorAll('[name="produto_unidade[]"]')].map(e=>e.value.trim());
  const vals   = [...document.querySelectorAll('[name="valor_estimado[]"]')].map(e=>e.value!==''?parseFloat(e.value):null);

  const itens = nomes.map((n,i) => ({produto_nome:n, produto_codigo:codigos[i]||null, quantidade:qtds[i], produto_unidade:uns[i]||'UN', valor_estimado:vals[i]})).filter(it=>it.produto_nome!=='');

  if (!itens.length) { alert('Adicione ao menos um item.'); return; }

  const dados = {
    prioridade:         form.prioridade.value,
    destino:            form.destino.value,
    destino_referencia: form.destino_referencia?.value||null,
    centro_custo_id:    form.centro_custo_id.value||null,
    categoria_id:       form.categoria_id.value||null,
    justificativa:      form.justificativa.value.trim(),
    observacoes:        form.observacoes.value.trim()||null,
    itens,
    enviar,
  };

  const btn = enviar ? document.querySelector('.btn-primario') : document.querySelector('.btn-neutro');
  btn.disabled = true;
  btn.textContent = 'Salvando…';

  const r = await fetch('/app-tecnicos/api/compras/criar.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json','Authorization':'Bearer '+(window.APP_JWT||'')},
    body: JSON.stringify(dados),
  });
  const d = await r.json();
  btn.disabled = false;
  btn.textContent = enviar ? 'Enviar para Aprovação' : 'Salvar Rascunho';

  if (d.sucesso) {
    window.location.href = '/app-tecnicos/admin/compras/solicitacoes/detalhe.php?id='+d.dados.id+'&msg=criado';
  } else {
    document.getElementById('alerta').innerHTML = `<div class="card" style="background:#fbe4e4;border:1px solid #c62f2f;color:#c62f2f;padding:10px 16px;margin-bottom:12px;">${d.erro||'Erro ao salvar.'}</div>`;
  }
}

// Adicionar item inicial
adicionarItem();
</script>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
