import React, {useState, useEffect} from 'react';
import {
  View,
  Text,
  TextInput,
  TouchableOpacity,
  StyleSheet,
  KeyboardAvoidingView,
  Platform,
  ScrollView,
  ActivityIndicator,
  Image,
  Switch,
} from 'react-native';
import {login} from '../api/auth';
import {salvarSessao, salvarCredenciais, obterCredenciais, limparCredenciais} from '../storage';
import {useAuth} from '../hooks/useAuth';
import {CORES} from '../config';

const LOGO = require('../assets/logo.png');

export function LoginScreen() {
  const {fazerLogin} = useAuth();
  const [email, setEmail]         = useState('');
  const [senha, setSenha]         = useState('');
  const [lembrar, setLembrar]     = useState(false);
  const [carregando, setCarregando] = useState(false);
  const [erro, setErro]           = useState('');

  // Carrega credenciais salvas ao abrir a tela
  useEffect(() => {
    (async () => {
      const cred = await obterCredenciais();
      if (cred) {
        setEmail(cred.email);
        setSenha(cred.senha);
        setLembrar(true);
      }
    })();
  }, []);

  async function entrar() {
    if (!email.trim() || !senha.trim()) {
      setErro('Preencha e-mail e senha.');
      return;
    }

    setErro('');
    setCarregando(true);

    try {
      const usuario = await login(email.trim().toLowerCase(), senha);
      if (lembrar) {
        await salvarCredenciais(email.trim().toLowerCase(), senha);
      } else {
        await limparCredenciais();
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
          <View style={estilos.logoCirculo}>
            <Image
              source={LOGO}
              style={estilos.logoImagem}
              resizeMode="contain"
            />
          </View>
          <Text style={estilos.titulo}>SmartVig</Text>
          <Text style={estilos.subtitulo}>Vigilância Inteligente 24h</Text>
          <View style={estilos.badgeRow}>
            <View style={estilos.badge}>
              <Text style={estilos.badgeText}>● SISTEMA OPERACIONAL</Text>
            </View>
          </View>
        </View>

        {/* Card de login */}
        <View style={estilos.card}>
          <Text style={estilos.cardTitulo}>Acesse sua conta</Text>

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

          {/* Toggle Salvar login */}
          <TouchableOpacity
            style={estilos.lembrarRow}
            onPress={() => setLembrar(v => !v)}
            activeOpacity={0.7}
          >
            <Switch
              value={lembrar}
              onValueChange={setLembrar}
              trackColor={{false: CORES.cinza300, true: CORES.azul500}}
              thumbColor={lembrar ? CORES.azul700 : '#fff'}
              ios_backgroundColor={CORES.cinza300}
            />
            <Text style={estilos.lembrarText}>Salvar login</Text>
          </TouchableOpacity>

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

        <Text style={estilos.versao}>SmartVig OS v2.0</Text>
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
    paddingHorizontal: 28,
    paddingVertical: 48,
  },
  logoBox: {
    alignItems: 'center',
    marginBottom: 36,
  },
  logoCirculo: {
    width: 110,
    height: 110,
    borderRadius: 55,
    backgroundColor: '#ffffff18',
    borderWidth: 2,
    borderColor: '#ffffff30',
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 16,
    shadowColor: '#000',
    shadowOffset: {width: 0, height: 6},
    shadowOpacity: 0.4,
    shadowRadius: 12,
    elevation: 10,
  },
  logoImagem: {
    width: 86,
    height: 86,
    borderRadius: 43,
  },
  titulo: {
    fontSize: 30,
    fontWeight: '800',
    color: '#fff',
    letterSpacing: 0.5,
  },
  subtitulo: {
    fontSize: 13,
    color: 'rgba(255,255,255,0.6)',
    marginTop: 4,
  },
  badgeRow: {
    marginTop: 10,
  },
  badge: {
    backgroundColor: 'rgba(255,255,255,0.1)',
    borderRadius: 999,
    paddingHorizontal: 12,
    paddingVertical: 4,
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.15)',
  },
  badgeText: {
    fontSize: 10,
    color: CORES.azul500,
    fontWeight: '700',
    letterSpacing: 1.5,
  },
  card: {
    backgroundColor: CORES.branco,
    borderRadius: 20,
    padding: 24,
    shadowColor: '#000',
    shadowOffset: {width: 0, height: 10},
    shadowOpacity: 0.3,
    shadowRadius: 20,
    elevation: 10,
  },
  cardTitulo: {
    fontSize: 17,
    fontWeight: '700',
    color: CORES.cinza900,
    marginBottom: 20,
    textAlign: 'center',
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
    borderWidth: 1.5,
    borderColor: CORES.cinza300,
    borderRadius: 10,
    paddingHorizontal: 14,
    paddingVertical: 12,
    fontSize: 15,
    color: CORES.cinza900,
    marginBottom: 16,
    backgroundColor: CORES.cinza100,
  },
  lembrarRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    marginBottom: 16,
    paddingVertical: 4,
  },
  lembrarText: {
    fontSize: 14,
    color: CORES.cinza700,
    fontWeight: '500',
  },
  botao: {
    backgroundColor: CORES.azul700,
    borderRadius: 999,
    paddingVertical: 15,
    alignItems: 'center',
    marginTop: 4,
    shadowColor: CORES.azul800,
    shadowOffset: {width: 0, height: 4},
    shadowOpacity: 0.4,
    shadowRadius: 8,
    elevation: 5,
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
    color: 'rgba(255,255,255,0.3)',
    fontSize: 11,
    marginTop: 28,
    letterSpacing: 0.5,
  },
});
