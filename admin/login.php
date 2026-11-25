<?php
require __DIR__.'/../includes/bootstrap.php';
if (is_admin()) redirect('admin/index.php');
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    check_csrf();
    if (($_SESSION['login_block_until']??0)>time()) $error='Muitas tentativas. Aguarde alguns minutos.';
    else {
        $login=strtolower(form_value('login',190));$password=(string)($_POST['senha']??'');
        foreach (read_store('admins') as $user) {
            $userEmail=strtolower((string)($user['email']??''));
            $userName=strtolower((string)($user['username']??''));
            if (($user['status']??'Ativo')!=='Ativo') continue;
            if ($login!=='' && (hash_equals($userEmail,$login) || ($userName!=='' && hash_equals($userName,$login))) && password_verify($password,$user['password_hash'])) {
                session_regenerate_id(true); $_SESSION['role']='admin';$_SESSION['admin_id']=(int)($user['id']??1);$_SESSION['admin_name']=$user['nome'];unset($_SESSION['ong_id'],$_SESSION['login_failures'],$_SESSION['login_block_until']);redirect('admin/index.php');
            }
        }
        $_SESSION['login_failures']=($_SESSION['login_failures']??0)+1;
        if ($_SESSION['login_failures']>=5) {$_SESSION['login_block_until']=time()+300;$_SESSION['login_failures']=0;}
        $error='Usuário/e-mail ou senha incorretos.';
    }
}
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Login administrativo | Guardião Animal</title><link rel="stylesheet" href="<?=base_url('assets/css/style.css')?>?v=25"></head><body class="admin"><div class="login-card"><a class="brand" href="<?=base_url()?>"><span class="logo-ring"><img src="<?=e(brand_logo_url())?>" alt="Logo Guardião Animal" decoding="async"></span><span class="admin-brand-copy"><b>Guardião Animal</b><small>Painel administrativo</small></span></a><div class="auth-intro"><span class="eyebrow">Acesso restrito</span><h1>Bem-vindo de volta.</h1><p>Gerencie as denúncias que chegam ao site e as solicitações das organizações parceiras.</p></div><?php if($error):?><div class="auth-error"><?=e($error)?></div><?php endif;?><?php if(!admin_configured()):?><p class="auth-error">A conta principal ainda não foi iniciada. Confira a permissão de gravação da pasta storage.</p><?php else: ?><form method="post"><input type="hidden" name="csrf" value="<?=csrf()?>"><div class="field"><label>Usuário ou e-mail</label><input type="text" name="login" autocomplete="username" required></div><div class="field"><label>Senha</label><input type="password" name="senha" autocomplete="current-password" required></div><button class="btn btn-dark" style="width:100%">Entrar no painel</button></form><?php endif;?><p class="auth-links"><a href="<?=base_url('admin/cadastro.php')?>">Solicitar acesso administrativo</a> · <a href="<?=base_url('portal/login.php')?>">Sou de uma ONG</a> · <a href="<?=base_url()?>">Voltar ao site</a></p></div></body></html>
