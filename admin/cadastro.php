<?php
require __DIR__.'/../includes/bootstrap.php';
$error = '';
$sent = false;
$oldName = '';
$oldEmail = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $oldName = form_value('nome', 120);
    $oldEmail = strtolower(form_value('email', 190));
    $password = (string)($_POST['senha'] ?? '');
    // Campo invisível contra bots; não exige ações adicionais dos usuários.
    if (trim((string)($_POST['site_empresa'] ?? '')) !== '') {
        http_response_code(400);
        exit('Solicitação inválida.');
    }
    if (mb_strlen($oldName) < 2) {
        $error = 'Informe seu nome.';
    } elseif (!filter_var($oldEmail, FILTER_VALIDATE_EMAIL)) {
        $error = 'Informe um e-mail válido.';
    } elseif (strlen($password) < 10) {
        $error = 'Crie uma senha de pelo menos 10 caracteres. Não é necessário usar símbolos especiais.';
    } elseif (strlen($password) > 200) {
        $error = 'A senha é longa demais.';
    } elseif (count(array_filter(read_store('admin_solicitacoes'), static fn($r) => ($r['created_at'] ?? '') > date('Y-m-d H:i:s', time() - 86400))) > 70) {
        $error = 'Limite temporário de solicitações. Tente mais tarde.';
    } else {
        foreach (array_merge(read_store('admins'), read_store('admin_solicitacoes')) as $row) {
            if (strtolower((string)($row['email'] ?? '')) === $oldEmail) {
                $error = 'Já existe uma conta ou solicitação com esse e-mail.';
                break;
            }
        }
    }
    if ($error === '') {
        // Usuário gerado automaticamente. Login pelo e-mail funciona normalmente.
        $prefix = strstr($oldEmail, '@', true) ?: 'admin';
        $prefix = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $prefix) ?: $prefix;
        $prefix = strtolower(preg_replace('/[^a-z0-9]+/', '_', $prefix) ?? 'admin');
        $prefix = trim($prefix, '_');
        if ($prefix === '') $prefix = 'admin';
        $prefix = substr($prefix, 0, 25);
        $taken = [];
        foreach (array_merge(read_store('admins'), read_store('admin_solicitacoes')) as $row) {
            $taken[strtolower((string)($row['username'] ?? ''))] = true;
        }
        $username = $prefix;
        for ($i = 2; isset($taken[$username]); $i++) {
            $username = $prefix . '_' . $i;
        }
        add_store('admin_solicitacoes', [
            'nome' => $oldName,
            'username' => $username,
            'email' => $oldEmail,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'status' => 'Pendente',
            'reviewed_at' => null,
            'reviewed_by' => null,
        ]);
        $sent = true;
    }
}
?><!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Solicitar acesso | Guardião Animal</title>
<link rel="stylesheet" href="<?=e(base_url('assets/css/style.css'))?>?v=34"></head>
<body class="admin"><main class="login-card" style="width:min(100% - 26px,540px)">
<p><a href="<?=e(base_url('admin/login.php'))?>">← Voltar ao login</a></p>
<span class="eyebrow">Acesso administrativo</span><h1>Solicitar cadastro</h1>
<p>Preencha seus dados. O administrador principal receberá a solicitação no painel e poderá aprovar ou recusar. O acesso fica bloqueado até a aprovação.</p>
<?php if ($sent): ?>
<div class="sent-confirmation is-visible" role="status"><div><h2>Solicitação enviada!</h2><p>Depois da aprovação, entre com seu <strong>e-mail</strong> e a senha escolhida.</p></div></div>
<?php else: ?>
<?php if ($error): ?><div class="auth-error" role="alert"><?=e($error)?></div><?php endif; ?>
<form method="post"><input type="hidden" name="csrf" value="<?=e(csrf())?>">
<div aria-hidden="true" style="position:absolute;left:-10000px;width:1px;height:1px;overflow:hidden"><label>Site <input name="site_empresa" tabindex="-1" autocomplete="off"></label></div>
<div class="field"><label for="admin-name">Seu nome *</label><input id="admin-name" name="nome" value="<?=e($oldName)?>" maxlength="120" autocomplete="name" required></div>
<div class="field"><label for="admin-email">Seu e-mail *</label><input id="admin-email" type="email" name="email" value="<?=e($oldEmail)?>" maxlength="190" autocomplete="email" required></div>
<div class="field"><label for="admin-password">Crie uma senha *</label><input id="admin-password" type="password" name="senha" minlength="10" maxlength="200" autocomplete="new-password" required><small>Ao menos 10 caracteres. Sem regras de símbolos, números ou nome de usuário.</small></div>
<div class="field" style="margin-top:10px"><label style="display:inline-flex;gap:8px;align-items:center"><input type="checkbox" id="show-admin-password" style="width:auto;min-height:auto"> Mostrar senha</label></div>
<button class="btn btn-dark" type="submit" style="width:100%;margin-top:12px">Enviar pedido de acesso</button>
</form>
<script>document.getElementById('show-admin-password')?.addEventListener('change', function(){document.getElementById('admin-password').type=this.checked?'text':'password';});</script>
<?php endif; ?></main></body></html>
