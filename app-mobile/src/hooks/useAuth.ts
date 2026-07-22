import {createContext, useContext} from 'react';
import {Usuario} from '../types';

export interface AuthContextType {
  usuario: Usuario | null;
  carregando: boolean;
  fazerLogin: (usuario: Usuario) => Promise<void>;
  fazerLogout: () => Promise<void>;
}

export const AuthContext = createContext<AuthContextType>({
  usuario: null,
  carregando: true,
  fazerLogin: async () => {},
  fazerLogout: async () => {},
});

export function useAuth(): AuthContextType {
  return useContext(AuthContext);
}
