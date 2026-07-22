import {apiPost} from './client';
import {Usuario} from '../types';

interface LoginResposta {
  usuario_id: number;
  nome: string;
  email: string;
  perfil: 'gestor' | 'tecnico';
  foto_perfil?: string;
  token: string;
}

export async function login(
  email: string,
  senha: string,
): Promise<Usuario> {
  const dados = await apiPost<LoginResposta>('/auth/login.php', {
    email,
    senha,
  });

  return {
    usuario_id: dados.usuario_id,
    nome: dados.nome,
    email: dados.email,
    perfil: dados.perfil,
    foto_perfil: dados.foto_perfil,
    jwt: dados.token,
  };
}

export async function alterarSenha(
  senhaAtual: string,
  novaSenha: string,
): Promise<void> {
  await apiPost('/auth/alterar_senha.php', {
    senha_atual: senhaAtual,
    nova_senha: novaSenha,
  });
}
