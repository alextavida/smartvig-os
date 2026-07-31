<?php
/** GET /api/orcamentos/listar?status= */

declare(strict_types=1);

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/auth.php';

$payload = exigirAutenticacao();
exigirPerfil($payload, ['gestor', 'supervisor', 'tecnico']);

$pdo       = obterConexao();
$status    = $_GET['status'] ?? '';
$perfil    = $payload['perfil'];
$usuarioId = (int) $payload['usuario_id'];

$validos = ['rascunho', 'enviado', 'aprovado', 'recusado', 'convertido', ''];
if (!in_array($status, $validos, true)) {
    responderErro('Status invalido.', 400);
}

// Técnico vê apenas os próprios orçamentos; gestor/supervisor vê todos
$where  = ['1=1'];
$params = [];

if ($perfil === 'tecnico') {
    $where[]       = 'o.criado_por = :uid';
    $params[':uid'] = $usuarioId;
}

if ($status !== '') {
    $where[]         = 'o.status = :status';
    $params[':status'] = $status;
}

$whereStr = implode(' AND ', $where);

$sql = "SELECT o.id, o.codigo, o.cliente_nome, o.cliente_telefone, o.status,
               o.validade_dias, o.criado_em, o.os_id_gerada,
               u.nome AS criado_por_nome,
               COALESCE(
                 (SELECT SUM(i.quantidade * i.valor_unitario) FROM orcamento_itens i WHERE i.orcamento_id = o.id),
                 0
               ) AS total,
               (SELECT COUNT(*) FROM orcamento_itens i WHERE i.orcamento_id = o.id) AS total_itens
        FROM orcamentos o
        LEFT JOIN usuarios u ON u.id = o.criado_por
        WHERE $whereStr
        ORDER BY o.criado_em DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orcamentos = $stmt->fetchAll();

foreach ($orcamentos as &$orc) {
    $orc['total'] = (float) $orc['total'];
}

responderSucesso(['orcamentos' => $orcamentos, 'total' => count($orcamentos)]);
