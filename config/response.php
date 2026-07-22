<?php
/**
 * Helpers de resposta JSON padronizada para todos os endpoints da API.
 */

declare(strict_types=1);

function responderSucesso($dados = null, int $codigoHttp = 200): void
{
    http_response_code($codigoHttp);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'sucesso' => true,
        'dados' => $dados,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function responderErro(string $mensagem, int $codigoHttp = 400): void
{
    http_response_code($codigoHttp);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'sucesso' => false,
        'erro' => $mensagem,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Le e decodifica o corpo JSON da requisicao. Retorna array vazio se nao houver corpo.
 */
function lerCorpoJson(): array
{
    $bruto = file_get_contents('php://input');
    if ($bruto === false || $bruto === '') {
        return [];
    }

    $dados = json_decode($bruto, true);
    if (!is_array($dados)) {
        responderErro('Corpo da requisicao invalido: JSON malformado.', 400);
    }

    return $dados;
}

/**
 * Garante que os campos obrigatorios existem no array (nao vazios).
 */
function exigirCampos(array $dados, array $campos): void
{
    $faltando = [];
    foreach ($campos as $campo) {
        if (!array_key_exists($campo, $dados) || $dados[$campo] === '' || $dados[$campo] === null) {
            $faltando[] = $campo;
        }
    }

    if (!empty($faltando)) {
        responderErro('Campos obrigatorios ausentes: ' . implode(', ', $faltando), 422);
    }
}
