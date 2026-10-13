(function () {
  var form = document.getElementById('proj-form');
  var latEl = document.getElementById('lat');
  var lngEl = document.getElementById('lng');
  var estadoEl = document.getElementById('estado');
  var muniEl = document.getElementById('municipio');
  var locEl = document.getElementById('localidad');
  var statusEl = document.getElementById('geo-status');

  // ---------- Mapa ----------
  var hasPoint = latEl.value !== '' && lngEl.value !== '';
  var map = null, marker = null;
  if (window.L) {
    map = L.map('map', { scrollWheelZoom: false }).setView(
      hasPoint ? [+latEl.value, +lngEl.value] : [23.6, -102.5], hasPoint ? 11 : 5);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 18, attribution: '&copy; OpenStreetMap'
    }).addTo(map);
    map.on('click', function (ev) { setPoint(ev.latlng.lat, ev.latlng.lng); });
  } else {
    document.getElementById('map').hidden = true;
  }

  function setPoint(lat, lng, zoom) {
    latEl.value = lat.toFixed(5);
    lngEl.value = lng.toFixed(5);
    if (!map) return;
    if (!marker) {
      marker = L.marker([lat, lng], {
        draggable: true,
        icon: L.divIcon({ className: 'pin', iconSize: [22, 22], iconAnchor: [11, 11] })
      }).addTo(map);
      marker.on('dragend', function () {
        var p = marker.getLatLng();
        setPoint(p.lat, p.lng);
      });
    } else {
      marker.setLatLng([lat, lng]);
    }
    if (zoom) map.setView([lat, lng], zoom);
  }
  if (hasPoint) setPoint(+latEl.value, +lngEl.value);

  function status(msg) { statusEl.textContent = msg; }

  function nominatim(q) {
    var url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=mx&accept-language=es&q=' + encodeURIComponent(q);
    return fetch(url).then(function (r) { return r.json(); }).then(function (res) { return res[0] || null; });
  }

  var geoSeq = 0;
  function geocode() {
    var est = window.ESTADOS[estadoEl.value];
    var muni = muniEl.value.trim();
    if (!est) { status('Primero selecciona el estado.'); return; }
    if (!muni) { setPoint(est.lat, est.lng, 7); status('Escribe el municipio para ubicarlo con más precisión.'); return; }
    var seq = ++geoSeq;
    var base = muni + ', ' + est.nombre + ', México';
    var loc = locEl.value.trim();
    status('Buscando ubicación…');
    (loc ? nominatim(loc + ', ' + base) : Promise.resolve(null))
      .then(function (hit) { return hit || nominatim(base); })
      .then(function (hit) {
        if (seq !== geoSeq) return;
        if (hit) {
          setPoint(+hit.lat, +hit.lon, 12);
          status('Ubicado: ' + hit.display_name + '. Si no es exacto, arrastra el punto.');
        } else {
          setPoint(est.lat, est.lng, 7);
          status('No se encontró el municipio. Haz clic en el mapa para colocar el punto.');
        }
      })
      .catch(function () {
        if (seq !== geoSeq) return;
        status('No se pudo buscar en este momento. Haz clic en el mapa para colocar el punto.');
      });
  }
  document.getElementById('geo-btn').addEventListener('click', geocode);
  muniEl.addEventListener('change', geocode);
  locEl.addEventListener('change', function () { if (muniEl.value.trim()) geocode(); });
  estadoEl.addEventListener('change', geocode);

  // ---------- Fotos ----------
  var photos = document.getElementById('photos');
  var addBox = document.getElementById('add-photo');
  var fileInput = document.getElementById('file-input');
  var newBlobs = new Map();

  function photoCount() { return photos.querySelectorAll('.photo:not(.add)').length; }
  function refreshAdd() { addBox.hidden = photoCount() >= window.MAX_FOTOS; }

  photos.addEventListener('click', function (ev) {
    var btn = ev.target.closest('.remove');
    if (!btn) return;
    var box = btn.closest('.photo');
    newBlobs.delete(box);
    box.remove();
    refreshAdd();
  });

  // Reduce la foto en el navegador para que suba rápido desde el celular
  function shrink(file) {
    return new Promise(function (resolve, reject) {
      var url = URL.createObjectURL(file);
      var img = new Image();
      img.onload = function () {
        var scale = Math.min(1, 1600 / Math.max(img.naturalWidth, img.naturalHeight));
        var c = document.createElement('canvas');
        c.width = Math.round(img.naturalWidth * scale);
        c.height = Math.round(img.naturalHeight * scale);
        c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
        URL.revokeObjectURL(url);
        c.toBlob(function (b) { b ? resolve(b) : reject(); }, 'image/jpeg', 0.85);
      };
      img.onerror = function () { URL.revokeObjectURL(url); reject(); };
      img.src = url;
    });
  }

  fileInput.addEventListener('change', function () {
    var files = Array.from(fileInput.files);
    var room = window.MAX_FOTOS - photoCount();
    if (files.length > room) alert('Solo se pueden tener ' + window.MAX_FOTOS + ' fotos. Se agregarán las primeras ' + room + '.');
    files.slice(0, room).forEach(function (file) {
      var box = document.createElement('div');
      box.className = 'photo loading';
      box.innerHTML = '<button type="button" class="remove" title="Quitar foto">✕</button>';
      photos.insertBefore(box, addBox);
      shrink(file).then(function (blob) {
        var img = document.createElement('img');
        img.src = URL.createObjectURL(blob);
        box.prepend(img);
        box.classList.remove('loading');
        newBlobs.set(box, blob);
      }).catch(function () {
        box.remove();
        refreshAdd();
        alert('No se pudo leer la foto "' + file.name + '". Usa formato JPG o PNG.');
      });
    });
    fileInput.value = '';
    refreshAdd();
  });
  refreshAdd();

  // ---------- Guardar ----------
  var errEl = document.getElementById('form-error');
  var saveBtn = document.getElementById('save-btn');
  function showError(msg) {
    errEl.textContent = msg;
    errEl.hidden = false;
    errEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    errEl.hidden = true;
    var missing = Array.from(form.querySelectorAll('[required]')).find(function (el) { return !el.value.trim(); });
    if (missing) { missing.focus(); showError('Falta llenar: ' + form.querySelector('label[for="' + missing.id + '"]').textContent.replace('*', '').trim()); return; }
    if (!latEl.value) { showError('Ubica el proyecto en el mapa.'); return; }
    if (photos.querySelector('.photo.loading')) { showError('Espera a que terminen de cargar las fotos.'); return; }
    if (!photoCount()) { showError('Agrega al menos una foto.'); return; }

    var fd = new FormData(form);
    photos.querySelectorAll('.photo:not(.add)').forEach(function (box) {
      if (box.dataset.keep) fd.append('keep[]', box.dataset.keep);
      else if (newBlobs.has(box)) fd.append('fotos[]', newBlobs.get(box), 'foto.jpg');
    });

    saveBtn.disabled = true;
    saveBtn.textContent = 'Guardando…';
    fetch(location.pathname + location.search, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Error del servidor (' + r.status + ').' }; }); })
      .then(function (res) {
        if (res.ok) { location.href = res.redirect; return; }
        showError(res.error || 'No se pudo guardar.');
        saveBtn.disabled = false;
        saveBtn.textContent = 'Guardar proyecto';
      })
      .catch(function () {
        showError('Sin conexión. Revisa tu internet e intenta de nuevo.');
        saveBtn.disabled = false;
        saveBtn.textContent = 'Guardar proyecto';
      });
  });
})();
