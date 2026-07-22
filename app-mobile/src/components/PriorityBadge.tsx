import React from 'react';
import {View, Text, StyleSheet} from 'react-native';
import {PRIORIDADE_CORES} from '../config';

const LABELS: Record<string, string> = {
  baixo: 'Baixo',
  intermediario: 'Intermediário',
  urgente: 'URGENTE',
};

interface Props {
  prioridade: string;
}

export function PriorityBadge({prioridade}: Props) {
  const cores = PRIORIDADE_CORES[prioridade] ?? {bg: '#eee', text: '#555'};

  return (
    <View style={[estilos.badge, {backgroundColor: cores.bg, borderColor: cores.text}]}>
      <Text style={[estilos.texto, {color: cores.text}]}>
        ⚑ {LABELS[prioridade] ?? prioridade}
      </Text>
    </View>
  );
}

const estilos = StyleSheet.create({
  badge: {
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 999,
    borderWidth: 1,
    alignSelf: 'flex-start',
  },
  texto: {fontSize: 11, fontWeight: '700'},
});
