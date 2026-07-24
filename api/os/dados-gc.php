<?php
/**
 * GET /api/os/dados-gc?gc_os_id=X (somente gestor)
 * Busca uma OS diretamente no GestaoClick por ID e retorna os dados brutos.
 * Usado para exibir/reconciliar dados no detalhe da OS.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/gestaoclick.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderErro('Metodo nao permitido. Use GET.', 405);
}

$payload = exigirAutenticacao();
// Técnicos também podem acessar — mas apenas OS que lhes pertencem
$gcOsId = (int) ($_GET['gc_os_id'] ?? 0);
if ($gcOsId <= 0) {
    responderErro('Parametro gc_os_id obrigatorio.', 422);
}

if ($payload['perfil'] !== 'gestor') {
    // Verifica se o técnico tem acesso a esta OS via gc_os_id
    $pdo = obterConexao();
    $tid = (int) $payload['usuario_id'];
    $stmtAcesso = $pdo->prepare(
        'SELECT 1 FROM ordens_servico os
         WHERE os.gc_os_id = :gc_os_id
           AND (EXISTS (SELECT 1 FROM os_tecnicos ot WHERE ot.os_id = os.id AND ot.tecnico_id = :tid)
                OR os.tecnico_id = :tid2)
         LIMIT 1'
    );
    $stmtAcesso->execute(['gc_os_id' => $gcOsId, 'tid' => $tid, 'tid2' => $tid]);
    if (!$stmtAcesso->fetch()) {
        responderErro('Acesso negado a esta OS.', 403);
    }
}

try {
    $gc  = new GestaoClickAPI();
    $raw = $gc->visualizarOS($gcOsId);

    // Extrai campos de interesse independente do nome exato
    $chaves = array_keys($raw);

    // Tenta extrair nome do cliente de todos os campos possíveis
    $clienteObj  = is_array($raw['cliente'] ?? null) ? $raw['cliente'] : [];
    $clienteNome = $clienteObj['nome']          ?? $clienteObj['razao_social']
                ?? $raw['nome_cliente']         ?? $raw['cliente_nome']
                ?? $raw['razao_social']         ?? null;

    $clienteTel  = $clienteObj['telefone']      ?? $clienteObj['celular']
                ?? $raw['telefone_cliente']     ?? $raw['telefone']
                ?? null;

    // Endereço do cliente: estrutura enderecos[0].endereco
    $endCliente = null;
    $enderecos  = $clienteObj['enderecos'] ?? $raw['enderecos'] ?? [];
    if (!empty($enderecos)) {
        $endObj  = $enderecos[0]['endereco'] ?? $enderecos[0] ?? [];
        $partes  = array_filter([
            trim(($endObj['logradouro'] ?? $endObj['endereco'] ?? '') . ' ' . ($endObj['numero'] ?? '')),
            $endObj['complemento'] ?? '',
            $endObj['bairro']      ?? '',
            $endObj['nome_cidade'] ?? $endObj['cidade'] ?? '',
            $endObj['estado']      ?? '',
        ]);
        $endCliente = trim(implode(', ', $partes)) ?: null;
    }

    // Produtos — GC aninha: produtos[n].produto = { nome, codigo, ... }, produtos[n].quantidade, produtos[n].valor_venda
    $produtosRaw = $raw['produtos'] ?? $raw['itens'] ?? $raw['pecas'] ?? [];
    $produtos = [];
    foreach ((is_array($produtosRaw) ? $produtosRaw : []) as $pItem) {
        if (!is_array($pItem)) { continue; }
        $pObj = (is_array($pItem['produto'] ?? null)) ? $pItem['produto'] : $pItem;
        $nomeProd = $pObj['nome'] ?? $pObj['descricao'] ?? $pItem['nome'] ?? $pItem['descricao'] ?? '';
        $codProd  = $pObj['codigo'] ?? $pItem['codigo'] ?? '';
        $produtos[] = [
            'nome'        => $codProd ? "{$codProd} - {$nomeProd}" : $nomeProd,
            'quantidade'  => $pItem['quantidade']  ?? $pItem['qtd'] ?? 1,
            'valor_venda' => $pItem['valor_venda']  ?? $pItem['valor'] ?? $pObj['valor_venda'] ?? 0,
        ];
    }

    // Serviços — GC aninha: servicos[n].servico = { nome, codigo, ... }, servicos[n].quantidade, servicos[n].valor
    $servicosRaw = $raw['servicos'] ?? [];
    $servicos = [];
    foreach ((is_array($servicosRaw) ? $servicosRaw : []) as $sItem) {
        if (!is_array($sItem)) { continue; }
        $sObj = (is_array($sItem['servico'] ?? null)) ? $sItem['servico'] : $sItem;
        $nomeSrv = $sObj['nome'] ?? $sObj['descricao'] ?? $sItem['nome'] ?? $sItem['descricao'] ?? '';
        $codSrv  = $sObj['codigo'] ?? $sItem['codigo'] ?? '';
        $servicos[] = [
            'nome'       => $codSrv ? "{$codSrv} - {$nomeSrv}" : $nomeSrv,
            'quantidade' => $sItem['quantidade'] ?? $sItem['qtd'] ?? 1,
            'valor'      => $sItem['valor']       ?? $sItem['valor_venda'] ?? $sObj['valor'] ?? 0,
        ];
    }

    // Equipamentos — confirmados como equipamentos[N].equipamento = { equipamento, defeitos, solucao, laudo, marca, modelo, serie }
    $equipamentosExtrato = [];
    foreach (($raw['equipamentos'] ?? []) as $eqItem) {
        $eq = $eqItem['equipamento'] ?? $eqItem ?? [];
        if (!is_array($eq)) { continue; }
        $equipamentosExtrato[] = [
            'tipo'       => $eq['equipamento'] ?? $eq['tipo'] ?? null,
            'marca'      => $eq['marca']        ?? null,
            'modelo'     => $eq['modelo']       ?? null,
            'serie'      => $eq['serie']        ?? null,
            'defeitos'   => $eq['defeitos']     ?? null,
            'solucao'    => $eq['solucao']      ?? null,
            'laudo'      => $eq['laudo']        ?? null,
            'acessorios' => $eq['acessorios']   ?? null,
        ];
    }

    responderSucesso([
        'chaves'   => $chaves,
        'raw'      => $raw,
        'extraido' => [
            'cliente_nome'     => $clienteNome,
            'cliente_telefone' => $clienteTel,
            'cliente_endereco' => $endCliente,
            'codigo'           => $raw['codigo'] ?? $raw['numero'] ?? $raw['numero_os'] ?? null,
            'data_entrada'     => $raw['data_entrada'] ?? $raw['data_agendamento'] ?? $raw['data'] ?? null,
            'situacao_id'      => $raw['situacao_id']    ?? null,
            'nome_situacao'    => $raw['nome_situacao']  ?? null,
            'nome_tecnico'     => $raw['nome_tecnico']   ?? null,
            'observacoes'      => $raw['observacoes']    ?? null,
            'produtos'         => $produtos,
            'servicos'         => $servicos,
            'equipamentos'     => $equipamentosExtrato,
        ],
    ]);
} catch (GestaoClickApiException $e) {
    responderErro($e->getMessage(), 502);
}
