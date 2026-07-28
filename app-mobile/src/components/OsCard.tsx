import React from 'react';
import {View, Text, TouchableOpacity, StyleSheet} from 'react-native';
import Icon from 'react-native-vector-icons/MaterialIcons';
import {OS} from '../types';
import {CORES} from '../config';
import {StatusBadge} from './StatusBadge';
import {PriorityBadge} from './PriorityBadge';

interface Props {
  os: OS;
  onPress: () => void;
  mostrarTecnico?: boolean;
}

export function OsCard({os, onPress, mostrarTecnico = false}: Props) {
  const dataFormatada = os.data_agendamento
    ? new Date(os.data_agendamento + 'T12:00:00').toLocaleDateString('pt-BR')
    : 'Sem data';

  return (
    <TouchableOpacity style={estilos.card} onPress={onPress} activeOpacity={0.75}>
      <View style={estilos.cabecalho}>
        <View style={estilos.cabecalhoEsq}>
          <Text style={estilos.numero}>#{os.id}</Text>
          {os.codigo ? (
            <Text style={estilos.codigo}>{os.codigo}</Text>
          ) : null}
        </View>
        <StatusBadge status={os.situacao_local} tamanho="sm" />
      </View>

      <Text style={estilos.cliente} numberOfLines={1}>
        {os.cliente_nome ?? 'Cliente não informado'}
      </Text>

      {os.cliente_endereco ? (
        <View style={estilos.detalheRow}>
          <Icon name="location-on" size={13} color={CORES.cinza500} />
          <Text style={[estilos.detalhe, {flex: 1, marginBottom: 0}]} numberOfLines={1}>{os.cliente_endereco}</Text>
        </View>
      ) : null}

      <View style={estilos.detalheRow}>
        <Icon name="calendar-today" size={13} color={CORES.cinza500} />
        <Text style={[estilos.detalhe, {marginBottom: 0}]}>{dataFormatada}</Text>
      </View>

      {mostrarTecnico && (
        <View style={[estilos.detalheRow, {marginTop: 2}]}>
          <Icon name="person" size={13} color={CORES.azul700} />
          <Text style={[estilos.tecnico, {marginBottom: 0}]} numberOfLines={1}>{os.tecnico_responsavel_nome ?? 'Sem técnico atribuído'}</Text>
        </View>
      )}

      <View style={estilos.rodape}>
        {os.prioridade ? (
          <PriorityBadge prioridade={os.prioridade} />
        ) : (
          <View />
        )}
        {os.situacao_local === 'em_andamento' && (
          <View style={estilos.ativo}>
            <View style={estilos.pulseDot} />
            <Text style={estilos.ativoText}>Em campo</Text>
          </View>
        )}
      </View>
    </TouchableOpacity>
  );
}

const estilos = StyleSheet.create({
  card: {
    backgroundColor: CORES.branco,
    borderRadius: 14,
    padding: 16,
    marginBottom: 12,
    shadowColor: '#10284A',
    shadowOffset: {width: 0, height: 2},
    shadowOpacity: 0.08,
    shadowRadius: 8,
    elevation: 3,
  },
  cabecalho: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 8,
  },
  cabecalhoEsq: {flexDirection: 'row', alignItems: 'center', gap: 8},
  numero: {
    fontWeight: '800',
    fontSize: 13,
    color: CORES.cinza500,
  },
  codigo: {
    fontSize: 12,
    color: CORES.azul700,
    fontWeight: '600',
    backgroundColor: CORES.azul100,
    paddingHorizontal: 6,
    paddingVertical: 2,
    borderRadius: 4,
  },
  cliente: {
    fontWeight: '700',
    fontSize: 15,
    color: CORES.cinza900,
    marginBottom: 6,
  },
  detalheRow: {
    flexDirection: 'row', alignItems: 'flex-start', gap: 5, marginBottom: 4,
  },
  detalhe: {
    fontSize: 13,
    color: CORES.cinza500,
    marginBottom: 4,
  },
  tecnico: {
    fontSize: 12.5,
    color: CORES.azul700,
    fontWeight: '600',
    marginBottom: 4,
    marginTop: 2,
  },
  rodape: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginTop: 10,
  },
  ativo: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 5,
  },
  pulseDot: {
    width: 8,
    height: 8,
    borderRadius: 4,
    backgroundColor: '#1e8e5a',
  },
  ativoText: {
    fontSize: 12,
    color: '#1e8e5a',
    fontWeight: '600',
  },
});
