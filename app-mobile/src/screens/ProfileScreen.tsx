import React, {useState} from 'react';
import {
  View,
  Text,
  ScrollView,
  StyleSheet,
  TouchableOpacity,
  TextInput,
  Image,
  Alert,
  ActivityIndicator,
} from 'react-native';
import {launchImageLibrary, launchCamera} from 'react-native-image-picker';
import {alterarSenha} from '../api/auth';
import {uploadFotoPerfil, urlMidia} from '../api/midias';
import {useAuth} from '../hooks/useAuth';
import {CORES} from '../config';
import Icon from 'react-native-vector-icons/MaterialIcons';

const PERFIL_LABEL: Record<string, string> = {
  gestor: 'Gestor',
  supervisor: 'Supervisor',
  tecnico: 'Técnico',
};

export function ProfileScreen() {
  const {usuario, fazerLogout, atualizarUsuario} = useAuth();

  const [salvando, setSalvando] = useState(false);
  const [sucesso, setSucesso] = useState('');
  const [erro, setErro] = useState('');

  const [senhaAtual, setSenhaAtual] = useState('');
  const [novaSenha, setNovaSenha] = useState('');
  const [confirmar, setConfirmar] = useState('');

  function mostrarSucesso(msg: string) {
    setSucesso(msg);
    setErro('');
    setTimeout(() => setSucesso(''), 3500);
  }

  async function salvarFoto(fonte: 'camera' | 'galeria') {
    const fn = fonte === 'camera' ? launchCamera : launchImageLibrary;
    fn({mediaType: 'photo', quality: 0.85, includeBase64: false}, async response => {
      if (response.didCancel || !response.assets?.[0]) {return;}
      const asset = response.assets[0];
      setSalvando(true);
      setErro('');
      try {
        const caminhoRelativo = await uploadFotoPerfil({
          uri: asset.uri!,
          name: asset.fileName ?? 'foto_perfil.jpg',
          type: asset.type ?? 'image/jpeg',
        });
        await atualizarUsuario({foto_perfil: caminhoRelativo});
        mostrarSucesso('Foto de perfil atualizada!');
      } catch (e: any) {
        setErro(e.message ?? 'Erro ao enviar foto.');
      } finally {
        setSalvando(false);
      }
    });
  }

  function escolherFoto() {
    Alert.alert('Alterar foto de perfil', 'Como deseja adicionar?', [
      {text: 'Câmera', onPress: () => salvarFoto('camera')},
      {text: 'Galeria', onPress: () => salvarFoto('galeria')},
      {text: 'Cancelar', style: 'cancel'},
    ]);
  }

  async function salvarSenhaFn() {
    if (!senhaAtual.trim() || !novaSenha.trim() || !confirmar.trim()) {
      setErro('Preencha todos os campos.');
      return;
    }
    if (novaSenha !== confirmar) {
      setErro('A nova senha e a confirmação não coincidem.');
      return;
    }
    if (novaSenha.length < 6) {
      setErro('A nova senha deve ter pelo menos 6 caracteres.');
      return;
    }

    setSalvando(true);
    setErro('');
    try {
      await alterarSenha(senhaAtual, novaSenha);
      mostrarSucesso('Senha alterada com sucesso!');
      setSenhaAtual('');
      setNovaSenha('');
      setConfirmar('');
    } catch (e: any) {
      setErro(e.message ?? 'Erro ao alterar senha.');
    } finally {
      setSalvando(false);
    }
  }

  const iniciais = usuario?.nome
    ?.split(' ')
    .slice(0, 2)
    .map(p => p?.[0] ?? '')
    .join('')
    .toUpperCase() || 'U';

  const fotoUri = usuario?.foto_perfil ? urlMidia(usuario.foto_perfil) : null;
  const isGestor    = usuario?.perfil === 'gestor';
  const isSupervisor = usuario?.perfil === 'supervisor';

  return (
    <ScrollView style={estilos.container} contentContainerStyle={estilos.scroll}>

      {sucesso ? (
        <View style={estilos.alertaSucesso}>
          <Text style={estilos.alertaSucessoText}>{sucesso}</Text>
        </View>
      ) : null}
      {erro ? (
        <View style={estilos.alertaErro}>
          <Text style={estilos.alertaErroText}>{erro}</Text>
        </View>
      ) : null}

      {/* Foto e nome */}
      <View style={estilos.perfilHeader}>
        <TouchableOpacity onPress={escolherFoto} activeOpacity={0.8}>
          {fotoUri ? (
            <Image source={{uri: fotoUri}} style={estilos.avatarGrande} />
          ) : (
            <View style={[estilos.avatarGrande, isGestor ? estilos.avatarGestor : isSupervisor ? estilos.avatarSupervisor : estilos.avatarTecnico]}>
              <Text style={estilos.avatarIniciais}>{iniciais}</Text>
            </View>
          )}
          <View style={estilos.avatarCamBadge}>
            <Icon name="photo-camera" size={14} color={CORES.cinza700} />
          </View>
        </TouchableOpacity>
        <Text style={estilos.nomeUsuario}>{usuario?.nome ?? '-'}</Text>
        <Text style={estilos.emailUsuario}>{usuario?.email ?? '-'}</Text>
        <View style={[estilos.perfilBadge, isGestor && estilos.perfilBadgeGestor, isSupervisor && estilos.perfilBadgeSupervisor]}>
          <Text style={[estilos.perfilBadgeText, isGestor && estilos.perfilBadgeTextGestor, isSupervisor && estilos.perfilBadgeTextSupervisor]}>
            {PERFIL_LABEL[usuario?.perfil ?? 'tecnico']}
          </Text>
        </View>
        {salvando && (
          <ActivityIndicator size="small" color={CORES.azul600} style={{marginTop: 8}} />
        )}
      </View>

      {/* Dados do perfil */}
      <View style={estilos.card}>
        <Text style={estilos.secaoTitulo}>Informações</Text>
        <View style={estilos.infoRow}>
          <Text style={estilos.infoLabel}>Nome</Text>
          <Text style={estilos.infoValor}>{usuario?.nome ?? '-'}</Text>
        </View>
        <View style={estilos.infoRow}>
          <Text style={estilos.infoLabel}>E-mail</Text>
          <Text style={estilos.infoValor}>{usuario?.email ?? '-'}</Text>
        </View>
        <View style={estilos.infoRow}>
          <Text style={estilos.infoLabel}>Perfil</Text>
          <Text style={estilos.infoValor}>{PERFIL_LABEL[usuario?.perfil ?? 'tecnico']}</Text>
        </View>
      </View>

      {/* Alterar senha */}
      <View style={estilos.card}>
        <Text style={estilos.secaoTitulo}>Alterar senha</Text>

        <Text style={estilos.label}>Senha atual *</Text>
        <TextInput
          style={estilos.input}
          value={senhaAtual}
          onChangeText={setSenhaAtual}
          secureTextEntry
          autoCorrect={false}
          autoCapitalize="none"
          autoComplete="current-password"
          textContentType="password"
          placeholder="Senha atual"
          placeholderTextColor={CORES.cinza300}
          editable={!salvando}
        />

        <Text style={estilos.label}>Nova senha *</Text>
        <TextInput
          style={estilos.input}
          value={novaSenha}
          onChangeText={setNovaSenha}
          secureTextEntry
          autoCorrect={false}
          autoCapitalize="none"
          autoComplete="new-password"
          textContentType="newPassword"
          placeholder="Mínimo 6 caracteres"
          placeholderTextColor={CORES.cinza300}
          editable={!salvando}
        />

        <Text style={estilos.label}>Confirmar nova senha *</Text>
        <TextInput
          style={[estilos.input, {marginBottom: 16}]}
          value={confirmar}
          onChangeText={setConfirmar}
          secureTextEntry
          autoCorrect={false}
          autoCapitalize="none"
          autoComplete="new-password"
          textContentType="newPassword"
          placeholder="Repita a nova senha"
          placeholderTextColor={CORES.cinza300}
          editable={!salvando}
        />

        <TouchableOpacity
          style={[estilos.botao, salvando && {opacity: 0.6}]}
          onPress={salvarSenhaFn}
          disabled={salvando}>
          {salvando ? (
            <ActivityIndicator color="#fff" />
          ) : (
            <Text style={estilos.botaoText}>Salvar senha</Text>
          )}
        </TouchableOpacity>
      </View>

      {/* Sair */}
      <TouchableOpacity
        style={estilos.botaoSair}
        onPress={() => {
          Alert.alert('Sair', 'Deseja encerrar a sessão?', [
            {text: 'Cancelar', style: 'cancel'},
            {text: 'Sair', style: 'destructive', onPress: fazerLogout},
          ]);
        }}>
        <Text style={estilos.botaoSairText}>Sair da conta</Text>
      </TouchableOpacity>

      <View style={{height: 32}} />
    </ScrollView>
  );
}

const estilos = StyleSheet.create({
  container: {flex: 1, backgroundColor: CORES.azul50},
  scroll: {padding: 16},
  alertaSucesso: {
    backgroundColor: CORES.verdeBg, borderRadius: 8,
    padding: 12, marginBottom: 12,
  },
  alertaSucessoText: {color: CORES.verde, fontWeight: '600', fontSize: 13.5},
  alertaErro: {
    backgroundColor: CORES.vermelhoBg, borderRadius: 8,
    padding: 12, marginBottom: 12,
  },
  alertaErroText: {color: CORES.vermelho, fontWeight: '600', fontSize: 13.5},
  perfilHeader: {
    alignItems: 'center', paddingVertical: 24,
    backgroundColor: CORES.branco, borderRadius: 16, marginBottom: 12,
    shadowColor: '#10284A', shadowOffset: {width: 0, height: 2},
    shadowOpacity: 0.07, shadowRadius: 6, elevation: 2,
  },
  avatarGrande: {width: 96, height: 96, borderRadius: 48},
  avatarTecnico:    {backgroundColor: CORES.azul600, alignItems: 'center', justifyContent: 'center'},
  avatarGestor:     {backgroundColor: '#b8860b',     alignItems: 'center', justifyContent: 'center'},
  avatarSupervisor: {backgroundColor: '#7c3aed',     alignItems: 'center', justifyContent: 'center'},
  avatarIniciais: {color: '#fff', fontSize: 32, fontWeight: '800'},
  avatarCamBadge: {
    position: 'absolute', bottom: 0, right: 0,
    backgroundColor: CORES.branco, borderRadius: 999,
    width: 28, height: 28, alignItems: 'center', justifyContent: 'center',
    shadowColor: '#000', shadowOffset: {width: 0, height: 1},
    shadowOpacity: 0.15, shadowRadius: 3, elevation: 3,
  },
  nomeUsuario: {fontSize: 18, fontWeight: '800', color: CORES.cinza900, marginTop: 14},
  emailUsuario: {fontSize: 13, color: CORES.cinza500, marginTop: 4},
  perfilBadge: {
    marginTop: 8,
    backgroundColor: CORES.azul100,
    borderRadius: 999, paddingHorizontal: 12, paddingVertical: 3,
  },
  perfilBadgeGestor:     {backgroundColor: '#fdf3d9'},
  perfilBadgeSupervisor: {backgroundColor: '#ede9fe'},
  perfilBadgeText:             {color: CORES.azul700, fontSize: 12, fontWeight: '700'},
  perfilBadgeTextGestor:     {color: '#b8860b'},
  perfilBadgeTextSupervisor: {color: '#7c3aed'},
  card: {
    backgroundColor: CORES.branco, borderRadius: 14,
    padding: 16, marginBottom: 12,
    shadowColor: '#10284A', shadowOffset: {width: 0, height: 2},
    shadowOpacity: 0.07, shadowRadius: 6, elevation: 2,
  },
  secaoTitulo: {fontSize: 14, fontWeight: '800', color: CORES.cinza900, marginBottom: 14},
  infoRow: {
    flexDirection: 'row', justifyContent: 'space-between',
    paddingVertical: 10, borderBottomWidth: 1, borderColor: CORES.cinza100,
  },
  infoLabel: {fontSize: 13, color: CORES.cinza500, fontWeight: '600'},
  infoValor: {fontSize: 13, color: CORES.cinza900, fontWeight: '500', textAlign: 'right', flex: 1, paddingLeft: 16},
  label: {fontSize: 13, fontWeight: '600', color: CORES.cinza700, marginBottom: 6},
  input: {
    borderWidth: 1, borderColor: CORES.cinza300, borderRadius: 8,
    paddingHorizontal: 12, paddingVertical: 10,
    fontSize: 14, color: CORES.cinza900, marginBottom: 14,
  },
  botao: {
    backgroundColor: CORES.azul700, borderRadius: 999,
    paddingVertical: 13, alignItems: 'center',
    shadowColor: CORES.azul800, shadowOffset: {width: 0, height: 2},
    shadowOpacity: 0.25, shadowRadius: 4, elevation: 3,
  },
  botaoText: {color: '#fff', fontWeight: '700', fontSize: 14.5},
  botaoSair: {
    borderWidth: 1.5, borderColor: CORES.vermelho,
    borderRadius: 999, paddingVertical: 13,
    alignItems: 'center', marginTop: 4,
  },
  botaoSairText: {color: CORES.vermelho, fontWeight: '700', fontSize: 14.5},
});
