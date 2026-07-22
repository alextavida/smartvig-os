import AsyncStorage from '@react-native-async-storage/async-storage';
import {Usuario} from '../types';

const KEYS = {
  JWT: '@smartvig:jwt',
  USUARIO: '@smartvig:usuario',
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
