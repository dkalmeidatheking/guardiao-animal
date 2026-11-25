<?php
require __DIR__.'/includes/bootstrap.php';
global $news;
$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
if ($id===false || $id===null || !isset($news[$id])) { http_response_code(404); $pageTitle='Conteúdo não encontrado'; require __DIR__.'/includes/header.php'; ?><section class="section"><div class="container"><h1>Conteúdo não encontrado.</h1><p>Esse guia não está disponível.</p><a class="btn btn-dark" href="<?=base_url('noticias.php')?>">Ver todos os guias</a></div></section><?php require __DIR__.'/includes/footer.php'; exit; }
$article=$news[$id]; $pageTitle=$article['titulo'];
require __DIR__.'/includes/header.php';
?>
<article class="article-page">
  <header class="article-top"><div class="container article-container"><a class="back-link" href="<?=base_url('noticias.php')?>">← Voltar aos guias</a><div class="editorial-meta"><span class="editorial-tag"><?=e($article['categoria'])?></span><span><?=e($article['tempo'])?></span></div><h1><?=e($article['titulo'])?></h1><p class="article-lead"><?=e($article['resumo'])?></p></div></header>
  <div class="container article-container"><figure class="article-cover"><img src="<?=e($article['foto'])?>" alt="Fotografia ilustrativa de animal" loading="eager"><figcaption>Imagem ilustrativa. Este guia traz orientações gerais, não relatos verificados de casos específicos.</figcaption></figure>
    <div class="article-content"><p class="article-intro"><?=e($article['intro'])?></p>
      <?php foreach($article['secoes'] as $section): ?><section><h2><?=e($section['titulo'])?></h2><p><?=e($section['texto'])?></p></section><?php endforeach; ?>
      <aside class="article-note"><strong>Importante lembrar</strong><p><?=e($article['destaque'])?></p></aside>
    </div>
    <nav class="article-footer-nav" aria-label="Próxima ação"><a class="btn btn-outline" href="<?=base_url('noticias.php')?>">← Todos os guias</a><a class="btn btn-dark" href="<?=base_url('ongs.php')?>">Conhecer as ONGs →</a></nav>
  </div>
</article>
<?php require __DIR__.'/includes/footer.php'; ?>
