<?php
/**
 * Mapa ao vivo de tecnicos — Leaflet.js + OpenStreetMap (sem API key).
 * Atualiza marcadores a cada 15s sem recarregar a pagina.
 */

declare(strict_types=1);

$perfisPermitidosPagina = ['gestor', 'supervisor'];
$tituloPagina = 'Mapa de Técnicos';
$paginaAtiva  = 'mapa';
require_once __DIR__ . '/../../includes/header.php';
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<style>
.mapa-layout { display:flex; gap:16px; height:72vh; min-height:450px; }
#mapa-container { flex:1; border-radius:12px; overflow:hidden; z-index:0; }
.mapa-sidebar {
  width:300px; flex-shrink:0; display:flex; flex-direction:column; gap:8px;
  overflow-y:auto;
}
.tec-card {
  display:flex; align-items:flex-start; gap:10px;
  padding:11px 12px; border-radius:10px; cursor:pointer;
  border:2px solid transparent; transition:.15s;
  background:var(--cinza-100, #f1f5f9);
}
.tec-card:hover { background:#e8f1fc; border-color:#1d4ed8; }
.tec-card.selecionado { background:#eff6ff; border-color:#1d4ed8; }
.tec-dot { width:11px; height:11px; border-radius:50%; flex-shrink:0; margin-top:3px; }
.tec-dot.ativo   { background:#16803c; box-shadow:0 0 0 3px #dcfce7; }
.tec-dot.inativo { background:#94a3b8; }
.badge-tempo {
  display:inline-block; font-size:10px; background:#f1f5f9;
  padding:2px 7px; border-radius:999px; color:#64748b;
}
.mapa-vazio {
  flex:1; border-radius:12px; background:#f1f5f9;
  display:flex; flex-direction:column; align-items:center; justify-content:center;
  color:#94a3b8; gap:10px;
}
</style>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;flex-wrap:wrap;gap:8px;">
  <div style="font-size:13px;color:#64748b;">
    <span style="color:#16803c;font-weight:700;">●</span> Ativo = enviou GPS há menos de 10 min &nbsp;|&nbsp;
    Atualiza automaticamente a cada 15 s
  </div>
  <button id="btn-refresh" class="btn btn-neutro btn-sm">↻ Atualizar agora</button>
</div>

<div class="card" style="padding:0; overflow:visible; background:transparent; box-shadow:none;">
  <div class="mapa-layout">
    <!-- Mapa -->
    <div id="mapa-container"></div>

    <!-- Sidebar -->
    <div class="mapa-sidebar" id="mapa-sidebar">
      <div style="font-size:12px;font-weight:700;color:#94a3b8;text-transform:uppercase;padding:4px 2px;">
        Técnicos em campo
      </div>
      <div id="lista-tecnicos">
        <div style="color:#94a3b8;font-size:13px;padding:16px 0;text-align:center;">Carregando...</div>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  // Inicializa mapa centrado no Brasil
  const mapa = L.map('mapa-container', {zoomControl: true}).setView([-15.78, -47.93], 5);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© <a href="https://openstreetmap.org">OpenStreetMap</a>',
    maxZoom: 19,
  }).addTo(mapa);

  // Ícones personalizados
  function criarIcone(cor) {
    return L.divIcon({
      className: '',
      html: `<div style="
        width:28px;height:28px;border-radius:50% 50% 50% 0;
        background:${cor};border:3px solid #fff;
        box-shadow:0 2px 6px rgba(0,0,0,.35);
        transform:rotate(-45deg);
      "></div>`,
      iconSize: [28, 28],
      iconAnchor: [14, 28],
      popupAnchor: [0, -30],
    });
  }

  const iconeAtivo   = criarIcone('#16803c');
  const iconeInativo = criarIcone('#94a3b8');

  const marcadores = {}; // tecnico_id → L.marker
  let posicoes = [];
  let tecSel = null;

  function minAtras(dtStr) {
    return Math.round((Date.now() - new Date(dtStr).getTime()) / 60000);
  }

  function popupHtml(p) {
    const min = minAtras(p.atualizado_em);
    const ativo = min < 10;
    return `
      <div style="min-width:200px;font-family:system-ui,sans-serif;">
        <div style="font-weight:700;font-size:14px;margin-bottom:4px;">${escHtml(p.tecnico_nome)}</div>
        <div style="font-size:12px;color:#475569;margin-bottom:8px;">
          ${p.cliente_nome
            ? `<a href="/app-tecnicos/admin/os/detalhe.php?id=${p.os_id}" style="color:#1d4ed8;text-decoration:none;">OS #${p.os_id} — ${escHtml(p.cliente_nome)}</a>`
            : '<span style="color:#94a3b8;">Sem OS ativa</span>'}
        </div>
        <div style="font-size:11px;color:${ativo ? '#16803c' : '#94a3b8'};">
          ${ativo ? '● Ativo agora' : `○ ${min} min atrás`}
        </div>
        <a href="https://www.google.com/maps?q=${p.latitude},${p.longitude}" target="_blank"
           style="display:inline-block;margin-top:8px;font-size:11px;color:#1d4ed8;">
          Ver no Google Maps ↗
        </a>
      </div>`;
  }

  function atualizarMarcadores() {
    posicoes.forEach(p => {
      const lat = parseFloat(p.latitude);
      const lng = parseFloat(p.longitude);
      const min = minAtras(p.atualizado_em);
      const ativo = min < 10;
      const icone = ativo ? iconeAtivo : iconeInativo;

      if (marcadores[p.tecnico_id]) {
        marcadores[p.tecnico_id].setLatLng([lat, lng]).setIcon(icone);
        marcadores[p.tecnico_id].getPopup()?.setContent(popupHtml(p));
      } else {
        const m = L.marker([lat, lng], {icon: icone})
          .addTo(mapa)
          .bindPopup(popupHtml(p));
        marcadores[p.tecnico_id] = m;
      }
    });

    // Remove marcadores de técnicos que saíram
    const ids = posicoes.map(p => String(p.tecnico_id));
    Object.keys(marcadores).forEach(id => {
      if (!ids.includes(id)) { mapa.removeLayer(marcadores[id]); delete marcadores[id]; }
    });
  }

  function renderizarSidebar() {
    const div = document.getElementById('lista-tecnicos');
    if (!posicoes.length) {
      div.innerHTML = `
        <div style="text-align:center;padding:32px 0;color:#94a3b8;">
          <div style="font-size:32px;margin-bottom:8px;">📡</div>
          <div style="font-size:13px;font-weight:600;">Nenhum técnico enviou GPS.</div>
          <div style="font-size:11px;margin-top:4px;">O app envia a cada 2 minutos quando aberto.</div>
        </div>`;
      return;
    }

    div.innerHTML = posicoes.map(p => {
      const min = minAtras(p.atualizado_em);
      const ativo = min < 10;
      const sel = tecSel === p.tecnico_id;
      return `
        <div class="tec-card ${sel ? 'selecionado' : ''}"
             onclick="selecionarTecnico(${p.tecnico_id},${p.latitude},${p.longitude})">
          <div class="tec-dot ${ativo ? 'ativo' : 'inativo'}"></div>
          <div style="flex:1;min-width:0;">
            <div style="font-weight:700;font-size:13px;color:#1c2430;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
              ${escHtml(p.tecnico_nome)}
            </div>
            <div style="font-size:11px;color:#64748b;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
              ${p.cliente_nome ? `OS #${p.os_id} — ${escHtml(p.cliente_nome)}` : 'Sem OS ativa'}
            </div>
            <div style="margin-top:4px;">
              <span class="badge-tempo">${ativo ? '● Ativo' : `${min}min atrás`}</span>
            </div>
          </div>
          <button onclick="event.stopPropagation();abrirGMaps(${p.latitude},${p.longitude})"
                  class="btn btn-neutro btn-sm" title="Google Maps"
                  style="padding:4px 8px;font-size:12px;flex-shrink:0;">🗺</button>
        </div>`;
    }).join('');
  }

  async function carregar() {
    try {
      const dados = await apiGet('/gps/listar.php');
      posicoes = dados.posicoes || [];
      atualizarMarcadores();
      renderizarSidebar();
      // Abre popup do primeiro ativo automaticamente na primeira carga
      if (tecSel === null && posicoes.length) {
        const p = posicoes[0];
        selecionarTecnico(p.tecnico_id, p.latitude, p.longitude);
      }
    } catch (e) {
      console.error('GPS:', e.message);
    }
  }

  window.selecionarTecnico = function(id, lat, lng) {
    tecSel = id;
    mapa.setView([parseFloat(lat), parseFloat(lng)], 15, {animate: true});
    if (marcadores[id]) marcadores[id].openPopup();
    renderizarSidebar();
  };

  window.abrirGMaps = function(lat, lng) {
    window.open(`https://www.google.com/maps?q=${lat},${lng}`, '_blank');
  };

  function escHtml(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  carregar();
  setInterval(carregar, 15000);
  document.getElementById('btn-refresh').addEventListener('click', carregar);
})();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
