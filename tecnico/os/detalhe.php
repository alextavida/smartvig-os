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

$stmtAcesso = $pdo->prepare('SELECT 1 FROM os_tecnicos WHERE os_id = :os_id AND tecnico_id = :tecnico_id');
$stmtAcesso->execute(['os_id' => $osId, 'tecnico_id' => $tecnicoId]);
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
  <div style="display:flex; align-items:center; gap:8px; margin-bottom:6px; font-size:14px; color:var(--cinza-700);">
    <?= ic('mapa', 15) ?> <?= htmlspecialchars($os['cliente_endereco'] ?? 'Endereco nao informado') ?>
  </div>
  <div style="display:flex; align-items:center; gap:8px; margin-bottom:6px; font-size:14px; color:var(--cinza-700);">
    <?= ic('notificacao', 15) ?> <?= htmlspecialchars($os['cliente_telefone'] ?? '-') ?>
  </div>
  <div style="display:flex; align-items:center; gap:8px; margin-bottom:12px; font-size:14px; color:var(--cinza-700);">
    <?= ic('calendario', 15) ?> <?= $os['data_agendamento'] ? date('d/m/Y', strtotime($os['data_agendamento'])) : 'Sem data definida' ?>
  </div>

  <?php if ($enderecoCodificado): ?>
    <a class="btn btn-secundario btn-sm" target="_blank" href="https://www.google.com/maps/dir/?api=1&destination=<?= $enderecoCodificado ?>">
      <?= ic('rota', 14) ?> Abrir rota no Google Maps
    </a>
  <?php endif; ?>

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
    <div class="vazio" style="padding:20px 0;">Nenhum produto adicionado ainda.</div>
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
    <div class="linha-form">
      <div class="campo"><label>ID do produto (GestaoClick)</label><input type="number" id="produtoId" placeholder="Ex: 45"></div>
      <div class="campo"><label>Nome do produto *</label><input type="text" id="produtoNome"></div>
    </div>
    <div class="linha-form">
      <div class="campo"><label>Quantidade *</label><input type="number" id="produtoQtd" value="1" min="0.01" step="0.01"></div>
      <div class="campo"><label>Valor unitario *</label><input type="number" id="produtoValor" min="0" step="0.01" placeholder="0.00"></div>
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
const OS_ID = <?= (int) $os['id'] ?>;
const SITUACAO_ATUAL = <?= json_encode($situacao) ?>;

function mostrar(id) { document.getElementById(id).style.display = 'block'; }
function esconder(id) { document.getElementById(id).style.display = 'none'; }

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
