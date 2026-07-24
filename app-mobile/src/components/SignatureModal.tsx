import React from 'react';
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

// HTML com canvas de assinatura — comunicação via postMessage
const HTML_ASSINATURA = `
<!DOCTYPE html>
<html>
<head>
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
    body { background: #f4f9fe; font-family: -apple-system, sans-serif; overflow: hidden; }
    #hint { text-align: center; font-size: 13px; color: #6b7789; padding: 8px 0 4px; letter-spacing: 0.2px; }
    #canvas-wrap { background: #fff; border-top: 1px solid #eef1f5; border-bottom: 1px solid #eef1f5; }
    canvas { display: block; cursor: crosshair; }
    .botoes { display: flex; gap: 10px; padding: 10px 14px; }
    button {
      flex: 1; padding: 13px; border: none; border-radius: 999px;
      font-size: 15px; font-weight: 700; cursor: pointer;
    }
    #btn-limpar { background: #eef1f5; color: #3c4859; }
    #btn-salvar { background: #1462b0; color: #fff; }
  </style>
</head>
<body>
<p id="hint">Assine com o dedo na área abaixo</p>
<div id="canvas-wrap">
  <canvas id="c"></canvas>
</div>
<div class="botoes">
  <button id="btn-limpar" onclick="limpar()">Limpar</button>
  <button id="btn-salvar" onclick="salvar()">Confirmar assinatura</button>
</div>
<script>
  var c = document.getElementById('c');
  var ctx = c.getContext('2d');
  var drawing = false;
  var hasDrawing = false;

  function resize() {
    var h = window.innerHeight - document.getElementById('hint').offsetHeight - 56;
    c.width  = window.innerWidth;
    c.height = h > 160 ? h : 160;
    ctx.strokeStyle = '#0b3a6f';
    ctx.lineWidth   = 2.5;
    ctx.lineCap     = 'round';
    ctx.lineJoin    = 'round';
  }
  resize();

  function pos(e) {
    var r = c.getBoundingClientRect();
    var t = e.touches ? e.touches[0] : e;
    return [t.clientX - r.left, t.clientY - r.top];
  }

  c.addEventListener('touchstart', function(e) {
    e.preventDefault();
    var p = pos(e);
    drawing = true;
    ctx.beginPath();
    ctx.moveTo(p[0], p[1]);
  }, {passive: false});

  c.addEventListener('touchmove', function(e) {
    if (!drawing) return;
    e.preventDefault();
    var p = pos(e);
    ctx.lineTo(p[0], p[1]);
    ctx.stroke();
    hasDrawing = true;
  }, {passive: false});

  c.addEventListener('touchend', function() { drawing = false; });

  // Suporte mouse (testes em web/emulador)
  c.addEventListener('mousedown', function(e) {
    drawing = true;
    var p = pos(e);
    ctx.beginPath();
    ctx.moveTo(p[0], p[1]);
  });
  c.addEventListener('mousemove', function(e) {
    if (!drawing) return;
    var p = pos(e);
    ctx.lineTo(p[0], p[1]);
    ctx.stroke();
    hasDrawing = true;
  });
  c.addEventListener('mouseup', function() { drawing = false; });

  function limpar() {
    ctx.clearRect(0, 0, c.width, c.height);
    hasDrawing = false;
  }

  function salvar() {
    if (!hasDrawing) {
      window.ReactNativeWebView && window.ReactNativeWebView.postMessage(
        JSON.stringify({tipo: 'erro', mensagem: 'Desenhe a assinatura antes de confirmar.'})
      );
      return;
    }
    var data = c.toDataURL('image/png');
    window.ReactNativeWebView && window.ReactNativeWebView.postMessage(
      JSON.stringify({tipo: 'assinatura', data: data})
    );
  }
</script>
</body>
</html>
`;

interface Props {
  visible: boolean;
  onConfirm: (base64Png: string) => void;
  onCancelar: () => void;
}

export function SignatureModal({visible, onConfirm, onCancelar}: Props) {
  function handleMessage(event: WebViewMessageEvent) {
    try {
      const msg = JSON.parse(event.nativeEvent.data);
      if (msg.tipo === 'assinatura') {
        onConfirm(msg.data as string);
      } else if (msg.tipo === 'erro') {
        Alert.alert('Atenção', msg.mensagem ?? 'Erro na assinatura.');
      }
    } catch {
      // ignora mensagens não JSON do WebView
    }
  }

  return (
    <Modal visible={visible} transparent animationType="slide" statusBarTranslucent>
      <View style={styles.overlay}>
        <View style={styles.container}>
          <View style={styles.header}>
            <Text style={styles.titulo}>✍ Assinatura do Cliente</Text>
            <TouchableOpacity onPress={onCancelar} hitSlop={{top: 12, bottom: 12, left: 12, right: 12}}>
              <Text style={styles.fechar}>✕</Text>
            </TouchableOpacity>
          </View>

          <WebView
            source={{html: HTML_ASSINATURA}}
            style={styles.webview}
            scrollEnabled={false}
            bounces={false}
            overScrollMode="never"
            showsVerticalScrollIndicator={false}
            showsHorizontalScrollIndicator={false}
            onMessage={handleMessage}
            // Necessário para iOS: permite que o canvas processe toques sem conflito de scroll
            automaticallyAdjustContentInsets={false}
            contentInset={{top: 0, left: 0, bottom: 0, right: 0}}
          />
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  overlay: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.55)',
    justifyContent: 'flex-end',
  },
  container: {
    backgroundColor: CORES.branco,
    borderTopLeftRadius: 20,
    borderTopRightRadius: 20,
    height: Platform.OS === 'ios' ? '65%' : '60%',
    overflow: 'hidden',
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: 20,
    paddingVertical: 14,
    borderBottomWidth: 1,
    borderBottomColor: CORES.cinza100,
  },
  titulo: {
    fontSize: 16,
    fontWeight: '800',
    color: CORES.azul900,
  },
  fechar: {
    fontSize: 18,
    color: CORES.cinza500,
    lineHeight: 22,
  },
  webview: {
    flex: 1,
    backgroundColor: CORES.azul50,
  },
});
