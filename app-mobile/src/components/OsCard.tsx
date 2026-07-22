import React from 'react';
import {View, Text, TouchableOpacity, StyleSheet} from 'react-native';
import {OS} from '../types';
import {CORES} from '../config';
import {StatusBadge} from './StatusBadge';
import {PriorityBadge} from './PriorityBadge';

interface Props {
  os: OS;
  onPress: () => void;
}

export function OsCard({os, onPress}: Props) {
  const dataFormatada = os.data_agendamento
    ? new Date(os.data_agendamento + 'T12:00:00').toLocaleDateString('pt-BR')
    : 'Sem data';

  return (
    <TouchableOpacity style={estilos.card} onPress={onPress} activeOpacity={0.75}>
      <View style={estilos.cabecalho}>
        <Text style={estilos.numero}>#{os.id}</Text>
        <StatusBadge status={os.situacao_local} tamanho="sm" />
      </View>

      <Text style={estilos.cliente} numberOfLines={1}>
        {os.cliente_nome ?? 'Cliente não informado'}
      </Text>

      {os.cliente_endereco ? (
        <Text style={estilos.detalhe} numberOfLines={1}>
          📍 {os.cliente_endereco}
        </Text>
      ) : null}

      <Text style={estilos.detalhe}>📅 {dataFormatada}</Text>

      <View style={estilos.rodape}>
        <PriorityBadge prioridade={os.prioridade} />
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
  numero: {
    fontWeight: '800',
    fontSize: 13,
    color: CORES.cinza500,
  },
  cliente: {
    fontWeight: '700',
    fontSize: 15,
    color: CORES.cinza900,
    marginBottom: 6,
  },
  detalhe: {
    fontSize: 13,
    color: CORES.cinza500,
    marginBottom: 4,
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
