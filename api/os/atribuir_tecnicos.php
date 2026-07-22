<?php
/**
 * POST /api/os/atribuir_tecnicos (somente gestor)
 * Corpo: { "os_id": 1, "tecnicos": [ { "tecnico_id": 2, "responsavel": true }, ... ] }
 * Substitui a lista de tecnicos atribuidos a uma OS e define o responsavel.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/response.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/os_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErro('Metodo nao permitido. Use POST.', 405);
}

$payload = exigirAutenticacao();
exigirPerfil($payload, ['gestor']);

$dados = lerCorpoJson();
exigirCampos($dados, ['os_id', 'tecnicos']);

$osId = (int) $dados['os_id'];
$tecnicos = $dados['tecnicos'];

if (!is_array($tecnicos) || empty($tecnicos)) {
    responderErro('Informe ao menos um tecnico em "tecnicos".', 422);
}

$pdo = obterConexao();
$os = buscarOsOuFalhar($pdo, $osId, $payload);

$temResponsavel = false;
foreach ($tecnicos as $t) {
    if (!empty($t['responsavel'])) {
        $temResponsavel = true;
        break;
    }
}
if (!$temResponsavel) {
    $tecnicos[0]['responsavel'] = true;
}

$pdo->beginTransaction();
try {
    $pdo->prepare('DELETE FROM os_tecnicos WHERE os_id = :os_id')->execute(['os_id' => $osId]);

    $stmtTec = $pdo->prepare(
        'INSERT INTO os_tecnicos (os_id, tecnico_id, responsavel) VALUES (:os_id, :tecnico_id, :responsavel)'
    );

    $tecnicoResponsavelId = null;
    foreach ($tecnicos as $t) {
        $responsavel = !empty($t['responsavel']);
        if ($responsavel) {
            $tecnicoResponsavelId = (int) $t['tecnico_id'];
        }
        $stmtTec->execute([
            'os_id' => $osId,
            'tecnico_id' => (int) $t['tecnico_id'],
            'responsavel' => $responsavel ? 1 : 0,
        ]);
    }

    $pdo->prepare('UPDATE ordens_servico SET tecnico_id = :tecnico_id WHERE id = :id')
        ->execute(['tecnico_id' => $tecnicoResponsavelId, 'id' => $osId]);

    registrarHistorico($pdo, $osId, (int) $payload['usuario_id'], 'tecnicos_atribuidos', 'Tecnicos redefinidos pelo gestor ' . $payload['nome']);

    foreach ($tecnicos as $t) {
        criarNotificacao(
            $pdo,
            (int) $t['tecnico_id'],
            $osId,
            'nova_os',
            'Voce foi atribuido a uma OS',
            'OS #' . $osId . ' - Cliente: ' . $os['cliente_nome']
        );
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    responderErro('Falha ao atribuir tecnicos: ' . $e->getMessage(), 500);
}

responderSucesso(['os_id' => $osId, 'tecnico_responsavel_id' => $tecnicoResponsavelId]);
