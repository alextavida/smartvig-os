<?php
/**
 * Integracao real com a API do GestaoClick (https://api.gestaoclick.com/).
 * Autenticacao via headers access_token / secret_access (lidos da tabela configuracoes).
 * Sem modo de simulacao: todas as chamadas sao feitas de verdade via cURL.
 */

declare(strict_types=1);

require_once __DIR__ . '/database.php';

class GestaoClickApiException extends RuntimeException
{
}

class GestaoClickAPI
{
    private string $baseUrl;
    private string $accessToken;
    private string $secretAccess;

    public function __construct()
    {
        $this->baseUrl = rtrim(obterConfiguracao('gc_base_url', 'https://api.gestaoclick.com/') ?? '', '/') . '/';
        $this->accessToken = obterConfiguracao('gc_access_token', '') ?? '';
        $this->secretAccess = obterConfiguracao('gc_secret_access', '') ?? '';
    }

    /**
     * Executa uma requisicao HTTP contra a API do GestaoClick.
     */
    private function requisitar(string $metodo, string $caminho, array $dados = []): array
    {
        $url = $this->baseUrl . ltrim($caminho, '/');

        if ($metodo === 'GET' && !empty($dados)) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($dados);
        }

        $ch = curl_init($url);

        // GC usa 'access_token' e 'secret_access_token' como nomes de header.
        // O campo se chama "Secret Access Token" (3 palavras) → snake_case = secret_access_token.
        $headers = [
            'access_token: '        . $this->accessToken,
            'secret_access_token: ' . $this->secretAccess,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $opcoes = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CUSTOMREQUEST => $metodo,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ];

        if ($metodo !== 'GET') {
            $opcoes[CURLOPT_POSTFIELDS] = json_encode($dados, JSON_UNESCAPED_UNICODE);
        }

        curl_setopt_array($ch, $opcoes);

        $respostaBruta = curl_exec($ch);
        $codigoHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $erroCurl = curl_error($ch);
        curl_close($ch);

        if ($respostaBruta === false) {
            throw new GestaoClickApiException("Falha de conexao cURL: {$erroCurl}");
        }

        $resposta = json_decode((string) $respostaBruta, true);

        if ($codigoHttp >= 400) {
            // Inclui o corpo bruto no erro para facilitar diagnóstico
            $detalhe = is_array($resposta)
                ? ($resposta['message'] ?? $resposta['error'] ?? $resposta['mensagem'] ?? json_encode($resposta, JSON_UNESCAPED_UNICODE))
                : (string) $respostaBruta;
            throw new GestaoClickApiException("HTTP {$codigoHttp}: {$detalhe}");
        }

        return is_array($resposta) ? $resposta : [];
    }

    public function get(string $caminho, array $parametros = []): array
    {
        return $this->requisitar('GET', $caminho, $parametros);
    }

    public function post(string $caminho, array $dados): array
    {
        return $this->requisitar('POST', $caminho, $dados);
    }

    public function put(string $caminho, array $dados): array
    {
        return $this->requisitar('PUT', $caminho, $dados);
    }

    /**
     * Lista ordens de servico. GestaoClick pagina em blocos de 100 registros.
     */
    public function listarOS(int $pagina = 1): array
    {
        return $this->get('ordens_servicos/', ['pagina' => $pagina]);
    }

    public function visualizarOS(int $gcOsId): array
    {
        $resposta = $this->get("ordens_servicos/{$gcOsId}/");
        // GC envolve registros únicos em { "code":200, "status":"success", "data":{ ... } }
        return $resposta['data'] ?? $resposta;
    }

    public function criarOS(array $dados): array
    {
        return $this->post('ordens_servicos/', $dados);
    }

    public function atualizarOS(int $gcOsId, array $dados): array
    {
        return $this->put("ordens_servicos/{$gcOsId}/", $dados);
    }

    public function listarSituacoes(): array
    {
        return $this->get('situacoes_ordens_servicos/');
    }

    public function visualizarCliente(int $gcClienteId): array
    {
        return $this->get("clientes/{$gcClienteId}/");
    }

    public function listarProdutos(int $pagina = 1, string $busca = ''): array
    {
        $parametros = ['pagina' => $pagina];
        if ($busca !== '') {
            $parametros['nome'] = $busca;
        }

        return $this->get('produtos/', $parametros);
    }

    public function listarUsuarios(int $pagina = 1): array
    {
        return $this->get('usuarios/', ['pagina' => $pagina]);
    }

    public function criarRecebimento(array $dados): array
    {
        return $this->post('recebimentos/', $dados);
    }

    public function listarClientes(string $busca = '', int $pagina = 1): array
    {
        $parametros = ['pagina' => $pagina];
        if ($busca !== '') {
            $parametros['nome'] = $busca;
        }
        return $this->get('clientes/', $parametros);
    }

    public function criarCliente(array $dados): array
    {
        return $this->post('clientes/', $dados);
    }

    public function criarOrcamento(array $dados): array
    {
        return $this->post('orcamentos/', $dados);
    }

    public function listarRecebimentos(array $parametros = []): array
    {
        return $this->get('recebimentos/', $parametros);
    }

    public function listarTiposPagamento(): array
    {
        return $this->get('tipos_pagamento/');
    }
}
