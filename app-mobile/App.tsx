import React from 'react';
import {StatusBar} from 'react-native';
import {SafeAreaProvider} from 'react-native-safe-area-context';
import {AuthProvider} from './src/hooks/AuthProvider';
import {Navigation} from './src/navigation';
import {CORES} from './src/config';

export default function App() {
  return (
    <SafeAreaProvider>
      <StatusBar
        barStyle="light-content"
        backgroundColor={CORES.azul900}
        translucent={false}
      />
      <AuthProvider>
        <Navigation />
      </AuthProvider>
    </SafeAreaProvider>
  );
}
