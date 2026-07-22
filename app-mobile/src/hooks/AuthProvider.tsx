import React, {useState, useEffect, ReactNode} from 'react';
import {obterUsuario, limparSessao, salvarSessao} from '../storage';
import {Usuario} from '../types';
import {AuthContext} from './useAuth';

export function AuthProvider({children}: {children: ReactNode}) {
  const [usuario, setUsuario] = useState<Usuario | null>(null);
  const [carregando, setCarregando] = useState(true);

  useEffect(() => {
    obterUsuario()
      .then(u => setUsuario(u))
      .finally(() => setCarregando(false));
  }, []);

  async function fazerLogin(u: Usuario) {
    await salvarSessao(u);
    setUsuario(u);
  }

  async function fazerLogout() {
    await limparSessao();
    setUsuario(null);
  }

  return (
    <AuthContext.Provider value={{usuario, carregando, fazerLogin, fazerLogout}}>
      {children}
    </AuthContext.Provider>
  );
}
