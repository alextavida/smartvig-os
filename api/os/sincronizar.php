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
 * Monta string de endereço a partir do objeto endereço do GestaoClick.
 * Estrutura: enderecos[N]['endereco'] = { logradouro, numero, bairro, nome_cidade, estado }
 */
function extrairEnderecoGc(?array $enderecos): ?string
{
    if (empty($enderecos) || !is_array($enderecos)) {
        return null;
    }
    $end = $enderecos[0]['endereco'] ?? [];
    if (empty($end)) {
        return null;
    }
    $partes = array_filter([
        trim(($end['logradouro'] ?? '') . ' ' . ($end['numero'] ?? '')),
        $end['complemento'] ?? '',
        $end['bairro'] ?? '',
        $end['nome_cidade'] ?? '',
        $end['estado'] ?? '',
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
            $dataAgendamento = $item['data_prevista'] ?? $item['data_agendamento']
                            ?? $item['data'] ?? $item['data_emissao'] ?? null;

            // --- Descrição / título ---
            $descricaoGc = $item['titulo'] ?? $item['descricao'] ?? $item['problema']
                        ?? $item['observacoes'] ?? $item['servico'] ?? null;

            // --- Cliente: GC pode retornar objeto aninhado OU campos planos ---
            $clienteObj = is_array($item['cliente'] ?? null) ? $item['cliente'] : [];

            // Tenta TODOS os nomes possíveis que o GC pode usar
            $clienteNome = $clienteObj['nome']         ?? $clienteObj['razao_social']
                        ?? $clienteObj['nome_completo'] ?? null;
            if (empty($clienteNome)) {
                // Campos planos no próprio item
                $clienteNome = $item['nome_cliente']   ?? $item['cliente_nome']
                            ?? $item['razao_social']   ?? $item['nome']
                            ?? null;
            }

            $clienteTelefone = $clienteObj['telefone']  ?? $clienteObj['celular']
                            ?? $item['telefone_cliente'] ?? $item['cliente_telefone']
                            ?? $item['telefone']         ?? null;

            $clienteEndereco = extrairEnderecoGc($clienteObj['enderecos'] ?? null);

            // Se cliente_id ainda não veio, tenta pelo objeto
            if ($gcClienteId === null && !empty($clienteObj['id'])) {
                $gcClienteId = (int) $clienteObj['id'];
            }

            // Se ainda sem nome mas temos cliente_id, usa o id como fallback legível
            if (empty($clienteNome) && $gcClienteId) {
                $clienteNome = 'Cliente GC #' . $gcClienteId;
            }

            // --- Upsert ---
            $stmt = $pdo->prepare('SELECT id, situacao_local FROM ordens_servico WHERE gc_os_id = :gc_os_id LIMIT 1');
            $stmt->execute(['gc_os_id' => $gcOsId]);
            $existente = $stmt->fetch();

            if ($existente) {
                $pdo->prepare(
                    'UPDATE ordens_servico SET
                        gc_situacao_id   = :gc_situacao_id,
                        gc_cliente_id    = :gc_cliente_id,
                        codigo           = :codigo,
                        cliente_nome     = COALESCE(:cliente_nome, cliente_nome),
                        cliente_endereco = COALESCE(:cliente_endereco, cliente_endereco),
                        cliente_telefone = COALESCE(:cliente_telefone, cliente_telefone),
                        observacoes      = COALESCE(observacoes, :descricao_gc),
                        sincronizado_gc  = 1
                     WHERE id = :id'
                )->execute([
                    'gc_situacao_id'  => $gcSituacaoId,
                    'gc_cliente_id'   => $gcClienteId,
                    'codigo'          => $codigo,
                    'cliente_nome'    => $clienteNome,
                    'cliente_endereco'=> $clienteEndereco,
                    'cliente_telefone'=> $clienteTelefone,
                    'descricao_gc'    => $descricaoGc,
                    'id'              => $existente['id'],
                ]);
                $atualizadas++;
            } else {
                $situacaoLocal = $situacoesMap[$gcSituacaoId] ?? 'aberto';
                $pdo->prepare(
                    'INSERT INTO ordens_servico
                        (gc_os_id, gc_cliente_id, gc_situacao_id, codigo, cliente_nome, cliente_endereco,
                         cliente_telefone, observacoes, situacao_local, data_agendamento, sincronizado_gc)
                     VALUES
                        (:gc_os_id, :gc_cliente_id, :gc_situacao_id, :codigo, :cliente_nome, :cliente_endereco,
                         :cliente_telefone, :observacoes, :situacao_local, :data_agendamento, 1)'
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
                ]);
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
