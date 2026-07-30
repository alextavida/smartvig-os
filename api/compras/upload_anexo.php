<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/compras_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { responderErro('Metodo nao permitido.', 405); }

$payload        = exigirAutenticacao();
$pdo            = obterConexao();
$solicitacaoId  = (int) ($_POST['solicitacao_id'] ?? 0);
$usuarioId      = (int) $payload['usuario_id'];

if ($solicitacaoId <= 0) { responderErro('ID inválido.', 400); }

$sc = $pdo->prepare('SELECT id FROM solicitacoes_compra WHERE id = :id LIMIT 1');
$sc->execute(['id' => $solicitacaoId]);
if (!$sc->fetch()) { responderErro('Solicitação não encontrada.', 404); }

$dir      = __DIR__ . '/../../imgs/compras_anexos/' . $solicitacaoId . '/';
if (!is_dir($dir)) { mkdir($dir, 0755, true); }

$arquivos = $_FILES['files'] ?? [];
$salvos   = 0;

if (!empty($arquivos['name'])) {
    $nomes = is_array($arquivos['name']) ? $arquivos['name'] : [$arquivos['name']];
    $tmps  = is_array($arquivos['tmp_name']) ? $arquivos['tmp_name'] : [$arquivos['tmp_name']];
    $sizes = is_array($arquivos['size']) ? $arquivos['size'] : [$arquivos['size']];
    $types = is_array($arquivos['type']) ? $arquivos['type'] : [$arquivos['type']];

    $stmt = $pdo->prepare(
        'INSERT INTO solicitacao_anexos (solicitacao_id, usuario_id, nome_original, caminho, tamanho, tipo_mime)
         VALUES (:sid, :uid, :nome, :caminho, :tam, :mime)'
    );

    foreach ($nomes as $i => $nome) {
        if (empty($tmps[$i]) || !is_uploaded_file($tmps[$i])) { continue; }
        if ($sizes[$i] > 20 * 1024 * 1024) { continue; } // max 20 MB

        $ext    = strtolower(pathinfo($nome, PATHINFO_EXTENSION));
        $seguro = preg_replace('/[^a-z0-9_.-]/', '', strtolower(pathinfo($nome, PATHINFO_FILENAME)));
        $arquivo = $seguro . '_' . uniqid() . '.' . $ext;
        $destino = $dir . $arquivo;
        $caminho = 'imgs/compras_anexos/' . $solicitacaoId . '/' . $arquivo;

        if (move_uploaded_file($tmps[$i], $destino)) {
            $stmt->execute([
                'sid'    => $solicitacaoId,
                'uid'    => $usuarioId,
                'nome'   => $nome,
                'caminho'=> $caminho,
                'tam'    => (int) $sizes[$i],
                'mime'   => $types[$i],
            ]);
            $salvos++;
        }
    }
}

registrarHistoricoCompra($pdo, $solicitacaoId, $usuarioId, $payload['nome'] ?? '', 'Anexo(s) adicionado(s)', $salvos . ' arquivo(s)');

responderSucesso(['salvos' => $salvos]);
