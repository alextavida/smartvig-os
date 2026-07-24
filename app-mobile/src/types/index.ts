export interface Usuario {
  usuario_id: number;
  nome: string;
  email: string;
  perfil: 'gestor' | 'tecnico';
  foto_perfil?: string;
  jwt: string;
}

export interface OS {
  id: number;
  gc_os_id: number;
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

export interface OSDetalhe extends OS {
  tecnicos: OSTecnico[];
  historico: Historico[];
  midias: Midia[];
  produtos: Produto[];
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
