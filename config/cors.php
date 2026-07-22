<?php
/**
 * Cabecalhos CORS - deve ser incluido no topo de todo endpoint em /api/.
 * Libera acesso para o app Android e para os paineis web.
 */

declare(strict_types=1);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
