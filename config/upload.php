<?php
/**
 * Armazenamento local de fotos e videos das OSs (sem Firebase).
 * Arquivos ficam em imgs/os/{os_id}/ e sao servidos diretamente pelo Apache.
 */

declare(strict_types=1);

require_once __DIR__ . '/response.php';

const TAMANHO_MAXIMO_FOTO_BYTES = 5 * 1024 * 1024;     // 5 MB
const TAMANHO_MAXIMO_VIDEO_BYTES = 100 * 1024 * 1024;  // 100 MB

const EXTENSOES_FOTO_PERMITIDAS = ['jpg', 'jpeg', 'png', 'webp'];
const EXTENSOES_VIDEO_PERMITIDAS = ['mp4', 'mov', '3gp', 'webm'];

/**
 * Move um arquivo enviado (multipart/form-data) para imgs/os/{osId}/,
 * validando tipo e tamanho. Retorna caminho relativo e URL publica.
 */
function salvarMidiaLocal(array $arquivoEnviado, int $osId, string $tipo): array
{
    if (!in_array($tipo, ['foto', 'video'], true)) {
        responderErro('Tipo de midia invalido. Use foto ou video.', 422);
    }

    if (!isset($arquivoEnviado['tmp_name']) || !is_uploaded_file($arquivoEnviado['tmp_name'])) {
        responderErro('Nenhum arquivo valido foi enviado.', 422);
    }

    $extensao = strtolower(pathinfo($arquivoEnviado['name'], PATHINFO_EXTENSION));
    $extensoesPermitidas = $tipo === 'foto' ? EXTENSOES_FOTO_PERMITIDAS : EXTENSOES_VIDEO_PERMITIDAS;

    if (!in_array($extensao, $extensoesPermitidas, true)) {
        responderErro("Extensao .{$extensao} nao permitida para {$tipo}.", 422);
    }

    $tamanhoMaximo = $tipo === 'foto' ? TAMANHO_MAXIMO_FOTO_BYTES : TAMANHO_MAXIMO_VIDEO_BYTES;
    if ($arquivoEnviado['size'] > $tamanhoMaximo) {
        $limiteMb = (int) ($tamanhoMaximo / 1024 / 1024);
        responderErro("Arquivo excede o limite de {$limiteMb}MB para {$tipo}.", 422);
    }

    $pastaBase = dirname(__DIR__) . '/imgs/os/' . $osId;
    if (!is_dir($pastaBase) && !mkdir($pastaBase, 0755, true) && !is_dir($pastaBase)) {
        responderErro('Nao foi possivel criar a pasta de destino para a midia.', 500);
    }

    $nomeArquivo = time() . '_' . bin2hex(random_bytes(4)) . '.' . $extensao;
    $caminhoDestino = $pastaBase . '/' . $nomeArquivo;

    if (!move_uploaded_file($arquivoEnviado['tmp_name'], $caminhoDestino)) {
        responderErro('Falha ao salvar o arquivo no servidor.', 500);
    }

    $caminhoRelativo = "imgs/os/{$osId}/{$nomeArquivo}";

    return [
        'caminho_relativo' => $caminhoRelativo,
        'nome_arquivo' => $nomeArquivo,
        'tamanho_bytes' => (int) $arquivoEnviado['size'],
        'url' => construirUrlPublica($caminhoRelativo),
    ];
}

/**
 * Monta a URL publica absoluta para um caminho relativo dentro do projeto.
 */
function construirUrlPublica(string $caminhoRelativo): string
{
    $esquema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return "{$esquema}://{$host}/app-tecnicos/{$caminhoRelativo}";
}
