import React, {useState, useCallback, useRef, useEffect} from 'react';
import {
  View,
  Text,
  FlatList,
  StyleSheet,
  TouchableOpacity,
  RefreshControl,
  ActivityIndicator,
  Linking,
  Alert,
} from 'react-native';
import {listarGps} from '../api/gps';
import {GpsTecnico} from '../types';
import {CORES} from '../config';
import Icon from 'react-native-vector-icons/MaterialIcons';

const INTERVALO_MS = 30_000;

function minAtras(dt: string | null): number | null {
  if (!dt) {return null;}
  return Math.round((Date.now() - new Date(dt).getTime()) / 60000);
}

function isOnline(p: GpsTecnico): boolean {
  if (!p.latitude || !p.longitude) {return false;}
  const min = minAtras(p.atualizado_em);
  return min !== null && min < 10;
}

function etiquetaTempo(p: GpsTecnico): string {
  const min = minAtras(p.atualizado_em);
  if (!p.atualizado_em || min === null) {return 'Sem GPS';}
  if (min < 1) {return 'Agora mesmo';}
  if (min < 60) {return `Há ${min} min`;}
  const h = Math.floor(min / 60);
  return `Há ${h}h${min % 60 > 0 ? String(min % 60) + 'min' : ''}`;
}

function abrirGoogleMaps(lat: number, lng: number, nome: string) {
  const url = `https://www.google.com/maps/search/?api=1&query=${lat},${lng}`;
  Linking.canOpenURL(url).then(ok => {
    if (ok) {
      Linking.openURL(url);
    } else {
      Alert.alert('Erro', 'Não foi possível abrir o Google Maps.');
    }
  });
}

type ItemProps = {p: GpsTecnico};

function TecnicoCard({p}: ItemProps) {
  const online = isOnline(p);
  const temGps = !!(p.latitude && p.longitude);

  return (
    <View style={[estilos.card, online && estilos.cardOnline]}>
      <View style={estilos.cardTopo}>
        <View style={[estilos.statusDot, online ? estilos.dotOnline : estilos.dotOffline]} />
        <View style={{flex: 1}}>
          <Text style={estilos.nome} numberOfLines={1}>{p.tecnico_nome}</Text>
          {p.cliente_nome ? (
            <Text style={estilos.os} numberOfLines={1}>
              OS #{p.os_id} — {p.cliente_nome}
            </Text>
          ) : (
            <Text style={estilos.osDisponivel}>Disponível</Text>
          )}
        </View>
        <View style={[estilos.badge, online ? estilos.badgeOnline : estilos.badgeOffline]}>
          <Text style={[estilos.badgeText, online ? estilos.badgeTextOnline : estilos.badgeTextOffline]}>
            {online ? 'Online' : 'Offline'}
          </Text>
        </View>
      </View>

      <View style={estilos.cardRodape}>
        <Text style={estilos.tempo}>{etiquetaTempo(p)}</Text>
        {temGps && (
          <TouchableOpacity
            style={estilos.btnMapa}
            onPress={() => abrirGoogleMaps(parseFloat(String(p.latitude!)), parseFloat(String(p.longitude!)), p.tecnico_nome)}
            activeOpacity={0.75}>
            <Text style={estilos.btnMapaText}>Ver no Maps</Text>
          </TouchableOpacity>
        )}
      </View>
    </View>
  );
}

export function MapaTecnicosScreen() {
  const [tecnicos, setTecnicos] = useState<GpsTecnico[]>([]);
  const [carregando, setCarregando] = useState(true);
  const [atualizando, setAtualizando] = useState(false);
  const [erro, setErro] = useState('');
  const timer = useRef<ReturnType<typeof setInterval> | null>(null);

  const carregar = useCallback(async (silencioso = false) => {
    if (!silencioso) {setErro('');}
    try {
      const dados = await listarGps();
      setTecnicos(dados);
    } catch (e: any) {
      if (!silencioso) {setErro(e.message ?? 'Erro ao carregar posições.');}
    }
  }, []);

  useEffect(() => {
    carregar(false).finally(() => setCarregando(false));
    timer.current = setInterval(() => carregar(true), INTERVALO_MS);
    return () => {
      if (timer.current) {clearInterval(timer.current);}
    };
  }, [carregar]);

  const atualizar = async () => {
    setAtualizando(true);
    await carregar(false);
    setAtualizando(false);
  };

  const online = tecnicos.filter(p => isOnline(p)).length;
  const total  = tecnicos.length;

  return (
    <View style={estilos.container}>
      {/* Cabeçalho */}
      <View style={estilos.header}>
        <View style={{flex: 1}}>
          <Text style={estilos.titulo}>Mapa de Técnicos</Text>
          <Text style={estilos.sub}>
            {total > 0
              ? `${online} online · ${total - online} offline · ${total} em campo`
              : 'Nenhum técnico em campo'}
          </Text>
        </View>
        <View style={estilos.legenda}>
          <View style={estilos.legendaItem}>
            <View style={[estilos.statusDot, estilos.dotOnline, {marginTop: 0}]} />
            <Text style={estilos.legendaText}>GPS &lt;10 min</Text>
          </View>
          <View style={estilos.legendaItem}>
            <View style={[estilos.statusDot, estilos.dotOffline, {marginTop: 0}]} />
            <Text style={estilos.legendaText}>Offline</Text>
          </View>
        </View>
      </View>

      {carregando ? (
        <View style={estilos.centro}>
          <ActivityIndicator size="large" color={CORES.azul600} />
          <Text style={estilos.carregandoText}>Carregando posições...</Text>
        </View>
      ) : erro ? (
        <View style={estilos.centro}>
          <Text style={estilos.erroText}>{erro}</Text>
          <TouchableOpacity style={estilos.btnRetry} onPress={atualizar}>
            <Text style={estilos.btnRetryText}>Tentar novamente</Text>
          </TouchableOpacity>
        </View>
      ) : (
        <FlatList
          data={tecnicos}
          keyExtractor={item => String(item.tecnico_id)}
          contentContainerStyle={estilos.lista}
          refreshControl={
            <RefreshControl
              refreshing={atualizando}
              onRefresh={atualizar}
              colors={[CORES.azul600]}
              tintColor={CORES.azul600}
            />
          }
          renderItem={({item}) => <TecnicoCard p={item} />}
          ListEmptyComponent={
            <View style={estilos.vazio}>
              <Icon name="location-off" size={48} color={CORES.cinza300} style={{marginBottom: 12}} />
              <Text style={estilos.vazioText}>Nenhum técnico em campo.</Text>
              <Text style={estilos.vazioSub}>
                O GPS é enviado automaticamente pelo app dos técnicos.
              </Text>
            </View>
          }
        />
      )}
    </View>
  );
}

const estilos = StyleSheet.create({
  container:  {flex: 1, backgroundColor: CORES.azul50},
  header: {
    backgroundColor: CORES.azul800,
    paddingTop: 52,
    paddingBottom: 16,
    paddingHorizontal: 20,
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 12,
  },
  titulo: {color: '#fff', fontSize: 18, fontWeight: '700'},
  sub:    {color: 'rgba(255,255,255,0.7)', fontSize: 12, marginTop: 3},
  legenda: {alignItems: 'flex-end', gap: 4},
  legendaItem: {flexDirection: 'row', alignItems: 'center', gap: 5},
  legendaText: {color: 'rgba(255,255,255,0.6)', fontSize: 11},

  lista: {padding: 14, paddingBottom: 40, gap: 10},

  card: {
    backgroundColor: CORES.branco,
    borderRadius: 12,
    padding: 14,
    borderLeftWidth: 3,
    borderLeftColor: CORES.cinza300,
    shadowColor: '#10284A',
    shadowOffset: {width: 0, height: 1},
    shadowOpacity: 0.07,
    shadowRadius: 4,
    elevation: 2,
  },
  cardOnline: {borderLeftColor: '#16803c'},

  cardTopo: {flexDirection: 'row', alignItems: 'flex-start', gap: 10, marginBottom: 10},
  statusDot: {
    width: 10, height: 10, borderRadius: 5, marginTop: 3, flexShrink: 0,
  },
  dotOnline:  {backgroundColor: '#16803c'},
  dotOffline: {backgroundColor: '#94a3b8'},

  nome:          {fontSize: 14, fontWeight: '700', color: CORES.cinza900},
  os:            {fontSize: 12, color: CORES.azul700, marginTop: 2},
  osDisponivel:  {fontSize: 12, color: CORES.cinza500, marginTop: 2},

  badge: {
    borderRadius: 999, paddingHorizontal: 8, paddingVertical: 2, alignSelf: 'flex-start',
  },
  badgeOnline:     {backgroundColor: '#dcfce7'},
  badgeOffline:    {backgroundColor: '#f1f5f9', borderWidth: 1, borderColor: '#e2e8f0'},
  badgeText:       {fontSize: 10, fontWeight: '700'},
  badgeTextOnline: {color: '#16803c'},
  badgeTextOffline:{color: '#64748b'},

  cardRodape: {
    flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between',
    borderTopWidth: 1, borderTopColor: CORES.cinza100, paddingTop: 8,
  },
  tempo: {fontSize: 12, color: CORES.cinza500},

  btnMapa: {
    backgroundColor: CORES.azul100,
    paddingHorizontal: 14,
    paddingVertical: 6,
    borderRadius: 999,
  },
  btnMapaText: {fontSize: 12, fontWeight: '700', color: CORES.azul700},

  centro: {flex: 1, alignItems: 'center', justifyContent: 'center', padding: 32},
  carregandoText: {marginTop: 12, color: CORES.cinza500, fontSize: 14},
  erroText: {color: CORES.vermelho, textAlign: 'center', fontSize: 14, marginBottom: 16},
  btnRetry: {backgroundColor: CORES.azul100, paddingHorizontal: 20, paddingVertical: 10, borderRadius: 999},
  btnRetryText: {color: CORES.azul700, fontWeight: '600'},

  vazio: {alignItems: 'center', paddingTop: 60},
  vazioText: {fontSize: 15, fontWeight: '700', color: CORES.cinza700, marginBottom: 6},
  vazioSub:  {fontSize: 13, color: CORES.cinza500, textAlign: 'center', maxWidth: 260},
});
