<?php
/**
 * Formulario de criacao de OS: autocomplete de clientes via GestaoClick,
 * campo de prioridade, tecnicos (multi-selecao + responsavel). Envio via AJAX.
 */

declare(strict_types=1);

$perfisPermitidosPagina = ['gestor'];
$tituloPagina = 'Nova Ordem de Servico';
$paginaAtiva = 'os_criar';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/icons.php';
require_once __DIR__ . '/../../config/database.php';

$pdo = obterConexao();
$tecnicos = $pdo->query("SELECT id, nome, email FROM usuarios WHERE perfil = 'tecnico' AND ativo = 1 ORDER BY nome")->fetchAll();
?>

<div class="card">
  <div id="alertaCriar"></div>

  <?php if (empty($tecnicos)): ?>
    <div class="alerta alerta-info" style="display:flex; gap:10px; align-items:center;">
      <?= ic('alerta') ?>
      Nenhum tecnico cadastrado ainda. <a href="/app-tecnicos/admin/tecnicos/lista.php">Cadastre um tecnico primeiro</a>.
    </div>
  <?php endif; ?>

  <form id="formCriarOs" autocomplete="off">

    <!-- Linha 1: Cliente (autocomplete) + Telefone -->
    <div class="linha-form">
      <div class="campo">
        <label for="cliente_nome">Nome do cliente *</label>
        <div class="autocomplete-wrapper">
          <input type="text" id="cliente_nome" name="cliente_nome" required
                 placeholder="Digite para buscar no GestaoClick...">
          <div class="autocomplete-lista" id="acLista"></div>
        </div>
        <input type="hidden" id="gc_cliente_id" name="gc_cliente_id">
      </div>
      <div class="campo">
        <label for="cliente_telefone">Telefone</label>
        <input type="tel" id="cliente_telefone" name="cliente_telefone" placeholder="(00) 00000-0000">
      </div>
    </div>

    <!-- Endereco -->
    <div class="campo">
      <label for="cliente_endereco">Endereco</label>
      <input type="text" id="cliente_endereco" name="cliente_endereco" placeholder="Rua, numero, bairro, cidade">
    </div>

    <!-- Linha 2: Data + Prioridade -->
    <div class="linha-form">
      <div class="campo">
        <label for="data_agendamento">Data de agendamento *</label>
        <input type="date" id="data_agendamento" name="data_agendamento" required>
      </div>
      <div class="campo">
        <label for="prioridade">Prioridade</label>
        <select id="prioridade" name="prioridade">
          <option value="baixo">Baixo</option>
          <option value="intermediario">Intermediario</option>
          <option value="urgente">Urgente</option>
        </select>
      </div>
    </div>

    <!-- Observacoes -->
    <div class="campo">
      <label for="observacoes">Observacoes / Descricao</label>
      <textarea id="observacoes" name="observacoes" placeholder="Detalhes do atendimento..."></textarea>
    </div>

    <!-- Tecnicos -->
    <div class="campo">
      <label>Tecnicos atribuidos *
        <small style="font-weight:400; color:var(--cinza-500);">marque quem vai atender e defina o responsavel</small>
      </label>
      <?php foreach ($tecnicos as $t): ?>
        <div class="tecnico-check">
          <input type="checkbox" class="chk-tecnico" value="<?= (int) $t['id'] ?>" id="tec_<?= (int) $t['id'] ?>">
          <label for="tec_<?= (int) $t['id'] ?>" style="margin:0; font-weight:500; flex:1;">
            <?= htmlspecialchars($t['nome']) ?>
            <small style="color:var(--cinza-500); font-weight:400;"><?= htmlspecialchars($t['email']) ?></small>
          </label>
          <label style="margin:0; display:flex; align-items:center; gap:6px; font-weight:400;">
            <input type="radio" name="responsavel" class="rd-responsavel" value="<?= (int) $t['id'] ?>">
            Responsavel
          </label>
        </div>
      <?php endforeach; ?>
    </div>

    <button type="submit" class="btn btn-primario" <?= empty($tecnicos) ? 'disabled' : '' ?>>
      <?= ic('os_nova', 16) ?> Criar Ordem de Servico
    </button>
  </form>
</div>

<!-- Modal: criar novo cliente no GestaoClick -->
<div class="modal-overlay" id="modalNovoCliente">
  <div class="modal-box">
    <button class="modal-fechar" onclick="fecharModal()"><?= ic('voltar', 18) ?></button>
    <h3><?= ic('perfil', 18) ?> Criar novo cliente no GestaoClick</h3>
    <div id="alertaModalCliente"></div>
    <div class="campo"><label>Nome *</label><input type="text" id="ncNome"></div>
    <div class="linha-form">
      <div class="campo"><label>Telefone</label><input type="tel" id="ncTelefone"></div>
      <div class="campo"><label>E-mail</label><input type="email" id="ncEmail"></div>
    </div>
    <div class="campo"><label>Endereco</label><input type="text" id="ncEndereco" placeholder="Rua e numero"></div>
    <div class="linha-form">
      <div class="campo"><label>Bairro</label><input type="text" id="ncBairro"></div>
      <div class="campo"><label>Cidade</label><input type="text" id="ncCidade"></div>
      <div class="campo"><label>Estado</label><input type="text" id="ncEstado" maxlength="2" placeholder="SP"></div>
    </div>
    <div style="display:flex; gap:10px; margin-top:6px;">
      <button class="btn btn-primario" onclick="criarNovoCliente()"><?= ic('check', 16) ?> Criar cliente</button>
      <button class="btn btn-neutro" onclick="fecharModal()">Cancelar</button>
    </div>
  </div>
</div>

<script>
// ---------- Autocomplete de clientes ----------
let acTimer;
const inputNome = document.getElementById('cliente_nome');
const acLista = document.getElementById('acLista');
const inputGcId = document.getElementById('gc_cliente_id');

inputNome.addEventListener('input', () => {
  clearTimeout(acTimer);
  const busca = inputNome.value.trim();
  if (busca.length < 2) { fecharAc(); return; }
  acTimer = setTimeout(() => buscarClientes(busca), 320);
});

inputNome.addEventListener('blur', () => setTimeout(fecharAc, 200));

async function buscarClientes(busca) {
  acLista.innerHTML = '<div class="ac-vazio"><?= ic('carregando', 14) ?> Buscando...</div>';
  acLista.classList.add('aberto');
  try {
    const dados = await apiGet('/clientes/buscar.php?busca=' + encodeURIComponent(busca));
    renderAc(dados.clientes, busca);
  } catch (e) {
    acLista.innerHTML = '<div class="ac-vazio">Erro ao buscar clientes.</div>';
  }
}

function renderAc(clientes, busca) {
  if (!clientes || !clientes.length) {
    acLista.innerHTML =
      '<div class="ac-vazio">Nenhum cliente encontrado.</div>' +
      '<div class="ac-criar" onclick="abrirModalNovoCliente(\'' + escHtml(busca) + '\')">' +
      '<?= ic('os_nova', 14) ?> Criar novo cliente no GestaoClick</div>';
    acLista.classList.add('aberto');
    return;
  }
  let html = clientes.map(c =>
    '<div class="ac-item" onclick="selecionarCliente(' + JSON.stringify(c) + ')">' +
    '<div class="ac-nome">' + escHtml(c.nome) + '</div>' +
    '<div class="ac-detalhe">' + (c.endereco ? escHtml(c.endereco.trim().replace(/^,\s*/, '').replace(/,\s*$/, '')) + ' &nbsp;' : '') + (c.telefone ? escHtml(c.telefone) : '') + '</div>' +
    '</div>'
  ).join('');
  html += '<div class="ac-criar" onclick="abrirModalNovoCliente(\'' + escHtml(busca) + '\')"><?= ic('os_nova', 14) ?> Criar novo cliente</div>';
  acLista.innerHTML = html;
  acLista.classList.add('aberto');
}

function selecionarCliente(c) {
  inputNome.value = c.nome;
  inputGcId.value = c.id || '';
  document.getElementById('cliente_telefone').value = c.telefone || '';
  document.getElementById('cliente_endereco').value = (c.endereco || '').trim().replace(/^,\s*/, '').replace(/,\s*$/, '');
  fecharAc();
}

function fecharAc() {
  acLista.classList.remove('aberto');
  acLista.innerHTML = '';
}

function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

// ---------- Modal novo cliente ----------
function abrirModalNovoCliente(nomeBusca) {
  document.getElementById('ncNome').value = nomeBusca || '';
  document.getElementById('alertaModalCliente').innerHTML = '';
  document.getElementById('modalNovoCliente').classList.add('aberto');
  fecharAc();
}

function fecharModal() {
  document.getElementById('modalNovoCliente').classList.remove('aberto');
}

async function criarNovoCliente() {
  const nome = document.getElementById('ncNome').value.trim();
  if (!nome) { document.getElementById('alertaModalCliente').innerHTML = '<div class="alerta alerta-erro">Informe o nome do cliente.</div>'; return; }
  try {
    const dados = await apiPost('/clientes/criar.php', {
      nome,
      telefone: document.getElementById('ncTelefone').value,
      email: document.getElementById('ncEmail').value,
      endereco: document.getElementById('ncEndereco').value,
      bairro: document.getElementById('ncBairro').value,
      cidade: document.getElementById('ncCidade').value,
      estado: document.getElementById('ncEstado').value,
    });
    inputNome.value = dados.nome || nome;
    inputGcId.value = dados.id || '';
    document.getElementById('alertaModalCliente').innerHTML = '<div class="alerta alerta-sucesso">Cliente criado no GestaoClick!</div>';
    setTimeout(fecharModal, 1200);
  } catch (e) {
    document.getElementById('alertaModalCliente').innerHTML = '<div class="alerta alerta-erro">Erro: ' + e.message + '</div>';
  }
}

// ---------- Submit ----------
document.getElementById('formCriarOs').addEventListener('submit', async function (ev) {
  ev.preventDefault();
  const alertaBox = document.getElementById('alertaCriar');
  alertaBox.innerHTML = '';

  const marcados = Array.from(document.querySelectorAll('.chk-tecnico:checked')).map(c => parseInt(c.value, 10));
  if (marcados.length === 0) {
    alertaBox.innerHTML = '<div class="alerta alerta-erro">Selecione ao menos um tecnico.</div>';
    return;
  }

  const responsavelSelecionado = document.querySelector('.rd-responsavel:checked');
  const responsavelId = responsavelSelecionado ? parseInt(responsavelSelecionado.value, 10) : marcados[0];
  const tecnicos = marcados.map(id => ({ tecnico_id: id, responsavel: id === responsavelId }));

  const payload = {
    cliente_nome: document.getElementById('cliente_nome').value,
    cliente_endereco: document.getElementById('cliente_endereco').value,
    cliente_telefone: document.getElementById('cliente_telefone').value,
    data_agendamento: document.getElementById('data_agendamento').value,
    prioridade: document.getElementById('prioridade').value,
    observacoes: document.getElementById('observacoes').value,
    gc_cliente_id: document.getElementById('gc_cliente_id').value || null,
    tecnicos,
  };

  try {
    const dados = await apiPost('/os/criar.php', payload);
    let msg = '<div class="alerta alerta-sucesso"><?= ic('check', 14) ?> OS #' + dados.os_id + ' criada com sucesso!</div>';
    if (dados.erro_sincronizacao) {
      msg += '<div class="alerta alerta-info"><?= ic('alerta', 14) ?> Aviso GestaoClick: ' + escHtml(dados.erro_sincronizacao) + '. A OS foi criada normalmente.</div>';
    }
    alertaBox.innerHTML = msg;
    setTimeout(() => { window.location.href = 'detalhe.php?id=' + dados.os_id; }, 1200);
  } catch (e) {
    alertaBox.innerHTML = '<div class="alerta alerta-erro">Erro ao criar OS: ' + e.message + '</div>';
  }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
