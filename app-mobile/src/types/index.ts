export interface Usuario {
  usuario_id: number;
  nome: string;
  email: string;
  perfil: 'gestor' | 'supervisor' | 'tecnico';
  foto_perfil?: string;
  jwt: string;
  roles?: string[];
}

export type RoleCompras = 'solicitante' | 'comprador' | 'aprovador';

export function temRoleCompras(usuario: Usuario, ...roles: RoleCompras[]): boolean {
  if (usuario.perfil === 'gestor' || usuario.perfil === 'supervisor') return true;
  const all = usuario.roles ?? [];
  return roles.some(r => all.includes(r));
}

export function temAcessoCompras(usuario: Usuario): boolean {
  return temRoleCompras(usuario, 'solicitante', 'comprador', 'aprovador');
}

export interface OS {
  id: number;
  gc_os_id: number;
  gc_cliente_id?: number | null;
  codigo?: string;
  cliente_nome: string;
  cliente_endereco: string;
  cliente_telefone: string;
  situacao_local: string;
  prioridade?: 'baixo' | 'intermediario' | 'urgente';
  data_agendamento: string | null;
  data_conclusao: string | null;
  observacoes: string | null;
  motivo_pausa: string | null;
  nps_token?: string | null;
  portal_token?: string | null;
  produtos_json: string | null;
  tecnico_id?: number | null;
  tecnico_responsavel_nome?: string | null;
  latitude_atual: string | null;
  longitude_atual: string | null;
  criado_em: string;
  atualizado_em?: string;
}

export interface OSTecnico {
  id: number;
  nome: string;
  foto_perfil: string | null;
  responsavel: boolean;
}

export interface Midia {
  id: number;
  tipo: 'foto' | 'video';
  caminho_arquivo: string;
  nome_arquivo: string;
  url: string;
  criado_em: string;
}

export interface Historico {
  acao: string;
  detalhe: string | null;
  criado_em: string;
  usuario_nome: string | null;
}

export interface GCProduto {
  nome: string;
  quantidade: number;
  valor_venda: number;
}

export interface GCServico {
  nome: string;
  quantidade: number;
  valor: number;
}

export interface GCEquipamento {
  tipo: string | null;
  marca: string | null;
  modelo: string | null;
  serie: string | null;
  defeitos: string | null;
  solucao: string | null;
  laudo: string | null;
}

export interface OSDetalhe extends OS {
  tecnicos: OSTecnico[];
  historico: Historico[];
  midias: Midia[];
  produtos: Produto[];
  gc_produtos: GCProduto[];
  gc_servicos: GCServico[];
  gc_equipamentos: GCEquipamento[];
}

export interface Produto {
  produto_id?: number;
  nome: string;
  quantidade: number;
  valor_venda: number;
}

export interface Notificacao {
  id: number;
  os_id: number | null;
  tipo: string;
  titulo: string;
  mensagem: string;
  lida: boolean;
  criado_em: string;
}

export interface TecnicoLista {
  id: number;
  nome: string;
  email: string;
  telefone: string | null;
  ativo: boolean | number;
  os_ativas: number;
  foto_perfil?: string | null;
}

export interface ClienteGC {
  id: number | null;
  nome: string;
  telefone: string;
  email: string;
  endereco: string;
}

export interface ProdutoGC {
  id: number;
  nome: string;
  valor_venda: number;
}

export interface SolicitacaoCompra {
  id: number;
  numero: string;
  solicitante_nome: string;
  prioridade: 'baixa' | 'media' | 'alta' | 'urgente';
  destino: string;
  destino_referencia: string | null;
  categoria_nome: string | null;
  justificativa: string;
  valor_estimado: number | null;
  valor_negociado: number | null;
  valor_final: number | null;
  status: 'rascunho' | 'aguardando_aprovacao' | 'aprovado' | 'reprovado' | 'devolvido' | 'em_compra' | 'recebido' | 'concluido' | 'cancelado';
  criado_em: string;
  atualizado_em: string;
}

export interface SolicitacaoCompraDetalhe extends SolicitacaoCompra {
  aprovador_nome: string | null;
  aprovado_em: string | null;
  motivo_reprovacao: string | null;
  comprador_nome: string | null;
  fornecedor_nome: string | null;
  valor_frete: number | null;
  prazo_entrega: string | null;
  numero_pedido: string | null;
  data_compra: string | null;
  nota_fiscal_numero: string | null;
  observacoes: string | null;
  observacoes_compra: string | null;
  itens: SolicitacaoItem[];
  historico: SolicitacaoHistorico[];
}

export interface SolicitacaoItem {
  id: number;
  descricao: string;
  quantidade: number;
  unidade: string | null;
  valor_unitario_estimado: number | null;
  valor_unitario_final: number | null;
  quantidade_recebida: number | null;
}

export interface SolicitacaoHistorico {
  id: number;
  usuario_nome: string | null;
  acao: string;
  detalhe: string | null;
  criado_em: string;
}

export interface GpsTecnico {
  tecnico_id: number;
  tecnico_nome: string;
  os_id: number | null;
  cliente_nome: string | null;
  latitude: string | null;
  longitude: string | null;
  atualizado_em: string | null;
  status_os: 'em_andamento' | 'disponivel';
}
