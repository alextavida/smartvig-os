import React, {useState, useEffect, useCallback} from 'react';
import {
  View,
  Text,
  TextInput,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  ActivityIndicator,
  Alert,
  FlatList,
} from 'react-native';
import DateTimePicker from '@react-native-community/datetimepicker';
import {NativeStackScreenProps} from '@react-navigation/native-stack';
import {criarOs} from '../api/os';
import {buscarClientes} from '../api/clientes';
import {listarTecnicos} from '../api/tecnicos';
import {ClienteGC, TecnicoLista} from '../types';
import {CORES, PRIORIDADE_CORES} from '../config';
import {RootStackParamList} from '../navigation';

type Props = NativeStackScreenProps<RootStackParamList, 'CreateOs'>;

type Prioridade = 'baixo' | 'intermediario' | 'urgente';

const PRIORIDADES: {key: Prioridade; label: string}[] = [
  {key: 'baixo', label: 'Baixo'},
  {key: 'intermediario', label: 'Intermediário'},
  {key: 'urgente', label: 'Urgente'},
];

export function CreateOsScreen({navigation}: Props) {
  const [salvando, setSalvando] = useState(false);
  const [erro, setErro] = useState('');

  // Cliente
  const [clienteBusca, setClienteBusca] = useState('');
  const [clienteSugestoes, setClienteSugestoes] = useState<ClienteGC[]>([]);
  const [clienteSelecionado, setClienteSelecionado] = useState<ClienteGC | null>(null);
  const [buscandoCliente, setBuscandoCliente] = useState(false);
  const [clienteManual, setClienteManual] = useState('');
  const [enderecoManual, setEnderecoManual] = useState('');
  const [telefoneManual, setTelefoneManual] = useState('');

  // Técnicos
  const [tecnicos, setTecnicos] = useState<TecnicoLista[]>([]);
  const [tecnicosSelecionados, setTecnicosSelecionados] = useState<number[]>([]);
  const [tecnicoResponsavel, setTecnicoResponsavel] = useState<number | null>(null);
  const [carregandoTecnicos, setCarregandoTecnicos] = useState(true);

  // Agendamento e prioridade
  const [data, setData] = useState(new Date());
  const [mostrarDatePicker, setMostrarDatePicker] = useState(false);
  const [prioridade, setPrioridade] = useState<Prioridade>('baixo');
  const [observacoes, setObservacoes] = useState('');

  useEffect(() => {
    listarTecnicos()
      .then(setTecnicos)
      .catch(() => {})
      .finally(() => setCarregandoTecnicos(false));
  }, []);

  // Busca clientes no GestãoClick com debounce
  useEffect(() => {
    if (clienteSelecionado) {return;}
    if (clienteBusca.length < 2) {setClienteSugestoes([]); return;}

    const timer = setTimeout(async () => {
      setBuscandoCliente(true);
      try {
        const lista = await buscarClientes(clienteBusca);
        setClienteSugestoes(lista);
      } catch {
        setClienteSugestoes([]);
      } finally {
        setBuscandoCliente(false);
      }
    }, 400);

    return () => clearTimeout(timer);
  }, [clienteBusca, clienteSelecionado]);

  function selecionarCliente(c: ClienteGC) {
    setClienteSelecionado(c);
    setClienteBusca(c.nome);
    setClienteManual(c.nome);
    setEnderecoManual(c.endereco);
    setTelefoneManual(c.telefone);
    setClienteSugestoes([]);
  }

  function limparCliente() {
    setClienteSelecionado(null);
    setClienteBusca('');
    setClienteManual('');
    setEnderecoManual('');
    setTelefoneManual('');
  }

  function toggleTecnico(id: number) {
    setTecnicosSelecionados(prev => {
      if (prev.includes(id)) {
        const novo = prev.filter(t => t !== id);
        if (tecnicoResponsavel === id) {
          setTecnicoResponsavel(novo[0] ?? null);
        }
        return novo;
      }
      const novo = [...prev, id];
      if (!tecnicoResponsavel) {setTecnicoResponsavel(id);}
      return novo;
    });
  }

  async function criar() {
    const nomeCliente = clienteSelecionado ? clienteSelecionado.nome : clienteManual.trim();
    if (!nomeCliente) {
      setErro('Informe o cliente.');
      return;
    }
    if (tecnicosSelecionados.length === 0) {
      setErro('Selecione ao menos um técnico.');
      return;
    }

    setSalvando(true);
    setErro('');

    try {
      const tecnicosPayload = tecnicosSelecionados.map(id => ({
        tecnico_id: id,
        responsavel: id === (tecnicoResponsavel ?? tecnicosSelecionados[0]),
      }));

      const resultado = await criarOs({
        cliente_nome: nomeCliente,
        cliente_endereco: clienteSelecionado ? clienteSelecionado.endereco : enderecoManual.trim() || undefined,
        cliente_telefone: clienteSelecionado ? clienteSelecionado.telefone : telefoneManual.trim() || undefined,
        gc_cliente_id: clienteSelecionado?.id ?? null,
        data_agendamento: data.toISOString().split('T')[0],
        observacoes: observacoes.trim() || undefined,
        prioridade,
        tecnicos: tecnicosPayload,
      });

      Alert.alert(
        'OS criada!',
        `OS #${resultado.os_id} criada com sucesso.${resultado.sincronizado_gc ? '\nSincronizada com GestãoClick.' : ''}`,
        [{text: 'OK', onPress: () => navigation.goBack()}],
      );
    } catch (e: any) {
      setErro(e.message ?? 'Erro ao criar OS.');
    } finally {
      setSalvando(false);
    }
  }

  return (
    <ScrollView style={estilos.container} contentContainerStyle={estilos.scroll} keyboardShouldPersistTaps="handled">

      {erro ? (
        <View style={estilos.alertaErro}>
          <Text style={estilos.alertaErroText}>{erro}</Text>
        </View>
      ) : null}

      {/* Seção: Cliente */}
      <View style={estilos.card}>
        <Text style={estilos.secaoTitulo}>Cliente</Text>

        {clienteSelecionado ? (
          <View style={estilos.clienteSelecionado}>
            <View style={{flex: 1}}>
              <Text style={estilos.clienteNomeSel}>{clienteSelecionado.nome}</Text>
              {clienteSelecionado.endereco ? (
                <Text style={estilos.clienteDetalhe} numberOfLines={1}>{clienteSelecionado.endereco}</Text>
              ) : null}
              {clienteSelecionado.telefone ? (
                <Text style={estilos.clienteDetalhe}>{clienteSelecionado.telefone}</Text>
              ) : null}
            </View>
            <TouchableOpacity onPress={limparCliente} style={estilos.botaoLimpar}>
              <Text style={{color: CORES.cinza700, fontSize: 18}}>×</Text>
            </TouchableOpacity>
          </View>
        ) : (
          <>
            <Text style={estilos.label}>Buscar no GestãoClick</Text>
            <View style={estilos.buscaRow}>
              <TextInput
                style={[estilos.input, {flex: 1, marginBottom: 0}]}
                value={clienteBusca}
                onChangeText={txt => {setClienteBusca(txt); setClienteManual(txt);}}
                placeholder="Digite o nome do cliente..."
                placeholderTextColor={CORES.cinza300}
              />
              {buscandoCliente && <ActivityIndicator size="small" color={CORES.azul600} style={{marginLeft: 8}} />}
            </View>

            {clienteSugestoes.length > 0 && (
              <View style={estilos.sugestoes}>
                {clienteSugestoes.map((c, i) => (
                  <TouchableOpacity
                    key={i}
                    style={estilos.sugestaoItem}
                    onPress={() => selecionarCliente(c)}>
                    <Text style={estilos.sugestaoNome}>{c.nome}</Text>
                    {c.endereco ? <Text style={estilos.sugestaoDetalhe} numberOfLines={1}>{c.endereco}</Text> : null}
                  </TouchableOpacity>
                ))}
              </View>
            )}

            <Text style={[estilos.label, {marginTop: 12}]}>Ou preencha manualmente</Text>
            <TextInput
              style={estilos.input}
              value={clienteManual}
              onChangeText={setClienteManual}
              placeholder="Nome do cliente *"
              placeholderTextColor={CORES.cinza300}
            />
            <TextInput
              style={estilos.input}
              value={enderecoManual}
              onChangeText={setEnderecoManual}
              placeholder="Endereço"
              placeholderTextColor={CORES.cinza300}
            />
            <TextInput
              style={[estilos.input, {marginBottom: 0}]}
              value={telefoneManual}
              onChangeText={setTelefoneManual}
              placeholder="Telefone"
              placeholderTextColor={CORES.cinza300}
              keyboardType="phone-pad"
            />
          </>
        )}
      </View>

      {/* Seção: Técnicos */}
      <View style={estilos.card}>
        <Text style={estilos.secaoTitulo}>Técnicos</Text>
        {carregandoTecnicos ? (
          <ActivityIndicator size="small" color={CORES.azul600} />
        ) : tecnicos.length === 0 ? (
          <Text style={estilos.vazio}>Nenhum técnico cadastrado.</Text>
        ) : (
          tecnicos.map(t => {
            const sel = tecnicosSelecionados.includes(t.id);
            const isResp = tecnicoResponsavel === t.id;
            return (
              <TouchableOpacity
                key={t.id}
                style={[estilos.tecnicoItem, sel && estilos.tecnicoItemSel]}
                onPress={() => toggleTecnico(t.id)}>
                <View style={[estilos.tecnicoCheck, sel && estilos.tecnicoCheckSel]}>
                  {sel && <Text style={{color: '#fff', fontSize: 12, fontWeight: '800'}}>✓</Text>}
                </View>
                <View style={{flex: 1}}>
                  <Text style={[estilos.tecnicoNome, sel && {color: CORES.azul800}]}>{t.nome}</Text>
                  <Text style={estilos.tecnicoInfo}>{t.os_ativas} OS ativa(s)</Text>
                </View>
                {sel && (
                  <TouchableOpacity
                    style={[estilos.respBadge, isResp && estilos.respBadgeSel]}
                    onPress={() => setTecnicoResponsavel(t.id)}>
                    <Text style={[estilos.respBadgeText, isResp && {color: '#fff'}]}>
                      {isResp ? 'Responsável' : 'Responsável'}
                    </Text>
                  </TouchableOpacity>
                )}
              </TouchableOpacity>
            );
          })
        )}
      </View>

      {/* Seção: Agendamento */}
      <View style={estilos.card}>
        <Text style={estilos.secaoTitulo}>Agendamento</Text>
        <Text style={estilos.label}>Data *</Text>
        <TouchableOpacity
          style={estilos.dateInput}
          onPress={() => setMostrarDatePicker(true)}>
          <Text style={{color: CORES.cinza900, fontSize: 15}}>
            {data.toLocaleDateString('pt-BR')}
          </Text>
        </TouchableOpacity>
        {mostrarDatePicker && (
          <DateTimePicker
            value={data}
            mode="date"
            minimumDate={new Date()}
            onChange={(_, d) => {setMostrarDatePicker(false); if (d) {setData(d);}}}
          />
        )}
      </View>

      {/* Seção: Prioridade */}
      <View style={estilos.card}>
        <Text style={estilos.secaoTitulo}>Prioridade</Text>
        <View style={estilos.prioridadeRow}>
          {PRIORIDADES.map(p => {
            const cor = PRIORIDADE_CORES[p.key];
            const ativa = prioridade === p.key;
            return (
              <TouchableOpacity
                key={p.key}
                style={[
                  estilos.prioridadeBtn,
                  ativa && {backgroundColor: cor.bg, borderColor: cor.text},
                ]}
                onPress={() => setPrioridade(p.key)}>
                <Text style={[estilos.prioridadeBtnText, ativa && {color: cor.text, fontWeight: '800'}]}>
                  {p.label}
                </Text>
              </TouchableOpacity>
            );
          })}
        </View>
      </View>

      {/* Seção: Observações */}
      <View style={estilos.card}>
        <Text style={estilos.secaoTitulo}>Observações</Text>
        <TextInput
          style={estilos.textarea}
          value={observacoes}
          onChangeText={setObservacoes}
          multiline
          numberOfLines={4}
          placeholder="Descreva o serviço, detalhes relevantes..."
          placeholderTextColor={CORES.cinza300}
        />
      </View>

      {/* Botão criar */}
      <TouchableOpacity
        style={[estilos.botaoCriar, salvando && {opacity: 0.6}]}
        onPress={criar}
        disabled={salvando}>
        {salvando ? (
          <ActivityIndicator color="#fff" />
        ) : (
          <Text style={estilos.botaoCriarText}>Criar Ordem de Serviço</Text>
        )}
      </TouchableOpacity>

      <View style={{height: 32}} />
    </ScrollView>
  );
}

const estilos = StyleSheet.create({
  container: {flex: 1, backgroundColor: CORES.azul50},
  scroll: {padding: 16},
  alertaErro: {
    backgroundColor: CORES.vermelhoBg, borderRadius: 8,
    padding: 12, marginBottom: 12,
  },
  alertaErroText: {color: CORES.vermelho, fontWeight: '600', fontSize: 13.5},
  card: {
    backgroundColor: CORES.branco, borderRadius: 14,
    padding: 16, marginBottom: 12,
    shadowColor: '#10284A', shadowOffset: {width: 0, height: 2},
    shadowOpacity: 0.07, shadowRadius: 6, elevation: 2,
  },
  secaoTitulo: {fontSize: 14, fontWeight: '800', color: CORES.cinza900, marginBottom: 14},
  label: {fontSize: 13, fontWeight: '600', color: CORES.cinza700, marginBottom: 6},
  input: {
    borderWidth: 1, borderColor: CORES.cinza300, borderRadius: 8,
    paddingHorizontal: 12, paddingVertical: 10,
    fontSize: 14, color: CORES.cinza900, marginBottom: 12,
  },
  buscaRow: {flexDirection: 'row', alignItems: 'center', marginBottom: 4},
  sugestoes: {
    borderWidth: 1, borderColor: CORES.cinza100, borderRadius: 8,
    marginBottom: 8, overflow: 'hidden',
  },
  sugestaoItem: {
    padding: 12, borderBottomWidth: 1, borderBottomColor: CORES.cinza100,
  },
  sugestaoNome: {fontSize: 14, fontWeight: '600', color: CORES.cinza900},
  sugestaoDetalhe: {fontSize: 12, color: CORES.cinza500, marginTop: 2},
  clienteSelecionado: {
    flexDirection: 'row', alignItems: 'center',
    backgroundColor: CORES.azul50, borderRadius: 10,
    padding: 12, borderWidth: 1, borderColor: CORES.azul100,
  },
  clienteNomeSel: {fontWeight: '700', fontSize: 15, color: CORES.azul800},
  clienteDetalhe: {fontSize: 12.5, color: CORES.cinza500, marginTop: 3},
  botaoLimpar: {padding: 4},
  tecnicoItem: {
    flexDirection: 'row', alignItems: 'center', gap: 10,
    paddingVertical: 10, borderBottomWidth: 1, borderBottomColor: CORES.cinza100,
  },
  tecnicoItemSel: {backgroundColor: CORES.azul50, borderRadius: 8, paddingHorizontal: 6},
  tecnicoCheck: {
    width: 22, height: 22, borderRadius: 11,
    borderWidth: 2, borderColor: CORES.cinza300,
    alignItems: 'center', justifyContent: 'center',
  },
  tecnicoCheckSel: {backgroundColor: CORES.azul700, borderColor: CORES.azul700},
  tecnicoNome: {fontSize: 14, fontWeight: '600', color: CORES.cinza900},
  tecnicoInfo: {fontSize: 12, color: CORES.cinza500, marginTop: 1},
  respBadge: {
    paddingHorizontal: 8, paddingVertical: 4,
    borderRadius: 999, borderWidth: 1, borderColor: CORES.cinza300,
  },
  respBadgeSel: {backgroundColor: CORES.azul700, borderColor: CORES.azul700},
  respBadgeText: {fontSize: 11, fontWeight: '600', color: CORES.cinza500},
  vazio: {color: CORES.cinza500, fontSize: 13},
  dateInput: {
    borderWidth: 1, borderColor: CORES.cinza300, borderRadius: 8,
    paddingHorizontal: 14, paddingVertical: 12,
  },
  prioridadeRow: {flexDirection: 'row', gap: 8},
  prioridadeBtn: {
    flex: 1, paddingVertical: 10,
    borderRadius: 999, alignItems: 'center',
    borderWidth: 1.5, borderColor: CORES.cinza200,
    backgroundColor: CORES.cinza100,
  },
  prioridadeBtnText: {fontSize: 13, fontWeight: '600', color: CORES.cinza500},
  textarea: {
    borderWidth: 1, borderColor: CORES.cinza300, borderRadius: 8,
    padding: 12, fontSize: 14, color: CORES.cinza900,
    minHeight: 80, textAlignVertical: 'top',
  },
  botaoCriar: {
    backgroundColor: CORES.azul700, borderRadius: 999,
    paddingVertical: 15, alignItems: 'center',
    shadowColor: CORES.azul800, shadowOffset: {width: 0, height: 3},
    shadowOpacity: 0.3, shadowRadius: 6, elevation: 4,
    marginTop: 4,
  },
  botaoCriarText: {color: '#fff', fontWeight: '800', fontSize: 15.5},
});
