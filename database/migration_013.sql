-- Migration 013: substituída pelo OneSignal (External User IDs)
-- A tabela push_tokens não é mais necessária.
-- O OneSignal armazena os device tokens internamente e os associa ao usuário
-- via OneSignal.login(userId) chamado no app após o login.
-- Esta migration não precisa ser executada.
SELECT 1;
