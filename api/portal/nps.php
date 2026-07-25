<?php
/**
 * POST /api/portal/nps  — Publico (sem auth)
 * Body: { token: string, nota: int (1-5), comentario?: string }
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { responderErro('Use POST.', 405); }

$dados = lerCorpoJson();
exigirCampos($dados, ['token', 'nota']);

$token = trim($dados['token']);
$nota  = (int) $dados['nota'];

if ($nota < 1 || $nota > 5) {
    responderErro('Nota deve ser entre 1 e 5.', 400);
}

$pdo  = obterConexao();
$stmt = $pdo->prepare(
    'SELECT id, nps_respondido FROM ordens_servico WHERE nps_token = :t AND situacao_local = "concluido"'
);
$stmt->execute([':t' => $token]);
$os = $stmt->fetch();

if (!$os) { responderErro('Link de avaliação inválido.', 404); }
if ($os['nps_respondido']) { responderErro('Esta avaliação já foi enviada.', 409); }

$comentario = trim($dados['comentario'] ?? '');

$pdo->prepare(
    'UPDATE ordens_servico SET nps_nota = :n, nps_comentario = :c, nps_respondido = 1 WHERE id = :id'
)->execute([':n' => $nota, ':c' => $comentario ?: null, ':id' => $os['id']]);

$pdo->prepare(
    'INSERT INTO nps_avaliacoes (os_id, nota, comentario) VALUES (:os, :n, :c)'
)->execute([':os' => $os['id'], ':n' => $nota, ':c' => $comentario ?: null]);

responderSucesso(['mensagem' => 'Avaliação registrada. Obrigado!']);
