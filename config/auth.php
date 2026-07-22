<?php
/**
 * Autenticacao JWT (HS256) implementada em PHP puro, sem bibliotecas externas.
 * Tambem expõe middlewares para exigir login e exigir perfil especifico.
 */

declare(strict_types=1);

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/response.php';

function base64UrlEncode(string $dados): string
{
    return rtrim(strtr(base64_encode($dados), '+/', '-_'), '=');
}

function base64UrlDecode(string $dados): string
{
    $pad = strlen($dados) % 4;
    if ($pad > 0) {
        $dados .= str_repeat('=', 4 - $pad);
    }

    return base64_decode(strtr($dados, '-_', '+/'));
}

function obterSegredoJwt(): string
{
    $segredo = obterConfiguracao('app_jwt_secret');
    if (!$segredo) {
        responderErro('Configuracao app_jwt_secret nao definida no banco.', 500);
    }

    return $segredo;
}

/**
 * Gera um token JWT HS256 para o usuario informado.
 */
function gerarJwt(int $usuarioId, string $perfil, string $nome, string $email): string
{
    $ttlHoras = (int) (obterConfiguracao('app_jwt_ttl_horas', '12'));
    $agora = time();

    $header = ['alg' => 'HS256', 'typ' => 'JWT'];
    $payload = [
        'usuario_id' => $usuarioId,
        'perfil' => $perfil,
        'nome' => $nome,
        'email' => $email,
        'iat' => $agora,
        'exp' => $agora + ($ttlHoras * 3600),
    ];

    $headerCodificado = base64UrlEncode(json_encode($header));
    $payloadCodificado = base64UrlEncode(json_encode($payload));

    $assinatura = hash_hmac('sha256', "{$headerCodificado}.{$payloadCodificado}", obterSegredoJwt(), true);
    $assinaturaCodificada = base64UrlEncode($assinatura);

    return "{$headerCodificado}.{$payloadCodificado}.{$assinaturaCodificada}";
}

/**
 * Valida um JWT e retorna o payload decodificado, ou null se invalido/expirado.
 */
function validarJwt(string $token): ?array
{
    $partes = explode('.', $token);
    if (count($partes) !== 3) {
        return null;
    }

    [$headerCodificado, $payloadCodificado, $assinaturaCodificada] = $partes;

    $assinaturaEsperada = hash_hmac('sha256', "{$headerCodificado}.{$payloadCodificado}", obterSegredoJwt(), true);
    $assinaturaEsperadaCodificada = base64UrlEncode($assinaturaEsperada);

    if (!hash_equals($assinaturaEsperadaCodificada, $assinaturaCodificada)) {
        return null;
    }

    $payload = json_decode(base64UrlDecode($payloadCodificado), true);
    if (!is_array($payload) || !isset($payload['exp'])) {
        return null;
    }

    if (time() >= (int) $payload['exp']) {
        return null;
    }

    return $payload;
}

/**
 * Extrai o token do header Authorization: Bearer.
 */
function obterTokenDoHeader(): ?string
{
    $header = $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? null;

    if (!$header && function_exists('apache_request_headers')) {
        $todos = apache_request_headers();
        foreach ($todos as $chave => $valor) {
            if (strcasecmp($chave, 'Authorization') === 0) {
                $header = $valor;
                break;
            }
        }
    }

    if (!$header || stripos($header, 'Bearer ') !== 0) {
        return null;
    }

    return trim(substr($header, 7));
}

/**
 * Middleware: exige um usuario autenticado. Retorna o payload do JWT.
 */
function exigirAutenticacao(): array
{
    $token = obterTokenDoHeader();
    if (!$token) {
        responderErro('Token de autenticacao ausente.', 401);
    }

    $payload = validarJwt($token);
    if (!$payload) {
        responderErro('Token invalido ou expirado.', 401);
    }

    return $payload;
}

/**
 * Middleware: exige que o usuario autenticado tenha um dos perfis informados.
 */
function exigirPerfil(array $payload, array $perfisPermitidos): void
{
    if (!in_array($payload['perfil'], $perfisPermitidos, true)) {
        responderErro('Acesso negado para o perfil atual.', 403);
    }
}
