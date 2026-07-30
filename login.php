<?php
/**
 * Login web (gestor ou tecnico). Valida credenciais direto no banco,
 * cria sessao PHP para proteger as paginas e gera um JWT (guardado na
 * sessao) que as paginas embutem para chamadas AJAX em /api/.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/guard.php';

iniciarSessaoSegura();

if (!empty($_SESSION['usuario_id'])) {
    $isAdmin = in_array($_SESSION['usuario_perfil'], ['gestor', 'supervisor'], true);
    $destino = $isAdmin ? '/app-tecnicos/admin/' : '/app-tecnicos/tecnico/';
    header('Location: ' . $destino);
    exit;
}

$erro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $senha = (string) ($_POST['senha'] ?? '');

    if ($email === '' || $senha === '') {
        $erro = 'Informe e-mail e senha.';
    } else {
        $pdo = obterConexao();
        $stmt = $pdo->prepare('SELECT id, nome, email, senha_hash, perfil, ativo FROM usuarios WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $usuario = $stmt->fetch();

        if (!$usuario || !password_verify($senha, $usuario['senha_hash'])) {
            $erro = 'E-mail ou senha invalidos.';
        } elseif ((int) $usuario['ativo'] !== 1) {
            $erro = 'Usuario inativo. Contate o gestor.';
        } else {
            // Carrega roles adicionais do módulo de compras
            $stmtRoles = $pdo->prepare('SELECT role FROM usuario_roles WHERE usuario_id = :id');
            $stmtRoles->execute(['id' => (int) $usuario['id']]);
            $roles = array_column($stmtRoles->fetchAll(), 'role');

            $_SESSION['usuario_id']     = (int) $usuario['id'];
            $_SESSION['usuario_nome']   = $usuario['nome'];
            $_SESSION['usuario_email']  = $usuario['email'];
            $_SESSION['usuario_perfil'] = $usuario['perfil'];
            $_SESSION['usuario_roles']  = $roles;
            $_SESSION['usuario_jwt']    = gerarJwt((int) $usuario['id'], $usuario['perfil'], $usuario['nome'], $usuario['email']);

            $isAdmin        = in_array($usuario['perfil'], ['gestor', 'supervisor'], true);
            $temRoleCompras = !empty(array_intersect($roles, ['solicitante', 'comprador', 'aprovador']));

            if ($isAdmin) {
                $destino = '/app-tecnicos/admin/';
            } elseif ($temRoleCompras) {
                $destino = '/app-tecnicos/admin/compras/';
            } else {
                $destino = '/app-tecnicos/tecnico/';
            }
            header('Location: ' . $destino);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Entrar - SmartVig OS</title>
<link rel="icon" href="/app-tecnicos/imgs/logo.png">
<link rel="stylesheet" href="/app-tecnicos/assets/css/app.css">
</head>
<body class="pagina-login">
  <div class="caixa-login">
    <img class="logo" src="/app-tecnicos/imgs/logo.png" alt="SmartVig">
    <h1>Gestão de OS</h1>
    <p class="sub">Acesse com seu e-mail e senha</p>

    <?php if ($erro): ?>
      <div class="erro-login"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <form method="post" action="login.php">
      <div class="campo">
        <label for="email">Usuário</label>
        <input type="email" id="email" name="email" required autofocus value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>
      <div class="campo">
        <label for="senha">Senha</label>
        <input type="password" id="senha" name="senha" required>
      </div>
      <button type="submit" class="btn btn-primario" style="width:100%; padding:12px;">Entrar</button>
    </form>
  </div>
</body>
</html>
