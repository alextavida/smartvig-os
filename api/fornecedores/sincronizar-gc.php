<?php
/**
 * POST /api/fornecedores/sincronizar-gc
 * Importa TODOS os fornecedores do GestaoClick para a tabela local.
 * Faz upsert: insere novos, atualiza existentes (por gc_id ou CNPJ).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/gestaoclick.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErro('Metodo nao permitido.', 405);
}

$payload = exigirAutenticacao();
if (!in_array($payload['perfil'], ['gestor', 'supervisor'], true)) {
    responderErro('Acesso negado.', 403);
}

try {
    $pdo = obterConexao();
    $gc  = new GestaoClickAPI();

    $inseridos   = 0;
    $atualizados = 0;
    $ignorados   = 0;
    $pagina      = 1;

    $stmtPorGcId = $pdo->prepare(
        'SELECT id FROM fornecedores WHERE gc_id = :gc_id LIMIT 1'
    );
    $stmtPorCnpj = $pdo->prepare(
        "SELECT id FROM fornecedores WHERE cnpj = :cnpj AND cnpj IS NOT NULL AND cnpj <> '' LIMIT 1"
    );
    $stmtInsert = $pdo->prepare(
        'INSERT INTO fornecedores (gc_id, nome, cnpj, email, telefone, contato)
         VALUES (:gc_id, :nome, :cnpj, :email, :telefone, :contato)'
    );
    $stmtUpdate = $pdo->prepare(
        'UPDATE fornecedores
         SET nome=:nome, cnpj=:cnpj, email=:email, telefone=:telefone, contato=:contato, gc_id=:gc_id
         WHERE id=:id'
    );

    do {
        $resposta = $gc->listarFornecedores($pagina);
        $itens    = $resposta['data'] ?? $resposta['dados'] ?? [];
        if (!is_array($itens) || empty($itens)) {
            break;
        }

        foreach ($itens as $f) {
            $gcId    = isset($f['id']) ? (int) $f['id'] : null;
            $nome    = trim((string) ($f['nome'] ?? $f['razao_social'] ?? ''));
            $cnpj    = trim((string) ($f['cnpj'] ?? $f['cpf_cnpj'] ?? ''));
            $email   = trim((string) ($f['email'] ?? ''));
            $tel     = trim((string) ($f['telefone'] ?? $f['celular'] ?? ''));
            $contato = trim((string) ($f['contato'] ?? $f['responsavel'] ?? ''));

            if ($nome === '') {
                $ignorados++;
                continue;
            }
            $cnpj    = $cnpj    ?: null;
            $email   = $email   ?: null;
            $tel     = $tel     ?: null;
            $contato = $contato ?: null;

            $idLocal = null;

            if ($gcId) {
                $stmtPorGcId->execute(['gc_id' => $gcId]);
                $row = $stmtPorGcId->fetch();
                if ($row) {
                    $idLocal = (int) $row['id'];
                }
            }

            if (!$idLocal && $cnpj) {
                $stmtPorCnpj->execute(['cnpj' => $cnpj]);
                $row = $stmtPorCnpj->fetch();
                if ($row) {
                    $idLocal = (int) $row['id'];
                }
            }

            if ($idLocal) {
                $stmtUpdate->execute([
                    'id'       => $idLocal,
                    'gc_id'    => $gcId,
                    'nome'     => $nome,
                    'cnpj'     => $cnpj,
                    'email'    => $email,
                    'telefone' => $tel,
                    'contato'  => $contato,
                ]);
                $atualizados++;
            } else {
                $stmtInsert->execute([
                    'gc_id'    => $gcId,
                    'nome'     => $nome,
                    'cnpj'     => $cnpj,
                    'email'    => $email,
                    'telefone' => $tel,
                    'contato'  => $contato,
                ]);
                $inseridos++;
            }
        }

        $temMais = !empty($resposta['meta']['proxima_pagina'])
                || (isset($resposta['meta']['total_paginas']) && $pagina < (int) $resposta['meta']['total_paginas'])
                || count($itens) >= 100;
        $pagina++;

    } while ($temMais && $pagina <= 50);

    // Salva timestamp — usa parâmetros distintos para evitar limitação do PDO com nomes repetidos
    $agora = date('Y-m-d H:i:s');
    $pdo->prepare(
        "INSERT INTO configuracoes (chave, valor) VALUES ('fornecedores_gc_sync_em', :ts1)
         ON DUPLICATE KEY UPDATE valor = :ts2"
    )->execute(['ts1' => $agora, 'ts2' => $agora]);

    responderSucesso([
        'inseridos'   => $inseridos,
        'atualizados' => $atualizados,
        'ignorados'   => $ignorados,
        'total'       => $inseridos + $atualizados,
    ]);

} catch (Throwable $e) {
    responderErro('Erro interno: ' . $e->getMessage(), 500);
}
