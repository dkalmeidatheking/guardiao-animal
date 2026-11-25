<?php
$pageTitle='ONGs';require __DIR__.'/includes/header.php';global $ongs;
?>
<section class="page-hero"><div class="container"><span class="eyebrow">Rede Guardião · Ourinhos</span><h1>Pessoas que se unem pelo cuidado.</h1><p>Conheça os perfis das organizações exibidas no projeto e encontre o tipo de atuação com que deseja colaborar.</p></div></section>
<section class="section-tight"><div class="container">
  <div class="listing-tools" data-listing="ongs"><div class="listing-tools-top"><div><span class="eyebrow">Organizações da rede</span><h2>Explore as ONGs</h2></div><span class="listing-total" data-count-label aria-live="polite"><?=count($ongs)?> organizações encontradas</span></div>
    <label class="search-label"><span>Buscar ONG ou especialidade</span><input data-list-search type="search" placeholder="Ex.: adoção, resgate, cuidado..." autocomplete="off"></label>
  </div>
  <div class="ong-grid listing-results"><?php foreach($ongs as $id=>$o): ?><article class="ong-card" data-search="<?=e(mb_strtolower($o['nome'].' '.$o['foco'].' '.$o['descricao'].' '.$o['bairro']))?>"><a href="<?=base_url('ong.php?id='.$id)?>"><div class="ong-photo" style="background-image:url('<?=e($o['foto'])?>')"></div><div class="ong-body"><span class="chip"><?=e($o['foco'])?></span><h3><?=e($o['nome'])?></h3><div class="ong-location">⌖ <?=e($o['cidade'])?> · <?=e($o['bairro'])?></div><p><?=e($o['descricao'])?></p><span class="text-link">Conhecer organização →</span></div></a></article><?php endforeach; ?></div>
  <div class="listing-empty" data-list-empty hidden><strong>Nenhuma organização encontrada.</strong><p>Verifique o termo pesquisado ou limpe a busca.</p><button type="button" class="btn btn-outline" data-clear-list>Limpar busca</button></div>
  <p class="listing-disclaimer">Os perfis de exemplo apresentados neste projeto acadêmico são ilustrativos. Nomes, contatos e vínculos institucionais devem ser confirmados antes de uso público.</p>
</div></section>
<?php require __DIR__.'/includes/footer.php'; ?>
