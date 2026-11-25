<?php
require __DIR__.'/includes/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $nome=form_value('nome',150); $email=form_value('email',190); $assunto=form_value('assunto',200); $mensagem=form_value('mensagem',3000);
    if (mb_strlen($nome)<2 || !filter_var($email,FILTER_VALIDATE_EMAIL) || $mensagem==='') {
        flash('bad','Confira seu nome, e-mail e mensagem.'); redirect('contato.php');
    }
    $id=add_store('contatos',['nome'=>$nome,'email'=>$email,'assunto'=>$assunto,'mensagem'=>$mensagem,'status'=>'Nova']);
    flash('ok','Mensagem registrada diretamente no Supabase.');
    redirect('contato.php');
}
$pageTitle='Contato';require __DIR__.'/includes/header.php';
?>
<section class="page-hero"><div class="container"><span class="eyebrow">Contato</span><h1>Tem uma dúvida, parceria ou quer ajudar?</h1><p>Fale com a rede. A mensagem fica registrada no painel interno diretamente no Supabase.</p></div></section>
<section class="section contact-section"><div class="container form-shell contact-shell"><div class="contact-form-heading"><span class="eyebrow">Estamos por aqui</span><h2>Envie sua mensagem</h2><p>Preencha os campos abaixo. Sua mensagem será registrada para a equipe do Guardião Animal.</p></div><form method="post" class="contact-form" data-enhanced-form><input type="hidden" name="csrf" value="<?=csrf()?>"><div class="form-grid"><div class="field"><label for="contact-nome">Nome</label><input id="contact-nome" name="nome" maxlength="150" autocomplete="name" placeholder="Como podemos chamar você?" required></div><div class="field"><label for="contact-email">E-mail</label><input id="contact-email" type="email" name="email" maxlength="190" autocomplete="email" placeholder="voce@exemplo.com" required></div><div class="field full"><label for="contact-assunto">Assunto</label><input id="contact-assunto" name="assunto" maxlength="200" placeholder="Ex.: Quero ser voluntário" required></div><div class="field full"><label for="contact-mensagem">Mensagem</label><textarea id="contact-mensagem" name="mensagem" maxlength="3000" placeholder="Escreva aqui como podemos ajudar..." required></textarea></div></div><button class="btn btn-dark contact-submit" type="submit" data-submit-label="Enviando mensagem..."><span>Enviar mensagem</span><span class="contact-submit-icon" aria-hidden="true">↗</span></button></form></div></section>
<?php require __DIR__.'/includes/footer.php'; ?>
