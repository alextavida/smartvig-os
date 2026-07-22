<?php
/**
 * POST /api/auth/logout
 * JWT e stateless: o "logout" apenas confirma o token e orienta o cliente
 * a descartar o token localmente (nao ha blacklist no servidor).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErro('Metodo nao permitido. Use POST.', 405);
}

exigirAutenticacao();

responderSucesso(['mensagem' => 'Logout realizado. Descarte o token no cliente.']);
