import React from 'react';
import {View, Text, StyleSheet} from 'react-native';
import {STATUS_CORES, STATUS_LABELS} from '../config';

interface Props {
  status: string;
  tamanho?: 'sm' | 'md';
}

export function StatusBadge({status, tamanho = 'md'}: Props) {
  const cores = STATUS_CORES[status] ?? {bg: '#eee', text: '#555'};
  const label = STATUS_LABELS[status] ?? status;
  const sm = tamanho === 'sm';

  return (
    <View style={[estilos.badge, {backgroundColor: cores.bg}, sm && estilos.sm]}>
      <View style={[estilos.dot, {backgroundColor: cores.text}]} />
      <Text style={[estilos.texto, {color: cores.text}, sm && estilos.textoSm]}>
        {label}
      </Text>
    </View>
  );
}

const estilos = StyleSheet.create({
  badge: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 999,
    alignSelf: 'flex-start',
    gap: 5,
  },
  sm: {paddingHorizontal: 8, paddingVertical: 3},
  dot: {width: 6, height: 6, borderRadius: 3},
  texto: {fontSize: 12, fontWeight: '700'},
  textoSm: {fontSize: 11},
});
