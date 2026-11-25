<?php
// ============================================================================
// GUARDIÃO ANIMAL — MODELO DE CONFIGURAÇÃO PRIVADA DO SERVIDOR
// TCC Técnico em Desenvolvimento de Sistemas — SENAI Ourinhos / SP
// Autores: Arthur Morais, Davi Kiliam e Vinicius Lourenço
// ============================================================================
// INSTRUÇÕES:
// 1. Duplique este arquivo e renomeie a cópia para: guardiao_supabase_private.php
// 2. Preencha 'secret_key' com a sua chave secreta service_role do Supabase.
// 3. Este arquivo nunca deve ser compartilhado publicamente.
// ============================================================================

return [
    'secret_key' => 'SUA_CHAVE_SERVICE_ROLE_DO_SUPABASE',
    'setup_token' => 'SEU_TOKEN_DE_SETUP',
    'migration_token' => 'SEU_TOKEN_DE_MIGRACAO',
];
