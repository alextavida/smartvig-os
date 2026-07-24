import {apiGet, apiPost} from './client';
import {OS, OSDetalhe, ProdutoGC} from '../types';

interface ListarOsParams {
  status?: string;
  pagina?: number;
  tecnico_id?: number;
}

interface ListarOsResposta {
  ordens_servico: OS[];
  paginacao: {pagina: number; total: number; total_paginas: number};
}

export async function listarOs(params: ListarOsParams = {}): Promise<OS[]> {
  // Hermes (React Native) não implementa URLSearchParams.set — construção manual
  const partes: string[] = [];
  if (params.status)     { partes.push(`status=${encodeURIComponent(params.status)}`); }
  if (params.pagina)     { partes.push(`pagina=${params.pagina}`); }
  if (params.tecnico_id) { partes.push(`tecnico_id=${params.tecnico_id}`); }
  const qs = partes.length ? '?' + partes.join('&') : '';
  const resultado = await apiGet<ListarOsResposta>(`/os/listar.php${qs}`);
  return resultado.ordens_servico ?? [];
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

export interface CriarOsDados {
  cliente_nome: string;
  cliente_endereco?: string;
  cliente_telefone?: string;
  gc_cliente_id?: number | null;
  data_agendamento: string;
  observacoes?: string;
  prioridade: 'baixo' | 'intermediario' | 'urgente';
  tecnicos: {tecnico_id: number; responsavel: boolean}[];
}

export async function criarOs(dados: CriarOsDados): Promise<{os_id: number; gc_os_id: number; sincronizado_gc: boolean}> {
  return apiPost('/os/criar.php', dados);
}

export async function atribuirTecnicos(
  osId: number,
  tecnicos: {tecnico_id: number; responsavel: boolean}[],
): Promise<void> {
  await apiPost('/os/atribuir_tecnicos.php', {os_id: osId, tecnicos});
}

export async function buscarProdutosGC(busca: string): Promise<ProdutoGC[]> {
  const qs = busca ? `?busca=${encodeURIComponent(busca)}` : '';
  const resultado = await apiGet<{produtos: any[]}>(`/produtos/listar.php${qs}`);
  return (resultado.produtos ?? []).slice(0, 20).map((p: any) => ({
    id: p.id ?? 0,
    nome: p.nome ?? p.descricao ?? '',
    valor_venda: parseFloat(p.preco_venda ?? p.valor_venda ?? p.preco ?? '0') || 0,
  }));
}
