<?php require_once __DIR__.'/bootstrap.php'; $pageTitle=$pageTitle??'Guardião Animal'; $flash=take_flash(); $current=basename($_SERVER['PHP_SELF']); ?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<script>document.documentElement.classList.add("js");</script>
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#123d34">
<title><?=e($pageTitle)?> | Guardião Animal</title>
<meta name="description" content="Guardião Animal, uma rede para registrar ocorrências, conhecer ONGs e encontrar animais que esperam uma família.">
<link rel="stylesheet" href="<?=base_url('assets/css/style.css')?>?v=32">
<?php if(!empty($includeMapAssets)): ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<?php endif; ?>
</head>
<body>
<a class="skip-link" href="#main">Pular para o conteúdo</a>
<header class="site-header">
  <div class="top-strip">
    <div class="top-strip-inner">
      <span>Juntos por um mundo mais gentil</span>
      <span class="top-heart">♡</span>
      <div class="top-social" aria-label="Atalhos">
        <a href="<?=base_url('consultar.php')?>" style="font-weight:600;color:#ccebe2">🔍 Consultar protocolo</a>
        <a href="<?=base_url('mapa.php')?>">Mapa</a>
        <a href="<?=base_url('ongs.php')?>">ONGs</a>
        <a href="<?=base_url('contato.php')?>">Contato</a>
        <a href="https://www.instagram.com/guardiaoanimal.ourinhos/" target="_blank" rel="noopener noreferrer" aria-label="Instagram fictício do Guardião Animal">Instagram ↗</a>
      </div>
    </div>
  </div>
  <div class="nav-wrap">
    <a class="brand" href="<?=base_url()?>" aria-label="Guardião Animal - página inicial">
      <span class="logo-ring"><img src="<?=e(brand_logo_url())?>" alt="Logo Guardião Animal" decoding="async"></span>
      <span class="brand-copy">
        <strong>Guardião Animal</strong>
        <small>Informação organizada para quem protege.</small>
      </span>
    </a>
    <button class="menu" type="button" aria-label="Abrir menu" aria-expanded="false" aria-controls="main-nav">☰</button>
    <nav class="main-nav" id="main-nav" aria-label="Navegação principal">
      <a class="<?=in_array($current,['index.php'])?'current':''?>" href="<?=base_url()?>">Início</a>
      <a class="<?=in_array($current,['denuncia.php'])?'current':''?>" href="<?=base_url('denuncia.php')?>">Denunciar</a>
      <a class="<?=in_array($current,['consultar.php'])?'current':''?>" href="<?=base_url('consultar.php')?>">Consultar protocolo</a>
      <a class="<?=in_array($current,['mapa.php'])?'current':''?>" href="<?=base_url('mapa.php')?>">Mapa</a>
      <a class="<?=in_array($current,['ongs.php','ong.php'])?'current':''?>" href="<?=base_url('ongs.php')?>">ONGs</a>
      <a class="<?=in_array($current,['doacao.php'])?'current':''?>" href="<?=base_url('doacao.php')?>">Doar</a>
      <a class="<?=in_array($current,['apadrinhar.php'])?'current':''?>" href="<?=base_url('apadrinhar.php')?>">Apadrinhar</a>
      <a class="<?=in_array($current,['noticias.php','noticia.php'])?'current':''?>" href="<?=base_url('noticias.php')?>">Notícias</a>
      <a class="<?=in_array($current,['sobre.php'])?'current':''?>" href="<?=base_url('sobre.php')?>">Sobre</a>
      <a class="nav-mobile-login" href="<?=base_url('entrar.php')?>">Entrar / Cadastrar ONG</a>
      <a class="<?=in_array($current,['contato.php'])?'current':''?>" href="<?=base_url('contato.php')?>">Contato</a>
    </nav>
    <div class="nav-actions">
      <a class="btn btn-mint nav-join" href="<?=base_url('doacao.php')?>"><span>♡</span> Faça parte</a>
      <a class="btn btn-outline nav-login" href="<?=base_url('entrar.php')?>">Entrar <span aria-hidden="true">→</span></a>
    </div>
  </div>
</header>
<?php if($flash): ?>
<div class="toast <?=$flash['type']==='ok'?'ok':'bad'?>" data-toast role="<?=$flash['type']==='ok'?'status':'alert'?>" aria-live="polite">
  <span class="toast-symbol" aria-hidden="true"><?=$flash['type']==='ok'?'✓':'!'?></span>
  <span class="toast-copy"><strong><?=$flash['type']==='ok'?'Tudo certo!':'Confira esta informação'?></strong><span><?=e($flash['msg'])?></span></span>
  <button class="toast-close" type="button" data-toast-close aria-label="Fechar mensagem">×</button>
</div>
<?php endif; ?>
<main id="main" class="page-enter">
