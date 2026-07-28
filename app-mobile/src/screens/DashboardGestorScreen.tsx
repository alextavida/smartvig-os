import React, {useState, useEffect, useCallback} from 'react';
import {
  View,
  Text,
  ScrollView,
  StyleSheet,
  TouchableOpacity,
  RefreshControl,
  ActivityIndicator,
} from 'react-native';
import {useNavigation} from '@react-navigation/native';
import {apiGet} from '../api/client';
import {CORES} from '../config';
import Icon from 'react-native-vector-icons/MaterialIcons';

interface Resumo {
  os: {
    total: number; aberto: number; em_andamento: number;
    pausado: number; reagendado: number; concluido: number; cancelado: number;
  };
  sla: {atrasadas: number; criticas: number; atencao: number; no_prazo: number};
  tecnicos: {total: number; online: number};
}

export function DashboardGestorScreen() {
  const nav = useNavigation<any>();
  const [resumo, setResumo]       = useState<Resumo | null>(null);
  const [carregando, setCarregando] = useState(true);
  const [atualizando, setAtualizando] = useState(false);
  const [erro, setErro]           = useState('');

  const carregar = useCallback(async (silencioso = false) => {
    if (!silencioso) { setErro(''); }
    try {
      const dados = await apiGet<Resumo>('/os/resumo.php');
      setResumo(dados);
    } catch (e: any) {
      if (!silencioso) { setErro(e.message ?? 'Erro ao carregar dashboard.'); }
    }
  }, []);

  useEffect(() => {
    carregar(false).finally(() => setCarregando(false));
  }, [carregar]);

  const atualizar = async () => {
    setAtualizando(true);
    await carregar(true);
    setAtualizando(false);
  };

  if (carregando) {
    return (
      <View style={estilos.centro}>
        <ActivityIndicator size="large" color={CORES.azul600} />
        <Text style={estilos.subTexto}>Carregando dashboard...</Text>
      </View>
    );
  }

  const sla = resumo?.sla;
  const os  = resumo?.os;
  const tec = resumo?.tecnicos;
  const alertaSla = (sla?.atrasadas ?? 0) + (sla?.criticas ?? 0);

  return (
    <ScrollView
      style={estilos.container}
      contentContainerStyle={estilos.scroll}
      refreshControl={
        <RefreshControl refreshing={atualizando} onRefresh={atualizar}
          colors={[CORES.azul600]} tintColor={CORES.azul600} />
      }>

      {/* Header */}
      <View style={estilos.header}>
        <View style={{flex: 1}}>
          <Text style={estilos.headerTitulo}>Dashboard</Text>
          <Text style={estilos.headerSub}>Visão geral do sistema</Text>
        </View>
        {alertaSla > 0 && (
          <View style={estilos.alertaBadge}>
            <Icon name="warning" size={14} color="#fff" />
            <Text style={estilos.alertaBadgeText}>{alertaSla} SLA</Text>
          </View>
        )}
      </View>

      {erro ? (
        <View style={estilos.erroBox}>
          <Text style={estilos.erroText}>{erro}</Text>
        </View>
      ) : null}

      {/* Técnicos em campo */}
      <TouchableOpacity style={estilos.tecnicosBanner} onPress={() => nav.navigate('MapaTecnicos')} activeOpacity={0.85}>
        <View style={estilos.tecnicosInfo}>
          <View style={estilos.tecnicosDot} />
          <Text style={estilos.tecnicosTitulo}>
            {tec?.online ?? 0} técnico{tec?.online !== 1 ? 's' : ''} online agora
          </Text>
        </View>
        <Text style={estilos.tecnicosTotal}>{tec?.total ?? 0} no total</Text>
        <Icon name="chevron-right" size={20} color="rgba(255,255,255,0.7)" />
      </TouchableOpacity>

      {/* OS por status */}
      <Text style={estilos.secaoTitulo}>Ordens de Serviço</Text>
      <View style={estilos.statsGrid}>
        <StatCard
          valor={os?.aberto ?? 0}
          rotulo="Abertas"
          cor="#1462b0"
          bg="#e8f1fc"
          icone="assignment"
          onPress={() => nav.navigate('Home')}
        />
        <StatCard
          valor={os?.em_andamento ?? 0}
          rotulo="Em andamento"
          cor="#b8860b"
          bg="#fdf3d9"
          icone="build"
          onPress={() => nav.navigate('Home')}
        />
        <StatCard
          valor={os?.pausado ?? 0}
          rotulo="Pausadas"
          cor="#6b7789"
          bg="#eef1f5"
          icone="pause-circle"
          onPress={() => nav.navigate('Home')}
        />
        <StatCard
          valor={os?.reagendado ?? 0}
          rotulo="Reagendadas"
          cor="#c8641a"
          bg="#fbe7d6"
          icone="event"
          onPress={() => nav.navigate('Home')}
        />
        <StatCard
          valor={os?.concluido ?? 0}
          rotulo="Concluídas"
          cor="#1e8e5a"
          bg="#e5f5ec"
          icone="check-circle"
        />
        <StatCard
          valor={os?.cancelado ?? 0}
          rotulo="Canceladas"
          cor="#c62f2f"
          bg="#fbe4e4"
          icone="cancel"
        />
      </View>

      {/* SLA */}
      <Text style={estilos.secaoTitulo}>Painel SLA</Text>
      <View style={estilos.slaGrid}>
        <SlaCard valor={sla?.atrasadas ?? 0} rotulo="Atrasadas" cor="#dc2626" bg="#fef2f2" icone="error" />
        <SlaCard valor={sla?.criticas ?? 0}  rotulo="Críticas (≤1d)" cor="#ea580c" bg="#fff7ed" icone="warning" />
        <SlaCard valor={sla?.atencao ?? 0}   rotulo="Atenção (2-3d)" cor="#ca8a04" bg="#fefce8" icone="schedule" />
        <SlaCard valor={sla?.no_prazo ?? 0}  rotulo="No prazo" cor="#16803c" bg="#f0fdf4" icone="check" />
      </View>

      {/* Ações rápidas */}
      <Text style={estilos.secaoTitulo}>Ações rápidas</Text>
      <View style={estilos.acoesGrid}>
        <TouchableOpacity style={estilos.acaoBotao} onPress={() => nav.navigate('CreateOs')} activeOpacity={0.8}>
          <Icon name="add-circle" size={28} color={CORES.azul700} />
          <Text style={estilos.acaoTexto}>Nova OS</Text>
        </TouchableOpacity>
        <TouchableOpacity style={estilos.acaoBotao} onPress={() => nav.navigate('MapaTecnicos')} activeOpacity={0.8}>
          <Icon name="location-on" size={28} color="#16803c" />
          <Text style={estilos.acaoTexto}>Mapa</Text>
        </TouchableOpacity>
        <TouchableOpacity style={estilos.acaoBotao} onPress={() => nav.navigate('Home')} activeOpacity={0.8}>
          <Icon name="list" size={28} color={CORES.cinza700} />
          <Text style={estilos.acaoTexto}>Todas as OS</Text>
        </TouchableOpacity>
      </View>
    </ScrollView>
  );
}

function StatCard({valor, rotulo, cor, bg, icone, onPress}: {
  valor: number; rotulo: string; cor: string; bg: string; icone: string; onPress?: () => void;
}) {
  const content = (
    <View style={[estilos.statCard, {borderLeftColor: cor}]}>
      <View style={[estilos.statIconBox, {backgroundColor: bg}]}>
        <Icon name={icone} size={18} color={cor} />
      </View>
      <View style={{flex: 1}}>
        <Text style={[estilos.statValor, {color: cor}]}>{valor}</Text>
        <Text style={estilos.statRotulo}>{rotulo}</Text>
      </View>
    </View>
  );
  if (onPress) { return <TouchableOpacity onPress={onPress} activeOpacity={0.8}>{content}</TouchableOpacity>; }
  return content;
}

function SlaCard({valor, rotulo, cor, bg, icone}: {
  valor: number; rotulo: string; cor: string; bg: string; icone: string;
}) {
  return (
    <View style={[estilos.slaCard, {backgroundColor: bg, borderColor: cor + '40'}]}>
      <Icon name={icone} size={20} color={cor} />
      <Text style={[estilos.slaValor, {color: cor}]}>{valor}</Text>
      <Text style={[estilos.slaRotulo, {color: cor}]}>{rotulo}</Text>
    </View>
  );
}

const estilos = StyleSheet.create({
  container: {flex: 1, backgroundColor: CORES.azul50},
  scroll:    {paddingBottom: 40},
  centro:    {flex: 1, alignItems: 'center', justifyContent: 'center', gap: 12},
  subTexto:  {color: CORES.cinza500, fontSize: 14},

  header: {
    backgroundColor: CORES.azul800,
    paddingTop: 52,
    paddingBottom: 20,
    paddingHorizontal: 20,
    flexDirection: 'row',
    alignItems: 'center',
  },
  headerTitulo: {color: '#fff', fontSize: 20, fontWeight: '800'},
  headerSub:    {color: 'rgba(255,255,255,0.65)', fontSize: 13, marginTop: 2},
  alertaBadge: {
    backgroundColor: '#dc2626', borderRadius: 999,
    flexDirection: 'row', alignItems: 'center', gap: 4,
    paddingHorizontal: 10, paddingVertical: 4,
  },
  alertaBadgeText: {color: '#fff', fontSize: 12, fontWeight: '700'},

  erroBox: {margin: 16, padding: 12, backgroundColor: CORES.vermelhoBg, borderRadius: 8},
  erroText: {color: CORES.vermelho, fontSize: 13},

  tecnicosBanner: {
    backgroundColor: '#16803c',
    marginHorizontal: 16, marginTop: 16,
    borderRadius: 12, padding: 16,
    flexDirection: 'row', alignItems: 'center', gap: 10,
  },
  tecnicosInfo:  {flex: 1, flexDirection: 'row', alignItems: 'center', gap: 8},
  tecnicosDot:   {width: 10, height: 10, borderRadius: 5, backgroundColor: '#4ade80'},
  tecnicosTitulo:{color: '#fff', fontSize: 14, fontWeight: '700'},
  tecnicosTotal: {color: 'rgba(255,255,255,0.75)', fontSize: 12},

  secaoTitulo: {
    marginHorizontal: 16, marginTop: 20, marginBottom: 10,
    fontSize: 13, fontWeight: '700', color: CORES.cinza700,
    textTransform: 'uppercase', letterSpacing: 0.5,
  },

  statsGrid: {marginHorizontal: 16, gap: 8},
  statCard: {
    backgroundColor: CORES.branco, borderRadius: 10, padding: 14,
    flexDirection: 'row', alignItems: 'center', gap: 12,
    borderLeftWidth: 3,
    shadowColor: '#10284A', shadowOffset: {width: 0, height: 1},
    shadowOpacity: 0.06, shadowRadius: 3, elevation: 2,
  },
  statIconBox: {
    width: 36, height: 36, borderRadius: 8,
    alignItems: 'center', justifyContent: 'center', flexShrink: 0,
  },
  statValor:  {fontSize: 22, fontWeight: '800', lineHeight: 26},
  statRotulo: {fontSize: 11, color: CORES.cinza500, fontWeight: '600', marginTop: 1},

  slaGrid: {
    marginHorizontal: 16,
    flexDirection: 'row', flexWrap: 'wrap', gap: 8,
  },
  slaCard: {
    flex: 1, minWidth: '45%',
    borderRadius: 10, padding: 14, borderWidth: 1,
    alignItems: 'center', gap: 4,
  },
  slaValor:  {fontSize: 26, fontWeight: '800'},
  slaRotulo: {fontSize: 11, fontWeight: '600', textAlign: 'center'},

  acoesGrid: {
    marginHorizontal: 16,
    flexDirection: 'row', gap: 10,
  },
  acaoBotao: {
    flex: 1, backgroundColor: CORES.branco, borderRadius: 12,
    paddingVertical: 16, alignItems: 'center', gap: 6,
    shadowColor: '#10284A', shadowOffset: {width: 0, height: 1},
    shadowOpacity: 0.06, shadowRadius: 3, elevation: 2,
  },
  acaoTexto: {fontSize: 12, fontWeight: '600', color: CORES.cinza700, textAlign: 'center'},
});
