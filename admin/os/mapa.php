<?php
/**
 * Mapa com a ultima posicao dos tecnicos — Google Maps embed (sem API key).
 * Atualiza a lista a cada 15s; clicar em um tecnico centraliza o mapa.
 */

declare(strict_types=1);

$perfisPermitidosPagina = ['gestor'];
$tituloPagina = 'Mapa de Tecnicos';
$paginaAtiva = 'mapa';
require_once __DIR__ . '/../../includes/header.php';
?>

<style>
.mapa-wrap {position:relative; background:#e8edf2; border-radius:12px; overflow:hidden; height:68vh; min-height:400px;}
#mapa-iframe {width:100%; height:100%; border:0; display:block;}
.mapa-placeholder {
  position:absolute; inset:0; display:flex; flex-direction:column;
  align-items:center; justify-content:center; color:#64748b; gap:12px;
  background:#f1f5f9;
}
.tec-card {
  display:flex; align-items:center; gap:12px;
  padding:12px 14px; border-radius:10px; cursor:pointer;
  border:2px solid transparent; transition:.15s;
  background:var(--cinza-100, #f1f5f9);
}
.tec-card:hover { background:#e8f1fc; border-color:#1d4ed8; }
.tec-card.selecionado { background:#eff6ff; border-color:#1d4ed8; }
.tec-dot { width:12px; height:12px; border-radius:50%; flex-shrink:0; }
.tec-dot.ativo   { background:#16803c; box-shadow:0 0 0 3px #dcfce7; }
.tec-dot.inativo { background:#94a3b8; }
.badge-tempo { font-size:11px; background:#f1f5f9; padding:2px 8px; border-radius:999px; color:#64748b; }
</style>

<!-- Mapa Google embed -->
<div class="card" style="padding:0; overflow:hidden;">
  <div class="mapa-wrap" id="mapa-wrap">
    <div class="mapa-placeholder" id="mapa-placeholder">
      <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/><circle cx="12" cy="9" r="2.5"/></svg>
      <span style="font-size:14px; font-weight:600; color:#64748b;">Nenhum técnico com posição registrada.</span>
      <span style="font-size:12px; color:#94a3b8;">Selecione um técnico abaixo para ver no mapa.</span>
    </div>
    <iframe id="mapa-iframe" src="" style="display:none;" loading="lazy" allowfullscreen referrerpolicy="no-referrer-when-downgrade"></iframe>
  </div>
  <div style="position:absolute; top:calc(68vh + 12px); right:16px; z-index:10; display:none;" id="btn-wrap">
    <button id="btn-refresh" class="btn btn-primario btn-sm" style="box-shadow:0 2px 8px rgba(0,0,0,.25);">&#8635; Atualizar</button>
  </div>
</div>

<div style="position:relative;margin-top:12px;">
  <button id="btn-refresh2" class="btn btn-neutro btn-sm no-print" style="float:right;">&#8635; Atualizar posições</button>
</div>

<div class="card" style="margin-top:8px;">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:14px;">
    <h3 style="margin:0;">Técnicos com posição registrada</h3>
    <span style="font-size:12px;color:#64748b;">
      Atualiza a cada 15s &bull;
      <span style="color:#16803c; font-weight:700;">●</span> Ativo = enviou nos últimos 10 min
    </span>
  </div>
  <div id="lista-tecnicos" style="font-size:13px;color:#64748b;">Carregando...</div>
</div>

<script>
let tecnicoSelecionado = null;
let posicoes = [];

function centrarMapa(lat, lng, nome) {
  const iframe = document.getElementById('mapa-iframe');
  const placeholder = document.getElementById('mapa-placeholder');
  const q = encodeURIComponent(nome + ' (' + lat + ',' + lng + ')');
  iframe.src = `https://maps.google.com/maps?q=${lat},${lng}&z=16&output=embed&hl=pt-BR&markers=${lat},${lng}`;
  iframe.style.display = 'block';
  placeholder.style.display = 'none';
}

function abrirGoogleMaps(lat, lng) {
  window.open(`https://www.google.com/maps?q=${lat},${lng}`, '_blank');
}

document.addEventListener('DOMContentLoaded', function () {

  async function atualizarPosicoes() {
    try {
      const dados = await apiGet('/gps/listar.php');
      posicoes = dados.posicoes || [];
      renderizarLista();
    } catch (e) {
      console.error('Falha ao carregar posições:', e.message);
    }
  }

  function renderizarLista() {
    const listaDiv = document.getElementById('lista-tecnicos');
    if (!posicoes.length) {
      listaDiv.innerHTML = '<div class="vazio" style="padding:30px 20px;text-align:center;">' +
        '<div style="font-size:36px;margin-bottom:8px;">📡</div>' +
        '<div style="font-weight:600;color:#64748b;">Nenhum técnico enviou posição ainda.</div>' +
        '<div style="font-size:12px;color:#94a3b8;margin-top:4px;">O app envia GPS automaticamente a cada 2 minutos quando aberto.</div>' +
        '</div>';
      return;
    }

    const agora = Date.now();
    let html = '<div style="display:flex;flex-direction:column;gap:10px;">';

    posicoes.forEach(p => {
      const dt = new Date(p.atualizado_em);
      const minAtras = Math.round((agora - dt.getTime()) / 60000);
      const ativo = minAtras < 10;
      const isSel = tecnicoSelecionado === p.tecnico_id;
      const lat = parseFloat(p.latitude);
      const lng = parseFloat(p.longitude);

      html += `
        <div class="tec-card ${isSel ? 'selecionado' : ''}"
             onclick="selecionarTecnico(${p.tecnico_id}, ${lat}, ${lng}, '${escHtml(p.tecnico_nome)}')">
          <div class="tec-dot ${ativo ? 'ativo' : 'inativo'}"></div>
          <div style="flex:1; min-width:0;">
            <div style="font-weight:700; font-size:14px; color:#1c2430;">${escHtml(p.tecnico_nome)}</div>
            <div style="font-size:12px; color:#64748b; margin-top:2px;">
              ${p.cliente_nome
                ? `<a href="/app-tecnicos/admin/os/detalhe.php?id=${p.os_id}" style="color:#1d4ed8; text-decoration:none;">OS #${p.os_id} — ${escHtml(p.cliente_nome)}</a>`
                : '<span style="color:#94a3b8;">Sem OS ativa</span>'}
            </div>
          </div>
          <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px;">
            <span class="badge-tempo">${ativo ? '● Ativo' : '○ ' + minAtras + 'min atrás'}</span>
            <span style="font-size:11px;color:#94a3b8;">${dt.toLocaleString('pt-BR')}</span>
          </div>
          <button
            onclick="event.stopPropagation(); abrirGoogleMaps(${lat}, ${lng})"
            class="btn btn-neutro btn-sm"
            title="Abrir no Google Maps"
            style="padding:5px 10px; font-size:12px; flex-shrink:0;">
            🗺
          </button>
        </div>`;
    });

    html += '</div>';
    listaDiv.innerHTML = html;

    // Se nenhum ainda selecionado, seleciona o primeiro ativo
    if (tecnicoSelecionado === null && posicoes.length) {
      const p = posicoes[0];
      selecionarTecnico(p.tecnico_id, parseFloat(p.latitude), parseFloat(p.longitude), p.tecnico_nome);
    }
  }

  atualizarPosicoes();
  setInterval(atualizarPosicoes, 15000);

  document.getElementById('btn-refresh2')?.addEventListener('click', atualizarPosicoes);
});

function selecionarTecnico(id, lat, lng, nome) {
  tecnicoSelecionado = id;
  centrarMapa(lat, lng, nome);
  // Re-renderiza para atualizar a classe "selecionado"
  document.querySelectorAll('.tec-card').forEach(el => el.classList.remove('selecionado'));
  event.currentTarget?.classList?.add('selecionado');
}

function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
