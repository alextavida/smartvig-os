import {useState, useEffect} from 'react';
import NetInfo from '@react-native-community/netinfo';

/**
 * Retorna true quando o dispositivo tem conexão de internet ativa.
 * Usa @react-native-community/netinfo para detecção em tempo real.
 */
export function useNetworkStatus(): boolean {
  const [isOnline, setIsOnline] = useState(true);

  useEffect(() => {
    // Verificação inicial
    NetInfo.fetch().then(state => {
      setIsOnline(state.isConnected ?? true);
    });

    const unsubscribe = NetInfo.addEventListener(state => {
      setIsOnline(state.isConnected ?? true);
    });

    return () => unsubscribe();
  }, []);

  return isOnline;
}
