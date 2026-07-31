<?php
/**
 * GET /api/clientes/buscar?busca={termo}
 * Busca clientes no GestaoClick por nome. Retorna lista para autocomplete.
 * Requer autenticacao (gestor).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/gestaoclick.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderErro('Metodo nao permitido. Use GET.', 405);
}

$payload = exigirAutenticacao();
exigirPerfil($payload, ['gestor', 'supervisor', 'tecnico']);

$busca = trim((string) ($_GET['busca'] ?? ''));

if (strlen($busca) < 2) {
    responderSucesso(['clientes' => []]);
}

try {
    $gc = new GestaoClickAPI();
    $resultado = $gc->listarClientes($busca);
    $clientes = $resultado['data'] ?? $resultado['clientes'] ?? $resultado ?? [];

    $lista = [];
    if (is_array($clientes)) {
        foreach (array_slice($clientes, 0, 15) as $c) {
            if (!is_array($c)) { continue; }
            // GC: enderecos[0]['endereco'] é o objeto de endereço; campos: logradouro, numero, bairro, nome_cidade, estado
            $end = $c['enderecos'][0]['endereco'] ?? [];
            $endStr = implode(', ', array_filter([
                trim(($end['logradouro'] ?? '') . ' ' . ($end['numero'] ?? '')),
                $end['bairro'] ?? '',
                $end['nome_cidade'] ?? '',
                $end['estado'] ?? '',
            ]));
            $lista[] = [
                'id'       => $c['id'] ?? null,
                'nome'     => $c['nome'] ?? $c['razao_social'] ?? '',
                'telefone' => $c['telefone'] ?? $c['celular'] ?? '',
                'email'    => $c['email'] ?? '',
                'endereco' => trim($endStr),
            ];
        }
    }

    responderSucesso(['clientes' => $lista]);
} catch (GestaoClickApiException $e) {
    responderErro('Falha ao buscar clientes no GestaoClick: ' . $e->getMessage(), 502);
}
