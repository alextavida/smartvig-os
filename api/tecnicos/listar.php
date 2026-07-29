<?php
/**
 * GET /api/tecnicos/listar (somente gestor)
 * Lista tecnicos ativos, usado para atribuir OSs.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderErro('Metodo nao permitido. Use GET.', 405);
}

$payload = exigirAutenticacao();
exigirPerfil($payload, ['gestor']);

$pdo = obterConexao();
$stmt = $pdo->query(
    "SELECT id, nome, email, telefone, ativo, foto_perfil,
            (SELECT COUNT(*) FROM os_tecnicos ot
             INNER JOIN ordens_servico o ON o.id = ot.os_id
             WHERE ot.tecnico_id = usuarios.id AND o.situacao_local IN ('aberto','em_andamento','pausado','reagendado')
            ) AS os_ativas
     FROM usuarios
     WHERE perfil = 'tecnico'
     ORDER BY nome ASC"
);

responderSucesso(['tecnicos' => $stmt->fetchAll()]);
