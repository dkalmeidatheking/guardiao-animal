<?php
require __DIR__.'/includes/bootstrap.php';
global $news;
$pageTitle='Guias e histórias';
require __DIR__.'/includes/header.php';
?>
<section class="page-hero editorial-hero">
  <div class="container editorial-heading">
    <div><span class="eyebrow">Biblioteca Guardião Animal</span><h1>Informação que ajuda a <em>cuidar melhor.</em></h1><p>Orientações práticas para denunciar, adotar e apoiar animais em Ourinhos — sem complicação.</p></div>
    <div class="editorial-hero-note"><span aria-hidden="true">✦</span><strong>Cuidar também é se informar.</strong><p>Escolha um assunto, leia as orientações e descubra como participar.</p></div>
  </div>
</section>
<section class="section editorial-section">
  <div class="container">
    <div class="editorial-list-head"><div><span class="eyebrow">Leia no seu ritmo</span><h2>Guias para começar</h2></div><span class="editorial-count"><?=count($news)?> conteúdos</span></div>
    <div class="editorial-grid">
      <?php foreach($news as $id=>$n): ?>
      <article class="editorial-card <?=($id===0?'editorial-featured':'')?>">
        <a class="editorial-card-link" href="<?=base_url('noticia.php?id='.$id)?>" aria-label="Ler <?=e($n['titulo'])?>">
          <div class="editorial-photo" style="background-image:url('<?=e($n['foto'])?>')" role="img" aria-label="Foto ilustrativa de animal"></div>
          <div class="editorial-content">
            <div class="editorial-meta"><span class="editorial-tag"><?=e($n['categoria'])?></span><span><?=e($n['tempo'])?></span></div>
            <h3><?=e($n['titulo'])?></h3>
            <p><?=e($n['resumo'])?></p>
            <span class="editorial-read">Ler conteúdo <span aria-hidden="true">↗</span></span>
          </div>
        </a>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<section class="editorial-action">
 <div class="container editorial-action-inner"><div><span class="eyebrow">Agora é com você</span><h2>Pronto para ajudar?</h2><p>Transforme o que você aprendeu em um próximo passo.</p></div><div class="action-row"><a class="btn btn-dark" href="<?=base_url('denuncia.php')?>">Registrar uma ocorrência</a><a class="btn btn-outline" href="<?=base_url('ongs.php')?>">Conhecer ONGs</a></div></div>
</section>
<?php require __DIR__.'/includes/footer.php'; ?>
