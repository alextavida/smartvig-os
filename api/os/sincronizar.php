<?php
/**
 * POST /api/os/sincronizar (somente gestor)
 * Busca as OSs no GestaoClick e espelha (upsert) na tabela local ordens_servico.
 * OSs ja existentes localmente tem apenas os dados de espelho atualizados
 * (codigo, dados do cliente, situacao remota) - a situacao_local e o laudo
 * continuam controlados pelo tecnico/gestor dentro do app.
 * Pode ser chamado manualmente pelo painel ou agendado via cron/php-cli.
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
$gc = new GestaoClickAPI();

function mapaSituacaoLocalPorGcId(): array
{
    $mapa = [];
    foreach (['aberto', 'em_andamento', 'pausado', 'reagendado', 'concluido', 'cancelado'] as $situacao) {
        $gcId = obterSituacaoGcId($situacao);
        if ($gcId !== null) {
            $mapa[$gcId] = $situacao;
        }
    }
    return $mapa;
}

$mapaSituacoes = mapaSituacaoLocalPorGcId();

$criadas = 0;
$atualizadas = 0;
$erros = [];

try {
    $pagina = 1;
    $maximoPaginas = 50; // seguranca contra loop infinito

    do {
        $resposta = $gc->listarOS($pagina);
        $itens = $resposta['data'] ?? $resposta['dados'] ?? [];
        if (!is_array($itens) || empty($itens)) {
            break;
        }

        foreach ($itens as $item) {
            $gcOsId = (int) ($item['id'] ?? 0);
            if ($gcOsId <= 0) {
                continue;
            }

            $gcSituacaoId = isset($item['situacao_id']) ? (int) $item['situacao_id'] : null;
            $clienteNome = $item['cliente']['nome'] ?? $item['cliente_nome'] ?? null;
            $clienteEndereco = $item['cliente']['endereco'] ?? null;
            $clienteTelefone = $item['cliente']['telefone'] ?? $item['cliente']['celular'] ?? null;
            $codigo = $item['codigo'] ?? $item['numero'] ?? null;
            $dataAgendamento = $item['data'] ?? null;

            $stmt = $pdo->prepare('SELECT id FROM ordens_servico WHERE gc_os_id = :gc_os_id LIMIT 1');
            $stmt->execute(['gc_os_id' => $gcOsId]);
            $existente = $stmt->fetch();

            if ($existente) {
                $pdo->prepare(
                    'UPDATE ordens_servico SET
                        gc_situacao_id = :gc_situacao_id,
                        gc_cliente_id = :gc_cliente_id,
                        codigo = :codigo,
                        cliente_nome = COALESCE(:cliente_nome, cliente_nome),
                        cliente_endereco = COALESCE(:cliente_endereco, cliente_endereco),
                        cliente_telefone = COALESCE(:cliente_telefone, cliente_telefone),
                        sincronizado_gc = 1
                     WHERE id = :id'
                )->execute([
                    'gc_situacao_id' => $gcSituacaoId,
                    'gc_cliente_id' => $item['cliente_id'] ?? null,
                    'codigo' => $codigo,
                    'cliente_nome' => $clienteNome,
                    'cliente_endereco' => $clienteEndereco,
                    'cliente_telefone' => $clienteTelefone,
                    'id' => $existente['id'],
                ]);
                $atualizadas++;
            } else {
                $situacaoLocal = $mapaSituacoes[$gcSituacaoId] ?? 'aberto';
                $pdo->prepare(
                    'INSERT INTO ordens_servico
                        (gc_os_id, gc_cliente_id, gc_situacao_id, codigo, cliente_nome, cliente_endereco,
                         cliente_telefone, situacao_local, data_agendamento, sincronizado_gc)
                     VALUES (:gc_os_id, :gc_cliente_id, :gc_situacao_id, :codigo, :cliente_nome, :cliente_endereco,
                             :cliente_telefone, :situacao_local, :data_agendamento, 1)'
                )->execute([
                    'gc_os_id' => $gcOsId,
                    'gc_cliente_id' => $item['cliente_id'] ?? null,
                    'gc_situacao_id' => $gcSituacaoId,
                    'codigo' => $codigo,
                    'cliente_nome' => $clienteNome,
                    'cliente_endereco' => $clienteEndereco,
                    'cliente_telefone' => $clienteTelefone,
                    'situacao_local' => $situacaoLocal,
                    'data_agendamento' => $dataAgendamento,
                ]);
                $criadas++;
            }
        }

        $pagina++;
    } while (count($itens) >= 100 && $pagina <= $maximoPaginas);
} catch (GestaoClickApiException $e) {
    $erros[] = $e->getMessage();
}

responderSucesso([
    'criadas' => $criadas,
    'atualizadas' => $atualizadas,
    'erros' => $erros,
]);
