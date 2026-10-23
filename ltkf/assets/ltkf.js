// Meniu telefone, klubų paieška ir žemėlapis
(function () {
  var toggle = document.querySelector('.nav-toggle');
  var nav = document.getElementById('main-nav');
  if (toggle && nav) {
    toggle.addEventListener('click', function () {
      var open = nav.classList.toggle('open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }

  // Klubų paieška ir miesto filtras
  var list = document.getElementById('club-list');
  if (list) {
    var input = document.getElementById('club-search');
    var empty = document.getElementById('club-empty');
    var city = '';
    var apply = function () {
      var q = (input.value || '').trim().toLowerCase();
      var shown = 0;
      list.querySelectorAll('.club').forEach(function (li) {
        var ok = (!q || li.dataset.name.indexOf(q) !== -1) && (!city || li.dataset.city === city);
        li.hidden = !ok;
        if (ok) shown++;
      });
      empty.hidden = shown > 0;
    };
    input.addEventListener('input', apply);
    document.querySelectorAll('#city-filter .fchip').forEach(function (b) {
      b.addEventListener('click', function () {
        city = b.dataset.city;
        document.querySelectorAll('#city-filter .fchip').forEach(function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
        apply();
      });
    });
  }

  // Žemėlapis (Leaflet + OpenStreetMap). Be interneto - lieka pilkas fonas su paaiškinimu.
  var mapEl = document.getElementById('club-map');
  if (mapEl) {
    var start = function () {
      if (!window.L) { mapEl.textContent = 'Žemėlapis neįsikėlė (reikia interneto).'; return; }
      var map = L.map(mapEl, { scrollWheelZoom: false }).setView([55.2, 23.9], 7);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 18, attribution: '© OpenStreetMap' }).addTo(map);
      var pts = [];
      try { pts = JSON.parse(mapEl.dataset.points || '[]'); } catch (e) {}
      pts.forEach(function (p) {
        L.circleMarker([p.lat, p.lng], { radius: 9, color: '#0E2A47', weight: 2, fillColor: '#B3202E', fillOpacity: 0.9 })
          .addTo(map).bindPopup('<b>' + p.n.replace(/</g, '&lt;') + '</b><br>' + p.m.replace(/</g, '&lt;'));
      });
    };
    if (document.readyState === 'complete') start(); else window.addEventListener('load', start);
  }
})();
