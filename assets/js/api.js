/**
 * Helper de chamadas a API JSON (/api/...) usando o JWT embutido na pagina
 * (window.APP_JWT, definido em includes/header.php).
 */

const API_BASE = '/app-tecnicos/api';

async function chamarApi(caminho, opcoes = {}) {
  const headers = Object.assign(
    { 'Content-Type': 'application/json' },
    opcoes.headers || {}
  );

  if (window.APP_JWT) {
    headers['Authorization'] = 'Bearer ' + window.APP_JWT;
  }

  const resposta = await fetch(API_BASE + caminho, Object.assign({}, opcoes, { headers }));
  const json = await resposta.json().catch(() => ({ sucesso: false, erro: 'Resposta invalida do servidor.' }));

  if (!json.sucesso) {
    throw new Error(json.erro || 'Erro desconhecido na API.');
  }

  return json.dados;
}

function apiGet(caminho) {
  return chamarApi(caminho, { method: 'GET' });
}

function apiPost(caminho, corpo) {
  return chamarApi(caminho, { method: 'POST', body: JSON.stringify(corpo || {}) });
}

async function apiUpload(caminho, formData) {
  const headers = {};
  if (window.APP_JWT) {
    headers['Authorization'] = 'Bearer ' + window.APP_JWT;
  }
  const resposta = await fetch(API_BASE + caminho, { method: 'POST', headers, body: formData });
  const json = await resposta.json().catch(() => ({ sucesso: false, erro: 'Resposta invalida do servidor.' }));
  if (!json.sucesso) {
    throw new Error(json.erro || 'Erro desconhecido na API.');
  }
  return json.dados;
}
