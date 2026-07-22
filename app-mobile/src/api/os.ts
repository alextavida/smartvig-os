import {apiGet, apiPost} from './client';
import {OS, OSDetalhe} from '../types';

interface ListarOsParams {
  status?: string;
  pagina?: number;
}

interface ListarOsResposta {
  os: OS[];
  paginacao: {pagina: number; total: number; paginas: number};
}

export async function listarOs(params: ListarOsParams = {}): Promise<OS[]> {
  const qs = new URLSearchParams();
  if (params.status) {qs.set('status', params.status);}
  if (params.pagina) {qs.set('pagina', String(params.pagina));}
  const resultado = await apiGet<ListarOsResposta>(
    `/os/listar.php?${qs.toString()}`,
  );
  return resultado.os ?? [];
}

export async function visualizarOs(id: number): Promise<OSDetalhe> {
  return apiGet<OSDetalhe>(`/os/visualizar.php?id=${id}`);
}

export async function iniciarOs(osId: number): Promise<void> {
  await apiPost('/os/iniciar.php', {os_id: osId});
}

export async function pausarOs(osId: number, motivo: string): Promise<void> {
  await apiPost('/os/pausar.php', {os_id: osId, motivo});
}

export async function reagendarOs(
  osId: number,
  novaData: string,
  motivo?: string,
): Promise<void> {
  await apiPost('/os/reagendar.php', {os_id: osId, nova_data: novaData, motivo});
}

export async function encerrarOs(
  osId: number,
  laudoFinal: string,
): Promise<void> {
  await apiPost('/os/encerrar.php', {os_id: osId, laudo_final: laudoFinal});
}

export async function salvarDescricao(
  osId: number,
  observacoes: string,
): Promise<void> {
  await apiPost('/os/descricao.php', {os_id: osId, observacoes});
}

export async function adicionarProduto(
  osId: number,
  produto: {produto_id?: number; nome: string; quantidade: number; valor_venda: number},
): Promise<void> {
  await apiPost('/produtos/adicionar_os.php', {os_id: osId, ...produto});
}
