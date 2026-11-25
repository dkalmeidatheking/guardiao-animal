<?php
declare(strict_types=1);

// A chave sb_publishable_ é própria para o cliente público e nunca deve ser substituída por sb_secret_ / service_role aqui.
// Para hospedar, prefira configurar SUPABASE_URL e SUPABASE_PUBLISHABLE_KEY como variáveis de ambiente do PHP.
if (!defined('SUPABASE_URL')) {
    define('SUPABASE_URL', rtrim((string)(getenv('SUPABASE_URL') ?: 'https://znuwnuktnqogevwisqbj.supabase.co'), '/'));
}
if (!defined('SUPABASE_PUBLISHABLE_KEY')) {
    define('SUPABASE_PUBLISHABLE_KEY', (string)(getenv('SUPABASE_PUBLISHABLE_KEY') ?: ''));
}

function supabase_is_configured(): bool {
    return SUPABASE_URL !== '' && str_starts_with(SUPABASE_URL, 'https://') && str_starts_with(SUPABASE_PUBLISHABLE_KEY, 'sb_publishable_');
}

/** Operações públicas limitadas pelas políticas RLS do projeto. Nunca inclua senhas/segredos neste cliente. */
function supabase_insert(string $table, array $row): array {
    $allowed = ['denuncias', 'doacoes', 'contatos', 'solicitacoes_adocao', 'apadrinhamentos'];
    if (!in_array($table, $allowed, true)) {
        return ['ok'=>false, 'status'=>0, 'error'=>'Tabela não permitida para inserção pelo site.'];
    }
    if (!supabase_is_configured()) {
        return ['ok'=>false, 'status'=>0, 'error'=>'Configuração do Supabase ausente.'];
    }
    $payload = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($payload === false) {
        return ['ok'=>false, 'status'=>0, 'error'=>'Não foi possível preparar os dados para o Supabase.'];
    }
    $url = SUPABASE_URL . '/rest/v1/' . rawurlencode($table);
    $status = 0;
    $response = false;
    $transportError = '';
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'apikey: ' . SUPABASE_PUBLISHABLE_KEY,
                'Prefer: return=minimal',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($response === false) $transportError = (string)curl_error($ch);
        curl_close($ch);
    } else {
        $ctx = stream_context_create(['http'=>[
            'method'=>'POST',
            'header'=>"Content-Type: application/json\r\n" .
                'apikey: ' . SUPABASE_PUBLISHABLE_KEY . "\r\n" .
                "Prefer: return=minimal\r\n",
            'content'=>$payload, 'ignore_errors'=>true, 'timeout'=>12,
        ]]);
        $response = @file_get_contents($url, false, $ctx);
        foreach (($http_response_header ?? []) as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $matches)) $status = (int)$matches[1];
        }
        if ($response === false) $transportError = 'A hospedagem não conseguiu estabelecer conexão HTTPS.';
    }
    $decoded = is_string($response) && $response !== '' ? json_decode($response, true) : null;
    $ok = $status >= 200 && $status < 300;
    $error = '';
    if (!$ok) {
        $message = is_array($decoded) ? (string)($decoded['message'] ?? $decoded['hint'] ?? $decoded['details'] ?? '') : '';
        $error = $status === 0
            ? ('Falha de conexão de saída HTTPS: ' . ($transportError !== '' ? $transportError : 'sem resposta do Supabase'))
            : 'Supabase HTTP ' . $status . ($message !== '' ? ': ' . $message : ' (verifique tabela, chave, grants e RLS)');
    }
    return ['ok'=>$ok, 'status'=>$status, 'data'=>$decoded, 'error'=>$error];
}
