<?php
/**
 * POST /api/orcamentos/sincronizar-gc
 * Importa orçamentos do GestãoClick para o banco local.
 * Cria novos registros e atualiza status dos existentes.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/gestaoclick.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErro('Use POST.', 405);
}

$payload = exigirAutenticacao();
exigirPerfil($payload, ['gestor', 'supervisor']);

$pdo = obterConexao();
$gc  = new GestaoClickAPI();

// Mapeamento de status GC → local
$mapaStatus = [
    'em_aberto'  => 'enviado',
    'aberto'     => 'enviado',
    'pendente'   => 'enviado',
    'enviado'    => 'enviado',
    'aprovado'   => 'aprovado',
    'recusado'   => 'recusado',
    'cancelado'  => 'recusado',
    'convertido' => 'convertido',
    'finalizado' => 'convertido',
];

$stmtVerifica = $pdo->prepare('SELECT id, status FROM orcamentos WHERE gc_orcamento_id = :gcid');
$stmtInsert   = $pdo->prepare(
    "INSERT INTO orcamentos
     (gc_orcamento_id, gc_cliente_id, cliente_nome, cliente_email, cliente_telefone,
      observacoes, validade_dias, status, token, codigo, criado_por, criado_em)
     VALUES (:gcid, :gccli, :nome, :email, :tel,
             :obs, :val, :status, :tok, :cod, 1, :criado)"
);
// Não atualiza status se já estiver em estado final local (aprovado/convertido)
$stmtUpdate = $pdo->prepare(
    "UPDATE orcamentos SET status = :status
     WHERE gc_orcamento_id = :gcid
       AND status NOT IN ('aprovado', 'convertido')"
);
$stmtItemIns = $pdo->prepare(
    "INSERT INTO orcamento_itens (orcamento_id, tipo, descricao, quantidade, valor_unitario)
     VALUES (:orc, :tipo, :desc, :qtd, :vl)"
);

$importados  = 0;
$atualizados = 0;
$pagina      = 1;

try {
    do {
        $resp    = $gc->listarOrcamentos($pagina);
        $lista   = $resp['data'] ?? $resp['orcamentos'] ?? [];
        $proxima = $resp['meta']['proxima_pagina'] ?? null;

        foreach ($lista as $gcOrc) {
            if (empty($gcOrc['id'])) { continue; }
            $gcId = (int) $gcOrc['id'];

            $stmtVerifica->execute([':gcid' => $gcId]);
            $local = $stmtVerifica->fetch();

            $statusGcRaw = strtolower((string)($gcOrc['status'] ?? 'enviado'));
            $statusLocal = $mapaStatus[$statusGcRaw] ?? 'enviado';

            if (!$local) {
                // Novo orçamento vindo do GC
                $seq    = (int) $pdo->query("SELECT COUNT(*) FROM orcamentos")->fetchColumn() + 1;
                $codigo = 'GC-' . date('Y') . '-' . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
                $token  = bin2hex(random_bytes(16));

                // GC pode retornar diferentes campos dependendo da versão
                $clienteNome = $gcOrc['cliente_nome']
                    ?? $gcOrc['nome_cliente']
                    ?? ($gcOrc['cliente']['nome'] ?? ('Cliente GC #' . $gcId));

                $clienteEmail = $gcOrc['cliente_email']
                    ?? $gcOrc['email']
                    ?? ($gcOrc['cliente']['email'] ?? null);

                $clienteTel = $gcOrc['cliente_telefone']
                    ?? $gcOrc['telefone']
                    ?? ($gcOrc['cliente']['telefone'] ?? null);

                $criado = $gcOrc['data_cadastro']
                    ?? $gcOrc['criado_em']
                    ?? $gcOrc['created_at']
                    ?? date('Y-m-d H:i:s');

                $stmtInsert->execute([
                    ':gcid'   => $gcId,
                    ':gccli'  => $gcOrc['cliente_id'] ?? null,
                    ':nome'   => $clienteNome,
                    ':email'  => $clienteEmail,
                    ':tel'    => $clienteTel,
                    ':obs'    => $gcOrc['observacoes'] ?? null,
                    ':val'    => max(1, (int)($gcOrc['validade_dias'] ?? 7)),
                    ':status' => $statusLocal,
                    ':tok'    => $token,
                    ':cod'    => $codigo,
                    ':criado' => $criado,
                ]);
                $orcId = (int) $pdo->lastInsertId();

                // Importar itens do orçamento GC
                $itensGc = $gcOrc['itens'] ?? $gcOrc['produtos'] ?? $gcOrc['orcamento_itens'] ?? [];
                foreach ((array)$itensGc as $item) {
                    $descricao = $item['descricao'] ?? $item['nome'] ?? $item['produto_nome'] ?? 'Item GC';
                    $quantidade = max(0.01, (float)($item['quantidade'] ?? 1));
                    $valorUnit  = max(0, (float)($item['valor_unitario'] ?? $item['valor'] ?? $item['preco'] ?? 0));
                    $tipo = (str_contains(strtolower($descricao), 'serv') || isset($item['servico'])) ? 'servico' : 'peca';

                    $stmtItemIns->execute([
                        ':orc'  => $orcId,
                        ':tipo' => $tipo,
                        ':desc' => $descricao,
                        ':qtd'  => $quantidade,
                        ':vl'   => $valorUnit,
                    ]);
                }

                $importados++;
            } else {
                // Atualiza status se mudou e ainda não está finalizado localmente
                $stmtUpdate->execute([':status' => $statusLocal, ':gcid' => $gcId]);
                if ($stmtUpdate->rowCount() > 0) {
                    $atualizados++;
                }
            }
        }

        $pagina++;
    } while (!empty($lista) && $proxima !== null);

    responderSucesso([
        'importados'  => $importados,
        'atualizados' => $atualizados,
        'paginas'     => $pagina - 1,
    ]);

} catch (GestaoClickApiException $e) {
    responderErro('Falha ao comunicar com GestãoClick: ' . $e->getMessage(), 502);
}
