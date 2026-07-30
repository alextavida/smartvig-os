import React, {useState, useEffect, ReactNode} from 'react';
import {DeviceEventEmitter} from 'react-native';
import {obterUsuario, obterJwt, limparSessao, salvarSessao} from '../storage';
import {Usuario} from '../types';
import {AuthContext} from './useAuth';
import {inicializarNotificacoes} from '../services/pushNotifications';

function jwtExpirou(jwt: string): boolean {
  try {
    const parte = jwt.split('.')[1];
    const base64 = parte.replace(/-/g, '+').replace(/_/g, '/');
    const pad = (4 - (base64.length % 4)) % 4;
    const payload = JSON.parse(atob(base64 + '='.repeat(pad)));
    return Math.floor(Date.now() / 1000) >= (payload.exp ?? 0);
  } catch {
    return true;
  }
}

export function AuthProvider({children}: {children: ReactNode}) {
  const [usuario, setUsuario] = useState<Usuario | null>(null);
  const [carregando, setCarregando] = useState(true);

  // Cria o canal de notificações Android uma vez na montagem
  useEffect(() => {
    inicializarNotificacoes();
  }, []);

  // Carrega sessão salva; descarta se JWT já expirou
  useEffect(() => {
    (async () => {
      const u = await obterUsuario();
      if (u) {
        const jwt = await obterJwt();
        if (jwt && jwtExpirou(jwt)) {
          await limparSessao();
        } else {
          setUsuario(u);
        }
      }
    })().finally(() => setCarregando(false));
  }, []);

  // Quando qualquer chamada API recebe 401, limpa estado imediatamente
  useEffect(() => {
    const sub = DeviceEventEmitter.addListener('smartvig:sessao_expirada', () => {
      setUsuario(null);
    });
    return () => sub.remove();
  }, []);

  async function fazerLogin(u: Usuario) {
    await salvarSessao(u);
    setUsuario(u);
  }

  async function fazerLogout() {
    await limparSessao();
    setUsuario(null);
  }

  async function atualizarUsuario(parcial: Partial<Usuario>) {
    if (!usuario) { return; }
    const atualizado = {...usuario, ...parcial};
    await salvarSessao(atualizado);
    setUsuario(atualizado);
  }

  return (
    <AuthContext.Provider value={{usuario, carregando, fazerLogin, fazerLogout, atualizarUsuario}}>
      {children}
    </AuthContext.Provider>
  );
}
