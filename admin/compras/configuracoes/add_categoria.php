<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../config/cors.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/response.php';
require_once __DIR__ . '/../../../config/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { responderErro('Metodo nao permitido.', 405); }
$payload = exigirAutenticacao();
if ($payload['perfil'] !== 'gestor') { responderErro('Acesso negado.', 403); }
$pdo   = obterConexao();
$dados = lerCorpoJson();
if (empty($dados['nome'])) { responderErro('Nome obrigatório.', 422); }
$pdo->prepare('INSERT INTO categorias_compra (nome) VALUES (:nome)')->execute(['nome' => trim($dados['nome'])]);
responderSucesso(['id' => (int) $pdo->lastInsertId()]);
