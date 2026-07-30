<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') { responderErro('Metodo nao permitido.', 405); }
exigirAutenticacao();
$pdo  = obterConexao();
$stmt = $pdo->query('SELECT id, nome FROM categorias_compra WHERE ativo = 1 ORDER BY nome');
responderSucesso($stmt->fetchAll());
