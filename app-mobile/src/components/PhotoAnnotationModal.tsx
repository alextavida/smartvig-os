/**
 * Modal de anotações sobre foto.
 * Recebe a imagem como base64, permite ao técnico desenhar setas/texto,
 * e retorna o resultado final como base64 PNG.
 */
import React, {useRef} from 'react';
import {
  Modal,
  View,
  Text,
  TouchableOpacity,
  StyleSheet,
  Alert,
  Platform,
} from 'react-native';
import {WebView, WebViewMessageEvent} from 'react-native-webview';
import {CORES} from '../config';

const criarHtml = (imagemBase64: string) => `
<!DOCTYPE html>
<html>
<head>
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <style>
    * { margin:0; padding:0; box-sizing:border-box; -webkit-tap-highlight-color:transparent; }
    body { background:#111; overflow:hidden; }
    #wrap { position:relative; width:100vw; height:100vh; display:flex; align-items:center; justify-content:center; }
    canvas { position:absolute; top:0; left:0; cursor:crosshair; }
    #toolbar {
      position:fixed; bottom:0; left:0; right:0;
      background:rgba(0,0,0,.85); padding:10px 14px;
      display:flex; gap:8px; align-items:center; flex-wrap:wrap; z-index:10;
    }
    button {
      padding:8px 14px; border:none; border-radius:999px;
      font-size:13px; font-weight:700; cursor:pointer;
    }
    .btn-cor { width:30px; height:30px; border-radius:50%; border:3px solid transparent; }
    .btn-cor.ativo { border-color:#fff; }
    .btn-acao { background:#333; color:#fff; }
    .btn-salvar { background:#1462b0; color:#fff; flex:1; }
    #btn-desfazer { background:#555; color:#fff; }
    input[type=range] { width:70px; accent-color:#1462b0; }
    .lbl { color:#aaa; font-size:11px; }
  </style>
</head>
<body>
<div id="wrap">
  <canvas id="bg"></canvas>
  <canvas id="draw"></canvas>
</div>

<div id="toolbar">
  <button class="btn-cor ativo" style="background:red;" onclick="setCor('red',this)" title="Vermelho"></button>
  <button class="btn-cor" style="background:#fff;" onclick="setCor('white',this)" title="Branco"></button>
  <button class="btn-cor" style="background:#00e676;" onclick="setCor('#00e676',this)" title="Verde"></button>
  <button class="btn-cor" style="background:#ffeb3b;" onclick="setCor('#ffeb3b',this)" title="Amarelo"></button>
  <span class="lbl">Esp:</span>
  <input type="range" id="espessura" min="2" max="20" value="4">
  <button id="btn-desfazer" onclick="desfazer()">↩</button>
  <button class="btn-acao" onclick="adicionarSeta()">➜ Seta</button>
  <button class="btn-salvar" onclick="salvar()">✓ Usar foto</button>
</div>

<script>
var bg   = document.getElementById('bg');
var draw = document.getElementById('draw');
var bgCtx  = bg.getContext('2d');
var ctx    = draw.getContext('2d');

var corAtual = 'red';
var desenhando = false;
var historico = [];
var modoSeta  = false;
var setaInicio = null;

function setup() {
  var dpr = window.devicePixelRatio || 1;
  var W = window.innerWidth, H = window.innerHeight - 56;
  [bg, draw].forEach(function(c) {
    c.width  = W * dpr; c.height = H * dpr;
    c.style.width = W + 'px'; c.style.height = H + 'px';
    c.getContext('2d').scale(dpr, dpr);
  });
  var img = new Image();
  img.onload = function() {
    var ratio = Math.min(W / img.width, H / img.height);
    var sw = img.width * ratio, sh = img.height * ratio;
    bgCtx.drawImage(img, (W - sw) / 2, (H - sh) / 2, sw, sh);
    salvarHistorico();
  };
  img.src = '${imagemBase64}';
}

function salvarHistorico() {
  historico.push(ctx.getImageData(0, 0, draw.width, draw.height));
  if (historico.length > 20) { historico.shift(); }
}

function desfazer() {
  if (historico.length > 1) { historico.pop(); ctx.putImageData(historico[historico.length-1], 0, 0); }
}

function setCor(c, btn) {
  corAtual = c;
  document.querySelectorAll('.btn-cor').forEach(function(b) { b.classList.remove('ativo'); });
  btn.classList.add('ativo');
}

function adicionarSeta() {
  modoSeta = !modoSeta;
  document.querySelector('[onclick="adicionarSeta()"]').textContent = modoSeta ? '✏ Livre' : '➜ Seta';
}

function getEspessura() { return parseInt(document.getElementById('espessura').value, 10); }

function pos(e) {
  var r = draw.getBoundingClientRect();
  var t = e.touches ? e.touches[0] : e;
  return [t.clientX - r.left, t.clientY - r.top];
}

draw.addEventListener('touchstart', function(e) {
  e.preventDefault();
  var p = pos(e);
  if (modoSeta) { setaInicio = p; return; }
  desenhando = true;
  ctx.beginPath(); ctx.moveTo(p[0], p[1]);
  ctx.strokeStyle = corAtual; ctx.lineWidth = getEspessura(); ctx.lineCap = 'round'; ctx.lineJoin = 'round';
}, {passive:false});

draw.addEventListener('touchmove', function(e) {
  if (!desenhando) return;
  e.preventDefault();
  var p = pos(e);
  ctx.lineTo(p[0], p[1]); ctx.stroke();
}, {passive:false});

draw.addEventListener('touchend', function(e) {
  if (modoSeta && setaInicio) {
    var t = e.changedTouches[0];
    var r = draw.getBoundingClientRect();
    var fim = [t.clientX - r.left, t.clientY - r.top];
    desenharSeta(setaInicio, fim);
    setaInicio = null;
  }
  if (desenhando) { desenhando = false; salvarHistorico(); }
});

function desenharSeta(de, para) {
  var esp = getEspessura();
  var ang = Math.atan2(para[1]-de[1], para[0]-de[0]);
  var tam = esp * 4;
  ctx.strokeStyle = corAtual; ctx.fillStyle = corAtual;
  ctx.lineWidth = esp; ctx.lineCap = 'round';
  ctx.beginPath(); ctx.moveTo(de[0], de[1]); ctx.lineTo(para[0], para[1]); ctx.stroke();
  ctx.beginPath();
  ctx.moveTo(para[0], para[1]);
  ctx.lineTo(para[0] - tam*Math.cos(ang-Math.PI/6), para[1] - tam*Math.sin(ang-Math.PI/6));
  ctx.lineTo(para[0] - tam*Math.cos(ang+Math.PI/6), para[1] - tam*Math.sin(ang+Math.PI/6));
  ctx.closePath(); ctx.fill();
  salvarHistorico();
}

function salvar() {
  var W = bg.width, H = bg.height;
  var merged = document.createElement('canvas');
  merged.width = W; merged.height = H;
  var mCtx = merged.getContext('2d');
  mCtx.drawImage(bg, 0, 0);
  mCtx.drawImage(draw, 0, 0);
  var data = merged.toDataURL('image/jpeg', 0.88);
  window.ReactNativeWebView && window.ReactNativeWebView.postMessage(JSON.stringify({tipo:'foto', data:data}));
}

window.addEventListener('load', setup);
</script>
</body>
</html>
`;

interface Props {
  visible: boolean;
  imagemBase64: string;
  onConfirm: (base64: string) => void;
  onCancelar: () => void;
}

export function PhotoAnnotationModal({visible, imagemBase64, onConfirm, onCancelar}: Props) {
  function handleMessage(e: WebViewMessageEvent) {
    try {
      const msg = JSON.parse(e.nativeEvent.data);
      if (msg.tipo === 'foto') { onConfirm(msg.data as string); }
    } catch {}
  }

  if (!imagemBase64) { return null; }

  return (
    <Modal visible={visible} transparent={false} animationType="slide" statusBarTranslucent>
      <View style={s.container}>
        <View style={s.header}>
          <Text style={s.titulo}>✏ Adicionar anotações</Text>
          <TouchableOpacity onPress={onCancelar} hitSlop={{top:12,bottom:12,left:12,right:12}}>
            <Text style={s.fechar}>✕ Cancelar</Text>
          </TouchableOpacity>
        </View>
        <WebView
          source={{html: criarHtml(imagemBase64)}}
          style={s.webview}
          scrollEnabled={false}
          bounces={false}
          overScrollMode="never"
          onMessage={handleMessage}
          automaticallyAdjustContentInsets={false}
        />
      </View>
    </Modal>
  );
}

const s = StyleSheet.create({
  container: {flex: 1, backgroundColor: '#111'},
  header: {
    flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center',
    paddingHorizontal: 16, paddingVertical: 12,
    backgroundColor: '#0b3a6f',
  },
  titulo: {color: '#fff', fontWeight: '800', fontSize: 15},
  fechar: {color: '#93c5fd', fontWeight: '600', fontSize: 13},
  webview: {flex: 1},
});
