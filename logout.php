<?php
declare(strict_types=1);

require_once __DIR__ . '/config/guard.php';

iniciarSessaoSegura();
$_SESSION = [];
session_destroy();

header('Location: /app-tecnicos/login.php');
exit;
