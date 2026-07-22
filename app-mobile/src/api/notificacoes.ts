import {apiGet, apiPost} from './client';
import {Notificacao} from '../types';

interface NotificacoesResposta {
  notificacoes: Notificacao[];
  total_nao_lidas: number;
}

export async function listarNotificacoes(
  apenasNaoLidas = false,
): Promise<NotificacoesResposta> {
  const qs = apenasNaoLidas ? '?nao_lidas=1' : '';
  return apiGet<NotificacoesResposta>(`/notificacoes/listar.php${qs}`);
}

export async function marcarLida(id?: number): Promise<void> {
  await apiPost('/notificacoes/marcar_lida.php', id ? {id} : {todas: true});
}
