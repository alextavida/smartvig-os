import React, {useState} from 'react';
import {
  View,
  Text,
  TextInput,
  TouchableOpacity,
  StyleSheet,
  Image,
  KeyboardAvoidingView,
  Platform,
  ScrollView,
  ActivityIndicator,
  Alert,
} from 'react-native';
import {login} from '../api/auth';
import {salvarSessao} from '../storage';
import {useAuth} from '../hooks/useAuth';
import {CORES} from '../config';

export function LoginScreen() {
  const {fazerLogin} = useAuth();
  const [email, setEmail] = useState('');
  const [senha, setSenha] = useState('');
  const [carregando, setCarregando] = useState(false);
  const [erro, setErro] = useState('');

  async function entrar() {
    if (!email.trim() || !senha.trim()) {
      setErro('Preencha e-mail e senha.');
      return;
    }

    setErro('');
    setCarregando(true);

    try {
      const usuario = await login(email.trim().toLowerCase(), senha);

      if (usuario.perfil !== 'tecnico') {
        setErro('Este app é exclusivo para técnicos. Use o painel web para gestores.');
        setCarregando(false);
        return;
      }

      await salvarSessao(usuario);
      await fazerLogin(usuario);
    } catch (e: any) {
      setErro(e.message ?? 'Falha ao conectar com o servidor.');
    } finally {
      setCarregando(false);
    }
  }

  return (
    <KeyboardAvoidingView
      style={estilos.container}
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <ScrollView
        contentContainerStyle={estilos.scroll}
        keyboardShouldPersistTaps="handled">

        {/* Logo */}
        <View style={estilos.logoBox}>
          <View style={estilos.logoBg}>
            <Text style={estilos.logoText}>SV</Text>
          </View>
          <Text style={estilos.titulo}>SmartVig</Text>
          <Text style={estilos.subtitulo}>Vigilância Inteligente 24h</Text>
          <Text style={estilos.appLabel}>Técnico · App</Text>
        </View>

        {/* Card de login */}
        <View style={estilos.card}>
          {erro ? (
            <View style={estilos.erroBox}>
              <Text style={estilos.erroText}>{erro}</Text>
            </View>
          ) : null}

          <Text style={estilos.label}>E-mail</Text>
          <TextInput
            style={estilos.input}
            value={email}
            onChangeText={setEmail}
            placeholder="seu@email.com"
            placeholderTextColor={CORES.cinza300}
            keyboardType="email-address"
            autoCapitalize="none"
            autoCorrect={false}
            returnKeyType="next"
            editable={!carregando}
          />

          <Text style={estilos.label}>Senha</Text>
          <TextInput
            style={estilos.input}
            value={senha}
            onChangeText={setSenha}
            placeholder="••••••••"
            placeholderTextColor={CORES.cinza300}
            secureTextEntry
            returnKeyType="done"
            onSubmitEditing={entrar}
            editable={!carregando}
          />

          <TouchableOpacity
            style={[estilos.botao, carregando && estilos.botaoDesabilitado]}
            onPress={entrar}
            disabled={carregando}
            activeOpacity={0.85}>
            {carregando ? (
              <ActivityIndicator color="#fff" />
            ) : (
              <Text style={estilos.botaoText}>Entrar</Text>
            )}
          </TouchableOpacity>
        </View>

        <Text style={estilos.versao}>SmartVig OS v1.0</Text>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const estilos = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: CORES.azul900,
  },
  scroll: {
    flexGrow: 1,
    justifyContent: 'center',
    paddingHorizontal: 24,
    paddingVertical: 40,
  },
  logoBox: {
    alignItems: 'center',
    marginBottom: 32,
  },
  logoBg: {
    width: 80,
    height: 80,
    borderRadius: 20,
    backgroundColor: CORES.azul600,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 12,
    shadowColor: '#000',
    shadowOffset: {width: 0, height: 4},
    shadowOpacity: 0.3,
    shadowRadius: 8,
    elevation: 6,
  },
  logoText: {
    fontSize: 28,
    fontWeight: '900',
    color: '#fff',
    letterSpacing: 1,
  },
  titulo: {
    fontSize: 26,
    fontWeight: '800',
    color: '#fff',
    letterSpacing: 0.5,
  },
  subtitulo: {
    fontSize: 13,
    color: 'rgba(255,255,255,0.7)',
    marginTop: 4,
  },
  appLabel: {
    fontSize: 12,
    color: CORES.azul500,
    marginTop: 6,
    fontWeight: '600',
    letterSpacing: 1,
    textTransform: 'uppercase',
  },
  card: {
    backgroundColor: CORES.branco,
    borderRadius: 20,
    padding: 24,
    shadowColor: '#000',
    shadowOffset: {width: 0, height: 8},
    shadowOpacity: 0.25,
    shadowRadius: 16,
    elevation: 8,
  },
  erroBox: {
    backgroundColor: CORES.vermelhoBg,
    borderRadius: 8,
    padding: 12,
    marginBottom: 16,
  },
  erroText: {
    color: CORES.vermelho,
    fontSize: 13.5,
    textAlign: 'center',
  },
  label: {
    fontSize: 13,
    fontWeight: '600',
    color: CORES.cinza700,
    marginBottom: 6,
  },
  input: {
    borderWidth: 1,
    borderColor: CORES.cinza300,
    borderRadius: 8,
    paddingHorizontal: 13,
    paddingVertical: 11,
    fontSize: 15,
    color: CORES.cinza900,
    marginBottom: 16,
    backgroundColor: CORES.branco,
  },
  botao: {
    backgroundColor: CORES.azul700,
    borderRadius: 999,
    paddingVertical: 14,
    alignItems: 'center',
    marginTop: 4,
    shadowColor: CORES.azul800,
    shadowOffset: {width: 0, height: 3},
    shadowOpacity: 0.35,
    shadowRadius: 6,
    elevation: 4,
  },
  botaoDesabilitado: {
    opacity: 0.65,
  },
  botaoText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '700',
    letterSpacing: 0.5,
  },
  versao: {
    textAlign: 'center',
    color: 'rgba(255,255,255,0.4)',
    fontSize: 12,
    marginTop: 24,
  },
});
