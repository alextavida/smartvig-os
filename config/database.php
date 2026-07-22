<?php
/**
 * Conexao PDO com o MySQL (XAMPP) + helpers de acesso a tabela configuracoes.
 */

declare(strict_types=1);

function obterConexao(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = 'localhost';
    $porta = '3306';
    $banco = 'app_tecnicos';
    $usuario = 'root';
    $senha = '';

    $dsn = "mysql:host={$host};port={$porta};dbname={$banco};charset=utf8mb4";

    try {
        $pdo = new PDO($dsn, $usuario, $senha, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'sucesso' => false,
            'erro' => 'Falha na conexao com o banco de dados: ' . $e->getMessage(),
        ]);
        exit;
    }

    return $pdo;
}

/**
 * Le um valor da tabela configuracoes pela chave. Retorna $padrao se nao existir.
 */
function obterConfiguracao(string $chave, ?string $padrao = null): ?string
{
    $pdo = obterConexao();
    $stmt = $pdo->prepare('SELECT valor FROM configuracoes WHERE chave = :chave LIMIT 1');
    $stmt->execute(['chave' => $chave]);
    $valor = $stmt->fetchColumn();

    return $valor === false ? $padrao : (string) $valor;
}

/**
 * Grava (insere ou atualiza) um valor na tabela configuracoes.
 */
function definirConfiguracao(string $chave, string $valor): void
{
    $pdo = obterConexao();
    $stmt = $pdo->prepare(
        'INSERT INTO configuracoes (chave, valor) VALUES (:chave, :valor)
         ON DUPLICATE KEY UPDATE valor = VALUES(valor)'
    );
    $stmt->execute(['chave' => $chave, 'valor' => $valor]);
}
