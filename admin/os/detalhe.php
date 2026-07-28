<?php
/**
 * Detalhe completo da OS: edicao, tecnicos (com fotos), historico, midias, impressao.
 */

declare(strict_types=1);

$perfisPermitidosPagina = ['gestor'];
$tituloPagina = 'Detalhe da OS';
$paginaAtiva = 'os_lista';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/icons.php';
require_once __DIR__ . '/../../config/database.php';

$pdo = obterConexao();
$osId = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT os.*, resp.nome AS tecnico_responsavel_nome
     FROM ordens_servico os
     LEFT JOIN usuarios resp ON resp.id = os.tecnico_id
     WHERE os.id = :id LIMIT 1'
);
$stmt->execute(['id' => $osId]);
$os = $stmt->fetch();

if (!$os) {
    echo '<div class="card"><div class="alerta alerta-erro">OS nao encontrada.</div></div>';
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$tecnicosAtribuidos = $pdo->prepare(
    'SELECT u.id, u.nome, u.foto_perfil, ot.responsavel
     FROM os_tecnicos ot INNER JOIN usuarios u ON u.id = ot.tecnico_id
     WHERE ot.os_id = :id ORDER BY ot.responsavel DESC, u.nome'
);
$tecnicosAtribuidos->execute(['id' => $osId]);
$tecnicosAtribuidos = $tecnicosAtribuidos->fetchAll();
$idsAtribuidos = array_column($tecnicosAtribuidos, 'id');

$todosTecnicos = $pdo->query("SELECT id, nome, email, foto_perfil FROM usuarios WHERE perfil = 'tecnico' AND ativo = 1 ORDER BY nome")->fetchAll();

$historico = $pdo->prepare(
    'SELECT h.acao, h.detalhe, h.criado_em, u.nome AS usuario_nome
     FROM historico_os h LEFT JOIN usuarios u ON u.id = h.usuario_id
     WHERE h.os_id = :id ORDER BY h.criado_em DESC'
);
$historico->execute(['id' => $osId]);
$historico = $historico->fetchAll();

$midias = $pdo->prepare('SELECT tipo, caminho_arquivo, nome_arquivo, criado_em FROM midias_os WHERE os_id = :id ORDER BY criado_em DESC');
$midias->execute(['id' => $osId]);
$midias = $midias->fetchAll();

$rotulosAcao = [
    'os_criada' => 'OS criada', 'tecnicos_atribuidos' => 'Tecnicos redefinidos',
    'os_atualizada' => 'OS atualizada', 'descricao_atualizada' => 'Descricao atualizada',
    'os_pausada' => 'OS pausada', 'os_reagendada' => 'OS reagendada', 'os_iniciada' => 'OS iniciada',
    'os_encerrada' => 'OS encerrada', 'midia_enviada' => 'Midia enviada',
    'produto_adicionado' => 'Produto adicionado', 'falha_sincronizacao_gc' => 'Falha ao sincronizar com GestaoClick',
    'recebimento_gerado' => 'Recebimento gerado',
];

function rotuloStatusDet(string $s): string
{
    $mapa = ['aberto' => 'Aberto', 'em_andamento' => 'Em andamento', 'pausado' => 'Pausado',
             'reagendado' => 'Reagendado', 'concluido' => 'Concluido', 'cancelado' => 'Cancelado'];
    return $mapa[$s] ?? $s;
}

function inicialTecnico(string $nome): string
{
    $iniciais = '';
    foreach (explode(' ', trim($nome)) as $p) {
        if ($p !== '') { $iniciais .= mb_strtoupper(mb_substr($p, 0, 1)); }
        if (mb_strlen($iniciais) >= 2) { break; }
    }
    return $iniciais;
}
?>

<div class="topbar" style="margin-top:-10px;">
  <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
    <span class="badge <?= $os['situacao_local'] ?>"><?= rotuloStatusDet($os['situacao_local']) ?></span>
    <span class="chip-prioridade <?= $os['prioridade'] ?>"><?= ic('flag', 11) ?> <?= ucfirst($os['prioridade']) ?></span>
    <?php if ($os['codigo']): ?>
      <span style="font-size:13px;color:#64748b;font-weight:600;"><?= htmlspecialchars($os['codigo']) ?></span>
    <?php endif; ?>
    <?php if ($os['gc_os_id']): ?>
      <span style="font-size:11px;background:#eff6ff;color:#1d4ed8;padding:2px 8px;border-radius:999px;">GC #<?= (int) $os['gc_os_id'] ?></span>
    <?php endif; ?>
  </div>
  <div class="acoes-tabela no-print">
    <?php if ($os['gc_os_id']): ?>
    <button onclick="resyncOS()" class="btn btn-neutro btn-sm" id="btn-resync">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
      Atualizar do GC
    </button>
    <?php endif; ?>
    <?php if (!empty($os['portal_token'])): ?>
    <a href="https://wa.me/?text=<?= urlencode('Olá! Acompanhe o status da sua OS em tempo real: ' . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/app-tecnicos/portal/os.php?token=' . $os['portal_token']) ?>"
       target="_blank" class="btn btn-neutro btn-sm" style="background:#e6f4ea;color:#1e8e5a;">
      Enviar portal ao cliente
    </a>
    <?php endif; ?>
    <a href="/app-tecnicos/admin/os/imprimir.php?id=<?= (int) $os['id'] ?>" target="_blank" class="btn btn-neutro btn-sm">
      <?= ic('imprimir', 14) ?> Imprimir OS
    </a>
    <a href="/app-tecnicos/admin/os/lista.php" class="btn btn-neutro btn-sm">
      <?= ic('voltar', 14) ?> Voltar
    </a>
  </div>
</div>

<div id="alertaDetalhe"></div>

<div class="card">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:4px;">
    <h3 style="margin:0;">OS #<?= (int) $os['id'] ?> &middot; <?= htmlspecialchars($os['cliente_nome'] ?? '-') ?></h3>
    <a href="/app-tecnicos/admin/clientes/historico.php?nome=<?= urlencode($os['cliente_nome'] ?? '') ?>"
       class="btn btn-secundario btn-sm" style="font-size:11px;" title="Ver todas OS deste cliente">
      Histórico do cliente
    </a>
  </div>

  <?php if ($os['gc_os_id']): ?>
  <!-- Faixa de equipamento GC — preenchida via JS -->
  <div id="gc-equip-box" style="display:none; background:#f0f9ff; border:1px solid #bae6fd; border-radius:8px; padding:14px; margin-bottom:18px; font-size:13px;">
    <div style="font-size:11px;color:#0369a1;font-weight:700;margin-bottom:10px;display:flex;align-items:center;gap:6px;">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      EQUIPAMENTO / INFORMAÇÕES DO GESTÃOCLICK
      <span id="gc-status" style="font-weight:400;color:#64748b;margin-left:8px;">Carregando...</span>
    </div>
    <div id="gc-equip-content"></div>
  </div>
  <?php endif; ?>

  <form id="formAtualizarOs">
    <div class="linha-form">
      <div class="campo"><label>Cliente</label><input type="text" name="cliente_nome" value="<?= htmlspecialchars($os['cliente_nome'] ?? '') ?>"></div>
      <div class="campo"><label>Telefone</label><input type="tel" name="cliente_telefone" value="<?= htmlspecialchars($os['cliente_telefone'] ?? '') ?>"></div>
    </div>
    <div class="campo"><label>Endereco</label><input type="text" name="cliente_endereco" value="<?= htmlspecialchars($os['cliente_endereco'] ?? '') ?>"></div>
    <div class="linha-form">
      <div class="campo">
        <label>Data de agendamento</label>
        <input type="date" name="data_agendamento" value="<?= htmlspecialchars($os['data_agendamento'] ?? '') ?>">
      </div>
      <div class="campo">
        <label>Prazo (SLA)</label>
        <input type="date" name="data_prazo" value="<?= htmlspecialchars($os['data_prazo'] ?? '') ?>">
      </div>
      <div class="campo">
        <label>Prioridade</label>
        <select name="prioridade">
          <?php foreach (['baixo' => 'Baixo', 'intermediario' => 'Intermediario', 'urgente' => 'Urgente'] as $v => $l): ?>
            <option value="<?= $v ?>" <?= $os['prioridade'] === $v ? 'selected' : '' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="campo">
        <label>Status</label>
        <select name="situacao_local">
          <?php foreach (['aberto','em_andamento','pausado','reagendado','concluido','cancelado'] as $s): ?>
            <option value="<?= $s ?>" <?= $os['situacao_local'] === $s ? 'selected' : '' ?>><?= rotuloStatusDet($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="campo"><label>Observacoes / Laudo</label><textarea name="observacoes"><?= htmlspecialchars($os['observacoes'] ?? '') ?></textarea></div>
    <button type="submit" class="btn btn-primario"><?= ic('check', 15) ?> Salvar alteracoes</button>
  </form>
</div>

<?php if ($os['gc_os_id']): ?>
<!-- Produtos e Serviços do GC — preenchido via JS -->
<div id="card-gc-produtos" class="card" style="display:none;">
  <h3 style="margin-top:0;">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
    Produtos e Serviços (GestãoClick)
  </h3>
  <div id="gc-produtos-content"></div>
</div>
<?php endif; ?>

<div class="card">
  <h3 style="margin-top:0;"><?= ic('tecnicos') ?> Tecnicos atribuidos</h3>
  <?php if (!empty($tecnicosAtribuidos)): ?>
  <div style="display:flex; gap:14px; flex-wrap:wrap; margin-bottom:18px; padding-bottom:16px; border-bottom:1px solid var(--cinza-100);">
    <?php foreach ($tecnicosAtribuidos as $ta): ?>
      <div style="display:flex; align-items:center; gap:10px; background:var(--cinza-100); border-radius:var(--raio-sm); padding:8px 14px;">
        <?php if ($ta['foto_perfil'] && file_exists(__DIR__ . '/../../' . $ta['foto_perfil'])): ?>
          <img src="/app-tecnicos/<?= htmlspecialchars($ta['foto_perfil']) ?>" class="avatar-foto" alt="">
        <?php else: ?>
          <div class="avatar-placeholder" style="width:36px;height:36px;font-size:13px;"><?= inicialTecnico($ta['nome']) ?></div>
        <?php endif; ?>
        <div>
          <div style="font-weight:600; font-size:13.5px;"><?= htmlspecialchars($ta['nome']) ?></div>
          <?php if ($ta['responsavel']): ?>
            <div style="font-size:11px; color:var(--azul-700); font-weight:600;">Responsavel</div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div style="margin-bottom:12px;">
    <button type="button" onclick="abrirSugestaoTecnico()" class="btn btn-sm" style="background:#f0fdf4;color:#16803c;border:1px solid #bbf7d0;">
      🎯 Sugerir técnico mais próximo
    </button>
  </div>

  <!-- Modal sugestão de técnico -->
  <div id="modal-sugestao" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:14px;padding:24px;max-width:500px;width:90%;max-height:80vh;overflow-y:auto;box-shadow:0 16px 48px rgba(0,0,0,.25);">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <h3 style="margin:0;font-size:15px;">🎯 Distribuição Automática</h3>
        <button onclick="document.getElementById('modal-sugestao').style.display='none'"
                style="background:none;border:none;cursor:pointer;font-size:20px;color:#64748b;">×</button>
      </div>
      <div id="sugestao-lista" style="font-size:13px;color:#64748b;">Carregando...</div>
    </div>
  </div>

  <form id="formTecnicos">
    <?php foreach ($todosTecnicos as $t): ?>
      <?php
        $marcado = in_array($t['id'], $idsAtribuidos, true);
        $ehResponsavel = false;
        foreach ($tecnicosAtribuidos as $ta) { if ($ta['id'] === $t['id'] && $ta['responsavel']) { $ehResponsavel = true; } }
      ?>
      <div class="tecnico-check">
        <?php if ($t['foto_perfil'] && file_exists(__DIR__ . '/../../' . $t['foto_perfil'])): ?>
          <img src="/app-tecnicos/<?= htmlspecialchars($t['foto_perfil']) ?>" class="avatar-foto" alt="">
        <?php else: ?>
          <div class="avatar-placeholder" style="width:32px;height:32px;font-size:11px; flex-shrink:0;"><?= inicialTecnico($t['nome']) ?></div>
        <?php endif; ?>
        <input type="checkbox" class="chk-tecnico" value="<?= (int) $t['id'] ?>" id="tec_<?= (int) $t['id'] ?>" <?= $marcado ? 'checked' : '' ?>>
        <label for="tec_<?= (int) $t['id'] ?>" style="margin:0; font-weight:500; flex:1;"><?= htmlspecialchars($t['nome']) ?></label>
        <label style="margin:0; display:flex; align-items:center; gap:6px; font-weight:400;">
          <input type="radio" name="responsavel" class="rd-responsavel" value="<?= (int) $t['id'] ?>" <?= $ehResponsavel ? 'checked' : '' ?>> Responsavel
        </label>
      </div>
    <?php endforeach; ?>
    <button type="submit" class="btn btn-secundario"><?= ic('check', 15) ?> Salvar tecnicos</button>
  </form>
</div>

<div class="card">
  <h3 style="margin-top:0;"><?= ic('foto') ?> Midias (fotos e videos)</h3>
  <?php if (empty($midias)): ?>
    <div class="vazio" style="padding:30px;"><?= ic('camera', 36) ?><br>Nenhuma midia enviada para esta OS ainda.</div>
  <?php else: ?>
    <div class="galeria">
      <?php foreach ($midias as $m): ?>
        <div class="item">
          <?php if ($m['tipo'] === 'foto'): ?>
            <a href="/app-tecnicos/<?= htmlspecialchars($m['caminho_arquivo']) ?>" target="_blank">
              <img src="/app-tecnicos/<?= htmlspecialchars($m['caminho_arquivo']) ?>" alt="Foto">
            </a>
          <?php else: ?>
            <video src="/app-tecnicos/<?= htmlspecialchars($m['caminho_arquivo']) ?>" controls></video>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <h3 style="margin-top:0;"><?= ic('os_lista') ?> Historico</h3>
  <?php if (empty($historico)): ?>
    <div class="vazio" style="padding:20px;">Sem historico registrado.</div>
  <?php else: ?>
    <ul class="timeline">
      <?php foreach ($historico as $h): ?>
        <li>
          <div class="acao"><?= htmlspecialchars($rotulosAcao[$h['acao']] ?? $h['acao']) ?></div>
          <?php if ($h['detalhe']): ?><div class="detalhe"><?= htmlspecialchars($h['detalhe']) ?></div><?php endif; ?>
          <div class="quando"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($h['criado_em']))) ?><?= $h['usuario_nome'] ? ' &middot; ' . htmlspecialchars($h['usuario_nome']) : '' ?></div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>

<script>
const OS_ID    = <?= (int) $os['id'] ?>;
const GC_OS_ID = <?= (int) ($os['gc_os_id'] ?? 0) ?>;

function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// Carrega dados ao vivo do GestãoClick após api.js estar disponível (carregado no footer)
document.addEventListener('DOMContentLoaded', function () {

if (GC_OS_ID) {
  (async () => {
    const statusEl     = document.getElementById('gc-status');
    const equipBox     = document.getElementById('gc-equip-box');
    const equipContent = document.getElementById('gc-equip-content');
    const cardProdutos = document.getElementById('card-gc-produtos');
    const prodContent  = document.getElementById('gc-produtos-content');

    try {
      const d  = await apiGet('/os/dados-gc.php?gc_os_id=' + GC_OS_ID);
      const ex = d.extraido || {};

      statusEl.style.color = '#0369a1';
      statusEl.textContent = 'Dados GC carregados ✓';

      // — AUTO PREENCHE campos do formulário local se estiverem vazios —
      const fill = (name, val) => {
        const el = document.querySelector(`[name="${name}"]`);
        if (el && !el.value.trim() && val) { el.value = val; }
      };
      fill('cliente_nome',      ex.cliente_nome);
      fill('cliente_telefone',  ex.cliente_telefone);
      fill('cliente_endereco',  ex.cliente_endereco);
      if (!document.querySelector('[name="observacoes"]').value.trim()) {
        const eqs0 = (ex.equipamentos || [])[0];
        if (eqs0) {
          const partes = [];
          if (eqs0.tipo)     { partes.push('Equipamento: ' + eqs0.tipo); }
          if (eqs0.defeitos) { partes.push('Defeito: ' + eqs0.defeitos); }
          if (partes.length) { document.querySelector('[name="observacoes"]').value = partes.join('\n'); }
        }
      }

      // — SEÇÃO DE EQUIPAMENTOS inline no card principal —
      const cel = (label, val) => val
        ? `<span style="color:#64748b;">${label}:</span> <strong>${escHtml(String(val))}</strong>`
        : '';

      let equipHtml = '';

      // Informações gerais GC (código, situação, técnico, data)
      const gcMeta = [
        ex.codigo       ? `<div style="margin-bottom:4px;">${cel('Código GC', ex.codigo)}</div>` : '',
        ex.nome_situacao ? `<div style="margin-bottom:4px;">${cel('Situação GC', ex.nome_situacao)}</div>` : '',
        ex.nome_tecnico  ? `<div style="margin-bottom:4px;">${cel('Técnico GC', ex.nome_tecnico)}</div>` : '',
        ex.data_entrada  ? `<div style="margin-bottom:4px;">${cel('Data entrada', ex.data_entrada)}</div>` : '',
      ].filter(Boolean).join('');
      if (gcMeta) {
        equipHtml += `<div style="display:grid;grid-template-columns:1fr 1fr;gap:0 24px;margin-bottom:12px;">${gcMeta}</div>`;
      }

      // Equipamentos
      const eqs = ex.equipamentos || [];
      eqs.forEach((eq, i) => {
        equipHtml += `<div style="border-top:1px solid #bae6fd;padding-top:10px;margin-top:10px;">
          <div style="font-weight:700;font-size:12px;margin-bottom:8px;">EQUIPAMENTO${eqs.length > 1 ? ' #' + (i+1) : ''}</div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 24px;">
            ${eq.tipo   ? `<div style="margin-bottom:4px;">${cel('Tipo', eq.tipo)}</div>` : ''}
            ${eq.marca  ? `<div style="margin-bottom:4px;">${cel('Marca', eq.marca)}</div>` : ''}
            ${eq.modelo ? `<div style="margin-bottom:4px;">${cel('Modelo', eq.modelo)}</div>` : ''}
            ${eq.serie  ? `<div style="margin-bottom:4px;">${cel('Série', eq.serie)}</div>` : ''}
          </div>`;
        if (eq.defeitos) {
          equipHtml += `<div style="margin-top:8px;background:#fffbeb;border:1px solid #fde68a;border-radius:6px;padding:8px 12px;">
            <div style="font-size:10px;color:#92400e;font-weight:700;margin-bottom:3px;">DEFEITO RELATADO</div>
            <div style="font-size:13px;white-space:pre-wrap;">${escHtml(eq.defeitos)}</div>
          </div>`;
        }
        if (eq.solucao) {
          equipHtml += `<div style="margin-top:6px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:6px;padding:8px 12px;">
            <div style="font-size:10px;color:#166534;font-weight:700;margin-bottom:3px;">SOLUÇÃO APLICADA</div>
            <div style="font-size:13px;white-space:pre-wrap;">${escHtml(eq.solucao)}</div>
          </div>`;
        }
        if (eq.laudo) {
          equipHtml += `<div style="margin-top:6px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:6px;padding:8px 12px;">
            <div style="font-size:10px;color:#1d4ed8;font-weight:700;margin-bottom:3px;">LAUDO TÉCNICO</div>
            <div style="font-size:13px;white-space:pre-wrap;">${escHtml(eq.laudo)}</div>
          </div>`;
        }
        equipHtml += '</div>';
      });

      if (ex.observacoes) {
        equipHtml += `<div style="margin-top:10px;border-top:1px solid #bae6fd;padding-top:10px;">
          <span style="color:#64748b;">Observações GC:</span> <span>${escHtml(ex.observacoes)}</span>
        </div>`;
      }

      if (equipHtml) {
        equipContent.innerHTML = equipHtml;
        equipBox.style.display = 'block';
      }

      // — CARD DE PRODUTOS/SERVIÇOS GC —
      const servicos = ex.servicos || [];
      const produtos  = ex.produtos || [];
      let prodHtml = '';

      if (servicos.length > 0) {
        prodHtml += '<div style="margin-bottom:14px;"><strong style="font-size:13px;">Serviços</strong><table style="width:100%;margin-top:8px;font-size:13px;"><thead><tr style="text-align:left;border-bottom:2px solid var(--cinza-100);"><th style="padding:6px 8px;">Serviço</th><th style="padding:6px 8px;">Qtd</th><th style="padding:6px 8px;">Valor</th></tr></thead><tbody>';
        servicos.forEach(s => {
          prodHtml += `<tr style="border-bottom:1px solid var(--cinza-100);">
            <td style="padding:6px 8px;">${escHtml(String(s.nome || ''))}</td>
            <td style="padding:6px 8px;">${s.quantidade ?? '-'}</td>
            <td style="padding:6px 8px;">R$ ${parseFloat(s.valor || 0).toFixed(2).replace('.', ',')}</td>
          </tr>`;
        });
        prodHtml += '</tbody></table></div>';
      }

      if (produtos.length > 0) {
        prodHtml += '<div><strong style="font-size:13px;">Produtos / Peças</strong><table style="width:100%;margin-top:8px;font-size:13px;"><thead><tr style="text-align:left;border-bottom:2px solid var(--cinza-100);"><th style="padding:6px 8px;">Nome</th><th style="padding:6px 8px;">Qtd</th><th style="padding:6px 8px;">Valor unit.</th></tr></thead><tbody>';
        produtos.forEach(p => {
          prodHtml += `<tr style="border-bottom:1px solid var(--cinza-100);">
            <td style="padding:6px 8px;">${escHtml(String(p.nome || ''))}</td>
            <td style="padding:6px 8px;">${p.quantidade ?? '-'}</td>
            <td style="padding:6px 8px;">R$ ${parseFloat(p.valor_venda || 0).toFixed(2).replace('.', ',')}</td>
          </tr>`;
        });
        prodHtml += '</tbody></table></div>';
      }

      if (prodHtml) {
        prodContent.innerHTML = prodHtml;
        cardProdutos.style.display = 'block';
      }

    } catch (e) {
      if (statusEl) {
        statusEl.style.color = '#c0392b';
        statusEl.textContent = 'Erro GC: ' + e.message;
      }
    }
  })();
}

}); // fim DOMContentLoaded

async function resyncOS() {
  const btn = document.getElementById('btn-resync');
  const alertaBox = document.getElementById('alertaDetalhe');
  btn.disabled = true;
  btn.textContent = 'Atualizando...';
  try {
    const r = await fetch('/app-tecnicos/api/os/sincronizar.php', {
      method: 'POST',
      headers: {'Authorization': 'Bearer ' + (window.APP_JWT || ''), 'Content-Type': 'application/json'},
      body: JSON.stringify({ force: true }),
    });
    const d = await r.json();
    if (d.sucesso) {
      alertaBox.innerHTML = '<div class="alerta alerta-sucesso">OS atualizada do GestaoClick.</div>';
      setTimeout(() => location.reload(), 900);
    } else {
      alertaBox.innerHTML = '<div class="alerta alerta-erro">Falha: ' + (d.erro || 'erro desconhecido') + '</div>';
    }
  } catch {
    alertaBox.innerHTML = '<div class="alerta alerta-erro">Falha de conexao.</div>';
  } finally {
    btn.disabled = false;
    btn.textContent = 'Atualizar do GC';
  }
}

document.getElementById('formAtualizarOs').addEventListener('submit', async function (ev) {
  ev.preventDefault();
  const alertaBox = document.getElementById('alertaDetalhe');
  const fd = new FormData(ev.target);
  const payload = { os_id: OS_ID };
  for (const [k, v] of fd.entries()) payload[k] = v;
  try {
    await apiPost('/os/atualizar.php', payload);
    alertaBox.innerHTML = '<div class="alerta alerta-sucesso">Alteracoes salvas com sucesso.</div>';
    setTimeout(() => window.location.reload(), 900);
  } catch (e) {
    alertaBox.innerHTML = '<div class="alerta alerta-erro">Erro: ' + e.message + '</div>';
  }
});

async function abrirSugestaoTecnico() {
  const modal = document.getElementById('modal-sugestao');
  const lista = document.getElementById('sugestao-lista');
  modal.style.display = 'flex';
  lista.innerHTML = 'Carregando...';
  try {
    const dados = await apiGet('/os/sugerir_tecnico.php?os_id=' + OS_ID);
    const tecs = dados.tecnicos || [];
    if (!tecs.length) { lista.innerHTML = 'Nenhum técnico cadastrado.'; return; }
    lista.innerHTML = tecs.map((t, i) => `
      <div style="display:flex;align-items:center;gap:10px;padding:10px;border-radius:8px;background:${i===0?'#f0fdf4':'#f8fafc'};margin-bottom:8px;border:1px solid ${i===0?'#bbf7d0':'#e2e8f0'};">
        <div style="width:32px;height:32px;border-radius:50%;background:${t.livre?'#16803c':'#94a3b8'};color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:12px;flex-shrink:0;">
          ${i+1}
        </div>
        <div style="flex:1;">
          <div style="font-weight:700;color:#1c2430;">${escHtml(t.nome)}</div>
          <div style="font-size:11px;color:#64748b;margin-top:2px;">
            ${t.livre ? '<span style="color:#16803c;font-weight:600;">Livre</span>' : `<span style="color:#dc2626;">${t.os_ativas} OS ativa(s)</span>`}
            ${t.gps_ativo ? ' &bull; GPS ativo' : (t.gps_min_atras ? ` &bull; GPS ${t.gps_min_atras}min atrás` : ' &bull; Sem GPS')}
            ${t.distancia_km !== null ? ` &bull; ~${t.distancia_km}km` : ''}
          </div>
        </div>
        <button onclick="atribuirSugestao(${t.id})"
                class="btn btn-secundario btn-sm" style="${i===0?'background:#16803c;color:#fff;border-color:#16803c;':''}">
          ${i===0 ? '✓ Atribuir' : 'Atribuir'}
        </button>
      </div>`).join('');
  } catch (e) {
    lista.innerHTML = 'Erro: ' + e.message;
  }
}

function escHtml(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

async function atribuirSugestao(tecId) {
  try {
    await apiPost('/os/atribuir_tecnicos.php', {
      os_id: OS_ID,
      tecnicos: [{tecnico_id: tecId, responsavel: true}]
    });
    document.getElementById('modal-sugestao').style.display = 'none';
    document.getElementById('alertaDetalhe').innerHTML = '<div class="alerta alerta-sucesso">Técnico atribuído com sucesso!</div>';
    setTimeout(() => window.location.reload(), 800);
  } catch (e) {
    alert('Erro: ' + e.message);
  }
}

document.getElementById('formTecnicos').addEventListener('submit', async function (ev) {
  ev.preventDefault();
  const alertaBox = document.getElementById('alertaDetalhe');
  const marcados = Array.from(document.querySelectorAll('.chk-tecnico:checked')).map(c => parseInt(c.value, 10));
  if (marcados.length === 0) {
    alertaBox.innerHTML = '<div class="alerta alerta-erro">Selecione ao menos um tecnico.</div>';
    return;
  }
  const responsavelSelecionado = document.querySelector('.rd-responsavel:checked');
  const responsavelId = responsavelSelecionado ? parseInt(responsavelSelecionado.value, 10) : marcados[0];
  const tecnicos = marcados.map(id => ({ tecnico_id: id, responsavel: id === responsavelId }));
  try {
    await apiPost('/os/atribuir_tecnicos.php', { os_id: OS_ID, tecnicos });
    alertaBox.innerHTML = '<div class="alerta alerta-sucesso">Tecnicos atualizados.</div>';
    setTimeout(() => window.location.reload(), 900);
  } catch (e) {
    alertaBox.innerHTML = '<div class="alerta alerta-erro">Erro: ' + e.message + '</div>';
  }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
