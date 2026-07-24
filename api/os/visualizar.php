<?php
/**
 * GET /api/os/visualizar?id=X
 * Retorna detalhes completos da OS: dados, tecnicos atribuidos, historico e midias.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/os_helpers.php';
require_once __DIR__ . '/../../config/gestaoclick.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderErro('Metodo nao permitido. Use GET.', 405);
}

$payload = exigirAutenticacao();
$pdo = obterConexao();

$osId = (int) ($_GET['id'] ?? 0);
if ($osId <= 0) {
    responderErro('Parametro id e obrigatorio.', 422);
}

$os = buscarOsOuFalhar($pdo, $osId, $payload);

$tecnicos = listarTecnicosDaOs($pdo, $osId);

$stmtHistorico = $pdo->prepare(
    'SELECT h.id, h.acao, h.detalhe, h.criado_em, u.nome AS usuario_nome
     FROM historico_os h
     LEFT JOIN usuarios u ON u.id = h.usuario_id
     WHERE h.os_id = :os_id
     ORDER BY h.criado_em DESC'
);
$stmtHistorico->execute(['os_id' => $osId]);
$historico = $stmtHistorico->fetchAll();

$stmtMidias = $pdo->prepare(
    'SELECT id, tipo, caminho_arquivo, nome_arquivo, tamanho_bytes, criado_em
     FROM midias_os WHERE os_id = :os_id ORDER BY criado_em DESC'
);
$stmtMidias->execute(['os_id' => $osId]);
$midias = $stmtMidias->fetchAll();

$esquema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
foreach ($midias as &$midia) {
    $midia['url'] = "{$esquema}://{$host}/app-tecnicos/{$midia['caminho_arquivo']}";
}
unset($midia);

$os['produtos'] = $os['produtos_json'] ? json_decode($os['produtos_json'], true) : [];
unset($os['produtos_json'], $os['midias_json']);

// Busca produtos e serviços ao vivo do GestãoClick (silencioso em caso de falha)
$gcProdutos = [];
$gcServicos = [];
$gcEquipamentos = [];
if (!empty($os['gc_os_id'])) {
    try {
        $gc     = new GestaoClickAPI();
        $gcDados = $gc->visualizarOS((int) $os['gc_os_id']);

        $primeiraNaoVazia = static function (array ...$fontes): string {
            $campos = ['nome', 'descricao', 'nome_produto', 'nome_servico',
                       'descricao_produto', 'descricao_servico', 'titulo'];
            foreach ($fontes as $fonte) {
                if (!is_array($fonte)) { continue; }
                foreach ($campos as $c) {
                    if (isset($fonte[$c]) && is_string($fonte[$c]) && $fonte[$c] !== '') {
                        return $fonte[$c];
                    }
                }
            }
            return '';
        };

        foreach (($gcDados['produtos'] ?? []) as $pItem) {
            if (!is_array($pItem)) { continue; }
            $pObj = is_array($pItem['produto'] ?? null) ? $pItem['produto'] : [];
            $nome = $primeiraNaoVazia($pObj, $pItem);
            $cod  = (string) ($pObj['codigo'] ?? $pItem['codigo'] ?? '');
            $gcProdutos[] = [
                'nome'        => ($cod && $nome) ? "{$cod} - {$nome}" : ($nome ?: $cod),
                'quantidade'  => (float) ($pItem['quantidade'] ?? 1),
                'valor_venda' => (float) ($pItem['valor_venda'] ?? $pItem['valor'] ?? 0),
            ];
        }

        foreach (($gcDados['servicos'] ?? []) as $sItem) {
            if (!is_array($sItem)) { continue; }
            $sObj = is_array($sItem['servico'] ?? null) ? $sItem['servico'] : [];
            $nome = $primeiraNaoVazia($sObj, $sItem);
            $cod  = (string) ($sObj['codigo'] ?? $sItem['codigo'] ?? '');
            $gcServicos[] = [
                'nome'       => ($cod && $nome) ? "{$cod} - {$nome}" : ($nome ?: $cod),
                'quantidade' => (float) ($sItem['quantidade'] ?? 1),
                'valor'      => (float) ($sItem['valor'] ?? $sItem['valor_venda'] ?? 0),
            ];
        }

        foreach (($gcDados['equipamentos'] ?? []) as $eqItem) {
            $eq = is_array($eqItem['equipamento'] ?? null) ? $eqItem['equipamento'] : $eqItem;
            if (!is_array($eq)) { continue; }
            $gcEquipamentos[] = [
                'tipo'     => $eq['equipamento'] ?? $eq['tipo'] ?? null,
                'marca'    => $eq['marca']   ?? null,
                'modelo'   => $eq['modelo']  ?? null,
                'serie'    => $eq['serie']   ?? null,
                'defeitos' => $eq['defeitos'] ?? null,
                'solucao'  => $eq['solucao'] ?? null,
                'laudo'    => $eq['laudo']   ?? null,
            ];
        }
    } catch (GestaoClickApiException) {
        // Falha silenciosa — dados locais continuam disponíveis
    }
}

// Retorna tudo flat: OSDetalhe = campos da OS + tecnicos + historico + midias + dados GC
responderSucesso(array_merge($os, [
    'tecnicos'        => $tecnicos,
    'historico'       => $historico,
    'midias'          => $midias,
    'gc_produtos'     => $gcProdutos,
    'gc_servicos'     => $gcServicos,
    'gc_equipamentos' => $gcEquipamentos,
]));
