<?php
/**
 * POST /api/os/sincronizar (somente gestor)
 * Busca TODAS as OSs no GestaoClick (paginacao via meta.proxima_pagina) e
 * faz upsert na tabela local ordens_servico.
 * Situacao_local e laudo continuam controlados internamente; apenas os dados
 * espelhados (cliente, codigo, descricao, situacao GC) sao sobrescritos.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/gestaoclick.php';
require_once __DIR__ . '/../../config/os_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErro('Metodo nao permitido. Use POST.', 405);
}

$payload = exigirAutenticacao();
exigirPerfil($payload, ['gestor']);

$pdo = obterConexao();
$gc  = new GestaoClickAPI();

/**
 * Extrai nome do cliente de um item de OS do GC (tenta todos os campos conhecidos).
 */
function extrairNomeClienteGc(array $item): ?string
{
    $obj = is_array($item['cliente'] ?? null) ? $item['cliente'] : [];

    return $obj['nome']         ?? $obj['razao_social']   ?? $obj['nome_completo']
        ?? $item['nome_cliente']  ?? $item['cliente_nome']
        ?? $item['razao_social']  ?? $item['nome']
        ?? null;
}

/**
 * Extrai telefone do cliente de um item de OS do GC.
 */
function extrairTelefoneClienteGc(array $item): ?string
{
    $obj = is_array($item['cliente'] ?? null) ? $item['cliente'] : [];

    return $obj['telefone'] ?? $obj['celular']
        ?? $item['telefone_cliente'] ?? $item['cliente_telefone']
        ?? $item['telefone'] ?? null;
}

/**
 * Monta string de endereço a partir de uma lista de endereços do GestaoClick.
 * Suporta estrutura: enderecos[N]['endereco'] = { logradouro, numero, ... }
 * E estrutura plana:  enderecos[N] = { logradouro, numero, ... }
 */
function extrairEnderecoGc(?array $enderecos): ?string
{
    if (empty($enderecos) || !is_array($enderecos)) {
        return null;
    }
    // Tenta estrutura aninhada (enderecos[0]['endereco']) e plana (enderecos[0])
    $end = $enderecos[0]['endereco'] ?? $enderecos[0] ?? [];
    if (empty($end) || !is_array($end)) {
        return null;
    }
    $partes = array_filter([
        trim(($end['logradouro'] ?? $end['endereco'] ?? '') . ' ' . ($end['numero'] ?? '')),
        $end['complemento'] ?? '',
        $end['bairro']      ?? '',
        $end['nome_cidade'] ?? $end['cidade'] ?? '',
        $end['estado']      ?? '',
    ]);
    $str = trim(implode(', ', $partes));
    return $str !== '' ? $str : null;
}

/** Constroi mapa gc_situacao_id → situacao_local a partir das configuracoes. */
function mapaSituacoes(): array
{
    $mapa = [];
    foreach (['aberto', 'em_andamento', 'pausado', 'reagendado', 'concluido', 'cancelado'] as $s) {
        $gcId = obterSituacaoGcId($s);
        if ($gcId !== null) {
            $mapa[$gcId] = $s;
        }
    }
    return $mapa;
}

$situacoesMap = mapaSituacoes();
// force=1 → sobrescreve cliente_nome mesmo se o registro já tem valor (corrige syncs antigos)
$body = json_decode(file_get_contents('php://input'), true) ?? [];
$forceAtualizar = !empty($body['force']) || !empty($_GET['force']);
$criadas      = 0;
$atualizadas  = 0;
$erros        = [];

try {
    $pagina     = 1;
    $maxPaginas = 200; // segurança contra loop infinito (200 × 20 = 4000 OS)

    do {
        $resposta = $gc->listarOS($pagina);
        $itens    = $resposta['data'] ?? $resposta['dados'] ?? [];

        if (!is_array($itens) || empty($itens)) {
            break;
        }

        foreach ($itens as $item) {
            $gcOsId = (int) ($item['id'] ?? 0);
            if ($gcOsId <= 0) {
                continue;
            }

            // --- Código da OS (GC pode usar vários nomes) ---
            $codigo = $item['codigo'] ?? $item['numero'] ?? $item['numero_os']
                   ?? $item['cod_os'] ?? null;

            // --- Situação e datas ---
            $gcSituacaoId    = isset($item['situacao_id']) ? (int) $item['situacao_id'] : null;
            $gcClienteId     = isset($item['cliente_id']) ? (int) $item['cliente_id'] : null;
            // data_entrada é o campo confirmado no GestãoClick
            $dataAgendamento = $item['data_entrada']     ?? $item['data_agendamento']
                            ?? $item['data_prevista']   ?? $item['data']
                            ?? $item['data_emissao']    ?? null;

            // --- Equipamentos (CONTROLE DE ACESSO, CFTV, etc.) ---
            $eqArr   = $item['equipamentos'] ?? [];
            $eqTexto = '';
            if (!empty($eqArr) && is_array($eqArr[0]['equipamento'] ?? null)) {
                $eq     = $eqArr[0]['equipamento'];
                $partes = array_filter([
                    !empty($eq['equipamento']) ? 'Equipamento: ' . $eq['equipamento'] : '',
                    !empty($eq['marca'])       ? 'Marca: '       . $eq['marca']       : '',
                    !empty($eq['modelo'])      ? 'Modelo: '      . $eq['modelo']      : '',
                    !empty($eq['serie'])       ? 'Série: '       . $eq['serie']       : '',
                    !empty($eq['defeitos'])    ? 'Defeito: '     . $eq['defeitos']    : '',
                ]);
                $eqTexto = implode("\n", $partes);
            }

            // --- Descrição: observacoes do GC > equipamentos > titulo/descricao ---
            $descricaoGc = ($item['observacoes'] ?: null)
                        ?? ($eqTexto ?: null)
                        ?? ($item['titulo'] ?? $item['descricao'] ?? $item['problema'] ?? null);

            // --- Cliente: GC pode retornar objeto aninhado OU campos planos ---
            $clienteNome     = extrairNomeClienteGc($item);
            $clienteTelefone = extrairTelefoneClienteGc($item);

            $clienteObj      = is_array($item['cliente'] ?? null) ? $item['cliente'] : [];
            $clienteEndereco = extrairEnderecoGc($clienteObj['enderecos'] ?? $item['enderecos'] ?? null);

            // Se cliente_id ainda não veio, tenta pelo objeto
            if ($gcClienteId === null && !empty($clienteObj['id'])) {
                $gcClienteId = (int) $clienteObj['id'];
            }

            // Se ainda sem nome mas temos cliente_id, usa o id como fallback legível
            if (empty($clienteNome) && $gcClienteId) {
                $clienteNome = 'Cliente GC #' . $gcClienteId;
            }

            // Normaliza vazios para null (COALESCE ignora null, não string vazia)
            $clienteNome     = ($clienteNome     !== '' ? $clienteNome     : null);
            $clienteTelefone = ($clienteTelefone !== '' ? $clienteTelefone : null);
            $clienteEndereco = ($clienteEndereco !== '' ? $clienteEndereco : null);

            // GestãoClick usa "CONSUMIDOR" como cliente padrão quando não há cliente real.
            // Nunca sobrescrever o nome local com esse valor genérico.
            $nomesGenericos = ['CONSUMIDOR', 'CONSUMIDOR FINAL', 'SEM CLIENTE', 'NÃO INFORMADO', 'NAO INFORMADO'];
            if ($clienteNome !== null && in_array(mb_strtoupper(trim($clienteNome)), $nomesGenericos, true)) {
                $clienteNome = null;
            }

            // --- Upsert ---
            $stmt = $pdo->prepare('SELECT id, situacao_local FROM ordens_servico WHERE gc_os_id = :gc_os_id LIMIT 1');
            $stmt->execute(['gc_os_id' => $gcOsId]);
            $existente = $stmt->fetch();

            if ($existente) {
                // force=1 → sempre sobrescreve cliente_nome (corrige registros com nome vazio de syncs antigos)
                $sqlNome = $forceAtualizar ? ':cliente_nome' : 'COALESCE(:cliente_nome, NULLIF(cliente_nome, \'\'))';
                $pdo->prepare(
                    "UPDATE ordens_servico SET
                        gc_situacao_id   = :gc_situacao_id,
                        gc_cliente_id    = COALESCE(:gc_cliente_id, gc_cliente_id),
                        codigo           = COALESCE(:codigo, codigo),
                        cliente_nome     = {$sqlNome},
                        cliente_telefone = COALESCE(:cliente_telefone, cliente_telefone),
                        cliente_endereco = COALESCE(:cliente_endereco, cliente_endereco),
                        data_agendamento = COALESCE(:data_agendamento, data_agendamento),
                        sincronizado_gc  = 1
                     WHERE id = :id"
                )->execute([
                    'gc_situacao_id'   => $gcSituacaoId,
                    'gc_cliente_id'    => $gcClienteId,
                    'codigo'           => $codigo,
                    'cliente_nome'     => $clienteNome,
                    'cliente_telefone' => $clienteTelefone,
                    'cliente_endereco' => $clienteEndereco,
                    'data_agendamento' => $dataAgendamento,
                    'id'               => $existente['id'],
                ]);
                $atualizadas++;
            } else {
                $situacaoLocal = $situacoesMap[$gcSituacaoId] ?? 'aberto';

                // Tenta mapear o nome_tecnico/tecnico_id do GC para um usuário local
                $gcNomeTecnico = $item['nome_tecnico'] ?? null;
                $tecnicoLocalId = null;
                if ($gcNomeTecnico) {
                    $stmtTec = $pdo->prepare("SELECT id FROM usuarios WHERE perfil='tecnico' AND ativo=1 AND nome LIKE :nome LIMIT 1");
                    $stmtTec->execute(['nome' => '%' . trim($gcNomeTecnico) . '%']);
                    $tecnicoLocal = $stmtTec->fetch();
                    if ($tecnicoLocal) {
                        $tecnicoLocalId = (int) $tecnicoLocal['id'];
                    }
                }

                $pdo->prepare(
                    'INSERT INTO ordens_servico
                        (gc_os_id, gc_cliente_id, gc_situacao_id, codigo, cliente_nome, cliente_endereco,
                         cliente_telefone, observacoes, situacao_local, prioridade, data_agendamento,
                         tecnico_id, sincronizado_gc)
                     VALUES
                        (:gc_os_id, :gc_cliente_id, :gc_situacao_id, :codigo, :cliente_nome, :cliente_endereco,
                         :cliente_telefone, :observacoes, :situacao_local, \'baixo\', :data_agendamento,
                         :tecnico_id, 1)'
                )->execute([
                    'gc_os_id'        => $gcOsId,
                    'gc_cliente_id'   => $gcClienteId,
                    'gc_situacao_id'  => $gcSituacaoId,
                    'codigo'          => $codigo,
                    'cliente_nome'    => $clienteNome,
                    'cliente_endereco'=> $clienteEndereco,
                    'cliente_telefone'=> $clienteTelefone,
                    'observacoes'     => $descricaoGc,
                    'situacao_local'  => $situacaoLocal,
                    'data_agendamento'=> $dataAgendamento,
                    'tecnico_id'      => $tecnicoLocalId,
                ]);

                // Se mapeou técnico local, insere na tabela os_tecnicos
                if ($tecnicoLocalId) {
                    $novaOsId = (int) $pdo->lastInsertId();
                    $pdo->prepare(
                        'INSERT IGNORE INTO os_tecnicos (os_id, tecnico_id, responsavel) VALUES (:os_id, :tecnico_id, 1)'
                    )->execute(['os_id' => $novaOsId, 'tecnico_id' => $tecnicoLocalId]);
                }
                $criadas++;
            }
        }

        // Usa meta.proxima_pagina do GC (correto para qualquer tamanho de página)
        $proximaPagina = $resposta['meta']['proxima_pagina'] ?? null;
        $pagina++;
    } while ($proximaPagina !== null && $pagina <= $maxPaginas);

} catch (GestaoClickApiException $e) {
    $erros[] = $e->getMessage();
}

responderSucesso([
    'criadas'     => $criadas,
    'atualizadas' => $atualizadas,
    'erros'       => $erros,
]);
