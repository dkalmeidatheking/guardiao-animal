<?php
$pageTitle='Início';
$includeMapAssets=true;
require __DIR__.'/includes/header.php';
global $animals,$ongs,$news;
$reportedCases=read_store('denuncias');
$ngoApplications=read_store('ong_accounts');
$ongIds=array_slice(array_keys($ongs),0,4);
$homePhotos=read_store('home_photos');
$curatedGallery = [
    'https://images.unsplash.com/photo-1543466835-00a7907e9de1?w=800&auto=format&fit=crop', // Joca
    'https://images.unsplash.com/photo-1514888286974-6c03e2ca1dba?w=800&auto=format&fit=crop', // Gatinho
    'https://images.unsplash.com/photo-1552053831-71594a27632d?w=800&auto=format&fit=crop', // Thor labrador
    'https://images.unsplash.com/photo-1537151625747-768eb6cf92b2?w=800&auto=format&fit=crop', // Filhote Pipoca
    'https://images.unsplash.com/photo-1561037404-61cd46aa615b?w=800&auto=format&fit=crop', // Bento
    'https://images.unsplash.com/photo-1574158622682-e40e69881006?w=800&auto=format&fit=crop', // Caju
    'https://images.unsplash.com/photo-1548199973-03cce0bbc87b?w=800&auto=format&fit=crop', // Zeca
    'https://images.unsplash.com/photo-1583511655857-d19b40a7a54e?w=800&auto=format&fit=crop', // Theo
];
$animalPhotos = array_values(array_filter(array_unique(array_map(static fn($a)=>$a['foto']??'', $animals))));
$galleryPhotos=[];
for ($i=0; $i<8; $i++) {
    $galleryPhotos[] = $homePhotos['dog'.($i+1)] ?? ($animalPhotos[$i] ?? $curatedGallery[$i]);
}
$heroPhoto=$homePhotos['hero']??($animalPhotos[0]??$curatedGallery[0]);
$storyPhoto=$homePhotos['story']??($animalPhotos[1]??$curatedGallery[1]);
$featurePhoto=$homePhotos['feature']??($animalPhotos[2]??$curatedGallery[2]);
?>
<section class="home-hero">
  <img class="home-hero-picture" src="<?=e($heroPhoto)?>" alt="Fotografia de um cachorro" fetchpriority="high">
  <div class="hero-glow"></div>
  <div class="container hero-inner">
    <div class="hero-copy">
      <span class="hero-kicker">PROTEÇÃO ANIMAL É UM ESFORÇO COLETIVO</span>
      <h1>Eles não podem<br><em>se defender.</em><br>Mas você pode.</h1>
      <p>O Guardião Animal conecta pessoas, ONGs e ações que fazem a diferença na vida de animais em situação de risco, abandono ou vulnerabilidade.</p>
      <div class="hero-actions">
        <a class="btn btn-mint" href="<?=base_url('denuncia.php')?>">Fazer uma denúncia</a>
        <a class="btn btn-white" href="<?=base_url('ongs.php')?>">Conhecer as ONGs ♡</a>
      </div>
    </div>
  </div>
  <div class="hero-note">
    <span>Hoje na rede</span>
    <strong><?=count($animals)?> animais</strong>
    <small>com perfil publicado</small>
  </div>
  <div class="hero-scroll"><span>Role para continuar</span><i></i></div>
</section>

<section class="ways-section" id="participar">
  <div class="container">
    <div class="ways-head reveal">
      <span class="ways-overline"><span class="ways-overline-dot" aria-hidden="true"></span> FAÇA PARTE DA MUDANÇA</span>
      <h2>Escolha por onde começar<span class="accent-dot">.</span></h2>
      <p>Escolha uma forma prática de participar da rede de proteção animal.</p>
    </div>
    <div class="ways-grid">
      <a class="way-card way-card-denounce reveal" href="<?=base_url('denuncia.php')?>">
        <div class="way-top"><span class="way-number">01</span><span class="way-label">ATENÇÃO</span></div>
        <div class="way-icon-image way-symbol" aria-hidden="true">!</div>
        <div class="way-content"><h3>Denunciar</h3><p>Avistou um animal em risco? Registre o local, explique o que aconteceu e informe a urgência.</p><span>Registrar denúncia <b aria-hidden="true">→</b></span></div>
      </a>
      <a class="way-card way-card-support reveal" href="<?=base_url('doacao.php')?>">
        <div class="way-top"><span class="way-number">02</span><span class="way-label">DOAÇÃO</span></div>
        <div class="way-icon-image way-symbol" aria-hidden="true">✚</div>
        <div class="way-content"><h3>Doar</h3><p>Registre sua intenção de contribuir com uma organização da rede. Nenhum pagamento é cobrado aqui.</p><span>Quero doar <b aria-hidden="true">→</b></span></div>
      </a>
      <a class="way-card way-card-sponsor reveal" href="<?=base_url('apadrinhar.php')?>">
        <div class="way-top"><span class="way-number">03</span><span class="way-label">CUIDADO CONTÍNUO</span></div>
        <div class="way-icon-image way-symbol" aria-hidden="true">♥</div>
        <div class="way-content"><h3>Apadrinhar</h3><p>Escolha um animal e registre seu interesse em ajudar mensalmente. A equipe entrará em contato.</p><span>Quero apadrinhar <b aria-hidden="true">→</b></span></div>
      </a>
    </div>
  </div>
</section>

<section class="story-section section">
  <div class="container story-split">
    <div class="story-photo reveal" role="img" aria-label="Fotografia de um cachorro" style="background-image:url('<?=e($storyPhoto)?>')"></div>
    <div class="story-copy reveal">
      <span class="eyebrow">O problema que a plataforma enfrenta</span>
      <h2>Um caso pode passar por várias mãos. A informação não pode se perder.</h2>
      <p>Entre a denúncia, o resgate, a consulta e a adoção, muita coisa acontece. O Guardião foi pensado para reunir essas informações de forma simples e facilitar o contato entre a comunidade e as organizações.</p>
      <div class="simple-points">
        <div><strong>Registro</strong><span>Local, urgência e descrição do caso.</span></div>
        <div><strong>Acompanhamento</strong><span>ONG responsável e situação do atendimento.</span></div>
        <div><strong>Destino</strong><span>Acompanhamento, apadrinhamento ou acolhimento.</span></div>
      </div>
      <a class="btn btn-dark" href="<?=base_url('sobre.php')?>">Conhecer o projeto</a>
    </div>
  </div>
</section>

<section class="stats-band">
  <div class="container stats">
    <div class="stat reveal"><strong class="count-up" data-count="<?=count($reportedCases)?>">0</strong><span>denúncias registradas</span></div>
    <div class="stat reveal"><strong class="count-up" data-count="<?=count($animals)?>">0</strong><span>animais com perfil</span></div>
    <div class="stat reveal"><strong class="count-up" data-count="<?=count($ongs)?>">0</strong><span>ONGs na rede</span></div>
    <div class="stat reveal"><strong class="count-up" data-count="<?=count($ngoApplications)?>">0</strong><span>solicitações de ONG</span></div>
  </div>
</section>

<section class="section section-dark">
  <div class="container">
    <div class="big-story reveal">
      <div class="big-story-photo" style="background-image:url('<?=e($featurePhoto)?>')"></div>
      <div class="big-story-copy"><span class="eyebrow" style="color:var(--mint)">História em destaque</span><h2>Joca chegou com uma pata machucada. A rede ajudou na recuperação.</h2><p>O perfil dele faz parte da página da ONG responsável, onde ficam reunidas as histórias e informações dos animais acompanhados.</p><a class="btn btn-mint" href="ong.php?id=1">Conhecer a ONG responsável</a></div>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="section-head reveal"><div><span class="eyebrow">Rede parceira</span><h2>Organizações que fazem o atendimento acontecer</h2></div><a class="text-link" href="<?=base_url('ongs.php')?>">Ver todas →</a></div>
    <div class="cards">
      <?php foreach($ongIds as $oid): $o=$ongs[$oid]; ?>
      <article class="ong-card reveal"><a href="ong.php?id=<?=$oid?>"><div class="ong-photo" style="background-image:url('<?=e($o['foto'])?>')"></div><div class="ong-body"><div class="card-bottom" style="margin-top:0"><span class="chip">Desde <?=$o['ano']?></span><span class="meta"><?=$o['animais']?> animais</span></div><h3><?=e($o['nome'])?></h3><div class="ong-location"><?=e($o['cidade'])?> · <?=e($o['bairro'])?></div><span class="ong-focus"><?=e($o['foco'])?></span><p><?=e($o['descricao'])?></p><span class="text-link">Abrir perfil →</span></div></a></article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php if (!empty($news)): ?>
<section class="section section-soft">
  <div class="container">
    <div class="section-head reveal"><div><span class="eyebrow">Histórias da rede</span><h2>O que está acontecendo por aqui</h2></div><a class="text-link" href="<?=base_url('noticias.php')?>">Ver histórias →</a></div>
    <div class="cards">
      <?php foreach(array_slice($news,0,3,true) as $nid=>$n): ?>
      <article class="story-card reveal"><div class="card-photo" style="background-image:url('<?=e($n['foto'])?>')"></div><div class="card-body"><span class="chip"><?=e($n['categoria'])?></span><div class="meta" style="margin:11px 0"><?=e($n['tempo'])?></div><h3><?=e($n['titulo'])?></h3><p><?=e($n['resumo'])?></p><a class="text-link" href="<?=base_url('noticia.php?id='.$nid)?>">Ler guia completo →</a></div></article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section photo-gallery-section">
  <div class="container">
    <div class="section-head reveal"><div><span class="eyebrow">Olhares que importam</span><h2>Vidas que merecem atenção.</h2></div><p class="gallery-caption">Fotografias ilustrativas de animais que inspiram o trabalho de cuidado e proteção.</p></div>
    <?php
      $galleryMessages = [
        'Toda vida merece proteção.',
        'O carinho também transforma.',
        'Cuidar é um ato de respeito.',
        'Acolher faz a diferença.',
        'Compromisso com cada vida.',
        'O cuidado muda histórias.',
        'Proteção começa com atitude.',
        'Nenhum animal deve ser esquecido.'
      ];
    ?>
    <div class="animal-gallery">
      <?php foreach($galleryPhotos as $i=>$photo): ?>
        <figure class="animal-gallery-item reveal"><img src="<?=e($photo)?>" alt="Fotografia de animal <?=($i+1)?>" loading="lazy"><figcaption><?=e($galleryMessages[$i]??'Toda vida merece proteção.')?></figcaption></figure>
      <?php endforeach; ?>
    </div>
    <p class="gallery-disclaimer">Fotos ilustrativas: os animais retratados não são necessariamente os animais listados para adoção. As fichas demonstrativas devem ser substituídas por registros verificados antes de uso público.</p>
  </div>
</section>

<section class="section map-home-section">
  <div class="container">
    <div class="section-head reveal"><div><span class="eyebrow">Ourinhos</span><h2>Veja onde os registros estão concentrados.</h2></div><a class="text-link" href="<?=base_url('mapa.php')?>">Abrir mapa completo →</a></div>
    <div class="map-mini">
      <div id="ourinhos-map" class="ourinhos-map" aria-label="Mapa de denúncias de Ourinhos"></div>
    </div>
  </div>
</section>

<section class="request-panel">
  <div class="container request-inner reveal">
    <div class="request-copy"><span class="eyebrow">Precisa registrar uma situação?</span><h2>Comece pelo que você sabe.</h2><p>Local, espécie, urgência e uma descrição já ajudam a equipe a entender o caso.</p></div>
    <a class="request-button" href="<?=base_url('denuncia.php')?>"><span>Registrar denúncia</span><b>→</b></a>
  </div>
</section>
<?php require __DIR__.'/includes/footer.php'; ?>
