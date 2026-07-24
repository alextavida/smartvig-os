import {apiPost} from './client';
import {Usuario} from '../types';

interface LoginResposta {
  token: string;
  usuario: {
    id: number;
    nome: string;
    email: string;
    perfil: 'gestor' | 'tecnico';
    foto_perfil?: string;
  };
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
    usuario_id: dados.usuario.id,
    nome: dados.usuario.nome,
    email: dados.usuario.email,
    perfil: dados.usuario.perfil,
    foto_perfil: dados.usuario.foto_perfil,
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
