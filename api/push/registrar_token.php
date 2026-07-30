<?php
// Endpoint descontinuado — push notifications agora via OneSignal (External User ID).
// O app não precisa registrar tokens manualmente.
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/response.php';
http_response_code(410);
header('Content-Type: application/json');
echo json_encode(['erro' => 'Endpoint descontinuado. Use OneSignal SDK no app.']);
