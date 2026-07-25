<?php
/** GET /api/orcamentos/visualizar?id= */

declare(strict_types=1);

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/auth.php';

$payload = exigirAutenticacao();
exigirPerfil($payload, ['gestor', 'supervisor', 'tecnico']);

$id = (int) ($_GET['id'] ?? 0);
if (!$id) { responderErro('id obrigatorio.', 400); }

$pdo  = obterConexao();
$stmt = $pdo->prepare(
    "SELECT o.*, u.nome AS criado_por_nome FROM orcamentos o
     LEFT JOIN usuarios u ON u.id = o.criado_por
     WHERE o.id = :id"
);
$stmt->execute([':id' => $id]);
$orc = $stmt->fetch();
if (!$orc) { responderErro('Orçamento não encontrado.', 404); }

$itens = $pdo->prepare("SELECT * FROM orcamento_itens WHERE orcamento_id = :id ORDER BY id");
$itens->execute([':id' => $id]);

$orc['itens'] = $itens->fetchAll();
$orc['total'] = array_sum(array_map(fn($i) => (float)$i['quantidade'] * (float)$i['valor_unitario'], $orc['itens']));

responderSucesso(['orcamento' => $orc]);
