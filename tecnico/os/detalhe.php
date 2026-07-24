<?php
/**
 * Detalhe da OS para o tecnico: todas as acoes, prioridade, produtos, midias, GPS.
 */

declare(strict_types=1);

$perfisPermitidosPagina = ['tecnico'];
$tituloPagina = 'Detalhe da OS';
$paginaAtiva = 'dashboard';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/icons.php';
require_once __DIR__ . '/../../config/database.php';

$pdo = obterConexao();
$osId = (int) ($_GET['id'] ?? 0);
$tecnicoId = $usuarioAtual['usuario_id'];

$stmtAcesso = $pdo->prepare(
    'SELECT 1 FROM os_tecnicos WHERE os_id = :os_id AND tecnico_id = :tecnico_id
     UNION ALL
     SELECT 1 FROM ordens_servico WHERE id = :os_id2 AND tecnico_id = :tecnico_id2
     LIMIT 1'
);
$stmtAcesso->execute(['os_id' => $osId, 'tecnico_id' => $tecnicoId, 'os_id2' => $osId, 'tecnico_id2' => $tecnicoId]);
if (!$stmtAcesso->fetch()) {
    echo '<div class="card"><div class="alerta alerta-erro">Voce nao tem acesso a esta OS.</div></div>';
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM ordens_servico WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $osId]);
$os = $stmt->fetch();

if (!$os) {
    echo '<div class="card"><div class="alerta alerta-erro">OS nao encontrada.</div></div>';
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$produtos = $os['produtos_json'] ? json_decode($os['produtos_json'], true) : [];
$gcOsId = (int) ($os['gc_os_id'] ?? 0);

$midias = $pdo->prepare('SELECT tipo, caminho_arquivo, criado_em FROM midias_os WHERE os_id = :id ORDER BY criado_em DESC');
$midias->execute(['id' => $osId]);
$midias = $midias->fetchAll();

function rotuloStatusTecDet(string $s): string
{
    $mapa = ['aberto' => 'Aberto', 'em_andamento' => 'Em andamento', 'pausado' => 'Pausado',
             'reagendado' => 'Reagendado', 'concluido' => 'Concluido', 'cancelado' => 'Cancelado'];
    return $mapa[$s] ?? $s;
}

$situacao = $os['situacao_local'];
$prioridade = $os['prioridade'] ?? 'baixo';
$enderecoCodificado = urlencode($os['cliente_endereco'] ?? '');
?>

<div class="topbar" style="margin-top:-10px;">
  <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
    <span class="badge <?= $situacao ?>"><?= rotuloStatusTecDet($situacao) ?></span>
    <span class="chip-prioridade <?= $prioridade ?>"><?= ic('flag', 11) ?> <?= ucfirst($prioridade) ?></span>
  </div>
  <a href="/app-tecnicos/tecnico/" class="btn btn-neutro btn-sm"><?= ic('voltar', 14) ?> Minhas OS</a>
</div>

<div id="alertaOs"></div>

<!-- Dados do cliente -->
<div class="card">
  <h3 style="margin-top:0;">OS #<?= (int) $os['id'] ?> &middot; <?= htmlspecialchars($os['cliente_nome'] ?? '-') ?></h3>

  <?php if ($gcOsId): ?>
  <!-- Bloco GC preenchido via JS: equipamento, defeito, situação -->
  <div id="gc-equip-box" style="display:none; background:#f0f9ff; border:1px solid #bae6fd; border-radius:8px; padding:13px; margin-bottom:16px; font-size:13px;">
    <div style="font-size:10px;color:#0369a1;font-weight:700;margin-bottom:8px;">EQUIPAMENTO (GESTÃOCLICK)</div>
    <div id="gc-equip-content"></div>
  </div>
  <?php endif; ?>

  <div style="display:flex; align-items:center; gap:8px; margin-bottom:6px; font-size:14px; color:var(--cinza-700);">
    <?= ic('mapa', 15) ?> <?= htmlspecialchars($os['cliente_endereco'] ?? 'Endereco nao informado') ?>
  </div>
  <div style="display:flex; align-items:center; gap:8px; margin-bottom:6px; font-size:14px; color:var(--cinza-700);">
    <?= ic('notificacao', 15) ?> <?= htmlspecialchars($os['cliente_telefone'] ?? '-') ?>
  </div>
  <div style="display:flex; align-items:center; gap:8px; margin-bottom:12px; font-size:14px; color:var(--cinza-700);">
    <?= ic('calendario', 15) ?> <?= $os['data_agendamento'] ? date('d/m/Y', strtotime($os['data_agendamento'])) : 'Sem data definida' ?>
  </div>

  <div style="display:flex; gap:8px; flex-wrap:wrap;">
    <?php if ($enderecoCodificado): ?>
      <a class="btn btn-secundario btn-sm" target="_blank" href="https://www.google.com/maps/dir/?api=1&destination=<?= $enderecoCodificado ?>">
        <?= ic('rota', 14) ?> Rota Google Maps
      </a>
    <?php endif; ?>
    <?php
      $tel = preg_replace('/\D/', '', $os['cliente_telefone'] ?? '');
      if (strlen($tel) >= 8):
    ?>
      <a class="btn btn-neutro btn-sm" href="https://wa.me/55<?= $tel ?>" target="_blank" style="background:#25d366;color:#fff;border-color:#25d366;">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor" style="vertical-align:-1px;"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z"/></svg>
        WhatsApp
      </a>
      <a class="btn btn-neutro btn-sm" href="tel:<?= $tel ?>">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1px;"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.62 3.41 2 2 0 0 1 3.6 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.57a16 16 0 0 0 6.06 6.06l.97-.97a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
        Ligar
      </a>
    <?php endif; ?>
  </div>

  <!-- Botoes de acao -->
  <div style="margin-top:18px; display:flex; gap:10px; flex-wrap:wrap;">
    <?php if (in_array($situacao, ['aberto', 'reagendado', 'pausado'], true)): ?>
      <button class="btn btn-primario" onclick="acaoIniciar()"><?= ic('play', 15) ?> Iniciar atendimento</button>
    <?php endif; ?>
    <?php if ($situacao === 'em_andamento'): ?>
      <button class="btn btn-neutro" onclick="mostrar('boxPausar')"><?= ic('pausa', 15) ?> Pausar</button>
      <button class="btn btn-neutro" onclick="mostrar('boxReagendar')"><?= ic('calendario', 15) ?> Reagendar</button>
      <button class="btn btn-sucesso" onclick="mostrar('boxEncerrar')"><?= ic('check', 15) ?> Encerrar OS</button>
    <?php endif; ?>
    <?php if (in_array($situacao, ['aberto', 'pausado'], true)): ?>
      <button class="btn btn-neutro" onclick="mostrar('boxReagendar')"><?= ic('calendario', 15) ?> Reagendar</button>
    <?php endif; ?>
  </div>

  <!-- Pausar -->
  <div id="boxPausar" style="display:none; margin-top:16px; border-top:1px solid var(--cinza-100); padding-top:16px;">
    <div class="campo"><label>Motivo da pausa *</label><textarea id="motivoPausa" placeholder="Ex: cliente ausente, falta de material..."></textarea></div>
    <div style="display:flex; gap:8px;">
      <button class="btn btn-neutro" onclick="acaoPausar()"><?= ic('pausa', 14) ?> Confirmar pausa</button>
      <button class="btn btn-neutro" onclick="esconder('boxPausar')">Cancelar</button>
    </div>
  </div>

  <!-- Reagendar -->
  <div id="boxReagendar" style="display:none; margin-top:16px; border-top:1px solid var(--cinza-100); padding-top:16px;">
    <div class="linha-form">
      <div class="campo"><label>Nova data *</label><input type="date" id="novaData"></div>
      <div class="campo"><label>Motivo (opcional)</label><input type="text" id="motivoReagendar"></div>
    </div>
    <div style="display:flex; gap:8px;">
      <button class="btn btn-neutro" onclick="acaoReagendar()"><?= ic('calendario', 14) ?> Confirmar reagendamento</button>
      <button class="btn btn-neutro" onclick="esconder('boxReagendar')">Cancelar</button>
    </div>
  </div>

  <!-- Encerrar -->
  <div id="boxEncerrar" style="display:none; margin-top:16px; border-top:1px solid var(--cinza-100); padding-top:16px;">
    <div class="campo"><label>Laudo final *</label><textarea id="laudoFinal" placeholder="Descreva o servico realizado..."></textarea></div>
    <div style="display:flex; gap:8px;">
      <button class="btn btn-sucesso" onclick="acaoEncerrar()"><?= ic('check', 14) ?> Confirmar encerramento</button>
      <button class="btn btn-neutro" onclick="esconder('boxEncerrar')">Cancelar</button>
    </div>
  </div>
</div>

<!-- Descricao / Laudo -->
<div class="card">
  <h3 style="margin-top:0;"><?= ic('os_lista') ?> Descricao / Laudo</h3>
  <div class="campo"><textarea id="descricaoTexto" placeholder="Descreva o servico, observacoes..."><?= htmlspecialchars($os['observacoes'] ?? '') ?></textarea></div>
  <button class="btn btn-primario" onclick="acaoSalvarDescricao()"><?= ic('check', 14) ?> Salvar descricao</button>
</div>

<!-- Produtos -->
<div class="card">
  <h3 style="margin-top:0;"><?= ic('os_lista') ?> Produtos utilizados</h3>
  <?php if (empty($produtos)): ?>
    <div class="vazio" style="padding:16px 0;">Nenhum produto adicionado ainda.</div>
  <?php else: ?>
    <table>
      <thead><tr><th>Produto</th><th>Qtd</th><th>Valor unit.</th><th>Subtotal</th></tr></thead>
      <tbody>
        <?php $totalGeral = 0; foreach ($produtos as $p): $sub = (float) $p['valor_venda'] * (float) $p['quantidade']; $totalGeral += $sub; ?>
          <tr>
            <td><?= htmlspecialchars($p['nome']) ?></td>
            <td><?= htmlspecialchars((string) $p['quantidade']) ?></td>
            <td>R$ <?= number_format((float) $p['valor_venda'], 2, ',', '.') ?></td>
            <td>R$ <?= number_format($sub, 2, ',', '.') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="3" style="text-align:right; font-weight:700;">Total</td>
          <td style="font-weight:700;">R$ <?= number_format($totalGeral, 2, ',', '.') ?></td>
        </tr>
      </tfoot>
    </table>
  <?php endif; ?>

  <div style="margin-top:16px; border-top:1px solid var(--cinza-100); padding-top:16px;">
    <!-- Busca de produto no catálogo do GestãoClick -->
    <div class="campo" style="position:relative; margin-bottom:12px;">
      <label>Buscar produto no catálogo GestãoClick</label>
      <input type="text" id="gcBuscaProduto" placeholder="Digite o nome ou código do produto..." autocomplete="off">
      <div id="gcProdutoResultados" style="display:none; position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid var(--cinza-200); border-radius:var(--raio-sm); box-shadow:var(--sombra); z-index:200; max-height:200px; overflow-y:auto;"></div>
    </div>
    <div class="linha-form">
      <div class="campo"><label>Nome do produto *</label><input type="text" id="produtoNome"></div>
      <div class="campo"><label>Quantidade *</label><input type="number" id="produtoQtd" value="1" min="0.01" step="0.01"></div>
    </div>
    <div class="linha-form">
      <div class="campo"><label>Valor unitario *</label><input type="number" id="produtoValor" min="0" step="0.01" placeholder="0.00"></div>
      <div class="campo" style="display:none;"><input type="number" id="produtoId" value="0"></div>
    </div>
    <button class="btn btn-secundario" onclick="acaoAdicionarProduto()"><?= ic('os_nova', 14) ?> Adicionar produto</button>
  </div>
</div>

<!-- Fotos e videos -->
<div class="card">
  <h3 style="margin-top:0;"><?= ic('camera') ?> Fotos e videos</h3>
  <?php if (empty($midias)): ?>
    <div class="vazio" style="padding:20px 0;"><?= ic('camera', 32) ?><br>Nenhuma midia enviada ainda.</div>
  <?php else: ?>
    <div class="galeria">
      <?php foreach ($midias as $m): ?>
        <div class="item">
          <?php if ($m['tipo'] === 'foto'): ?>
            <a href="/app-tecnicos/<?= htmlspecialchars($m['caminho_arquivo']) ?>" target="_blank">
              <img src="/app-tecnicos/<?= htmlspecialchars($m['caminho_arquivo']) ?>">
            </a>
          <?php else: ?>
            <video src="/app-tecnicos/<?= htmlspecialchars($m['caminho_arquivo']) ?>" controls></video>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div style="margin-top:16px; border-top:1px solid var(--cinza-100); padding-top:16px;">
    <div class="linha-form">
      <div class="campo">
        <label>Tipo</label>
        <select id="midiaTipo"><option value="foto">Foto</option><option value="video">Video</option></select>
      </div>
      <div class="campo">
        <label>Arquivo (camera ou galeria)</label>
        <input type="file" id="midiaArquivo" accept="image/*,video/*" capture="environment">
      </div>
    </div>
    <button class="btn btn-secundario" onclick="acaoEnviarMidia()"><?= ic('camera', 14) ?> Enviar midia</button>
  </div>
</div>

<script>
const OS_ID      = <?= (int) $os['id'] ?>;
const GC_OS_ID   = <?= $gcOsId ?>;
const SITUACAO_ATUAL = <?= json_encode($situacao) ?>;

function mostrar(id) { document.getElementById(id).style.display = 'block'; }
function esconder(id) { document.getElementById(id).style.display = 'none'; }

function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// Carrega dados do GestãoClick (equipamento, defeito) inline no card
document.addEventListener('DOMContentLoaded', function () {
  if (!GC_OS_ID) { return; }
  (async () => {
    try {
      const d  = await apiGet('/os/dados-gc.php?gc_os_id=' + GC_OS_ID);
      const ex = d.extraido || {};
      const eqs = ex.equipamentos || [];

      let html = '';
      if (eqs.length > 0) {
        eqs.forEach((eq, i) => {
          html += `<div style="${i > 0 ? 'margin-top:8px;border-top:1px solid #bae6fd;padding-top:8px;' : ''}">`;
          if (eq.tipo)    { html += `<div><span style="color:#0369a1;">Tipo:</span> <strong>${escHtml(eq.tipo)}</strong></div>`; }
          if (eq.marca)   { html += `<div><span style="color:#0369a1;">Marca:</span> ${escHtml(eq.marca)}</div>`; }
          if (eq.modelo)  { html += `<div><span style="color:#0369a1;">Modelo:</span> ${escHtml(eq.modelo)}</div>`; }
          if (eq.serie)   { html += `<div><span style="color:#0369a1;">Série:</span> ${escHtml(eq.serie)}</div>`; }
          if (eq.defeitos) {
            html += `<div style="margin-top:6px;background:#fffbeb;border:1px solid #fde68a;border-radius:6px;padding:8px 10px;">
              <div style="font-size:10px;color:#92400e;font-weight:700;margin-bottom:2px;">DEFEITO</div>
              <div style="white-space:pre-wrap;">${escHtml(eq.defeitos)}</div>
            </div>`;
          }
          if (eq.solucao) {
            html += `<div style="margin-top:4px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:6px;padding:8px 10px;">
              <div style="font-size:10px;color:#166534;font-weight:700;margin-bottom:2px;">SOLUÇÃO</div>
              <div style="white-space:pre-wrap;">${escHtml(eq.solucao)}</div>
            </div>`;
          }
          html += '</div>';
        });
      }

      if (ex.nome_situacao) {
        html += `<div style="margin-top:8px;font-size:12px;color:#64748b;">Situação GC: <strong>${escHtml(ex.nome_situacao)}</strong></div>`;
      }
      if (ex.data_entrada) {
        html += `<div style="font-size:12px;color:#64748b;">Data entrada: <strong>${escHtml(ex.data_entrada)}</strong></div>`;
      }

      if (html) {
        document.getElementById('gc-equip-content').innerHTML = html;
        document.getElementById('gc-equip-box').style.display = 'block';
      }
    } catch (e) { /* silencioso — dados locais já disponíveis */ }
  })();

  // Busca de produto no catálogo GC
  const inputBusca = document.getElementById('gcBuscaProduto');
  const resDiv     = document.getElementById('gcProdutoResultados');
  let gcTimer;

  if (inputBusca) {
    inputBusca.addEventListener('input', function () {
      clearTimeout(gcTimer);
      const termo = this.value.trim();
      if (termo.length < 2) { resDiv.style.display = 'none'; return; }
      gcTimer = setTimeout(async () => {
        try {
          const r = await apiGet('/produtos/buscar-gc.php?busca=' + encodeURIComponent(termo));
          const lista = r.produtos || [];
          if (lista.length === 0) { resDiv.style.display = 'none'; return; }
          resDiv.innerHTML = lista.map(p =>
            `<div data-id="${p.id}" data-nome="${escHtml(p.nome)}" data-valor="${p.valor_venda}"
              style="padding:8px 12px;cursor:pointer;font-size:13px;border-bottom:1px solid var(--cinza-100);"
              onmousedown="selecionarProdutoGC(${p.id}, '${escHtml(p.nome).replace(/'/g,"\\'")}', ${p.valor_venda})">
              <strong>${escHtml(p.nome)}</strong>
              ${p.codigo ? `<span style="color:#94a3b8;margin-left:6px;font-size:11px;">${escHtml(p.codigo)}</span>` : ''}
              <span style="float:right;color:var(--azul-700);font-weight:600;">R$ ${parseFloat(p.valor_venda || 0).toFixed(2).replace('.', ',')}</span>
            </div>`
          ).join('');
          resDiv.style.display = 'block';
        } catch { resDiv.style.display = 'none'; }
      }, 400);
    });

    inputBusca.addEventListener('blur', () => setTimeout(() => { resDiv.style.display = 'none'; }, 200));
  }
});

function selecionarProdutoGC(id, nome, valor) {
  document.getElementById('produtoId').value    = id;
  document.getElementById('produtoNome').value  = nome;
  document.getElementById('produtoValor').value = valor;
  document.getElementById('gcBuscaProduto').value = '';
  document.getElementById('gcProdutoResultados').style.display = 'none';
  document.getElementById('produtoQtd').focus();
}

function mostrarAlerta(tipo, msg) {
  const box = document.getElementById('alertaOs');
  box.innerHTML = '<div class="alerta alerta-' + tipo + '">' + msg + '</div>';
  box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

async function acaoIniciar() {
  try {
    await apiPost('/os/iniciar.php', { os_id: OS_ID });
    mostrarAlerta('sucesso', 'OS iniciada! Atualizando...');
    setTimeout(() => window.location.reload(), 700);
  } catch (e) { mostrarAlerta('erro', e.message); }
}

async function acaoPausar() {
  const motivo = document.getElementById('motivoPausa').value.trim();
  if (!motivo) { mostrarAlerta('erro', 'Informe o motivo da pausa.'); return; }
  try {
    await apiPost('/os/pausar.php', { os_id: OS_ID, motivo });
    mostrarAlerta('sucesso', 'OS pausada.');
    setTimeout(() => window.location.reload(), 700);
  } catch (e) { mostrarAlerta('erro', e.message); }
}

async function acaoReagendar() {
  const novaData = document.getElementById('novaData').value;
  const motivo = document.getElementById('motivoReagendar').value;
  if (!novaData) { mostrarAlerta('erro', 'Informe a nova data.'); return; }
  try {
    await apiPost('/os/reagendar.php', { os_id: OS_ID, nova_data: novaData, motivo });
    mostrarAlerta('sucesso', 'OS reagendada.');
    setTimeout(() => window.location.reload(), 700);
  } catch (e) { mostrarAlerta('erro', e.message); }
}

async function acaoEncerrar() {
  const laudo = document.getElementById('laudoFinal').value.trim();
  if (!laudo) { mostrarAlerta('erro', 'Informe o laudo final.'); return; }
  if (!confirm('Confirma o encerramento desta OS? Essa acao nao pode ser desfeita.')) return;
  try {
    await apiPost('/os/encerrar.php', { os_id: OS_ID, laudo_final: laudo });
    mostrarAlerta('sucesso', 'OS encerrada com sucesso!');
    setTimeout(() => window.location.reload(), 700);
  } catch (e) { mostrarAlerta('erro', e.message); }
}

async function acaoSalvarDescricao() {
  const observacoes = document.getElementById('descricaoTexto').value;
  try {
    await apiPost('/os/descricao.php', { os_id: OS_ID, observacoes });
    mostrarAlerta('sucesso', 'Descricao salva.');
  } catch (e) { mostrarAlerta('erro', e.message); }
}

async function acaoAdicionarProduto() {
  const nome = document.getElementById('produtoNome').value.trim();
  const quantidade = parseFloat(document.getElementById('produtoQtd').value);
  const valor_venda = parseFloat(document.getElementById('produtoValor').value);
  const produto_id = parseInt(document.getElementById('produtoId').value || '0', 10);
  if (!nome || !quantidade || isNaN(valor_venda)) { mostrarAlerta('erro', 'Preencha nome, quantidade e valor.'); return; }
  try {
    await apiPost('/produtos/adicionar_os.php', { os_id: OS_ID, produto_id, nome, quantidade, valor_venda });
    mostrarAlerta('sucesso', 'Produto adicionado.');
    setTimeout(() => window.location.reload(), 700);
  } catch (e) { mostrarAlerta('erro', e.message); }
}

async function acaoEnviarMidia() {
  const arquivo = document.getElementById('midiaArquivo').files[0];
  const tipo = document.getElementById('midiaTipo').value;
  if (!arquivo) { mostrarAlerta('erro', 'Selecione um arquivo.'); return; }
  const fd = new FormData();
  fd.append('os_id', OS_ID);
  fd.append('tipo', tipo);
  fd.append('arquivo', arquivo);
  try {
    await apiUpload('/midias/upload.php', fd);
    mostrarAlerta('sucesso', 'Midia enviada com sucesso.');
    setTimeout(() => window.location.reload(), 700);
  } catch (e) { mostrarAlerta('erro', e.message); }
}

// GPS automatico enquanto OS em andamento
if (SITUACAO_ATUAL === 'em_andamento' && navigator.geolocation) {
  function enviarPosicao() {
    navigator.geolocation.getCurrentPosition(
      async (pos) => {
        try {
          await apiPost('/gps/atualizar.php', {
            os_id: OS_ID,
            latitude: pos.coords.latitude,
            longitude: pos.coords.longitude,
          });
        } catch (e) { /* silencioso */ }
      },
      () => {},
      { enableHighAccuracy: true, timeout: 15000 }
    );
  }
  enviarPosicao();
  setInterval(enviarPosicao, 60000);
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
