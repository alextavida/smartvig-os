<?php
/**
 * Mapa com a ultima posicao conhecida de cada tecnico.
 * Usa Leaflet + OpenStreetMap (gratuito, sem API key) e faz polling em
 * /api/gps/listar a cada 15s para simular tempo real (sem Firebase).
 */

declare(strict_types=1);

$perfisPermitidosPagina = ['gestor'];
$tituloPagina = 'Mapa de Tecnicos';
$paginaAtiva = 'mapa';
require_once __DIR__ . '/../../includes/header.php';
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<div class="card" style="padding:0; overflow:hidden; position:relative;">
  <div id="mapa" style="width:100%; height:68vh; min-height:420px;"></div>
  <div style="position:absolute;top:10px;right:10px;z-index:1000;">
    <button id="btn-refresh-mapa" class="btn btn-primario btn-sm no-print" style="box-shadow:0 2px 8px rgba(0,0,0,.3);">
      &#8635; Atualizar
    </button>
  </div>
</div>

<div class="card">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
    <h3 style="margin:0;">Tecnicos com posicao registrada</h3>
    <span style="font-size:12px;color:#64748b;">Atualiza a cada 15s &bull; <span style="color:#16803c;">●</span> Ativo = enviou posicao nos ultimos 10 min</span>
  </div>
  <div id="listaPosicoes" style="font-size:13px;color:#64748b;">Carregando...</div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
// Aguarda api.js (carregado no footer) estar disponível antes de iniciar o mapa
window.addEventListener('DOMContentLoaded', function () {
  const mapa = L.map('mapa').setView([-15.78, -47.93], 5);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors',
    maxZoom: 19,
  }).addTo(mapa);

  const marcadores = {};
  let primeiraCarga = true;

  async function atualizarMapa() {
    try {
      const dados = await apiGet('/gps/listar.php');
      const listaDiv = document.getElementById('listaPosicoes');
      const posicoes = dados.posicoes || [];

      if (!posicoes.length) {
        listaDiv.innerHTML = '<div class="vazio" style="padding:20px;">Nenhum tecnico enviou posicao ainda.<br><small style="color:#94a3b8;">O app envia GPS automaticamente a cada 2 minutos quando aberto.</small></div>';
      } else {
        const agora = Date.now();
        listaDiv.innerHTML = '<div style="overflow-x:auto;"><table><thead><tr><th>Tecnico</th><th>OS atual</th><th>Ultima posicao</th><th>Status</th></tr></thead><tbody>' +
          posicoes.map(p => {
            const dt = new Date(p.atualizado_em);
            const minAtras = Math.round((agora - dt.getTime()) / 60000);
            const ativo = minAtras < 10;
            return `<tr>
              <td><strong>${p.tecnico_nome}</strong></td>
              <td>${p.cliente_nome ? '<a href="/app-tecnicos/admin/os/detalhe.php?id=' + p.os_id + '">#' + p.os_id + ' — ' + p.cliente_nome + '</a>' : '<span style="color:#94a3b8;">Sem OS ativa</span>'}</td>
              <td>${dt.toLocaleString('pt-BR')}</td>
              <td><span style="color:${ativo ? '#16803c' : '#94a3b8'};font-weight:700;">${ativo ? '● Ativo' : '○ ' + minAtras + 'min atrás'}</span></td>
            </tr>`;
          }).join('') +
          '</tbody></table></div>';
      }

      const idsAtuais = new Set();
      posicoes.forEach(p => {
        idsAtuais.add(p.tecnico_id);
        const latlng = [parseFloat(p.latitude), parseFloat(p.longitude)];
        const dt = new Date(p.atualizado_em);
        const minAtras = Math.round((Date.now() - dt.getTime()) / 60000);
        const corCirculo = minAtras < 10 ? '#16803c' : '#94a3b8';
        const popup = `
          <div style="min-width:160px;">
            <strong>${p.tecnico_nome}</strong><br>
            ${p.cliente_nome
              ? `<a href="/app-tecnicos/admin/os/detalhe.php?id=${p.os_id}" style="color:#1d4ed8;">OS #${p.os_id} — ${p.cliente_nome}</a>`
              : '<span style="color:#94a3b8;">Sem OS ativa</span>'}
            <br><small style="color:#64748b;">${dt.toLocaleString('pt-BR')}</small>
          </div>`;

        if (marcadores[p.tecnico_id]) {
          marcadores[p.tecnico_id].marker.setLatLng(latlng).setPopupContent(popup);
          marcadores[p.tecnico_id].circulo.setLatLng(latlng).setStyle({color: corCirculo, fillColor: corCirculo});
        } else {
          const circulo = L.circleMarker(latlng, {
            radius: 10, color: corCirculo, fillColor: corCirculo,
            fillOpacity: 0.85, weight: 2,
          }).addTo(mapa).bindPopup(popup);
          const label = L.marker(latlng, {
            icon: L.divIcon({
              className: '',
              html: `<div style="background:${corCirculo};color:#fff;border-radius:999px;padding:2px 8px;font-size:11px;font-weight:700;white-space:nowrap;box-shadow:0 2px 6px rgba(0,0,0,.3);">${p.tecnico_nome.split(' ')[0]}</div>`,
              iconAnchor: [0, -14],
            }),
          }).addTo(mapa);
          marcadores[p.tecnico_id] = {marker: label, circulo};
        }
      });

      Object.keys(marcadores).forEach(id => {
        if (!idsAtuais.has(parseInt(id, 10))) {
          mapa.removeLayer(marcadores[id].marker);
          mapa.removeLayer(marcadores[id].circulo);
          delete marcadores[id];
        }
      });

      if (primeiraCarga && posicoes.length) {
        const bounds = L.latLngBounds(posicoes.map(p => [parseFloat(p.latitude), parseFloat(p.longitude)]));
        mapa.fitBounds(bounds.pad(0.4));
        primeiraCarga = false;
      }
    } catch (e) {
      console.error('Falha ao atualizar mapa:', e.message);
    }
  }

  atualizarMapa();
  setInterval(atualizarMapa, 15000); // atualiza a cada 15s

  // Botão de forçar atualização
  document.getElementById('btn-refresh-mapa')?.addEventListener('click', atualizarMapa);
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
