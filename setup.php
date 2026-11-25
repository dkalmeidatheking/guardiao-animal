<?php
require __DIR__.'/includes/bootstrap.php';
$error='';$done=false;
try {$configured=admin_configured();}catch(Throwable $e){$configured=false;$error='Configure a conexão Supabase e aplique o SQL V36 antes do cadastro inicial.';}
if($_SERVER['REQUEST_METHOD']==='POST'&&!$configured) {
  check_csrf();
  try {
    $cfg=ga_private_config();
    $token=(string)(getenv('GA_SETUP_TOKEN')?:($cfg['setup_token']??''));
    if(strlen($token)<24 || !hash_equals($token,(string)($_POST['codigo']??'')))throw new RuntimeException('Código de instalação inválido. Configure GA_SETUP_TOKEN no servidor.');
    $nome=form_value('nome',120);$email=form_value('email',190);$senha=(string)($_POST['senha']??'');
    if(mb_strlen($nome)<3 || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($senha)<12)throw new RuntimeException('Informe nome, e-mail e uma senha de 12 caracteres ou mais.');
    if(admin_configured())throw new RuntimeException('Já existe administrador.');
    add_store('admins',['nome'=>$nome,'username'=>'principal','email'=>$email,'password_hash'=>password_hash($senha,PASSWORD_DEFAULT),'tipo'=>'principal','status'=>'Ativo']);
    $done=true;$configured=true;
  } catch(Throwable $e){$error=$e->getMessage();}
}
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Configuração inicial | Guardião Animal</title><link rel="stylesheet" href="<?=base_url('assets/css/style.css')?>"></head><body><main class="login-card"><h1>Configuração inicial</h1>
<?php if($done):?><p>Administrador criado no Supabase. <a href="<?=base_url('admin/login.php')?>">Entrar</a></p>
<?php elseif($configured):?><p>A conta principal já existe. <a href="<?=base_url('admin/login.php')?>">Entrar no painel</a>.</p>
<?php else:?><?php if($error):?><p class="auth-error"><?=e($error)?></p><?php endif;?>
<form method="post"><input type="hidden" name="csrf" value="<?=csrf()?>"><div class="field"><label>Código de instalação do servidor</label><input name="codigo" type="password" required></div><div class="field"><label>Nome do responsável</label><input name="nome" required></div><div class="field"><label>E-mail</label><input name="email" type="email" required></div><div class="field"><label>Senha (mínimo 12 caracteres)</label><input name="senha" type="password" minlength="12" required></div><button class="btn btn-dark">Criar administrador</button></form>
<?php endif;?></main></body></html>
