<?php
/**
 * Mapa ao vivo de tecnicos — Google Maps embed.
 * Mostra TODOS os tecnicos com OS ativa, inclusive os sem GPS (offline).
 * Atualiza a lista a cada 15s; clicar em um tecnico exibe sua localizacao.
 */

declare(strict_types=1);

$perfisPermitidosPagina = ['gestor', 'supervisor'];
$tituloPagina = 'Mapa de Técnicos';
$paginaAtiva  = 'mapa';
require_once __DIR__ . '/../../includes/header.php';
?>

<style>
.mapa-layout { display:flex; gap:16px; align-items:flex-start; }
.mapa-wrap {
  flex:1; background:#e8edf2; border-radius:12px;
  overflow:hidden; position:relative; min-height:520px;
}
#mapa-iframe { width:100%; height:520px; border:0; display:none; }
.mapa-placeholder {
  position:absolute; inset:0; display:flex; flex-direction:column;
  align-items:center; justify-content:center; color:#64748b; gap:12px;
  background:#f1f5f9; border-radius:12px;
}
.mapa-loading {
  position:absolute; inset:0; display:none; flex-direction:column;
  align-items:center; justify-content:center; gap:10px;
  background:rgba(241,245,249,.92); border-radius:12px; z-index:10;
}
.mapa-spinner {
  width:36px; height:36px; border:3px solid #cbd3dd;
  border-top-color:#1462b0; border-radius:50%;
  animation:spin .8s linear infinite;
}
@keyframes spin { to { transform:rotate(360deg); } }

.mapa-sidebar {
  width:290px; flex-shrink:0; display:flex; flex-direction:column;
  gap:6px; overflow-y:auto; max-height:580px; padding-right:2px;
}
.tec-card {
  display:flex; align-items:flex-start; gap:10px;
  padding:11px 12px; border-radius:10px; cursor:pointer;
  border:2px solid transparent; transition:.15s;
  background:var(--cinza-100,#f1f5f9);
}
.tec-card:hover  { background:#e8f1fc; border-color:#1d4ed8; }
.tec-card.sel    { background:#eff6ff; border-color:#1d4ed8; }
.tec-card.offline-card { opacity:.75; cursor:default; }
.tec-card.offline-card:hover { background:var(--cinza-100,#f1f5f9); border-color:transparent; }
.status-dot { width:10px; height:10px; border-radius:50%; flex-shrink:0; margin-top:4px; }
.dot-online  { background:#16803c; box-shadow:0 0 0 3px #dcfce7; }
.dot-offline { background:#94a3b8; box-shadow:0 0 0 3px #e2e8f0; }
.badge-status {
  display:inline-block; font-size:10px; font-weight:700; border-radius:4px;
  padding:1px 6px; margin-top:4px; letter-spacing:.3px;
}
.badge-online  { background:#dcfce7; color:#16803c; }
.badge-offline { background:#f1f5f9; color:#64748b; border:1px solid #e2e8f0; }
.legenda-row { display:flex; align-items:center; gap:16px; font-size:12px; color:#64748b; margin-bottom:12px; flex-wrap:wrap; }
.legenda-item { display:flex; align-items:center; gap:6px; }
.legenda-dot { width:10px; height:10px; border-radius:50%; }
</style>

<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:10px;">
  <div class="legenda-row" style="margin:0;">
    <div class="legenda-item">
      <div class="legenda-dot" style="background:#16803c;box-shadow:0 0 0 3px #dcfce7;"></div>
      <span>Online — GPS enviado há menos de 10 min</span>
    </div>
    <div class="legenda-item">
      <div class="legenda-dot" style="background:#94a3b8;"></div>
      <span>Offline — OS ativa sem localização</span>
    </div>
  </div>
  <button id="btn-refresh" class="btn btn-neutro btn-sm">↻ Atualizar agora</button>
</div>

<div class="mapa-layout">
  <!-- Mapa -->
  <div class="card" style="padding:0;overflow:hidden;flex:1;">
    <div class="mapa-wrap" id="mapa-wrap">
      <!-- Placeholder inicial -->
      <div class="mapa-placeholder" id="mapa-placeholder">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5">
          <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/>
          <circle cx="12" cy="9" r="2.5"/>
        </svg>
        <span id="placeholder-msg" style="font-size:14px;font-weight:600;color:#64748b;text-align:center;max-width:220px;">
          Selecione um técnico online para ver a localização no mapa.
        </span>
      </div>
      <!-- Overlay de carregamento -->
      <div class="mapa-loading" id="mapa-loading">
        <div class="mapa-spinner"></div>
        <span style="font-size:13px;color:#64748b;font-weight:600;">Carregando mapa...</span>
      </div>
      <!-- iframe Google Maps -->
      <iframe id="mapa-iframe" src="" allowfullscreen referrerpolicy="no-referrer-when-downgrade"
              onload="onIframeLoad()"></iframe>
    </div>
  </div>

  <!-- Sidebar -->
  <div class="mapa-sidebar" id="mapa-sidebar">
    <div style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;padding:2px 4px;">
      Técnicos em campo
    </div>
    <div id="lista-tecnicos">
      <div style="font-size:13px;color:#94a3b8;padding:20px 0;text-align:center;">Carregando...</div>
    </div>
  </div>
</div>

<script>
(function () {
  let posicoes     = [];
  let tecSelecionado = null;

  function minAtras(dtStr) {
    if (!dtStr) { return null; }
    return Math.round((Date.now() - new Date(dtStr).getTime()) / 60000);
  }

  function temGpsValido(p) {
    return p.latitude !== null && p.latitude !== undefined && p.latitude !== ''
        && p.longitude !== null && p.longitude !== undefined && p.longitude !== '';
  }

  function isOnline(p) {
    const min = minAtras(p.atualizado_em);
    return temGpsValido(p) && min !== null && min < 10;
  }

  function centrarMapa(lat, lng) {
    const iframe      = document.getElementById('mapa-iframe');
    const placeholder = document.getElementById('mapa-placeholder');
    const loading     = document.getElementById('mapa-loading');

    // Mostra iframe + spinner de carregamento
    placeholder.style.display = 'none';
    loading.style.display = 'flex';
    iframe.style.display = 'block';
    iframe.src = `https://maps.google.com/maps?q=${lat},${lng}&z=16&output=embed&hl=pt-BR`;
  }

  // Chamado pelo onload do iframe
  window.onIframeLoad = function () {
    const loading = document.getElementById('mapa-loading');
    if (loading) { loading.style.display = 'none'; }
  };

  function mostrarSemLocalizacao() {
    const iframe      = document.getElementById('mapa-iframe');
    const placeholder = document.getElementById('mapa-placeholder');
    const loading     = document.getElementById('mapa-loading');
    iframe.style.display = 'none';
    iframe.src = '';
    loading.style.display = 'none';
    placeholder.style.display = 'flex';
    document.getElementById('placeholder-msg').textContent =
      'Este técnico está offline — localização não disponível.';
  }

  function selecionarTecnico(id) {
    tecSelecionado = id;
    const p = posicoes.find(x => x.tecnico_id == id);
    if (!p) { return; }
    if (temGpsValido(p)) {
      centrarMapa(parseFloat(p.latitude), parseFloat(p.longitude));
    } else {
      mostrarSemLocalizacao();
    }
    renderizarSidebar();
  }

  function renderizarSidebar() {
    const div = document.getElementById('lista-tecnicos');
    if (!posicoes.length) {
      div.innerHTML = `
        <div style="text-align:center;padding:32px 0;color:#94a3b8;">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5" style="margin-bottom:8px;">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
            <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
          </svg>
          <div style="font-size:13px;font-weight:600;">Nenhum técnico em campo.</div>
          <div style="font-size:11px;margin-top:4px;">O GPS é enviado automaticamente pelo app.</div>
        </div>`;
      return;
    }

    div.innerHTML = posicoes.map(p => {
      const online   = isOnline(p);
      const temGps   = temGpsValido(p);
      const min      = minAtras(p.atualizado_em);
      const isSel    = tecSelecionado == p.tecnico_id;
      const offlineCd = !temGps;

      let tempoTxt = '';
      if (!p.atualizado_em)               { tempoTxt = 'Sem GPS'; }
      else if (min !== null && min < 1)   { tempoTxt = 'Agora mesmo'; }
      else if (min !== null && min < 60)  { tempoTxt = `Há ${min} min`; }
      else if (min !== null && min < 1440){ tempoTxt = `Há ${Math.floor(min/60)}h`; }
      else                                { tempoTxt = new Date(p.atualizado_em).toLocaleString('pt-BR'); }

      return `
        <div class="tec-card ${isSel ? 'sel' : ''} ${offlineCd ? 'offline-card' : ''}"
             onclick="${temGps ? `selecionarTecnico(${p.tecnico_id})` : `mostrarOffline(${p.tecnico_id})`}">
          <div class="status-dot ${online ? 'dot-online' : 'dot-offline'}"></div>
          <div style="flex:1;min-width:0;">
            <div style="font-weight:700;font-size:13px;color:#1c2430;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
              ${escHtml(p.tecnico_nome)}
            </div>
            <div style="font-size:11px;color:#64748b;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
              ${p.cliente_nome
                ? `<a href="/app-tecnicos/admin/os/detalhe.php?id=${p.os_id}" style="color:#1d4ed8;text-decoration:none;" onclick="event.stopPropagation();">OS #${p.os_id} — ${escHtml(p.cliente_nome)}</a>`
                : '<span style="color:#94a3b8;">Disponível</span>'}
            </div>
            <div style="margin-top:5px;display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
              <span class="badge-status ${online ? 'badge-online' : 'badge-offline'}">${online ? 'Online' : 'Offline'}</span>
              <span style="font-size:10px;color:#94a3b8;">${escHtml(tempoTxt)}</span>
            </div>
          </div>
          ${temGps ? `
          <button onclick="event.stopPropagation();abrirGMaps(${p.latitude},${p.longitude})"
                  class="btn btn-neutro btn-sm" style="padding:4px 8px;font-size:11px;flex-shrink:0;">
            Maps
          </button>` : ''}
        </div>`;
    }).join('');
  }

  async function carregar() {
    try {
      const dados = await apiGet('/gps/listar.php');
      posicoes = dados.posicoes || [];
      renderizarSidebar();
      // Auto-seleciona o primeiro online na carga inicial
      if (tecSelecionado === null) {
        const primeiro = posicoes.find(p => isOnline(p));
        if (primeiro) { selecionarTecnico(primeiro.tecnico_id); }
      }
    } catch (e) {
      console.error('GPS:', e.message);
    }
  }

  window.selecionarTecnico = selecionarTecnico;

  window.mostrarOffline = function (id) {
    tecSelecionado = id;
    mostrarSemLocalizacao();
    renderizarSidebar();
  };

  window.abrirGMaps = function (lat, lng) {
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
