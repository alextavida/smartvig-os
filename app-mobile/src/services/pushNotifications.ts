/**
 * Notificações locais nativas do Android via @notifee/react-native.
 *
 * Não depende de FCM, Firebase, OneSignal ou qualquer serviço externo.
 * As notificações aparecem como mensagens do WhatsApp — canal do sistema Android.
 *
 * Fluxo:
 *  1. inicializarNotificacoes() → cria o canal Android (uma vez na montagem)
 *  2. exibirNotificacaoLocal()  → chamado pelo useNotificacoes quando chega item novo
 */

import notifee, {
  AndroidImportance,
  AndroidVisibility,
  EventType,
} from '@notifee/react-native';
import {navigationRef} from '../navigation';

const CANAL_ID = 'smartvig_os';
let _inicializado = false;

export async function inicializarNotificacoes(): Promise<void> {
  if (_inicializado) { return; }
  _inicializado = true;

  try {
    // Cria o canal de notificações Android (obrigatório no Android 8+)
    await notifee.createChannel({
      id: CANAL_ID,
      name: 'SmartVig OS',
      importance: AndroidImportance.HIGH,
      visibility: AndroidVisibility.PUBLIC,
      vibration: true,
      sound: 'default',
    });

    // Navega para a tela correta quando o usuário toca na notificação (app aberto)
    notifee.onForegroundEvent(({type, detail}) => {
      if (type === EventType.PRESS) {
        _navegarPorDados(detail.notification?.data as Record<string, string> | undefined);
      }
    });
  } catch (e) {
    console.warn('[Notif] Erro ao inicializar canal:', e);
  }
}

export async function exibirNotificacaoLocal(
  titulo: string,
  corpo: string,
  dados?: Record<string, string>,
): Promise<void> {
  try {
    await notifee.displayNotification({
      title: `<b>${titulo}</b>`,
      body: corpo,
      data: dados,
      android: {
        channelId: CANAL_ID,
        importance: AndroidImportance.HIGH,
        sound: 'default',
        pressAction: {id: 'default'},
        smallIcon: 'ic_launcher',
        showTimestamp: true,
      },
    });
  } catch (e) {
    console.warn('[Notif] Erro ao exibir notificação:', e);
  }
}

function _navegarPorDados(dados?: Record<string, string>): void {
  if (!dados || !navigationRef.isReady()) { return; }
  try {
    if (dados.tipo === 'os' && dados.os_id) {
      navigationRef.navigate('OsDetail', {osId: parseInt(dados.os_id, 10)});
    } else if (dados.tipo === 'compra' && dados.solicitacao_id) {
      navigationRef.navigate('CompraDetalhe', {id: parseInt(dados.solicitacao_id, 10)});
    }
  } catch (e) {
    console.warn('[Notif] Erro ao navegar:', e);
  }
}
