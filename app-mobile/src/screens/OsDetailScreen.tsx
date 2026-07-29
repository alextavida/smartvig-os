import React, {useState, useEffect, useRef, useCallback} from 'react';
import {
  View,
  Text,
  ScrollView,
  StyleSheet,
  TouchableOpacity,
  TextInput,
  Modal,
  Alert,
  Linking,
  Image,
  ActivityIndicator,
  Platform,
} from 'react-native';
import {NativeStackScreenProps} from '@react-navigation/native-stack';
import DateTimePicker from '@react-native-community/datetimepicker';
import Geolocation from '@react-native-community/geolocation';
import AsyncStorage from '@react-native-async-storage/async-storage';
import NetInfo from '@react-native-community/netinfo';
import {launchCamera, launchImageLibrary} from 'react-native-image-picker';
import {
  visualizarOs, iniciarOs, pausarOs, reagendarOs, encerrarOs,
  salvarDescricao, adicionarProduto, atribuirTecnicos, buscarProdutosGC,
  salvarTempo, cancelarOs, deletarOs,
} from '../api/os';
import {listarTecnicos} from '../api/tecnicos';
import {atualizarGps} from '../api/gps';
import {uploadMidia, uploadBase64Midia, urlMidia} from '../api/midias';
import {OSDetalhe, TecnicoLista, ProdutoGC, GCEquipamento} from '../types';
import Icon from 'react-native-vector-icons/MaterialIcons';
import {StatusBadge} from '../components/StatusBadge';
import {PriorityBadge} from '../components/PriorityBadge';
import {SignatureModal} from '../components/SignatureModal';
import {PhotoAnnotationModal} from '../components/PhotoAnnotationModal';
import {useVoiceRecognition} from '../hooks/useVoiceRecognition';
import {useOfflineQueue} from '../hooks/useOfflineQueue';
import {useAuth} from '../hooks/useAuth';
import {CORES, API_BASE_URL} from '../config';
import {RootStackParamList} from '../navigation';

type Props = NativeStackScreenProps<RootStackParamList, 'OsDetail'>;

const ROTULOS_ACAO: Record<string, string> = {
  os_criada: 'OS criada', os_iniciada: 'OS iniciada', os_pausada: 'OS pausada',
  os_reagendada: 'OS reagendada', os_encerrada: 'OS encerrada',
  os_atualizada: 'OS atualizada', tecnicos_atribuidos: 'Técnicos redefinidos',
  midia_enviada: 'Mídia enviada', produto_adicionado: 'Produto adicionado',
  descricao_atualizada: 'Descrição atualizada', falha_sincronizacao_gc: 'Falha de sincronização GC',
  os_cancelada: 'OS cancelada', recebimento_gerado: 'Recebimento gerado',
};

export function OsDetailScreen({route, navigation}: Props) {
  const {osId} = route.params;
  const {usuario} = useAuth();
  const isGestor = usuario?.perfil === 'gestor';
  const [os, setOs] = useState<OSDetalhe | null>(null);
  const [carregando, setCarregando] = useState(true);
  const [salvando, setSalvando] = useState(false);
  const [erro, setErro] = useState('');
  const [sucesso, setSucesso] = useState('');

  // Modais
  const [modalPausar, setModalPausar] = useState(false);
  const [modalReagendar, setModalReagendar] = useState(false);
  const [modalEncerrar, setModalEncerrar] = useState(false);
  const [modalProduto, setModalProduto] = useState(false);
  const [modalHistorico, setModalHistorico] = useState(false);
  const [modalTecnicos, setModalTecnicos] = useState(false);

  // Campos dos modais
  const [motivoPausa, setMotivoPausa] = useState('');
  const [novaData, setNovaData] = useState(new Date());
  const [mostrarDatePicker, setMostrarDatePicker] = useState(false);
  const [motivoReagendar, setMotivoReagendar] = useState('');
  const [laudoFinal, setLaudoFinal] = useState('');
  const [descricao, setDescricao] = useState('');

  // Modal produto com busca GC
  const [prodBusca, setProdBusca] = useState('');
  const [prodSugestoes, setProdSugestoes] = useState<ProdutoGC[]>([]);
  const [buscandoProd, setBuscandoProd] = useState(false);
  const [prodNome, setProdNome] = useState('');
  const [prodQtd, setProdQtd] = useState('1');
  const [prodValor, setProdValor] = useState('');

  // Modal técnicos (gestor)
  const [todosTecnicos, setTodosTecnicos] = useState<TecnicoLista[]>([]);
  const [tecnicosSel, setTecnicosSel] = useState<number[]>([]);
  const [tecnicoResp, setTecnicoResp] = useState<number | null>(null);
  const [carregandoTec, setCarregandoTec] = useState(false);

  // Timer de atendimento
  const [timerSegundos, setTimerSegundos] = useState(0);
  const [timerInicio, setTimerInicio]     = useState<number | null>(null);
  // Modo offline
  const [isOnline, setIsOnline] = useState(true);
  // Assinatura digital
  const [modalAssinatura, setModalAssinatura] = useState(false);
  // Foto com anotações
  const [fotoBase64Para, setFotoBase64Para]   = useState('');
  const [modalAnotacao, setModalAnotacao]     = useState(false);
  // Checklist
  const [checklist, setChecklist] = useState<{texto: string; concluido: boolean}[]>([]);
  const [salvandoCheck, setSalvandoCheck] = useState<number | null>(null);

  const gpsTimer  = useRef<ReturnType<typeof setInterval> | null>(null);
  const timerRef  = useRef<ReturnType<typeof setInterval> | null>(null);
  const timerKey  = `timer_inicio_os_${osId}`;

  // Voice recognition para laudo
  const {ouvindo, textoReconhecido, erro: erroVoz, iniciarDitado, pararDitado, limpar: limparVoz} = useVoiceRecognition();
  // Fila offline
  const {pendentes: acoesOffline, enfileirar} = useOfflineQueue();

  const carregar = useCallback(async () => {
    try {
      const dados = await visualizarOs(osId);
      setOs(dados);
      setDescricao(dados.observacoes ?? '');
      // Carrega checklist junto
      try {
        const {apiGet: apiGetFn} = await import('../api/client');
        const ck = await apiGetFn<{itens: {texto: string; concluido: boolean}[]}>(`/os/checklist.php?os_id=${osId}`);
        setChecklist(ck.itens ?? []);
      } catch { /* sem checklist */ }
    } catch (e: any) {
      setErro(e.message ?? 'Erro ao carregar OS.');
    } finally {
      setCarregando(false);
    }
  }, [osId]);

  useEffect(() => {
    carregar();
  }, [carregar]);

  useEffect(() => {
    if (isGestor || os?.situacao_local !== 'em_andamento') {
      if (gpsTimer.current) {clearInterval(gpsTimer.current);}
      return;
    }

    function enviarGps() {
      Geolocation.getCurrentPosition(
        pos => {
          atualizarGps(pos.coords.latitude, pos.coords.longitude, osId).catch(() => {});
        },
        () => {},
        {enableHighAccuracy: true, timeout: 15000},
      );
    }

    enviarGps();
    gpsTimer.current = setInterval(enviarGps, 60_000);
    return () => {
      if (gpsTimer.current) {clearInterval(gpsTimer.current);}
    };
  }, [os?.situacao_local, osId, isGestor]);

  // Timer de atendimento — inicia/para conforme situacao_local
  useEffect(() => {
    if (isGestor || os?.situacao_local !== 'em_andamento') {
      if (timerRef.current) { clearInterval(timerRef.current); timerRef.current = null; }
      return;
    }
    let ativo = true;
    AsyncStorage.getItem(timerKey).then(val => {
      if (!ativo) { return; }
      const inicio = val ? parseInt(val, 10) : Date.now();
      if (!val) { AsyncStorage.setItem(timerKey, String(inicio)); }
      setTimerInicio(inicio);
      setTimerSegundos(Math.floor((Date.now() - inicio) / 1000));
      if (timerRef.current) { clearInterval(timerRef.current); }
      timerRef.current = setInterval(() => {
        setTimerSegundos(Math.floor((Date.now() - inicio) / 1000));
      }, 1000);
    });
    return () => {
      ativo = false;
      if (timerRef.current) { clearInterval(timerRef.current); timerRef.current = null; }
    };
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [os?.situacao_local, osId, isGestor]);

  // Detecção de conexão (NetInfo)
  useEffect(() => {
    NetInfo.fetch().then(s => setIsOnline(s.isConnected ?? true));
    const unsub = NetInfo.addEventListener(s => setIsOnline(s.isConnected ?? true));
    return () => unsub();
  }, []);

  // Captura texto de voz e adiciona à descrição
  useEffect(() => {
    if (textoReconhecido) {
      setDescricao(prev => prev ? `${prev} ${textoReconhecido}` : textoReconhecido);
      limparVoz();
    }
  }, [textoReconhecido, limparVoz]);

  // Busca produtos GC com debounce
  useEffect(() => {
    if (!modalProduto || prodBusca.length < 2) {setProdSugestoes([]); return;}
    const t = setTimeout(async () => {
      setBuscandoProd(true);
      try {
        const lista = await buscarProdutosGC(prodBusca);
        setProdSugestoes(lista);
      } catch {
        setProdSugestoes([]);
      } finally {
        setBuscandoProd(false);
      }
    }, 400);
    return () => clearTimeout(t);
  }, [prodBusca, modalProduto]);

  function mostrarSucesso(msg: string) {
    setSucesso(msg);
    setErro('');
    setTimeout(() => setSucesso(''), 3000);
  }

  function mostrarErro(msg: string) {
    setErro(msg);
    setSucesso('');
  }

  async function acao(fn: () => Promise<void>, successMsg: string) {
    setSalvando(true);
    setErro('');
    try {
      await fn();
      mostrarSucesso(successMsg);
      await carregar();
    } catch (e: any) {
      mostrarErro(e.message ?? 'Erro desconhecido.');
    } finally {
      setSalvando(false);
    }
  }

  function formatarTempo(seg: number): string {
    const h = Math.floor(seg / 3600);
    const m = Math.floor((seg % 3600) / 60);
    const s = seg % 60;
    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
  }

  async function pararTimer(): Promise<void> {
    if (timerRef.current) { clearInterval(timerRef.current); timerRef.current = null; }
    const val = await AsyncStorage.getItem(timerKey);
    if (val) {
      const seg = Math.floor((Date.now() - parseInt(val, 10)) / 1000);
      if (seg > 0) { await salvarTempo(osId, seg).catch(() => {}); }
      await AsyncStorage.removeItem(timerKey);
    }
    setTimerInicio(null);
    setTimerSegundos(0);
  }

  function abrirRota() {
    if (!os?.cliente_endereco) {return;}
    const destino = encodeURIComponent(os.cliente_endereco);
    Linking.openURL(`https://www.google.com/maps/dir/?api=1&destination=${destino}`);
  }

  async function enviarMidia(fonte: 'camera' | 'galeria', comAnotacao = false) {
    const fn = fonte === 'camera' ? launchCamera : launchImageLibrary;
    // launchCamera só aceita 'photo' ou 'video' — 'mixed' causa falha silenciosa no Android
    const opcoes = fonte === 'camera'
      ? {mediaType: 'photo' as const, quality: 0.8 as const, includeBase64: comAnotacao}
      : {mediaType: 'mixed' as const, quality: 0.8 as const, includeBase64: false as const};
    fn(opcoes, async response => {
      if (response.didCancel || !response.assets?.[0]) {return;}
      const asset = response.assets[0];
      const tipo = asset.type?.startsWith('video') ? 'video' : 'foto';

      // Foto com anotações: abre o editor antes de enviar
      if (comAnotacao && tipo === 'foto' && asset.base64) {
        setFotoBase64Para(`data:image/jpeg;base64,${asset.base64}`);
        setModalAnotacao(true);
        return;
      }

      setSalvando(true);
      try {
        await uploadMidia(osId, tipo, {
          uri: asset.uri!,
          name: asset.fileName ?? `midia.${tipo === 'foto' ? 'jpg' : 'mp4'}`,
          type: asset.type ?? 'image/jpeg',
        });
        mostrarSucesso('Mídia enviada com sucesso!');
        await carregar();
      } catch (e: any) {
        mostrarErro(e.message ?? 'Erro ao enviar mídia.');
      } finally {
        setSalvando(false);
      }
    });
  }

  function escolherMidia() {
    Alert.alert('Enviar mídia', 'Como deseja adicionar?', [
      {text: 'Câmera', onPress: () => enviarMidia('camera')},
      {text: 'Câmera + Anotação', onPress: () => enviarMidia('camera', true)},
      {text: 'Galeria', onPress: () => enviarMidia('galeria')},
      {text: 'Cancelar', style: 'cancel'},
    ]);
  }

  async function abrirModalTecnicos() {
    setModalTecnicos(true);
    setCarregandoTec(true);
    try {
      const lista = await listarTecnicos();
      setTodosTecnicos(lista);
      const idsAtuais = (os?.tecnicos ?? []).map(t => t.id);
      setTecnicosSel(idsAtuais);
      const resp = (os?.tecnicos ?? []).find(t => t.responsavel);
      setTecnicoResp(resp?.id ?? idsAtuais[0] ?? null);
    } catch {
      Alert.alert('Erro', 'Não foi possível carregar técnicos.');
      setModalTecnicos(false);
    } finally {
      setCarregandoTec(false);
    }
  }

  function toggleTecnicoModal(id: number) {
    setTecnicosSel(prev => {
      if (prev.includes(id)) {
        const novo = prev.filter(t => t !== id);
        if (tecnicoResp === id) {setTecnicoResp(novo[0] ?? null);}
        return novo;
      }
      const novo = [...prev, id];
      if (!tecnicoResp) {setTecnicoResp(id);}
      return novo;
    });
  }

  async function toggleChecklistItem(idx: number) {
    const novo = !checklist[idx].concluido;
    const atualizado = checklist.map((item, i) => i === idx ? {...item, concluido: novo} : item);
    setChecklist(atualizado);
    setSalvandoCheck(idx);
    try {
      const {apiPost: apiPostFn} = await import('../api/client');
      await apiPostFn('/os/checklist.php', {os_id: osId, item_idx: idx, concluido: novo});
    } catch { setChecklist(checklist); } // reverte se falhar
    finally { setSalvandoCheck(null); }
  }

  function selecionarProdutoGC(p: ProdutoGC) {
    setProdNome(p.nome);
    setProdValor(String(p.valor_venda));
    setProdBusca('');
    setProdSugestoes([]);
  }

  if (carregando) {
    return (
      <View style={estilos.centro}>
        <ActivityIndicator size="large" color={CORES.azul600} />
        <Text style={{color: CORES.cinza500, marginTop: 12}}>Carregando OS...</Text>
      </View>
    );
  }

  if (!os) {
    return (
      <View style={estilos.centro}>
        <Text style={{color: CORES.vermelho}}>{erro || 'OS não encontrada.'}</Text>
      </View>
    );
  }

  const situacao = os.situacao_local;
  const produtos = os.produtos ?? [];
  const totalProdutos = produtos.reduce((acc, p) => acc + p.valor_venda * p.quantidade, 0);
  const dataFormatada = os.data_agendamento
    ? new Date(os.data_agendamento + 'T12:00:00').toLocaleDateString('pt-BR')
    : 'Sem data';

  return (
    <>
      <ScrollView style={estilos.container} contentContainerStyle={estilos.scroll}>

        {!isOnline && (
          <View style={estilos.offlineBanner}>
            <Icon name="cloud-off" size={15} color="#fff" style={{marginRight: 8}} />
            <Text style={estilos.offlineText}>Sem conexão — funcionalidades podem falhar</Text>
          </View>
        )}

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

        {/* Cabeçalho da OS */}
        <View style={estilos.card}>
          <View style={estilos.osHeader}>
            <Text style={estilos.osNum}>OS #{os.id}</Text>
            <View style={{gap: 6}}>
              <StatusBadge status={situacao} />
              <PriorityBadge prioridade={os.prioridade} />
            </View>
          </View>

          <Text style={estilos.clienteNome}>{os.cliente_nome ?? '-'}</Text>

          {os.cliente_endereco ? (
            <TouchableOpacity style={estilos.infoRow} onPress={abrirRota}>
              <Icon name="location-on" size={14} color={CORES.cinza500} style={{marginTop: 1}} />
              <Text style={[estilos.infoText, {color: CORES.azul700}]}>{os.cliente_endereco}</Text>
            </TouchableOpacity>
          ) : null}

          {os.cliente_telefone ? (
            <TouchableOpacity
              style={estilos.infoRow}
              onPress={() => Linking.openURL(`tel:${os.cliente_telefone}`)}>
              <Icon name="phone" size={14} color={CORES.cinza500} style={{marginTop: 1}} />
              <Text style={[estilos.infoText, {color: CORES.azul700}]}>{os.cliente_telefone}</Text>
            </TouchableOpacity>
          ) : null}

          <View style={estilos.infoRow}>
            <Icon name="calendar-today" size={14} color={CORES.cinza500} style={{marginTop: 1}} />
            <Text style={estilos.infoText}>{dataFormatada}</Text>
          </View>

          <View style={{flexDirection: 'row', gap: 8, marginTop: 10, flexWrap: 'wrap'}}>
            {os.cliente_endereco ? (
              <TouchableOpacity style={[estilos.botaoRota, {flex: 1, minWidth: 80}]} onPress={abrirRota}>
                <Text style={estilos.botaoRotaText}>Rota</Text>
              </TouchableOpacity>
            ) : null}
            {os.cliente_telefone ? (
              <TouchableOpacity
                style={[estilos.botaoRota, {flex: 1, minWidth: 80, backgroundColor: '#e6f4ea'}]}
                onPress={() => {
                  const num = os.cliente_telefone!.replace(/\D/g, '');
                  Linking.openURL(`https://wa.me/55${num}`);
                }}>
                <Text style={[estilos.botaoRotaText, {color: '#1e8e5a'}]}>WhatsApp</Text>
              </TouchableOpacity>
            ) : null}
            {os.cliente_telefone ? (
              <TouchableOpacity
                style={[estilos.botaoRota, {flex: 1, minWidth: 80}]}
                onPress={() => Linking.openURL(`tel:${os.cliente_telefone}`)}>
                <Text style={estilos.botaoRotaText}>Ligar</Text>
              </TouchableOpacity>
            ) : null}
            {os.gc_cliente_id ? (
              <TouchableOpacity
                style={[estilos.botaoRota, {flex: 1, minWidth: 80, backgroundColor: '#eff6ff'}]}
                onPress={() => navigation.navigate('ClienteHistorico', {
                  gcClienteId: os.gc_cliente_id!,
                  clienteNome: os.cliente_nome ?? 'Cliente',
                })}>
                <Text style={[estilos.botaoRotaText, {color: CORES.azul700}]}>Histórico</Text>
              </TouchableOpacity>
            ) : null}
          </View>
        </View>

        {/* Botões de ação */}
        <View style={estilos.card}>
          <Text style={estilos.secaoTitulo}>Ações</Text>
          <View style={estilos.acoesGrid}>

            {['aberto', 'reagendado', 'pausado'].includes(situacao) && !isGestor && (
              <TouchableOpacity
                style={[estilos.botaoAcao, estilos.botaoPrimario]}
                disabled={salvando}
                onPress={async () => {
                  if (!isOnline) {
                    await enfileirar({tipo: 'iniciar', os_id: osId, params: {}});
                    mostrarSucesso('Sem conexão — ação salva para sincronizar.');
                    return;
                  }
                  acao(async () => {
                    await iniciarOs(osId);
                    const inicio = Date.now();
                    await AsyncStorage.setItem(timerKey, String(inicio));
                  }, 'OS iniciada!');
                }}>
                <Text style={estilos.botaoAcaoText}>Iniciar</Text>
              </TouchableOpacity>
            )}

            {situacao === 'em_andamento' && !isGestor && (
              <>
                <TouchableOpacity
                  style={[estilos.botaoAcao, estilos.botaoNeutro]}
                  onPress={() => setModalPausar(true)}>
                  <Text style={[estilos.botaoAcaoText, {color: CORES.cinza700}]}>Pausar</Text>
                </TouchableOpacity>

                <TouchableOpacity
                  style={[estilos.botaoAcao, estilos.botaoNeutro]}
                  onPress={() => setModalReagendar(true)}>
                  <Text style={[estilos.botaoAcaoText, {color: CORES.cinza700}]}>Reagendar</Text>
                </TouchableOpacity>

                <TouchableOpacity
                  style={[estilos.botaoAcao, estilos.botaoSucesso]}
                  onPress={() => setModalEncerrar(true)}>
                  <Text style={[estilos.botaoAcaoText, {color: CORES.verde}]}>Encerrar</Text>
                </TouchableOpacity>
              </>
            )}

            {['aberto', 'pausado'].includes(situacao) && !isGestor && (
              <TouchableOpacity
                style={[estilos.botaoAcao, estilos.botaoNeutro]}
                onPress={() => setModalReagendar(true)}>
                <Text style={[estilos.botaoAcaoText, {color: CORES.cinza700}]}>Reagendar</Text>
              </TouchableOpacity>
            )}

            {/* Coletar assinatura do cliente */}
            {!isGestor && !['concluido', 'cancelado'].includes(situacao) && (
              <TouchableOpacity
                style={[estilos.botaoAcao, estilos.botaoNeutro]}
                onPress={() => setModalAssinatura(true)}>
                <Text style={[estilos.botaoAcaoText, {color: CORES.cinza700}]}>Assinar</Text>
              </TouchableOpacity>
            )}

            {/* Ações exclusivas do gestor */}
            {isGestor && (
              <>
                <TouchableOpacity
                  style={[estilos.botaoAcao, estilos.botaoGestor]}
                  onPress={abrirModalTecnicos}>
                  <Text style={[estilos.botaoAcaoText, {color: CORES.azul800}]}>Técnicos</Text>
                </TouchableOpacity>
                <TouchableOpacity
                  style={[estilos.botaoAcao, estilos.botaoNeutro]}
                  onPress={() => setModalReagendar(true)}>
                  <Text style={[estilos.botaoAcaoText, {color: CORES.cinza700}]}>Reagendar</Text>
                </TouchableOpacity>
                {situacao !== 'concluido' && situacao !== 'cancelado' && (
                  <TouchableOpacity
                    style={[estilos.botaoAcao, estilos.botaoSucesso]}
                    onPress={() => setModalEncerrar(true)}>
                    <Text style={[estilos.botaoAcaoText, {color: CORES.verde}]}>Encerrar</Text>
                  </TouchableOpacity>
                )}
                {situacao !== 'cancelado' && (
                  <TouchableOpacity
                    style={[estilos.botaoAcao, {backgroundColor: '#fff7ed', borderColor: '#fed7aa', borderWidth: 1}]}
                    disabled={salvando}
                    onPress={() => {
                      Alert.alert(
                        'Cancelar OS',
                        'Informe o motivo do cancelamento (opcional):',
                        [
                          {text: 'Voltar', style: 'cancel'},
                          {
                            text: 'Cancelar OS',
                            style: 'destructive',
                            onPress: () => acao(() => cancelarOs(osId, ''), 'OS cancelada!'),
                          },
                        ],
                      );
                    }}>
                    <Text style={[estilos.botaoAcaoText, {color: '#c2410c'}]}>Cancelar OS</Text>
                  </TouchableOpacity>
                )}
                <TouchableOpacity
                  style={[estilos.botaoAcao, {backgroundColor: '#fef2f2', borderColor: '#fecaca', borderWidth: 1}]}
                  disabled={salvando}
                  onPress={() => {
                    Alert.alert(
                      'Deletar OS',
                      'Esta ação é IRREVERSÍVEL. Todos os dados da OS (histórico, fotos, produtos) serão apagados permanentemente.',
                      [
                        {text: 'Voltar', style: 'cancel'},
                        {
                          text: 'Deletar permanentemente',
                          style: 'destructive',
                          onPress: async () => {
                            setSalvando(true);
                            try {
                              await deletarOs(osId);
                              navigation.goBack();
                            } catch (e: any) {
                              mostrarErro(e.message ?? 'Erro ao deletar.');
                              setSalvando(false);
                            }
                          },
                        },
                      ],
                    );
                  }}>
                  <Text style={[estilos.botaoAcaoText, {color: CORES.vermelho}]}>Deletar OS</Text>
                </TouchableOpacity>
              </>
            )}
          </View>

          {situacao === 'em_andamento' && !isGestor && timerInicio !== null && (
            <View style={estilos.timerBox}>
              <Text style={estilos.timerText}>{formatarTempo(timerSegundos)}</Text>
              <Text style={estilos.timerLabel}>tempo em atendimento</Text>
            </View>
          )}

          {situacao === 'em_andamento' && !isGestor && (
            <View style={estilos.gpsIndicador}>
              <View style={estilos.gpsDot} />
              <Text style={estilos.gpsText}>GPS ativo — enviando posição a cada 60s</Text>
            </View>
          )}
        </View>

        {/* Equipamentos do GestãoClick */}
        {(os.gc_equipamentos ?? []).length > 0 && (
          <View style={[estilos.card, {borderLeftWidth: 3, borderLeftColor: CORES.azul600}]}>
            <Text style={[estilos.secaoTitulo, {color: CORES.azul700}]}>Equipamento (GestãoClick)</Text>
            {(os.gc_equipamentos ?? []).map((eq: GCEquipamento, i: number) => (
              <View key={i} style={i > 0 ? {marginTop: 12, paddingTop: 12, borderTopWidth: 1, borderTopColor: CORES.cinza100} : undefined}>
                {[
                  ['Tipo',   eq.tipo],
                  ['Marca',  eq.marca],
                  ['Modelo', eq.modelo],
                  ['Série',  eq.serie],
                ].filter(([, v]) => v).map(([k, v]) => (
                  <View key={k} style={{flexDirection: 'row', marginBottom: 3}}>
                    <Text style={{color: CORES.cinza500, fontSize: 12, width: 68, flexShrink: 0}}>{k}:</Text>
                    <Text style={{color: CORES.cinza900, fontSize: 12, flex: 1}}>{v}</Text>
                  </View>
                ))}
                {eq.defeitos ? (
                  <View style={{marginTop: 6, backgroundColor: '#fffbeb', borderRadius: 6, padding: 8}}>
                    <Text style={{fontSize: 10, color: '#92400e', fontWeight: '700', marginBottom: 2}}>DEFEITO</Text>
                    <Text style={{fontSize: 12, color: '#92400e'}}>{eq.defeitos}</Text>
                  </View>
                ) : null}
                {eq.solucao ? (
                  <View style={{marginTop: 4, backgroundColor: '#f0fdf4', borderRadius: 6, padding: 8}}>
                    <Text style={{fontSize: 10, color: '#166534', fontWeight: '700', marginBottom: 2}}>SOLUÇÃO</Text>
                    <Text style={{fontSize: 12, color: '#166534'}}>{eq.solucao}</Text>
                  </View>
                ) : null}
                {eq.laudo ? (
                  <View style={{marginTop: 4, backgroundColor: '#eff6ff', borderRadius: 6, padding: 8}}>
                    <Text style={{fontSize: 10, color: '#1d4ed8', fontWeight: '700', marginBottom: 2}}>LAUDO</Text>
                    <Text style={{fontSize: 12, color: '#1d4ed8'}}>{eq.laudo}</Text>
                  </View>
                ) : null}
              </View>
            ))}
          </View>
        )}

        {/* Serviços do GestãoClick */}
        {(os.gc_servicos ?? []).length > 0 && (
          <View style={estilos.card}>
            <Text style={estilos.secaoTitulo}>Serviços (GestãoClick)</Text>
            {(os.gc_servicos ?? []).map((s, i) => (
              <View key={i} style={estilos.produtoRow}>
                <View style={{flex: 1}}>
                  <Text style={estilos.produtoNome}>{s.nome || '—'}</Text>
                  <Text style={estilos.produtoDetalhe}>{s.quantidade}x</Text>
                </View>
                {s.valor > 0 && (
                  <Text style={estilos.produtoSubtotal}>
                    R$ {(s.quantidade * s.valor).toFixed(2).replace('.', ',')}
                  </Text>
                )}
              </View>
            ))}
          </View>
        )}

        {/* Produtos/Peças do GestãoClick */}
        {(os.gc_produtos ?? []).length > 0 && (
          <View style={estilos.card}>
            <Text style={estilos.secaoTitulo}>Produtos/Peças (GestãoClick)</Text>
            {(os.gc_produtos ?? []).map((p, i) => (
              <View key={i} style={estilos.produtoRow}>
                <View style={{flex: 1}}>
                  <Text style={estilos.produtoNome}>{p.nome || '—'}</Text>
                  <Text style={estilos.produtoDetalhe}>
                    {p.quantidade}x R$ {Number(p.valor_venda).toFixed(2).replace('.', ',')}
                  </Text>
                </View>
                <Text style={estilos.produtoSubtotal}>
                  R$ {(p.quantidade * p.valor_venda).toFixed(2).replace('.', ',')}
                </Text>
              </View>
            ))}
          </View>
        )}

        {/* Descrição / Laudo */}
        <View style={estilos.card}>
          <Text style={estilos.secaoTitulo}>Descrição / Laudo</Text>
          <TextInput
            style={estilos.textarea}
            value={descricao}
            onChangeText={setDescricao}
            multiline
            numberOfLines={4}
            placeholder="Descreva o serviço, observações..."
            placeholderTextColor={CORES.cinza300}
          />
          {erroVoz ? (
            <Text style={{color: CORES.vermelho, fontSize: 12, marginTop: 4}}>{erroVoz}</Text>
          ) : null}
          <View style={{flexDirection: 'row', gap: 8, marginTop: 10}}>
            <TouchableOpacity
              style={[estilos.botaoPequeno, {backgroundColor: CORES.azul100}]}
              disabled={salvando}
              onPress={() => acao(() => salvarDescricao(osId, descricao), 'Descrição salva.')}>
              <Text style={{color: CORES.azul700, fontWeight: '600'}}>Salvar descrição</Text>
            </TouchableOpacity>
            <TouchableOpacity
              style={[estilos.botaoPequeno, {backgroundColor: ouvindo ? '#fee2e2' : CORES.cinza100}]}
              onPress={() => ouvindo ? pararDitado() : iniciarDitado()}>
              <Text style={{color: ouvindo ? CORES.vermelho : CORES.cinza700, fontWeight: '600'}}>
                {ouvindo ? 'Parar' : 'Ditar'}
              </Text>
            </TouchableOpacity>
          </View>
        </View>

        {/* Checklist */}
        {checklist.length > 0 && (
          <View style={estilos.card}>
            <Text style={estilos.secaoTitulo}>
              Checklist ({checklist.filter(i => i.concluido).length}/{checklist.length})
            </Text>
            {checklist.map((item, idx) => (
              <TouchableOpacity
                key={idx}
                style={estilos.checkRow}
                onPress={() => toggleChecklistItem(idx)}
                disabled={salvandoCheck === idx || ['concluido', 'cancelado'].includes(situacao)}>
                <View style={[estilos.checkCircle, item.concluido && estilos.checkCircleFeito]}>
                  {item.concluido && <Icon name="check" size={14} color="#fff" />}
                  {salvandoCheck === idx && <ActivityIndicator size="small" color="#fff" />}
                </View>
                <Text style={[estilos.checkTexto, item.concluido && estilos.checkTextoFeito]}>
                  {item.texto}
                </Text>
              </TouchableOpacity>
            ))}
            {checklist.length > 0 && (
              <View style={estilos.checkProgress}>
                <View style={[estilos.checkProgressBar, {
                  width: `${Math.round((checklist.filter(i => i.concluido).length / checklist.length) * 100)}%` as any,
                }]} />
              </View>
            )}
          </View>
        )}

        {/* Produtos */}
        <View style={estilos.card}>
          <View style={estilos.secaoHeader}>
            <Text style={estilos.secaoTitulo}>Produtos ({produtos.length})</Text>
            <TouchableOpacity onPress={() => setModalProduto(true)}>
              <Text style={{color: CORES.azul700, fontWeight: '600', fontSize: 13}}>+ Adicionar</Text>
            </TouchableOpacity>
          </View>

          {produtos.length > 0 ? (
            <>
              {produtos.map((p, i) => (
                <View key={i} style={estilos.produtoRow}>
                  <View style={{flex: 1}}>
                    <Text style={estilos.produtoNome}>{p.nome}</Text>
                    <Text style={estilos.produtoDetalhe}>
                      {p.quantidade}x R$ {Number(p.valor_venda).toFixed(2).replace('.', ',')}
                    </Text>
                  </View>
                  <Text style={estilos.produtoSubtotal}>
                    R$ {(p.quantidade * p.valor_venda).toFixed(2).replace('.', ',')}
                  </Text>
                </View>
              ))}
              <View style={estilos.totalRow}>
                <Text style={estilos.totalLabel}>Total</Text>
                <Text style={estilos.totalValor}>
                  R$ {totalProdutos.toFixed(2).replace('.', ',')}
                </Text>
              </View>
            </>
          ) : (
            <Text style={estilos.vazioInline}>Nenhum produto adicionado.</Text>
          )}
        </View>

        {/* Mídias */}
        <View style={estilos.card}>
          <View style={estilos.secaoHeader}>
            <Text style={estilos.secaoTitulo}>Fotos e Vídeos ({os.midias.length})</Text>
            <TouchableOpacity onPress={escolherMidia}>
              <Text style={{color: CORES.azul700, fontWeight: '600', fontSize: 13}}>+ Enviar</Text>
            </TouchableOpacity>
          </View>

          {os.midias.length > 0 ? (
            <ScrollView horizontal showsHorizontalScrollIndicator={false} style={{marginTop: 8}}>
              {os.midias.map((m, i) => {
                // m.url já é a URL completa fornecida pela API (evita construção manual com duplo slash)
                const uri = m.url || `${API_BASE_URL.replace('/api', '')}/${m.caminho_arquivo}`;
                return m.tipo === 'foto' ? (
                  <TouchableOpacity key={i} onPress={() => Linking.openURL(uri)}>
                    <Image source={{uri}} style={estilos.miniaturaFoto} resizeMode="cover" />
                  </TouchableOpacity>
                ) : (
                  <TouchableOpacity key={i} style={[estilos.miniaturaFoto, estilos.miniaturaVideo]} onPress={() => Linking.openURL(uri)}>
                    <Text style={{color: '#fff', fontSize: 28}}>▶</Text>
                  </TouchableOpacity>
                );
              })}
            </ScrollView>
          ) : (
            <Text style={estilos.vazioInline}>Nenhuma mídia enviada.</Text>
          )}
        </View>

        {/* Técnicos */}
        {os.tecnicos.length > 0 && (
          <View style={estilos.card}>
            <View style={estilos.secaoHeader}>
              <Text style={estilos.secaoTitulo}>Técnicos atribuídos</Text>
              {isGestor && (
                <TouchableOpacity onPress={abrirModalTecnicos}>
                  <Text style={{color: CORES.azul700, fontSize: 13, fontWeight: '600'}}>Editar</Text>
                </TouchableOpacity>
              )}
            </View>
            {os.tecnicos.map(t => (
              <View key={t.id} style={estilos.tecnicoRow}>
                {t.foto_perfil ? (
                  <Image
                    source={{uri: urlMidia(t.foto_perfil)}}
                    style={estilos.tecnicoFoto}
                  />
                ) : (
                  <View style={estilos.tecnicoAvatar}>
                    <Text style={estilos.tecnicoIniciais}>
                      {t.nome.split(' ').slice(0, 2).map(p => p[0]).join('').toUpperCase()}
                    </Text>
                  </View>
                )}
                <View>
                  <Text style={estilos.tecnicoNome}>{t.nome}</Text>
                  {t.responsavel && <Text style={estilos.tecnicoResp}>Responsável</Text>}
                </View>
              </View>
            ))}
          </View>
        )}

        {/* Histórico */}
        {os.historico.length > 0 && (
          <View style={estilos.card}>
            <View style={estilos.secaoHeader}>
              <Text style={estilos.secaoTitulo}>Histórico ({os.historico.length})</Text>
              <TouchableOpacity onPress={() => setModalHistorico(true)}>
                <Text style={{color: CORES.azul700, fontSize: 13, fontWeight: '600'}}>Ver tudo</Text>
              </TouchableOpacity>
            </View>
            {os.historico.slice(0, 3).map((h, i) => (
              <View key={i} style={estilos.histRow}>
                <Text style={estilos.histAcao}>{ROTULOS_ACAO[h.acao] ?? h.acao}</Text>
                {h.detalhe && <Text style={estilos.histDetalhe} numberOfLines={1}>{h.detalhe}</Text>}
                <Text style={estilos.histData}>
                  {new Date(h.criado_em).toLocaleString('pt-BR')}
                  {h.usuario_nome ? ` · ${h.usuario_nome}` : ''}
                </Text>
              </View>
            ))}
          </View>
        )}

        <View style={{height: 32}} />
      </ScrollView>

      {/* =================== MODAIS =================== */}

      {/* Modal Pausar */}
      <Modal visible={modalPausar} transparent animationType="slide">
        <View style={estilos.modalOverlay}>
          <View style={estilos.modalBox}>
            <Text style={estilos.modalTitulo}>Pausar OS</Text>
            <Text style={estilos.modalLabel}>Motivo da pausa *</Text>
            <TextInput
              style={[estilos.textarea, {marginBottom: 16}]}
              value={motivoPausa}
              onChangeText={setMotivoPausa}
              multiline
              numberOfLines={3}
              placeholder="Ex: cliente ausente, falta de material..."
              placeholderTextColor={CORES.cinza300}
            />
            <View style={estilos.modalBotoes}>
              <TouchableOpacity
                style={[estilos.botaoModal, {backgroundColor: CORES.cinza100}]}
                onPress={() => {setModalPausar(false); setMotivoPausa('');}}>
                <Text style={{color: CORES.cinza700, fontWeight: '600'}}>Cancelar</Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={[estilos.botaoModal, {backgroundColor: CORES.azul700}]}
                disabled={salvando}
                onPress={async () => {
                  if (!motivoPausa.trim()) {Alert.alert('Informe o motivo da pausa.'); return;}
                  if (!isOnline) {
                    await pararTimer();
                    await enfileirar({tipo: 'pausar', os_id: osId, params: {motivo: motivoPausa}});
                    setModalPausar(false); setMotivoPausa('');
                    mostrarSucesso('Sem conexão — pausa salva para sincronizar.');
                    return;
                  }
                  acao(async () => {
                    await pararTimer();
                    await pausarOs(osId, motivoPausa);
                  }, 'OS pausada.').then(() => {
                    setModalPausar(false); setMotivoPausa('');
                  });
                }}>
                <Text style={{color: '#fff', fontWeight: '600'}}>
                  {salvando ? '...' : 'Confirmar'}
                </Text>
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>

      {/* Modal Reagendar */}
      <Modal visible={modalReagendar} transparent animationType="slide">
        <View style={estilos.modalOverlay}>
          <View style={estilos.modalBox}>
            <Text style={estilos.modalTitulo}>Reagendar OS</Text>
            <Text style={estilos.modalLabel}>Nova data *</Text>
            <TouchableOpacity
              style={estilos.dateInput}
              onPress={() => setMostrarDatePicker(true)}>
              <Text style={{color: CORES.cinza900}}>{novaData.toLocaleDateString('pt-BR')}</Text>
            </TouchableOpacity>

            {mostrarDatePicker && (
              <DateTimePicker
                value={novaData}
                mode="date"
                minimumDate={new Date()}
                onChange={(_, d) => {setMostrarDatePicker(false); if (d) {setNovaData(d);}}}
              />
            )}

            <Text style={estilos.modalLabel}>Motivo (opcional)</Text>
            <TextInput
              style={[estilos.input, {marginBottom: 16}]}
              value={motivoReagendar}
              onChangeText={setMotivoReagendar}
              placeholder="Motivo do reagendamento..."
              placeholderTextColor={CORES.cinza300}
            />
            <View style={estilos.modalBotoes}>
              <TouchableOpacity
                style={[estilos.botaoModal, {backgroundColor: CORES.cinza100}]}
                onPress={() => setModalReagendar(false)}>
                <Text style={{color: CORES.cinza700, fontWeight: '600'}}>Cancelar</Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={[estilos.botaoModal, {backgroundColor: CORES.azul700}]}
                disabled={salvando}
                onPress={() => {
                  const dataStr = novaData.toISOString().split('T')[0];
                  acao(() => reagendarOs(osId, dataStr, motivoReagendar), 'OS reagendada.').then(() => {
                    setModalReagendar(false); setMotivoReagendar('');
                  });
                }}>
                <Text style={{color: '#fff', fontWeight: '600'}}>
                  {salvando ? '...' : 'Confirmar'}
                </Text>
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>

      {/* Modal Encerrar */}
      <Modal visible={modalEncerrar} transparent animationType="slide">
        <View style={estilos.modalOverlay}>
          <View style={estilos.modalBox}>
            <Text style={estilos.modalTitulo}>Encerrar OS</Text>
            <Text style={estilos.modalLabel}>Laudo final *</Text>
            <TextInput
              style={[estilos.textarea, {marginBottom: 16}]}
              value={laudoFinal}
              onChangeText={setLaudoFinal}
              multiline
              numberOfLines={4}
              placeholder="Descreva detalhadamente o serviço realizado..."
              placeholderTextColor={CORES.cinza300}
            />
            <View style={estilos.modalBotoes}>
              <TouchableOpacity
                style={[estilos.botaoModal, {backgroundColor: CORES.cinza100}]}
                onPress={() => setModalEncerrar(false)}>
                <Text style={{color: CORES.cinza700, fontWeight: '600'}}>Cancelar</Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={[estilos.botaoModal, {backgroundColor: CORES.verde}]}
                disabled={salvando}
                onPress={async () => {
                  if (!laudoFinal.trim()) {Alert.alert('Informe o laudo final.'); return;}
                  if (!isOnline) {
                    await pararTimer();
                    await enfileirar({tipo: 'encerrar', os_id: osId, params: {laudo_final: laudoFinal}});
                    setModalEncerrar(false); setLaudoFinal('');
                    mostrarSucesso('Sem conexão — encerramento salvo para sincronizar.');
                    return;
                  }
                  acao(async () => {
                    await pararTimer();
                    await encerrarOs(osId, laudoFinal);
                  }, 'OS encerrada com sucesso!').then(() => {
                    setModalEncerrar(false); setLaudoFinal('');
                  });
                }}>
                <Text style={{color: '#fff', fontWeight: '600'}}>
                  {salvando ? '...' : 'Encerrar'}
                </Text>
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>

      {/* Modal Produto com busca GC */}
      <Modal visible={modalProduto} transparent animationType="slide">
        <View style={estilos.modalOverlay}>
          <ScrollView keyboardShouldPersistTaps="handled">
            <View style={estilos.modalBox}>
              <Text style={estilos.modalTitulo}>Adicionar produto</Text>

              <Text style={estilos.modalLabel}>Buscar no GestãoClick</Text>
              <View style={{flexDirection: 'row', alignItems: 'center', marginBottom: 4}}>
                <TextInput
                  style={[estilos.input, {flex: 1, marginBottom: 0}]}
                  value={prodBusca}
                  onChangeText={setProdBusca}
                  placeholder="Nome do produto..."
                  placeholderTextColor={CORES.cinza300}
                />
                {buscandoProd && <ActivityIndicator size="small" color={CORES.azul600} style={{marginLeft: 8}} />}
              </View>

              {prodSugestoes.length > 0 && (
                <View style={estilos.sugestoesProd}>
                  {prodSugestoes.map((p, i) => (
                    <TouchableOpacity
                      key={i}
                      style={estilos.sugestaoProdItem}
                      onPress={() => selecionarProdutoGC(p)}>
                      <Text style={estilos.sugestaoProdNome}>{p.nome}</Text>
                      {p.valor_venda > 0 && (
                        <Text style={estilos.sugestaoProdValor}>
                          R$ {p.valor_venda.toFixed(2).replace('.', ',')}
                        </Text>
                      )}
                    </TouchableOpacity>
                  ))}
                </View>
              )}

              <Text style={[estilos.modalLabel, {marginTop: 12}]}>Nome do produto *</Text>
              <TextInput
                style={[estilos.input, {marginBottom: 12}]}
                value={prodNome}
                onChangeText={setProdNome}
                placeholder="Nome do produto"
                placeholderTextColor={CORES.cinza300}
              />
              <View style={{flexDirection: 'row', gap: 10}}>
                <View style={{flex: 1}}>
                  <Text style={estilos.modalLabel}>Quantidade *</Text>
                  <TextInput
                    style={[estilos.input, {marginBottom: 12}]}
                    value={prodQtd}
                    onChangeText={setProdQtd}
                    keyboardType="decimal-pad"
                    placeholder="1"
                    placeholderTextColor={CORES.cinza300}
                  />
                </View>
                <View style={{flex: 1}}>
                  <Text style={estilos.modalLabel}>Valor unit. *</Text>
                  <TextInput
                    style={[estilos.input, {marginBottom: 12}]}
                    value={prodValor}
                    onChangeText={setProdValor}
                    keyboardType="decimal-pad"
                    placeholder="0,00"
                    placeholderTextColor={CORES.cinza300}
                  />
                </View>
              </View>
              <View style={estilos.modalBotoes}>
                <TouchableOpacity
                  style={[estilos.botaoModal, {backgroundColor: CORES.cinza100}]}
                  onPress={() => {
                    setModalProduto(false);
                    setProdNome(''); setProdQtd('1'); setProdValor(''); setProdBusca(''); setProdSugestoes([]);
                  }}>
                  <Text style={{color: CORES.cinza700, fontWeight: '600'}}>Cancelar</Text>
                </TouchableOpacity>
                <TouchableOpacity
                  style={[estilos.botaoModal, {backgroundColor: CORES.azul700}]}
                  disabled={salvando}
                  onPress={() => {
                    const nome = prodNome.trim();
                    const quantidade = parseFloat(prodQtd.replace(',', '.'));
                    const valor_venda = parseFloat(prodValor.replace(',', '.'));
                    if (!nome || isNaN(quantidade) || isNaN(valor_venda)) {
                      Alert.alert('Preencha nome, quantidade e valor.');
                      return;
                    }
                    acao(
                      () => adicionarProduto(osId, {nome, quantidade, valor_venda}),
                      'Produto adicionado!',
                    ).then(() => {
                      setModalProduto(false);
                      setProdNome(''); setProdQtd('1'); setProdValor(''); setProdBusca(''); setProdSugestoes([]);
                    });
                  }}>
                  <Text style={{color: '#fff', fontWeight: '600'}}>
                    {salvando ? '...' : '+ Adicionar'}
                  </Text>
                </TouchableOpacity>
              </View>
            </View>
          </ScrollView>
        </View>
      </Modal>

      {/* Modal Atribuir Técnicos (gestor) */}
      <Modal visible={modalTecnicos} transparent animationType="slide">
        <View style={estilos.modalOverlay}>
          <View style={[estilos.modalBox, {maxHeight: '80%'}]}>
            <Text style={estilos.modalTitulo}>Atribuir Técnicos</Text>
            {carregandoTec ? (
              <ActivityIndicator size="large" color={CORES.azul600} style={{marginVertical: 24}} />
            ) : (
              <ScrollView style={{maxHeight: 360}}>
                {todosTecnicos.map(t => {
                  const sel = tecnicosSel.includes(t.id);
                  const isResp = tecnicoResp === t.id;
                  return (
                    <TouchableOpacity
                      key={t.id}
                      style={[estilos.tecnicoItemModal, sel && estilos.tecnicoItemModalSel]}
                      onPress={() => toggleTecnicoModal(t.id)}>
                      <View style={[estilos.tecnicoCheck, sel && estilos.tecnicoCheckSel]}>
                        {sel && <Text style={{color: '#fff', fontSize: 11, fontWeight: '800'}}>✓</Text>}
                      </View>
                      <View style={{flex: 1}}>
                        <Text style={[estilos.tecnicoNome, sel && {color: CORES.azul800}]}>{t.nome}</Text>
                        <Text style={estilos.tecnicoInfo}>{t.os_ativas} OS ativa(s)</Text>
                      </View>
                      {sel && (
                        <TouchableOpacity
                          style={[estilos.respBadge, isResp && estilos.respBadgeSel]}
                          onPress={() => setTecnicoResp(t.id)}>
                          <Text style={[estilos.respBadgeText, isResp && {color: '#fff'}]}>
                            {isResp ? 'Resp.' : 'Resp.'}
                          </Text>
                        </TouchableOpacity>
                      )}
                    </TouchableOpacity>
                  );
                })}
              </ScrollView>
            )}
            <View style={[estilos.modalBotoes, {marginTop: 16}]}>
              <TouchableOpacity
                style={[estilos.botaoModal, {backgroundColor: CORES.cinza100}]}
                onPress={() => setModalTecnicos(false)}>
                <Text style={{color: CORES.cinza700, fontWeight: '600'}}>Cancelar</Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={[estilos.botaoModal, {backgroundColor: CORES.azul700}]}
                disabled={salvando || tecnicosSel.length === 0}
                onPress={() => {
                  if (tecnicosSel.length === 0) {Alert.alert('Selecione ao menos um técnico.'); return;}
                  const payload = tecnicosSel.map(id => ({
                    tecnico_id: id,
                    responsavel: id === (tecnicoResp ?? tecnicosSel[0]),
                  }));
                  acao(() => atribuirTecnicos(osId, payload), 'Técnicos atualizados!').then(() => {
                    setModalTecnicos(false);
                  });
                }}>
                <Text style={{color: '#fff', fontWeight: '600'}}>
                  {salvando ? '...' : 'Salvar'}
                </Text>
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>

      {/* Modal Foto com Anotações */}
      <PhotoAnnotationModal
        visible={modalAnotacao}
        imagemBase64={fotoBase64Para}
        onCancelar={() => { setModalAnotacao(false); setFotoBase64Para(''); }}
        onConfirm={async base64Anotada => {
          setModalAnotacao(false);
          setFotoBase64Para('');
          setSalvando(true);
          try {
            await uploadBase64Midia(osId, 'foto', base64Anotada, `foto_anotada_${Date.now()}.jpg`);
            mostrarSucesso('Foto com anotação enviada!');
            await carregar();
          } catch (e: any) {
            mostrarErro(e.message ?? 'Erro ao enviar foto com anotação.');
          } finally {
            setSalvando(false);
          }
        }}
      />

      {/* Modal Assinatura Digital */}
      <SignatureModal
        visible={modalAssinatura}
        onCancelar={() => setModalAssinatura(false)}
        onConfirm={async base64 => {
          setModalAssinatura(false);
          setSalvando(true);
          try {
            await uploadBase64Midia(osId, 'foto', base64, 'assinatura_cliente.png');
            mostrarSucesso('Assinatura registrada com sucesso!');
            await carregar();
          } catch (e: any) {
            mostrarErro(e.message ?? 'Erro ao salvar assinatura.');
          } finally {
            setSalvando(false);
          }
        }}
      />

      {/* Modal Histórico completo */}
      <Modal visible={modalHistorico} transparent animationType="slide">
        <View style={estilos.modalOverlay}>
          <View style={[estilos.modalBox, {maxHeight: '80%'}]}>
            <Text style={estilos.modalTitulo}>Histórico completo</Text>
            <ScrollView style={{maxHeight: 400}}>
              {os.historico.map((h, i) => (
                <View key={i} style={[estilos.histRow, {borderBottomWidth: 1, borderColor: CORES.cinza100, paddingBottom: 8, marginBottom: 8}]}>
                  <Text style={estilos.histAcao}>{ROTULOS_ACAO[h.acao] ?? h.acao}</Text>
                  {h.detalhe && <Text style={estilos.histDetalhe}>{h.detalhe}</Text>}
                  <Text style={estilos.histData}>
                    {new Date(h.criado_em).toLocaleString('pt-BR')}
                    {h.usuario_nome ? ` · ${h.usuario_nome}` : ''}
                  </Text>
                </View>
              ))}
            </ScrollView>
            <TouchableOpacity
              style={[estilos.botaoModal, {backgroundColor: CORES.cinza100, marginTop: 12, alignSelf: 'center'}]}
              onPress={() => setModalHistorico(false)}>
              <Text style={{color: CORES.cinza700, fontWeight: '600'}}>Fechar</Text>
            </TouchableOpacity>
          </View>
        </View>
      </Modal>
    </>
  );
}

const estilos = StyleSheet.create({
  container: {flex: 1, backgroundColor: CORES.azul50},
  scroll: {padding: 14, paddingTop: 10},
  centro: {flex: 1, alignItems: 'center', justifyContent: 'center', padding: 32},
  card: {
    backgroundColor: CORES.branco, borderRadius: 14,
    padding: 16, marginBottom: 12,
    shadowColor: '#10284A', shadowOffset: {width: 0, height: 2},
    shadowOpacity: 0.07, shadowRadius: 6, elevation: 2,
  },
  alertaSucesso: {backgroundColor: CORES.verdeBg, borderRadius: 8, padding: 12, marginBottom: 10},
  alertaSucessoText: {color: CORES.verde, fontWeight: '600', fontSize: 13.5},
  alertaErro: {backgroundColor: CORES.vermelhoBg, borderRadius: 8, padding: 12, marginBottom: 10},
  alertaErroText: {color: CORES.vermelho, fontWeight: '600', fontSize: 13.5},
  osHeader: {flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 10},
  osNum: {fontSize: 13, color: CORES.cinza500, fontWeight: '700'},
  clienteNome: {fontSize: 18, fontWeight: '800', color: CORES.cinza900, marginBottom: 10},
  infoRow: {flexDirection: 'row', alignItems: 'flex-start', marginBottom: 6, gap: 6},
  infoIcon: {fontSize: 14, marginTop: 1},
  infoText: {fontSize: 13.5, color: CORES.cinza700, flex: 1},
  botaoRota: {
    backgroundColor: CORES.azul100, borderRadius: 999,
    paddingVertical: 10, paddingHorizontal: 16,
    alignItems: 'center', marginTop: 10,
  },
  botaoRotaText: {color: CORES.azul700, fontWeight: '600', fontSize: 13.5},
  secaoTitulo: {fontSize: 14, fontWeight: '800', color: CORES.cinza900, marginBottom: 12, letterSpacing: 0.2},
  secaoHeader: {flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 10},
  acoesGrid: {flexDirection: 'row', flexWrap: 'wrap', gap: 10},
  botaoAcao: {paddingHorizontal: 18, paddingVertical: 11, borderRadius: 999, minWidth: 110, alignItems: 'center'},
  botaoPrimario: {
    backgroundColor: CORES.azul700,
    shadowColor: CORES.azul800, shadowOffset: {width: 0, height: 2},
    shadowOpacity: 0.3, shadowRadius: 4, elevation: 3,
  },
  botaoNeutro: {backgroundColor: CORES.cinza100},
  botaoSucesso: {backgroundColor: CORES.verdeBg},
  botaoGestor: {backgroundColor: CORES.azul100},
  botaoAcaoText: {fontWeight: '700', fontSize: 13.5, color: '#fff'},
  gpsIndicador: {
    flexDirection: 'row', alignItems: 'center', gap: 8,
    backgroundColor: '#e5f5ec', borderRadius: 8, padding: 8, marginTop: 12,
  },
  gpsDot: {width: 8, height: 8, borderRadius: 4, backgroundColor: CORES.verde},
  gpsText: {color: CORES.verde, fontSize: 12, fontWeight: '600'},
  textarea: {
    borderWidth: 1, borderColor: CORES.cinza300, borderRadius: 8,
    padding: 12, fontSize: 14, color: CORES.cinza900,
    minHeight: 90, textAlignVertical: 'top',
  },
  input: {
    borderWidth: 1, borderColor: CORES.cinza300, borderRadius: 8,
    paddingHorizontal: 12, paddingVertical: 10, fontSize: 14, color: CORES.cinza900,
  },
  botaoPequeno: {paddingHorizontal: 16, paddingVertical: 9, borderRadius: 999, alignSelf: 'flex-start', marginTop: 10},
  sugestoesProd: {
    borderWidth: 1, borderColor: CORES.cinza100, borderRadius: 8,
    marginVertical: 8, overflow: 'hidden',
  },
  sugestaoProdItem: {
    flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center',
    padding: 10, borderBottomWidth: 1, borderBottomColor: CORES.cinza100,
  },
  sugestaoProdNome: {fontSize: 13.5, fontWeight: '600', color: CORES.cinza900, flex: 1},
  sugestaoProdValor: {fontSize: 13, color: CORES.azul700, fontWeight: '700'},
  produtoRow: {
    flexDirection: 'row', alignItems: 'center',
    paddingVertical: 8, borderBottomWidth: 1, borderColor: CORES.cinza100,
  },
  produtoNome: {fontWeight: '600', fontSize: 13.5, color: CORES.cinza900},
  produtoDetalhe: {fontSize: 12, color: CORES.cinza500, marginTop: 2},
  produtoSubtotal: {fontWeight: '700', fontSize: 13, color: CORES.cinza900},
  totalRow: {flexDirection: 'row', justifyContent: 'space-between', paddingTop: 10, marginTop: 4},
  totalLabel: {fontWeight: '700', color: CORES.cinza700},
  totalValor: {fontWeight: '800', color: CORES.azul900, fontSize: 15},
  vazioInline: {color: CORES.cinza500, fontSize: 13, marginTop: 4},
  miniaturaFoto: {width: 90, height: 90, borderRadius: 8, marginRight: 8, backgroundColor: '#000'},
  miniaturaVideo: {alignItems: 'center', justifyContent: 'center', backgroundColor: '#1c2430'},
  tecnicoRow: {
    flexDirection: 'row', alignItems: 'center', gap: 12,
    paddingVertical: 8, borderBottomWidth: 1, borderColor: CORES.cinza100,
  },
  tecnicoAvatar: {
    width: 36, height: 36, borderRadius: 18,
    backgroundColor: CORES.azul600, alignItems: 'center', justifyContent: 'center',
  },
  tecnicoFoto: {width: 36, height: 36, borderRadius: 18},
  tecnicoIniciais: {color: '#fff', fontWeight: '700', fontSize: 13},
  tecnicoNome: {fontWeight: '600', fontSize: 13.5, color: CORES.cinza900},
  tecnicoResp: {fontSize: 11, color: CORES.azul700, fontWeight: '700'},
  tecnicoItemModal: {
    flexDirection: 'row', alignItems: 'center', gap: 10,
    paddingVertical: 10, borderBottomWidth: 1, borderBottomColor: CORES.cinza100,
  },
  tecnicoItemModalSel: {backgroundColor: CORES.azul50, borderRadius: 8, paddingHorizontal: 6},
  tecnicoCheck: {
    width: 22, height: 22, borderRadius: 11,
    borderWidth: 2, borderColor: CORES.cinza300,
    alignItems: 'center', justifyContent: 'center',
  },
  tecnicoCheckSel: {backgroundColor: CORES.azul700, borderColor: CORES.azul700},
  tecnicoInfo: {fontSize: 12, color: CORES.cinza500, marginTop: 1},
  respBadge: {
    paddingHorizontal: 8, paddingVertical: 4,
    borderRadius: 999, borderWidth: 1, borderColor: CORES.cinza300,
  },
  respBadgeSel: {backgroundColor: CORES.azul700, borderColor: CORES.azul700},
  respBadgeText: {fontSize: 11, fontWeight: '600', color: CORES.cinza500},
  offlineBanner: {
    backgroundColor: '#c62f2f', borderRadius: 8, padding: 10, marginBottom: 8,
    flexDirection: 'row', alignItems: 'center', gap: 0,
  },
  offlineText: {color: '#fff', fontWeight: '700', fontSize: 13, flex: 1},
  timerBox: {
    backgroundColor: '#fffbeb', borderRadius: 10, padding: 10, marginTop: 10,
    alignItems: 'center',
  },
  timerText: {
    color: '#92400e', fontWeight: '900', fontSize: 22, letterSpacing: 2, fontVariant: ['tabular-nums'],
  },
  timerLabel: {color: '#92400e', fontSize: 11, marginTop: 2, opacity: 0.8},
  checkRow: {
    flexDirection: 'row', alignItems: 'center', gap: 10,
    paddingVertical: 9, borderBottomWidth: 1, borderColor: CORES.cinza100,
  },
  checkCircle: {
    width: 24, height: 24, borderRadius: 12,
    borderWidth: 2, borderColor: CORES.cinza300,
    alignItems: 'center', justifyContent: 'center', flexShrink: 0,
  },
  checkCircleFeito: {backgroundColor: CORES.verde, borderColor: CORES.verde},
  checkTexto: {flex: 1, fontSize: 13.5, color: CORES.cinza900},
  checkTextoFeito: {color: CORES.cinza500, textDecorationLine: 'line-through'},
  checkProgress: {
    height: 4, backgroundColor: CORES.cinza100, borderRadius: 2, marginTop: 10, overflow: 'hidden',
  },
  checkProgressBar: {height: 4, backgroundColor: CORES.verde, borderRadius: 2},
  histRow: {marginBottom: 8},
  histAcao: {fontWeight: '700', fontSize: 13, color: CORES.cinza900},
  histDetalhe: {fontSize: 12.5, color: CORES.cinza700, marginTop: 1},
  histData: {fontSize: 11, color: CORES.cinza500, marginTop: 2},
  modalOverlay: {flex: 1, backgroundColor: 'rgba(0,0,0,0.5)', justifyContent: 'flex-end'},
  modalBox: {
    backgroundColor: CORES.branco,
    borderTopLeftRadius: 20, borderTopRightRadius: 20,
    padding: 24, paddingBottom: 32,
  },
  modalTitulo: {fontSize: 17, fontWeight: '800', color: CORES.azul900, marginBottom: 16},
  modalLabel: {fontSize: 13, fontWeight: '600', color: CORES.cinza700, marginBottom: 6},
  modalBotoes: {flexDirection: 'row', gap: 10, marginTop: 4},
  botaoModal: {flex: 1, paddingVertical: 13, borderRadius: 999, alignItems: 'center'},
  dateInput: {
    borderWidth: 1, borderColor: CORES.cinza300, borderRadius: 8,
    paddingHorizontal: 12, paddingVertical: 10, marginBottom: 12,
  },
});
