<?php
$pageTitle='Mapa de denúncias';
$includeMapAssets=true;
require __DIR__.'/includes/header.php';
?>
<section class="page-hero map-page-hero">
  <div class="container">
    <span class="eyebrow">Ourinhos · mapa de ocorrências</span>
    <h1>Onde os casos estão acontecendo.</h1>
    <p>O mapa exibe exclusivamente ocorrências ativas validadas e em atendimento pelas ONGs parceiras, agrupadas por bairro. Denúncias pendentes de validação, concluídas ou arquivadas não entram na contagem ativa do mapa.</p>
  </div>
</section>
<section class="map-section">
  <div class="container">
    <div class="map-toolbar">
      <div>
        <span class="eyebrow">Registros no site · atualização automática</span>
        <h2>Mapa de denúncias de Ourinhos</h2>
        <p id="map-status">Carregando registros...</p>
      </div>
      <div class="map-legend" aria-label="Legenda do mapa">
        <span><i class="legend-dot green"></i> 1 a 5 denúncias</span>
        <span><i class="legend-dot orange"></i> 6 a 10 denúncias</span>
        <span><i class="legend-dot red"></i> 11 ou mais denúncias</span>
      </div>
    </div>
    <p><button type="button" id="map-refresh" class="btn btn-outline">Atualizar mapa agora</button></p>
    <div class="map-demo-notice"><strong>Atenção:</strong> os pontos representam a concentração aproximada de denúncias registradas por bairro. Não são endereços exatos nem casos verificados por autoridade pública.</div>
    <div class="map-filter-tools" role="group" aria-label="Filtrar concentração de denúncias"><strong>Visualizar concentrações:</strong><label><input type="checkbox" data-heat-filter="baixo" checked> <span class="filter-swatch filter-low" aria-hidden="true"></span> Baixa (1–5)</label><label><input type="checkbox" data-heat-filter="medio" checked> <span class="filter-swatch filter-mid" aria-hidden="true"></span> Média (6–10)</label><label><input type="checkbox" data-heat-filter="alto" checked> <span class="filter-swatch filter-high" aria-hidden="true"></span> Alta (11+)</label></div>
    <div class="map-shell">
      <div id="ourinhos-map" data-api="<?=e(base_url('api/map_data.php'))?>" class="ourinhos-map" aria-label="Mapa de denúncias de Ourinhos"></div>
      <div class="map-loading" id="map-loading">Carregando mapa...</div>
    </div>
    <div class="map-note"><span><strong>Localização aproximada:</strong> cada mancha representa o centro estimado do bairro selecionado na denúncia, não o endereço exato nem os limites oficiais do bairro.</span></div>
    <div class="map-note">
      <strong>Como o calor é definido?</strong>
      <span>Verde: 1 a 5 denúncias · Amarelo/Laranja: 6 a 10 · Vermelho: 11 ou mais.</span>
      <span>O mapa consulta novos registros automaticamente a cada 15 segundos. Exemplos não são enviados ao Supabase e não substituem denúncias reais.</span>
    </div>
    <div class="map-ai-card">
      <div class="map-ai-icon">✦</div>
      <div>
        <span class="eyebrow">Localização das denúncias</span>
        <h3>Como o bairro é definido?</h3>
        <p>A denúncia usa o bairro escolhido no formulário. Não há substituição automática do bairro escolhido. O mapa não divulga o endereço exato das ocorrências nem os contatos de quem denunciou.</p>
      </div>
    </div>
  </div>
</section>
<?php require __DIR__.'/includes/footer.php'; ?>
