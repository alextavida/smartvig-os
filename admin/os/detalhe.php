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
    <a href="/app-tecnicos/admin/os/imprimir.php?id=<?= (int) $os['id'] ?>" target="_blank" class="btn btn-neutro btn-sm">
      <?= ic('imprimir', 14) ?> Imprimir OS
    </a>
    <a href="/app-tecnicos/admin/os/lista.php" class="btn btn-neutro btn-sm">
      <?= ic('voltar', 14) ?> Voltar
    </a>
  </div>
</div>

<div id="alertaDetalhe"></div>

<?php if ($os['gc_os_id']): ?>
<div class="card" id="painel-gc" style="border-left:4px solid #1d4ed8;">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
    <strong style="color:#1d4ed8;">Dados do GestãoClick (ao vivo)</strong>
    <span id="gc-status" style="font-size:12px;color:#64748b;">Carregando...</span>
  </div>
  <div id="gc-dados" style="margin-top:12px;font-size:13px;"></div>
</div>
<?php endif; ?>

<div class="card">
  <h3 style="margin-top:0;">OS #<?= (int) $os['id'] ?> &middot; <?= htmlspecialchars($os['cliente_nome'] ?? '-') ?></h3>
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

// Carrega dados ao vivo do GestãoClick se a OS tiver gc_os_id
if (GC_OS_ID) {
  (async () => {
    const statusEl = document.getElementById('gc-status');
    const dadosEl  = document.getElementById('gc-dados');
    try {
      const d = await apiGet('/os/dados-gc.php?gc_os_id=' + GC_OS_ID);
      const ex = d.extraido || {};
      const raw = d.raw || {};

      statusEl.style.color = '#16803c';
      statusEl.textContent = 'Sincronizado com GC';

      // Renderiza painel de dados GC
      const cel = (label, val) => val
        ? `<div style="margin-bottom:6px;"><span style="color:#64748b;min-width:120px;display:inline-block;">${label}:</span> <strong>${escHtml(String(val))}</strong></div>`
        : '';

      let html = '<div style="display:grid;grid-template-columns:1fr 1fr;gap:0 24px;">';
      html += cel('Cliente', ex.cliente_nome);
      html += cel('Código OS', ex.codigo);
      html += cel('Telefone', ex.cliente_telefone);
      html += cel('Data entrada', ex.data_entrada);
      html += cel('Endereço', ex.cliente_endereco);
      html += cel('Situação GC', ex.nome_situacao);
      html += cel('Técnico GC', ex.nome_tecnico);
      html += '</div>';

      // Equipamentos (preenchidos pelo técnico ao finalizar OS)
      const eqs = ex.equipamentos;
      if (Array.isArray(eqs) && eqs.length > 0) {
        eqs.forEach((eq, i) => {
          html += `<div style="margin-top:14px;border-top:1px solid #e2e8f0;padding-top:12px;">
            <strong style="font-size:13px;">🔧 Equipamento ${eqs.length > 1 ? '#' + (i+1) : ''}</strong>
            <div style="margin-top:8px;display:grid;grid-template-columns:1fr 1fr;gap:0 24px;font-size:13px;">
              ${eq.tipo    ? cel('Tipo',   eq.tipo)   : ''}
              ${eq.marca   ? cel('Marca',  eq.marca)  : ''}
              ${eq.modelo  ? cel('Modelo', eq.modelo) : ''}
              ${eq.serie   ? cel('Série',  eq.serie)  : ''}
            </div>`;
          if (eq.defeitos) {
            html += `<div style="margin-top:8px;background:#fffbeb;border:1px solid #fde68a;border-radius:6px;padding:8px 12px;">
              <div style="font-size:11px;color:#92400e;font-weight:700;margin-bottom:4px;">DEFEITO RELATADO</div>
              <div style="font-size:13px;white-space:pre-wrap;">${escHtml(eq.defeitos)}</div>
            </div>`;
          }
          if (eq.solucao) {
            html += `<div style="margin-top:6px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:6px;padding:8px 12px;">
              <div style="font-size:11px;color:#166534;font-weight:700;margin-bottom:4px;">SOLUÇÃO APLICADA</div>
              <div style="font-size:13px;white-space:pre-wrap;">${escHtml(eq.solucao)}</div>
            </div>`;
          }
          if (eq.laudo) {
            html += `<div style="margin-top:6px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:6px;padding:8px 12px;">
              <div style="font-size:11px;color:#1d4ed8;font-weight:700;margin-bottom:4px;">LAUDO TÉCNICO</div>
              <div style="font-size:13px;white-space:pre-wrap;">${escHtml(eq.laudo)}</div>
            </div>`;
          }
          html += '</div>';
        });
      }

      // Observações da OS no GC
      if (ex.observacoes) {
        html += `<div style="margin-top:10px;">${cel('Observações GC', ex.observacoes)}</div>`;
      }

      // Serviços
      const servs = ex.servicos;
      if (Array.isArray(servs) && servs.length > 0) {
        html += '<div style="margin-top:10px;"><strong>Serviços do GC:</strong><table style="width:100%;margin-top:6px;font-size:12px;"><thead><tr style="text-align:left;"><th>Serviço</th><th>Qtd</th><th>Valor</th></tr></thead><tbody>';
        servs.forEach(s => {
          html += `<tr><td>${escHtml(String(s.nome || s.descricao || s.servico || ''))}</td><td>${s.quantidade || '-'}</td><td>${s.valor || s.preco || '-'}</td></tr>`;
        });
        html += '</tbody></table></div>';
      }

      // Produtos/peças
      const prods = ex.produtos;
      if (Array.isArray(prods) && prods.length > 0) {
        html += '<div style="margin-top:10px;"><strong>Produtos/Peças do GC:</strong><table style="width:100%;margin-top:6px;font-size:12px;"><thead><tr style="text-align:left;"><th>Nome</th><th>Qtd</th><th>Valor</th></tr></thead><tbody>';
        prods.forEach(p => {
          const nome = p.nome || p.descricao || p.produto || JSON.stringify(p);
          html += `<tr><td>${escHtml(String(nome))}</td><td>${p.quantidade || p.qtd || '-'}</td><td>${p.valor || p.preco || p.valor_venda || '-'}</td></tr>`;
        });
        html += '</tbody></table></div>';
      }

      // Raw JSON colapsável
      html += `<details style="margin-top:12px;"><summary style="cursor:pointer;font-size:11px;color:#94a3b8;">Ver JSON completo do GC (${d.chaves?.length || 0} campos)</summary>
        <pre style="font-size:11px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:8px;max-height:200px;overflow:auto;margin-top:4px;">${escHtml(JSON.stringify(raw, null, 2))}</pre>
      </details>`;

      dadosEl.innerHTML = html || '<span style="color:#94a3b8;">Sem dados detalhados disponíveis no GC.</span>';

      // Preenche campos do formulário local se estiverem vazios
      const fill = (name, val) => { const el = document.querySelector(`[name="${name}"]`); if (el && !el.value.trim() && val) el.value = val; };
      fill('cliente_nome', ex.cliente_nome);
      fill('cliente_telefone', ex.cliente_telefone);
      fill('cliente_endereco', ex.cliente_endereco);
      // Preenche observacoes com defeito+tipo se vazio
      if (Array.isArray(eqs) && eqs.length > 0 && eqs[0].defeitos) {
        const eq0 = eqs[0];
        const textoInicial = [
          eq0.tipo    ? 'Equipamento: ' + eq0.tipo    : '',
          eq0.defeitos ? 'Defeito: '     + eq0.defeitos : '',
        ].filter(Boolean).join('\n');
        fill('observacoes', textoInicial);
      }

    } catch (e) {
      statusEl.style.color = '#c0392b';
      statusEl.textContent = 'Erro ao buscar do GC: ' + e.message;
      dadosEl.innerHTML = '';
    }
  })();
}

function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

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
