<?php
/**
 * POST /api/tecnicos/upload_foto (multipart/form-data)
 * Campos: tecnico_id (opcional, somente gestor pode informar outro ID),
 *         arquivo (imagem JPG/PNG/WEBP, max 3MB)
 * Salva a foto de perfil do tecnico em imgs/avatares/{id}.{ext}
 * e atualiza a coluna foto_perfil na tabela usuarios.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErro('Metodo nao permitido. Use POST.', 405);
}

$payload = exigirAutenticacao();

$tecnicoIdAlvo = (int) ($_POST['tecnico_id'] ?? $payload['usuario_id']);

if ($payload['perfil'] !== 'gestor' && $tecnicoIdAlvo !== (int) $payload['usuario_id']) {
    responderErro('Sem permissao para alterar foto de outro usuario.', 403);
}

if (empty($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
    $codErro = $_FILES['arquivo']['error'] ?? -1;
    responderErro('Arquivo invalido ou nao recebido (codigo ' . $codErro . ').', 422);
}

$arquivo = $_FILES['arquivo'];
$tamanhoMax = 3 * 1024 * 1024;
if ($arquivo['size'] > $tamanhoMax) {
    responderErro('Imagem muito grande. Maximo permitido: 3 MB.', 422);
}

$ext = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
$extensoesPermitidas = ['jpg', 'jpeg', 'png', 'webp'];
if (!in_array($ext, $extensoesPermitidas, true)) {
    responderErro('Extensao nao permitida. Use JPG, PNG ou WEBP.', 422);
}

$tipoMime = mime_content_type($arquivo['tmp_name']);
$mimesPermitidos = ['image/jpeg', 'image/png', 'image/webp'];
if (!in_array($tipoMime, $mimesPermitidos, true)) {
    responderErro('Tipo de arquivo invalido.', 422);
}

$dirAvatares = __DIR__ . '/../../imgs/avatares/';
if (!is_dir($dirAvatares)) {
    mkdir($dirAvatares, 0755, true);
}

$nomeArquivo = $tecnicoIdAlvo . '.' . $ext;
$caminhoFisico = $dirAvatares . $nomeArquivo;

// Remove foto anterior se for extensao diferente
foreach ($extensoesPermitidas as $e) {
    $velho = $dirAvatares . $tecnicoIdAlvo . '.' . $e;
    if ($velho !== $caminhoFisico && file_exists($velho)) {
        unlink($velho);
    }
}

if (!move_uploaded_file($arquivo['tmp_name'], $caminhoFisico)) {
    responderErro('Falha ao salvar imagem no servidor.', 500);
}

$caminhoRelativo = 'imgs/avatares/' . $nomeArquivo;

$pdo = obterConexao();
$stmt = $pdo->prepare('UPDATE usuarios SET foto_perfil = :foto WHERE id = :id');
$stmt->execute(['foto' => $caminhoRelativo, 'id' => $tecnicoIdAlvo]);

responderSucesso([
    'foto_perfil'  => $caminhoRelativo,
    'url'          => '/app-tecnicos/' . $caminhoRelativo,
    'tecnico_id'   => $tecnicoIdAlvo,
]);
