<?php
require __DIR__.'/../includes/bootstrap.php';
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    check_csrf();
    $email=strtolower(form_value('email',190));$password=(string)($_POST['senha']??'');
    $name=form_value('nome',120); $city=form_value('cidade',100);$phone=form_value('telefone',40);
    if (mb_strlen($name)<3 || mb_strlen($city)<3 || !$phone) $error='Informe o nome, a cidade e o telefone da organização.';
    elseif (!filter_var($email,FILTER_VALIDATE_EMAIL)) $error='Informe um e-mail válido.';
    elseif (strlen($password)<10) $error='A senha precisa ter pelo menos 10 caracteres.';
    elseif ($password!==($_POST['confirmacao']??'')) $error='As senhas não coincidem.';
    else {
        foreach (read_store('ong_accounts') as $a) if (strtolower($a['email'])===$email) {$error='Este e-mail já está cadastrado.';break;}
    }
    if (!$error) {
        $newOngId=add_store('ong_accounts',[
            'nome'=>$name,'email'=>$email,'password_hash'=>password_hash($password,PASSWORD_DEFAULT),
            'telefone'=>$phone,'cidade'=>$city,'bairro'=>form_value('bairro',90),'ano'=>form_value('ano',4),
            'foco'=>form_value('foco',100),'descricao'=>form_value('descricao',900),'historia'=>'',
            'voluntarios'=>0,'foto'=>'','status'=>'Pendente'
        ]);
        $_SESSION['ong_signup_id']=$newOngId;
        redirect('portal/cadastro.php?enviado=1');
    }
}
$showSuccess = ($_GET['enviado'] ?? '') === '1' && (int)($_SESSION['ong_signup_id'] ?? 0) > 0;
if ($showSuccess) {
    $found=false;
    foreach (read_store('ong_accounts') as $a) if ((int)($a['id']??0)===(int)$_SESSION['ong_signup_id']) { $found=true; break; }
    $showSuccess=$found;
}
$pageTitle='Cadastro de ONG';require __DIR__.'/../includes/header.php';
?><section class="page-hero"><div class="container"><span class="eyebrow">Para organizações</span><h1>Faça parte da rede de proteção.</h1><p>Cadastre sua ONG para organizar os atendimentos recebidos e publicar animais para adoção. O primeiro acesso depende de aprovação do administrador.</p></div></section>
<section class="section"><div class="container form-shell <?= $showSuccess ? 'ong-signup-success' : '' ?>">
<?php if ($showSuccess): ?>
    <div class="sent-confirmation reveal is-visible" role="status" aria-live="polite">
      <div class="sent-symbol" aria-hidden="true">✓</div>
      <div>
        <span class="eyebrow">Solicitação enviada</span>
        <h2>Cadastro recebido com sucesso!</h2>
        <p>Sua solicitação foi enviada ao administrador do Guardião Animal para análise. Após a avaliação, sua ONG poderá ser <strong>aprovada ou recusada</strong>. O acesso ao painel fica bloqueado até a aprovação.</p>
        <p>Você poderá consultar a situação do cadastro tentando acessar a conta com o e-mail e a senha informados.</p>
        <div class="action-row"><a class="btn btn-dark" href="<?=base_url('portal/login.php')?>">Ir para o acesso da ONG</a><a class="btn btn-outline" href="<?=base_url()?>">Voltar ao início</a></div>
      </div>
    </div>
<?php else: ?>
<h2>Cadastrar minha ONG</h2><?php if($error):?><div class="auth-error"><?=e($error)?></div><?php endif;?><form method="post" class="ong-registration-form"><input type="hidden" name="csrf" value="<?=csrf()?>"><div class="form-grid">
<div class="field"><label>Nome da ONG *</label><input name="nome" maxlength="120" value="<?=e($_POST['nome']??'')?>" required></div>
<div class="field"><label>E-mail para login *</label><input type="email" name="email" maxlength="190" value="<?=e($_POST['email']??'')?>" required></div>
<div class="field"><label>Telefone de contato *</label><input name="telefone" maxlength="40" required></div>
<div class="field"><label>Cidade / UF *</label><input name="cidade" placeholder="Ourinhos, SP" maxlength="100" required></div>
<div class="field"><label>Bairro</label><input name="bairro" maxlength="90"></div>
<div class="field"><label>Ano de criação</label><input name="ano" type="number" min="1900" max="<?=date('Y')?>" placeholder="2020"></div>
<div class="field"><label>Foco de atuação</label><input name="foco" placeholder="Resgates, adoção, tratamento..." maxlength="100"></div>
<div class="field full"><label>Sobre a ONG</label><textarea name="descricao" maxlength="900" placeholder="Apresente os trabalhos realizados pela organização"></textarea></div>
<div class="field"><label>Criar senha (mínimo 10 caracteres) *</label><input type="password" name="senha" minlength="10" autocomplete="new-password" required></div>
<div class="field"><label>Repetir senha *</label><input type="password" name="confirmacao" minlength="10" autocomplete="new-password" required></div>
</div><div class="action-row"><button class="btn btn-dark">Enviar cadastro</button><a class="btn btn-outline" href="<?=base_url('portal/login.php')?>">Já tenho cadastro</a></div></form><?php endif; ?></div></section><?php require __DIR__.'/../includes/footer.php'; ?>
