<?php
require __DIR__.'/../includes/bootstrap.php';
if (current_ong()) redirect('portal/index.php');
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    check_csrf();
    if (($_SESSION['ong_block_until']??0)>time()) $error='Muitas tentativas. Tente mais tarde.';
    else {
        $email=strtolower(form_value('email',190));$password=(string)($_POST['senha']??'');
        foreach (read_store('ong_accounts') as $ong) {
            if (strtolower($ong['email'])!==$email || !password_verify($password,$ong['password_hash'])) continue;
            if (($ong['status']??'')!=='Aprovada') {$error='Seu cadastro está '.strtolower($ong['status']??'pendente').'. Aguarde a liberação pelo administrador.';break;}
            session_regenerate_id(true);$_SESSION['role']='ong';$_SESSION['ong_id']=(int)$ong['id'];unset($_SESSION['ong_failures'],$_SESSION['ong_block_until']);redirect('portal/index.php');
        }
        if (!$error) $error='E-mail ou senha incorretos.';
        $_SESSION['ong_failures']=($_SESSION['ong_failures']??0)+1;
        if ($_SESSION['ong_failures']>=5) {$_SESSION['ong_block_until']=time()+300;$_SESSION['ong_failures']=0;}
    }
}
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Entrar como ONG | Guardião Animal</title><link rel="stylesheet" href="<?=base_url('assets/css/style.css')?>?v=25"></head><body class="admin"><div class="login-card"><a class="brand" href="<?=base_url()?>"><span class="logo-ring"><img src="<?=e(brand_logo_url())?>" alt="Logo Guardião Animal" decoding="async"></span><span class="admin-brand-copy"><b>Guardião Animal</b><small>Portal das ONGs</small></span></a><div class="auth-intro"><span class="eyebrow">Área da organização</span><h1>O cuidado continua aqui.</h1><p>Receba automaticamente as denúncias da rede, acompanhe seus atendimentos e publique animais.</p></div><?php if($error):?><div class="auth-error"><?=e($error)?></div><?php endif;?><form method="post"><input type="hidden" name="csrf" value="<?=csrf()?>"><div class="field"><label>E-mail da ONG</label><input type="email" name="email" autocomplete="username" required></div><div class="field"><label>Senha</label><input type="password" name="senha" autocomplete="current-password" required></div><button class="btn btn-dark" style="width:100%">Entrar como ONG</button></form><p class="auth-links"><a href="<?=base_url('portal/cadastro.php')?>">Cadastrar minha ONG</a> · <a href="<?=base_url('admin/login.php')?>">Sou administrador</a></p></div></body></html>
