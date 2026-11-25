<?php
require __DIR__.'/includes/bootstrap.php';
$id = (int)($_GET['id'] ?? 0);
$a = find_animal($id);
if (!$a) { http_response_code(404); exit('Animal não encontrado.'); }
$ong = find_ong((int)$a['ong']);
if (!$ong) { http_response_code(404); exit('ONG responsável não encontrada.'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    http_response_code(405);
    header('Allow: GET');
    exit('Solicitações de adoção não estão disponíveis nesta versão.');
}

$pageTitle = $a['nome'];
require __DIR__.'/includes/header.php';
?>
<section class="detail-hero">
  <div class="container detail-grid">
    <div><div class="detail-photo" style="background-image:url('<?=e($a['foto'])?>')"></div></div>
    <div class="detail-side">
      <span class="chip"><?=e($a['status'])?></span>
      <h1><?=e($a['nome'])?></h1>
      <p style="font-size:19px"><?=e($a['temperamento'])?></p>
      <div class="detail-facts">
        <div class="fact"><b>Espécie</b><span><?=e($a['especie'])?></span></div>
        <div class="fact"><b>Idade</b><span><?=e($a['idade'])?></span></div>
        <div class="fact"><b>Sexo</b><span><?=e($a['sexo'])?></span></div>
        <div class="fact"><b>Porte</b><span><?=e($a['porte'])?></span></div>
        <div class="fact"><b>Encontrado em</b><span><?=e($a['local'])?></span></div>
        <div class="fact"><b>ONG responsável</b><span><?=e($ong['nome'])?></span></div>
      </div>
      <div class="callout"><span class="eyebrow" style="color:var(--mint)">Apoie quem cuida</span><h3 style="margin-top:8px">Ajude a ONG responsável.</h3><p>Você pode direcionar uma contribuição para a organização que acompanha este animal.</p><a class="btn btn-mint" href="<?=base_url('doacao.php?ong='.(int)$a['ong'])?>">Apoiar esta ONG</a></div>
    </div>
  </div>
</section>
<section class="section"><div class="container split">
  <div><span class="eyebrow">Onde foi encontrado</span><h2><?=e($a['local'])?></h2><p>Data do registro: <strong><?=e($a['data'])?></strong></p><p><?=e($a['historia'])?></p></div>
  <div><span class="eyebrow">Quem acompanha o caso</span><h2><?=e($ong['nome'])?></h2><p><?=e($ong['descricao'])?></p><a class="btn btn-dark" href="<?=base_url('ong.php?id='.(int)$a['ong'])?>">Ver página da ONG</a></div>
</div></section>
<?php require __DIR__.'/includes/footer.php'; ?>
