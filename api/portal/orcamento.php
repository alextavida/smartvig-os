<?php
/**
 * POST /api/portal/orcamento  — Publico (sem auth)
 * Body: { token: string, decisao: "aprovado"|"recusado" }
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { responderErro('Use POST.', 405); }

$dados = lerCorpoJson();
exigirCampos($dados, ['token', 'decisao']);

$token   = trim($dados['token']);
$decisao = $dados['decisao'];

if (!in_array($decisao, ['aprovado', 'recusado'], true)) {
    responderErro('Decisão inválida. Use "aprovado" ou "recusado".', 400);
}

$pdo = obterConexao();
$stmt = $pdo->prepare('SELECT id, status FROM orcamentos WHERE token = :t');
$stmt->execute([':t' => $token]);
$orc = $stmt->fetch();

if (!$orc) { responderErro('Orçamento não encontrado.', 404); }
if (in_array($orc['status'], ['aprovado', 'recusado', 'convertido'], true)) {
    responderErro('Este orçamento já foi respondido.', 409);
}

$pdo->prepare('UPDATE orcamentos SET status = :s WHERE id = :id')
    ->execute([':s' => $decisao, ':id' => $orc['id']]);

responderSucesso(['id' => $orc['id'], 'status' => $decisao]);
