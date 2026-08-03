import AsyncStorage from '@react-native-async-storage/async-storage';
import {Usuario} from '../types';

const KEYS = {
  JWT: '@smartvig:jwt',
  USUARIO: '@smartvig:usuario',
  CRED_EMAIL: '@smartvig:cred_email',
  CRED_SENHA: '@smartvig:cred_senha',
};

export async function salvarSessao(usuario: Usuario): Promise<void> {
  await AsyncStorage.multiSet([
    [KEYS.JWT, usuario.jwt],
    [KEYS.USUARIO, JSON.stringify(usuario)],
  ]);
}

export async function obterJwt(): Promise<string | null> {
  return AsyncStorage.getItem(KEYS.JWT);
}

export async function obterUsuario(): Promise<Usuario | null> {
  const raw = await AsyncStorage.getItem(KEYS.USUARIO);
  if (!raw) {return null;}
  try {
    return JSON.parse(raw) as Usuario;
  } catch {
    return null;
  }
}

export async function limparSessao(): Promise<void> {
  await AsyncStorage.multiRemove([KEYS.JWT, KEYS.USUARIO]);
}

export async function salvarCredenciais(email: string, senha: string): Promise<void> {
  await AsyncStorage.multiSet([
    [KEYS.CRED_EMAIL, email],
    [KEYS.CRED_SENHA, senha],
  ]);
}

export async function obterCredenciais(): Promise<{email: string; senha: string} | null> {
  const [[, email], [, senha]] = await AsyncStorage.multiGet([KEYS.CRED_EMAIL, KEYS.CRED_SENHA]);
  if (!email || !senha) { return null; }
  return {email, senha};
}

export async function limparCredenciais(): Promise<void> {
  await AsyncStorage.multiRemove([KEYS.CRED_EMAIL, KEYS.CRED_SENHA]);
}
