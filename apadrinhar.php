<?php
require __DIR__.'/includes/bootstrap.php';
$listed=[];
foreach ($animals as $id=>$a) {
    if (($a['status'] ?? '') === 'Apadrinhamento') $listed[$id]=$a;
}
$selectedId=(int)($_GET['animal'] ?? 0);
if (!isset($listed[$selectedId])) $selectedId=(int)(array_key_first($listed) ?? 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $id=(int)($_POST['animal_id'] ?? 0);
    $animal=$listed[$id] ?? null;
    $nome=form_value('nome',150);
    $email=form_value('email',190);
    $mensagem=form_value('mensagem',1000);
    $valorEscolhido=form_value('valor',15);
    $valorRaw=$valorEscolhido === 'outro' ? form_value('outro_valor',20) : $valorEscolhido;
    $valorNormalizado=preg_match('/^[0-9]{1,5}(?:[,.][0-9]{1,2})?$/D', $valorRaw) ? str_replace(',', '.', $valorRaw) : '';
    $valor=is_numeric($valorNormalizado) ? round((float)$valorNormalizado,2) : 0.0;
    if (!$animal || !in_array($valorEscolhido,['30','50','80','outro'],true) || $valor < 10 || $valor > 10000 || mb_strlen($nome) < 2 || !filter_var($email,FILTER_VALIDATE_EMAIL)) {
        flash('bad','Selecione um animal, um valor mensal válido (a partir de R$ 10), informe seu nome e um e-mail válido.');
        redirect('apadrinhar.php#form');
    }
    $ownerAccount=approved_ong_account_for_public_id((int)($animal['ong'] ?? 0));
    $localId=add_store('apadrinhamentos',[
        'animal_id'=>$id, 'animal'=>$animal['nome'], 'nome'=>$nome, 'email'=>$email,
        'valor'=>$valor, 'mensagem'=>$mensagem, 'status'=>$ownerAccount ? 'Encaminhado' : 'Aguardando encaminhamento',
        'ong_id'=>$ownerAccount ? (int)$ownerAccount['id'] : null, 'animal_publico_id'=>$id,
        'supabase_sync'=>'sincronizado'
    ]);
    $_SESSION['last_apadrinhamento']=$localId;
    flash('ok','Seu interesse em apadrinhar '.$animal['nome'].' foi registrado no Supabase.');
    redirect('apadrinhar.php?enviado='.$localId.'#form');
}
$pageTitle='Apadrinhar';
require __DIR__.'/includes/header.php';
$showSuccess=isset($_GET['enviado']) && ctype_digit((string)$_GET['enviado']) && (int)$_GET['enviado']===(int)($_SESSION['last_apadrinhamento'] ?? 0);
?>
<section class="page-hero"><div class="container"><span class="eyebrow">Apadrinhamento</span><h1>Um gesto de cuidado que continua.</h1><p>O apadrinhamento pode ajudar com alimentação, exames e tratamento de animais em recuperação. Cadastre seu interesse para a equipe entrar em contato.</p></div></section>
<section class="section"><div class="container cards">
<?php foreach($listed as $id=>$a): ?>
<article class="animal-card"><div class="card-photo" style="background-image:url('<?=e($a['foto'])?>')"></div><div class="card-body"><span class="chip">Precisa de padrinho</span><?php if (!approved_ong_account_for_public_id((int)($a['ong']??0))): ?><span class="chip" style="margin-left:5px">Encaminhamento pelo ADM</span><?php endif;?><h3><?=e($a['nome'])?></h3><div class="meta"><span><?=e($a['idade'])?></span><span>·</span><span><?=e($a['porte'])?></span></div><p><?=e($a['temperamento'])?></p><div class="card-bottom"><a class="text-link" href="<?=base_url('animal.php?id='.$id)?>">Ver história →</a><a class="btn btn-mint" href="<?=base_url('apadrinhar.php?animal='.$id.'#form')?>">Apadrinhar</a></div></div></article>
<?php endforeach; ?>
<?php if(!$listed): ?><div class="empty-state">Não há animais cadastrados para apadrinhamento no momento. <a href="<?=base_url('ongs.php')?>">Conheça as ONGs da rede.</a></div><?php endif; ?>
</div></section>
<section class="section section-soft" id="form"><div class="container form-shell">
<?php if($showSuccess): ?>
  <div class="sent-confirmation" role="status"><div class="sent-symbol" aria-hidden="true">✓</div><div><span class="eyebrow">Interesse registrado</span><h2>Cadastro recebido!</h2><p>Seu pedido foi recebido pela administração e será encaminhado à ONG responsável quando ela tiver uma conta aprovada no portal. Isso não realiza cobrança nem ativa pagamento recorrente.</p><div class="form-action-row"><a class="btn btn-dark" href="<?=base_url('apadrinhar.php#form')?>">Fazer outro cadastro</a><a class="btn btn-outline" href="<?=base_url('ongs.php')?>">Conhecer as ONGs</a></div></div></div>
<?php elseif($listed): ?>
  <span class="eyebrow">Cadastro</span><h2>Quero apadrinhar</h2>
  <p>Preencha seus dados para demonstrar interesse. O valor é uma intenção mensal; nenhum pagamento será cobrado por este formulário.</p>
  <form method="post" class="sponsor-form" data-enhanced-form>
    <input type="hidden" name="csrf" value="<?=csrf()?>">
    <div class="form-grid">
      <div class="field"><label for="sponsor-animal">Animal *</label><select id="sponsor-animal" name="animal_id" required><?php foreach($listed as $id=>$a):?><option value="<?=$id?>" <?=$id===$selectedId?'selected':''?>><?=e($a['nome'])?></option><?php endforeach;?></select></div>
      <div class="field"><label for="sponsor-amount">Valor mensal estimado *</label><select id="sponsor-amount" name="valor" required><option value="30">R$ 30,00</option><option value="50">R$ 50,00</option><option value="80">R$ 80,00</option><option value="outro">Outro valor</option></select></div>
      <div class="field full custom-value" id="sponsor-custom-row" hidden><label for="sponsor-custom-amount">Informe outro valor mensal (R$) *</label><input id="sponsor-custom-amount" name="outro_valor" type="number" min="10" max="10000" step="0.01" placeholder="Ex.: 100,00" disabled></div>
      <div class="field"><label for="sponsor-name">Nome *</label><input id="sponsor-name" name="nome" autocomplete="name" maxlength="150" required></div>
      <div class="field"><label for="sponsor-email">E-mail *</label><input id="sponsor-email" name="email" type="email" autocomplete="email" maxlength="190" required></div>
      <div class="field full"><label for="sponsor-message">Mensagem (opcional)</label><textarea id="sponsor-message" name="mensagem" maxlength="1000" placeholder="Quer contar por que escolheu ajudar?"></textarea></div>
    </div>
    <div class="form-action-row"><button class="btn btn-dark" type="submit" data-submit-label="Enviando cadastro...">Enviar cadastro</button></div>
    <p class="form-note">Sua solicitação é avaliada pela equipe antes de qualquer compromisso.</p>
  </form>
<?php endif; ?>
</div></section>
<?php require __DIR__.'/includes/footer.php'; ?>
