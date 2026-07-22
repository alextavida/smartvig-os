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

<div class="card" style="padding:0; overflow:hidden;">
  <div id="mapa" style="width:100%; height:70vh;"></div>
</div>

<div class="card">
  <h3 style="margin-top:0;">Tecnicos com posicao registrada</h3>
  <div id="listaPosicoes" class="vazio">Carregando...</div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const mapa = L.map('mapa').setView([-14.235, -51.9253], 4);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  attribution: '&copy; OpenStreetMap contributors',
  maxZoom: 19,
}).addTo(mapa);

let marcadores = {};
let primeiraCarga = true;

async function atualizarMapa() {
  try {
    const dados = await apiGet('/gps/listar.php');
    const listaDiv = document.getElementById('listaPosicoes');

    if (!dados.posicoes.length) {
      listaDiv.innerHTML = '<div class="vazio">Nenhum tecnico enviou posicao ainda.</div>';
    } else {
      listaDiv.innerHTML = '<table><thead><tr><th>Tecnico</th><th>OS atual</th><th>Ultima atualizacao</th></tr></thead><tbody>' +
        dados.posicoes.map(p => `<tr><td>${p.tecnico_nome}</td><td>${p.cliente_nome ? '#' + p.os_id + ' - ' + p.cliente_nome : '-'}</td><td>${new Date(p.atualizado_em).toLocaleString('pt-BR')}</td></tr>`).join('') +
        '</tbody></table>';
    }

    const idsAtuais = new Set();
    dados.posicoes.forEach(p => {
      idsAtuais.add(p.tecnico_id);
      const latlng = [parseFloat(p.latitude), parseFloat(p.longitude)];
      const popup = `<strong>${p.tecnico_nome}</strong><br>${p.cliente_nome ? 'OS #' + p.os_id + ' - ' + p.cliente_nome : 'Sem OS ativa'}<br><small>${new Date(p.atualizado_em).toLocaleString('pt-BR')}</small>`;

      if (marcadores[p.tecnico_id]) {
        marcadores[p.tecnico_id].setLatLng(latlng).setPopupContent(popup);
      } else {
        marcadores[p.tecnico_id] = L.marker(latlng).addTo(mapa).bindPopup(popup);
      }
    });

    Object.keys(marcadores).forEach(id => {
      if (!idsAtuais.has(parseInt(id, 10))) {
        mapa.removeLayer(marcadores[id]);
        delete marcadores[id];
      }
    });

    if (primeiraCarga && dados.posicoes.length) {
      const grupo = L.featureGroup(Object.values(marcadores));
      mapa.fitBounds(grupo.getBounds().pad(0.3));
      primeiraCarga = false;
    }
  } catch (e) {
    console.error('Falha ao atualizar mapa:', e.message);
  }
}

atualizarMapa();
setInterval(atualizarMapa, 15000);
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
