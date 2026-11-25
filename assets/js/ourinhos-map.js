/* Guardião Animal v25: cada bairro com denúncias tem sua própria mancha de
 * calor com paleta ABSOLUTA (verde 1-5, laranja 6-10, vermelho 11+).
 * Não há gradiente único normalizado por intensidade que desbote as cores.
 * As manchas usam marcadores nativos Leaflet: não dependem do leaflet.heat.
 */
(function () {
  'use strict';
  const element = document.getElementById('ourinhos-map');
  if (!element) return;
  const status = document.getElementById('map-status');
  const loading = document.getElementById('map-loading');
  function error(message) {
    if (status) status.textContent = message;
    if (loading) {
      loading.classList.remove('hidden');
      loading.classList.add('map-error');
      loading.textContent = message;
    }
  }
  if (typeof L === 'undefined') {
    error('Não foi possível carregar o mapa. Verifique a conexão e atualize a página.');
    return;
  }
  const bands = {
    baixo: {label: 'Baixa · 1 a 5 denúncias', css: 'low'},
    medio: {label: 'Média · 6 a 10 denúncias', css: 'mid'},
    alto: {label: 'Alta · 11 ou mais denúncias', css: 'high'}
  };
  const center = [-22.9795, -49.8705];
  const bounds = L.latLngBounds([[-23.035, -49.935], [-22.930, -49.820]]);
  const map = L.map(element, {zoomControl: true, maxBounds: bounds, maxBoundsViscosity: .75, minZoom: 12, maxZoom: 17}).setView(center, 13);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors', maxZoom: 19
  }).addTo(map);
  const groups = Object.fromEntries(Object.keys(bands).map(level => [level, L.layerGroup().addTo(map)]));
  const filters = [...document.querySelectorAll('[data-heat-filter]')];
  filters.forEach(input => {
    input.addEventListener('change', () => {
      const group = groups[input.dataset.heatFilter];
      if (!group) return;
      if (input.checked) map.addLayer(group);
      else map.removeLayer(group);
    });
  });
  const escapeHTML = value => String(value).replace(/[&<>"']/g, char => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
  }[char]));
  const bandFor = count => count > 10 ? 'alto' : count >= 6 ? 'medio' : 'baixo';
  const sizeFor = count => Math.min(146, 86 + Math.sqrt(count) * 16);
  let previousSignature = '';
  let hasLoaded = false;
  let running = false;
  let latestPayload = null;
  // Apenas denúncias ativas presentes no Supabase.
  const plural = (n, singular, pluralText) => `${n} ${n === 1 ? singular : pluralText}`;

  function render(payload) {
    
    const regions = payload.regioes.filter(row =>
      Number.isFinite(Number(row.lat)) && Number.isFinite(Number(row.lng)) &&
      Number.isSafeInteger(Number(row.real_count)) && Number(row.real_count) >= 0 &&
      bounds.contains([Number(row.lat), Number(row.lng)])
    ).map(row => ({
      ...row,
      count: Number(row.real_count)
    })).filter(row => row.count > 0);

    const signature = JSON.stringify(regions);
    const totals = {baixo: 0, medio: 0, alto: 0};
    regions.forEach(row => { totals[bandFor(row.count)]++; });
    if (signature !== previousSignature) {
      Object.values(groups).forEach(group => group.clearLayers());
      regions.forEach(region => {
        const count = Number(region.count);
        const level = bandFor(count);
        const size = sizeFor(count);
        const icon = L.divIcon({
          className: 'heat-spot-icon heat-spot-interactive',
          html: `<span class="heat-spot heat-spot-${bands[level].css}"><span class="heat-spot-value">${count}</span></span>`,
          iconSize: [size, size], iconAnchor: [size / 2, size / 2]
        });
        const realCount = Number(region.real_count);

        const name = escapeHTML(region.bairro || 'Bairro não identificado');
        const details = `${plural(realCount, 'denúncia ativa', 'denúncias ativas')}`;
        L.marker([Number(region.lat), Number(region.lng)], {icon, keyboard: true, title: `${region.bairro}: ${count} denúncias ativas`})
          .bindPopup(`<strong>${name}</strong><div>${details}</div><small>Ponto representativo dentro do bairro. Não representa endereço exato nem limites oficiais.</small>`)
          .addTo(groups[level]);
      });
      previousSignature = signature;
    }
    const real = Number(payload.real || 0);


    if (status) status.textContent = `${plural(real,'denúncia ativa','denúncias ativas')} em ${plural(regions.length,'bairro','bairros')} · sincronizado com Supabase · ${String(payload.atualizado || '')}`;
    if (loading) { loading.classList.add('hidden'); loading.classList.remove('map-error'); }
  }

  async function update() {
    if (running) return;
    running = true;
    try {
      const url = new URL(element.dataset.api || 'api/map_data.php', window.location.href);
      url.searchParams.set('ts', Date.now());
      const response = await fetch(url.toString(), {cache: 'no-store', headers: {Accept: 'application/json'}});
      if (!response.ok) throw new Error('Resposta ' + response.status);
      const payload = await response.json();
      if (!Array.isArray(payload.regioes)) throw new Error('Dados inválidos');
      latestPayload = payload;
      render(payload);
      hasLoaded = true;
    } catch (e) {
      if (hasLoaded) {
        if (status) status.textContent = 'Não foi possível atualizar agora. Os últimos dados carregados continuam visíveis.';
      } else {
        error('Não foi possível carregar os registros. Tente atualizar a página.');
      }
      console.warn('[Guardião Animal] Atualização do mapa indisponível:', e.message);
    } finally { running = false; }
  }
  const refresh = document.getElementById('map-refresh');
  if (refresh) refresh.addEventListener('click', update);
  update();
  const timer = setInterval(update, 15000);
  window.addEventListener('pagehide', () => clearInterval(timer), {once:true});
  setTimeout(() => map.invalidateSize(), 200);
})();
