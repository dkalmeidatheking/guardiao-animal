<?php
require __DIR__.'/includes/bootstrap.php';
require __DIR__.'/includes/ourinhos.php';


if($_SERVER['REQUEST_METHOD']==='POST'){
    check_csrf();
    $cidade = 'Ourinhos - SP';
    $bairro = normalize_ourinhos_bairro(form_value('bairro', 100));
    $local = form_value('local', 250);
    $descricao = form_value('descricao', 3000);
    $especieEscolhida = form_value('especie', 40);
    $urgencia = form_value('urgencia', 30);
    $identificado = isset($_POST['identificacao']);
    $nome = $identificado ? form_value('nome', 150) : '';
    $email = $identificado ? form_value('email', 190) : '';
    $telefone = $identificado ? form_value('telefone', 40) : '';
    $urgencias = ['Baixa'=>'baixa','Média'=>'media','Alta'=>'alta','Emergência'=>'emergencia'];
    if (!$bairro || mb_strlen($local) < 5 || mb_strlen($descricao) < 15 || !isset($urgencias[$urgencia]) || !in_array($especieEscolhida, ['Cão','Gato','Outro','Não sei informar'], true)) {
        flash('bad', 'Informe um bairro válido, local com pelo menos 5 caracteres e descrição com pelo menos 15 caracteres.');
        redirect('denuncia.php');
    }
    // Quando a pessoa seleciona "Outro", salva a espécie digitada nos relatórios e no painel das ONGs.
    $especie = $especieEscolhida === 'Outro' ? form_value('especie_outro', 40) : $especieEscolhida;
    if ($especieEscolhida === 'Outro' && mb_strlen($especie) < 2) {
        flash('bad', 'Ao selecionar "Outro", informe qual é o animal (pelo menos 2 caracteres).');
        redirect('denuncia.php');
    }
    $telefoneDigitos = preg_replace('/\D/', '', $telefone) ?? '';
    if ($identificado && (mb_strlen($nome) < 2 || ($email === '' && $telefone === '') || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) || ($telefone !== '' && (strlen($telefoneDigitos) < 10 || strlen($telefoneDigitos) > 13)))) {
        flash('bad', 'Para se identificar, informe seu nome e pelo menos um contato válido.');
        redirect('denuncia.php');
    }
    // O bairro explicitamente escolhido não é substituído por uma classificação de IA.
    $codigo = 'GA-'.strtoupper(bin2hex(random_bytes(5)));
    try {
        // Publicação na rede: ONGs aprovadas leem esta denúncia imediatamente, sem atribuição.
        $protocolo = add_store('denuncias', [
            'protocolo'=>$codigo,
            'nome'=>$nome !== '' ? $nome : 'Não informado',
            'email'=>$email, 'telefone'=>$telefone,
            'especie'=>$especie, 'urgencia'=>$urgencia,
            'cidade'=>$cidade, 'bairro'=>$bairro,
            'bairro_origem'=>'Formulário', 'local'=>$local,
            'descricao'=>$descricao,
            'identificacao'=>$identificado ? 'Sim' : 'Não',
            'status'=>'Recebida',
            'ong_id'=>null,
            'routing'=>'visivel_todas_ongs_aprovadas',
            'supabase_sync'=>'sincronizado'
        ]);
        $_SESSION['last_denuncia']=$protocolo;
        $_SESSION['last_denuncia_ref']=$codigo;
        flash('ok','Denúncia '.$codigo.' recebida com sucesso! Guarde o seu número de protocolo para acompanhar.');
        redirect('denuncia.php?enviado='.$protocolo.'&ref='.$codigo);
    } catch (Throwable $ex) {
        flash('bad', 'Não foi possível registrar a denúncia no momento: '.$ex->getMessage());
        redirect('denuncia.php');
    }
}

$includeMapAssets=false;
$pageTitle='Denúncia';
require __DIR__.'/includes/header.php';
$bairros=array_keys(ourinhos_neighborhoods());

$enviadoId = (int)($_GET['enviado'] ?? 0);
$refCode = strtoupper(trim((string)($_GET['ref'] ?? '')));
$isSuccess = ($enviadoId > 0 && $enviadoId === (int)($_SESSION['last_denuncia'] ?? 0))
    || ($refCode !== '' && $refCode === ($_SESSION['last_denuncia_ref'] ?? ''));
if (!$isSuccess && $refCode !== '') {
    // Permite visualização caso tenha o código de referência válido
    foreach (read_store('denuncias') as $row) {
        if (strtoupper((string)($row['protocolo'] ?? '')) === $refCode) {
            $isSuccess = true;
            $_SESSION['last_denuncia_ref'] = $refCode;
            break;
        }
    }
}
$displayRef = $_SESSION['last_denuncia_ref'] ?? ($refCode ?: '#'.$enviadoId);
?>
<section class="page-hero">
  <div class="container">
    <span class="eyebrow">Denúncia · Ourinhos</span>
    <h1>O que você viu merece ser registrado.</h1>
    <p>Informe o local, o bairro e o que aconteceu. Cada registro ajuda a mostrar onde a rede precisa estar mais atenta.</p>
  </div>
</section>
<section class="section">
<?php if($isSuccess): ?>
  <div class="container sent-confirmation reveal is-visible" role="status" aria-live="polite">
    <div class="sent-symbol" aria-hidden="true">✓</div>
    <div>
      <span class="eyebrow">Registro enviado com sucesso</span>
      <h2>Recebemos sua denúncia!</h2>
      <p>Seu número de protocolo é: <strong style="font-family:monospace;font-size:20px;color:var(--forest)"><?=e($displayRef)?></strong></p>
      <p>O registro foi gravado com a situação <strong>Recebida</strong> e já está disponível para as ONGs cadastradas e para a administração. Dados de contato particulares ficam protegidos.</p>
      <div class="action-row" style="margin-top:20px">
        <a class="btn btn-mint" href="<?=base_url('consultar.php?protocolo='.urlencode($displayRef))?>">Acompanhar andamento desta denúncia →</a>
        <a class="btn btn-dark" href="<?=base_url('mapa.php')?>">Ver mapa de Ourinhos</a>
        <a class="btn btn-outline" href="<?=base_url('denuncia.php')?>">Registrar outra ocorrência</a>
      </div>
      <small style="display:block;margin-top:16px">Em caso de perigo imediato, procure também os serviços públicos de emergência competentes.</small>
    </div>
  </div>
<?php else: ?>
  <div class="container form-shell">
    <div style="background:#f4f9f6;border:1px solid #d4e7dd;border-radius:12px;padding:14px 18px;margin-bottom:24px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
      <span>Já enviou uma denúncia e deseja acompanhar o andamento?</span>
      <a class="btn btn-outline" style="padding:6px 14px;font-size:14px" href="<?=base_url('consultar.php')?>">Consultar protocolo →</a>
    </div>
    <div style="margin-bottom:24px"><span class="eyebrow">Leva poucos minutos</span><h2>Registrar uma ocorrência</h2><p>O bairro é usado para atualizar o mapa de denúncias de Ourinhos. O bairro escolhido é mantido no registro, sem substituição automática.</p></div>
    <p class="form-help-notice">Informe o máximo de detalhes que souber. Seus dados de contato são opcionais, mas podem facilitar esclarecimentos.</p>
    <form method="post" data-enhanced-form>
      <input type="hidden" name="csrf" value="<?=csrf()?>">
      <div class="form-grid">
        <div class="field"><label for="report-species">Espécie</label><select id="report-species" name="especie" aria-controls="report-other-species-fields" required><option>Cão</option><option>Gato</option><option>Outro</option><option>Não sei informar</option></select></div>
        <div class="field"><label for="report-urgency">Urgência</label><select id="report-urgency" name="urgencia" required><option>Baixa</option><option>Média</option><option>Alta</option><option>Emergência</option></select></div>
        <div class="field full" id="report-other-species-fields"><label for="report-other-species-input">Qual animal? *</label><input id="report-other-species-input" name="especie_outro" type="text" minlength="2" maxlength="40" placeholder="Ex.: cavalo, coelho ou pássaro" autocomplete="off"><small>Preencha apenas se escolher "Outro".</small></div>
        <div class="field"><label for="report-city">Cidade</label><input id="report-city" value="Ourinhos - SP" readonly></div>
        <div class="field"><label for="report-bairro">Bairro da ocorrência *</label><select id="report-bairro" name="bairro" required><option value="">Selecione o bairro</option><?php foreach($bairros as $bairro): ?><option value="<?=e($bairro)?>"><?=e($bairro)?></option><?php endforeach; ?></select></div>
        <div class="field full"><label for="report-location">Local ou ponto de referência *</label><input id="report-location" name="local" minlength="5" maxlength="250" placeholder="Rua, esquina, ponto de referência..." required></div>
        <div class="field full"><label for="report-description">O que aconteceu? *</label><textarea id="report-description" name="descricao" minlength="15" maxlength="3000" required placeholder="Descreva o que viu, desde quando e se o animal precisa de atendimento."></textarea></div>
      </div>
      <div class="report-identity">
        <label class="report-identity-toggle" for="report-identificacao">
          <input type="checkbox" id="report-identificacao" name="identificacao" aria-controls="report-contact-fields" aria-expanded="false">
          <span class="report-identity-copy"><strong>Quero me identificar</strong><small>Opcional. Seus dados de contato serão registrados somente se você marcar esta opção, para facilitar o retorno da equipe.</small></span>
        </label>
        <div class="report-identity-fields" id="report-contact-fields" hidden>
          <div class="form-grid">
            <div class="field"><label for="report-name">Seu nome *</label><input id="report-name" name="nome" autocomplete="name" maxlength="150" disabled></div>
            <div class="field"><label for="report-email">E-mail</label><input id="report-email" type="email" name="email" autocomplete="email" maxlength="190" placeholder="voce@exemplo.com" disabled></div>
            <div class="field"><label for="report-phone">Telefone</label><input id="report-phone" name="telefone" type="tel" autocomplete="tel" maxlength="40" placeholder="(14) 99999-9999" disabled></div>
          </div>
          <p class="form-note">Preencha o nome e pelo menos uma forma de contato: e-mail ou telefone.</p>
        </div>
      </div>
      <div class="form-action-row"><button class="btn btn-dark" type="submit" data-submit-label="Enviando denúncia...">Enviar denúncia</button><a class="btn btn-outline" href="<?=base_url('mapa.php')?>">Ver mapa de Ourinhos</a></div>
    </form>
    <script>
    // Mostra o campo extra apenas quando a espécie escolhida for "Outro".
    (() => {
      const species = document.getElementById('report-species');
      const otherRow = document.getElementById('report-other-species-fields');
      const otherInput = document.getElementById('report-other-species-input');
      if (!species || !otherRow || !otherInput) return;
      const syncOtherSpecies = () => {
        const enabled = species.value === 'Outro';
        otherRow.hidden = !enabled;
        otherRow.style.display = enabled ? '' : 'none';
        otherInput.disabled = !enabled;
        otherInput.required = enabled;
        if (!enabled) otherInput.value = '';
      };
      species.addEventListener('change', syncOtherSpecies);
      syncOtherSpecies();
    })();
    </script>
  </div>
<?php endif; ?>
</section>
<?php require __DIR__.'/includes/footer.php'; ?>
