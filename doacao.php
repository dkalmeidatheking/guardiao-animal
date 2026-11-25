<?php
require __DIR__.'/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $nome = form_value('nome', 150);
    $email = form_value('email', 190);
    $mensagem = form_value('mensagem', 700);
    $ongId = (int)($_POST['ong_id'] ?? 0);
    $ong = find_ong($ongId);
    $valorRaw = trim((string)($_POST['valor'] ?? ''));
    // Aceita tanto 50.00 quanto 50,00 e também valores digitados como R$ 1.250,00.
    $normalized = preg_match('/^(?:R\$\s*)?[0-9][0-9,.]*$/uD', $valorRaw) ? (preg_replace('/[^0-9,\.]/', '', $valorRaw) ?? '') : '';
    if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
        $normalized = str_replace('.', '', $normalized);
        $normalized = str_replace(',', '.', $normalized);
    } elseif (str_contains($normalized, ',')) {
        $normalized = str_replace(',', '.', $normalized);
    }
    $valor = is_numeric($normalized) ? round((float)$normalized, 2) : 0.0;

    if (mb_strlen($nome) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('bad', 'Informe seu nome e um e-mail válido.');
        redirect('doacao.php' . ($ongId ? '?ong='.$ongId : ''));
    }
    if (!$ong) {
        flash('bad', 'Selecione uma ONG válida para receber a doação.');
        redirect('doacao.php');
    }
    if ($valor < 1 || $valor > 100000) {
        flash('bad', 'Informe um valor entre R$ 1,00 e R$ 100.000,00.');
        redirect('doacao.php?ong='.$ongId);
    }

    $comissao = round($valor * 0.10, 2);
    $liquido = round($valor - $comissao, 2);
    $ownerAccount = approved_ong_account_for_public_id($ongId);
    $localId = add_store('doacoes', [
        'nome'=>$nome, 'email'=>$email, 'valor'=>$valor,
        'ong_id'=>$ongId, 'ong_account_id'=>$ownerAccount ? (int)$ownerAccount['id'] : null, 'ong_nome'=>$ong['nome'],
        'comissao_plataforma'=>$comissao, 'valor_ong'=>$liquido,
        'mensagem'=>$mensagem, 'forma'=>'Pix demonstrativo',
        'status'=>'Intenção registrada', 'pagamento_confirmado'=>false,
    ]);

    $_SESSION['last_doacao'] = $localId;
    $_SESSION['last_doacao_sync'] = true;
    flash('ok','Sua intenção de doação foi registrada no Supabase. Nenhum pagamento foi realizado.');
    redirect('doacao.php?ong='.$ongId.'&registrada='.$localId.'#doar');
}

$pageTitle='Doar';
$selectedOng = (int)($_GET['ong'] ?? 0);
if (!isset($ongs[$selectedOng])) $selectedOng = 0;
$donationRegistrationId = (int)($_GET['registrada'] ?? 0);
$showDonationSuccess = $donationRegistrationId > 0 && $donationRegistrationId === (int)($_SESSION['last_doacao'] ?? 0);
$successDonation = null;
if ($showDonationSuccess) {
    foreach (read_store('doacoes') as $savedDonation) {
        if ((int)($savedDonation['id'] ?? 0) === $donationRegistrationId) { $successDonation = $savedDonation; break; }
    }
}
require __DIR__.'/includes/header.php';
?>
<section class="page-hero"><div class="container"><span class="eyebrow">Doação direcionada</span><h1>Escolha a ONG que vai receber sua contribuição.</h1><p>Você decide qual organização deseja apoiar. Abaixo, o site mostra a divisão demonstrativa do valor: 10% para a plataforma e 90% para a ONG.</p></div></section>
<section class="section" id="doar"><div class="container">
  <?php if($showDonationSuccess && $successDonation): ?>
  <div class="sent-confirmation donation-success reveal is-visible" role="status" aria-live="polite">
    <div class="sent-symbol" aria-hidden="true">✓</div>
    <div><span class="eyebrow">Intenção registrada</span><h2>Obrigado por apoiar!</h2>
      <p>Sua intenção de contribuir para <strong><?=e($successDonation['ong_nome'] ?? 'a ONG selecionada')?></strong> foi registrada. <?=empty($successDonation['ong_account_id']) ? 'Esta ONG ainda não tem painel próprio vinculado; o registro ficará disponível para o administrador.' : 'O registro também está disponível para a ONG em seu painel.'?> Valor informado: <strong>R$ <?=number_format((float)($successDonation['valor'] ?? 0),2,',','.')?></strong>; taxa demonstrativa de 10%: <strong>R$ <?=number_format((float)($successDonation['comissao_plataforma'] ?? 0),2,',','.')?></strong>; valor líquido estimado para a ONG: <strong>R$ <?=number_format((float)($successDonation['valor_ong'] ?? 0),2,',','.')?></strong>.</p>
      <?php if(empty($_SESSION['last_doacao_sync'])): ?><p class="success-sync-note">O registro foi salvo no site, mas a sincronização com o Supabase ainda está pendente.</p><?php endif; ?>
      <div class="payment-simulation-note" role="note" aria-label="Aviso sobre pagamento demonstrativo">
        <span class="payment-simulation-icon" aria-hidden="true">i</span>
        <p><strong>Pagamento não realizado.</strong><span> Esta é uma simulação: nenhum Pix, cartão ou transferência foi processado.</span></p>
      </div>
      <div class="action-row"><a class="btn btn-dark" href="<?=base_url('ongs.php')?>">Conhecer outras ONGs</a><a class="btn btn-outline" href="<?=base_url('doacao.php')?>">Registrar outra intenção</a></div>
    </div>
  </div>
  <?php endif; ?>
  <div class="donate-box">
  <div><span class="eyebrow" style="color:var(--mint)">Transparência no repasse</span><h2 style="margin-top:8px">Sua contribuição, com destino definido.</h2><p>Este formulário é uma demonstração acadêmica. Ele registra a intenção de doação diretamente no Supabase, mas não processa Pix, cartão ou transferência.</p><div class="payout-explainer"><div><span>Doação de exemplo</span><strong>R$ 50,00</strong></div><div><span>Taxa da plataforma (10%)</span><strong>R$ 5,00</strong></div><div><span>Valor destinado à ONG (90%)</span><strong>R$ 45,00</strong></div></div><small class="form-help-notice">Os valores líquidos só representam o cálculo previsto. Para receber dinheiro de verdade, será necessário conectar um meio de pagamento e confirmar a transação antes de considerar a doação paga.</small></div>
  <form method="post" style="background:#fff;color:var(--ink);padding:24px;border-radius:20px" id="donation-form" data-enhanced-form>
    <input type="hidden" name="csrf" value="<?=csrf()?>">
    <div class="field"><label for="ong_id">ONG que vai receber *</label><select name="ong_id" id="ong_id" required><option value="">Selecione a organização</option><?php foreach($ongs as $oid=>$o): ?><option value="<?=e($oid)?>" <?=$selectedOng===(int)$oid?'selected':''?>><?=e($o['nome'])?> · <?=e($o['bairro'])?><?=approved_ong_account_for_public_id((int)$oid) ? ' · Portal da ONG ativo' : ' · Perfil demonstrativo (sem portal)'?></option><?php endforeach; ?></select></div>
    <div class="field" style="margin-top:12px"><label for="nome">Seu nome *</label><input id="nome" name="nome" maxlength="150" required></div>
    <div class="field" style="margin-top:12px"><label for="email">E-mail *</label><input id="email" type="email" name="email" maxlength="190" required></div>
    <div class="field" style="margin-top:12px"><label for="valor">Valor da contribuição (R$) *</label><input id="valor" name="valor" type="number" min="1" max="100000" step="0.01" placeholder="50,00" value="50.00" required></div>
    <div class="donation-live-breakdown" aria-live="polite"><div><span>Taxa do Guardião (10%)</span><strong id="fee-live">R$ 5,00</strong></div><div><span>ONG recebe (90%)</span><strong id="net-live">R$ 45,00</strong></div></div>
    <div class="field" style="margin-top:12px"><label for="mensagem">Mensagem (opcional)</label><textarea id="mensagem" name="mensagem" maxlength="700" style="min-height:90px"></textarea></div>
    <button class="btn btn-dark" type="submit" data-submit-label="Registrando sua intenção..." style="margin-top:14px;width:100%">Registrar intenção de doação</button>
    <p style="font-size:12px;margin:12px 0 0">Nenhuma cobrança será feita por este formulário.</p>
  </form>
</div></div></section>
<script>
(function(){
 const input=document.getElementById('valor'), fee=document.getElementById('fee-live'), net=document.getElementById('net-live');
 const brl=n=>new Intl.NumberFormat('pt-BR',{style:'currency',currency:'BRL'}).format(n);
 function update(){const amount=Math.max(0,Number(input.value)||0);const commission=Math.round((amount*0.10+Number.EPSILON)*100)/100;fee.textContent=brl(commission);net.textContent=brl(Math.round((amount-commission+Number.EPSILON)*100)/100);}
 input.addEventListener('input',update);update();
})();
</script>
<?php require __DIR__.'/includes/footer.php'; ?>
