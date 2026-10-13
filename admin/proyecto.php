<?php
declare(strict_types=1);
require __DIR__ . '/_app.php';
require_login();

$projects = load_projects();
$id = (string)($_GET['id'] ?? $_POST['id'] ?? '');
$index = $id === '' ? null : find_project($projects, $id);
if ($id !== '' && $index === null) {
  http_response_code(404);
  exit('Proyecto no encontrado.');
}
$p = $index === null ? [
  'id' => '', 'nombre' => '', 'tipo' => 'BLUDREN', 'etiqueta' => '', 'estado' => '', 'municipio' => '',
  'localidad' => '', 'lat' => null, 'lng' => null, 'que_se_hizo' => '', 'resultado' => '', 'beneficio' => '', 'fotos' => [],
] : $projects[$index];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  check_csrf();
  header('Content-Type: application/json; charset=utf-8');
  $text = function (string $k, int $max, bool $required = true) {
    $v = trim(preg_replace('/\r\n?/', "\n", (string)($_POST[$k] ?? '')));
    if ($required && $v === '') throw new RuntimeException('Falta llenar un campo obligatorio.');
    if (mb_strlen($v) > $max) throw new RuntimeException("Un texto es demasiado largo (máximo $max caracteres).");
    return $v;
  };
  $saved = [];
  try {
    $data = [
      'nombre'      => $text('nombre', 150),
      'tipo'        => (string)($_POST['tipo'] ?? ''),
      'etiqueta'    => $text('etiqueta', 80, false),
      'estado'      => (string)($_POST['estado'] ?? ''),
      'municipio'   => $text('municipio', 100),
      'localidad'   => $text('localidad', 120, false),
      'lat'         => round((float)($_POST['lat'] ?? 0), 5),
      'lng'         => round((float)($_POST['lng'] ?? 0), 5),
      'que_se_hizo' => $text('que_se_hizo', 1500),
      'resultado'   => $text('resultado', 1500),
      'beneficio'   => $text('beneficio', 1500),
    ];
    if (!isset(TIPOS[$data['tipo']])) throw new RuntimeException('Selecciona el tipo de proyecto.');
    if (!isset(ESTADOS[$data['estado']])) throw new RuntimeException('Selecciona el estado.');
    if ($data['lat'] < 14 || $data['lat'] > 33 || $data['lng'] < -119 || $data['lng'] > -86) {
      throw new RuntimeException('Ubica el proyecto en el mapa (el punto debe estar dentro de México).');
    }

    $current = [];
    foreach ($p['fotos'] as $f) $current[$f['src']] = $f;
    $keep = [];
    foreach ((array)($_POST['keep'] ?? []) as $src) {
      if (isset($current[$src])) $keep[$src] = $current[$src];
    }
    $uploads = $_FILES['fotos'] ?? null;
    $newCount = $uploads ? count(array_filter((array)$uploads['error'], fn($e) => $e !== UPLOAD_ERR_NO_FILE)) : 0;
    if (count($keep) + $newCount === 0) throw new RuntimeException('Agrega al menos una foto.');
    if (count($keep) + $newCount > MAX_FOTOS) throw new RuntimeException('Máximo ' . MAX_FOTOS . ' fotos por proyecto.');

    $data['id'] = $p['id'] !== '' ? $p['id'] : 'p' . bin2hex(random_bytes(4));
    $fotos = array_values($keep);
    if ($newCount) {
      foreach ((array)$uploads['error'] as $k => $err) {
        if ($err === UPLOAD_ERR_NO_FILE) continue;
        $src = store_uploaded_photo(['error' => $err, 'size' => $uploads['size'][$k], 'tmp_name' => $uploads['tmp_name'][$k]], $data['id']);
        $saved[] = $src;
        $fotos[] = ['src' => $src, 'alt' => $data['nombre'] . ' — foto ' . (count($fotos) + 1)];
      }
    }
    $data['fotos'] = $fotos;

    // Releer justo antes de escribir para no pisar cambios hechos mientras se subían las fotos
    $projects = load_projects();
    $index = $p['id'] === '' ? null : find_project($projects, $p['id']);
    if ($index === null) {
      $projects[] = $data;
    } else {
      $projects[$index] = $data;
    }
    save_projects($projects);
    foreach (array_diff_key($current, $keep) as $removed) delete_photo_file($removed['src']);

    flash($p['id'] === '' ? 'Proyecto agregado. Ya aparece en el sitio.' : 'Cambios guardados. Ya aparecen en el sitio.');
    echo json_encode(['ok' => true, 'redirect' => 'index.php']);
  } catch (RuntimeException $ex) {
    foreach ($saved as $src) delete_photo_file($src);
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => $ex->getMessage()], JSON_UNESCAPED_UNICODE);
  }
  exit;
}

$estadosJs = [];
foreach (ESTADOS as $k => [$nombre, $lat, $lng]) $estadosJs[$k] = ['nombre' => $nombre, 'lat' => $lat, 'lng' => $lng];

page_header($p['id'] === '' ? 'Nuevo proyecto' : 'Editar proyecto');
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<p><a href="index.php" class="back">← Volver a proyectos</a></p>
<h1><?= $p['id'] === '' ? 'Nuevo proyecto' : 'Editar proyecto' ?></h1>

<form id="proj-form" class="card form" novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= e($p['id']) ?>">

  <fieldset>
    <legend>1. Datos generales</legend>
    <label for="nombre">Nombre del proyecto *</label>
    <input id="nombre" name="nombre" maxlength="150" required value="<?= e($p['nombre']) ?>" placeholder="Ej: Colector pluvial de 1.80 m en Jicolapa">
    <div class="grid2">
      <div>
        <label for="tipo">Tipo *</label>
        <select id="tipo" name="tipo" required>
          <?php foreach (TIPOS as $k => $label): ?>
            <option value="<?= e($k) ?>" <?= $p['tipo'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="etiqueta">Etiqueta corta <span class="opt">(opcional)</span></label>
        <input id="etiqueta" name="etiqueta" maxlength="80" value="<?= e($p['etiqueta']) ?>" placeholder="Ej: BLUDREN · Drenaje pluvial · Puebla">
      </div>
    </div>
  </fieldset>

  <fieldset>
    <legend>2. Ubicación</legend>
    <div class="grid3">
      <div>
        <label for="estado">Estado *</label>
        <select id="estado" name="estado" required>
          <option value="">— Selecciona —</option>
          <?php foreach (ESTADOS as $k => [$nombre]): ?>
            <option value="<?= e($k) ?>" <?= $p['estado'] === $k ? 'selected' : '' ?>><?= e($nombre) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="municipio">Municipio *</label>
        <input id="municipio" name="municipio" maxlength="100" required value="<?= e($p['municipio']) ?>" placeholder="Ej: Zacatlán">
      </div>
      <div>
        <label for="localidad">Localidad / calle <span class="opt">(opcional)</span></label>
        <input id="localidad" name="localidad" maxlength="120" value="<?= e($p['localidad']) ?>" placeholder="Ej: Jicolapa">
      </div>
    </div>
    <div class="map-tools">
      <button type="button" id="geo-btn" class="btn">📍 Buscar en el mapa</button>
      <span id="geo-status" class="muted">Al escribir el municipio el punto se coloca solo. Puedes arrastrarlo o hacer clic en el mapa para ajustarlo.</span>
    </div>
    <div id="map"></div>
    <input type="hidden" name="lat" id="lat" value="<?= e($p['lat']) ?>">
    <input type="hidden" name="lng" id="lng" value="<?= e($p['lng']) ?>">
  </fieldset>

  <fieldset>
    <legend>3. Descripción</legend>
    <label for="que_se_hizo">Qué se hizo *</label>
    <textarea id="que_se_hizo" name="que_se_hizo" maxlength="1500" rows="4" required><?= e($p['que_se_hizo']) ?></textarea>
    <label for="resultado">Resultado *</label>
    <textarea id="resultado" name="resultado" maxlength="1500" rows="4" required><?= e($p['resultado']) ?></textarea>
    <label for="beneficio">Beneficio social *</label>
    <textarea id="beneficio" name="beneficio" maxlength="1500" rows="4" required><?= e($p['beneficio']) ?></textarea>
  </fieldset>

  <fieldset>
    <legend>4. Fotos <span class="opt">(de 1 a <?= MAX_FOTOS ?>; la primera es la principal)</span></legend>
    <div id="photos" class="photos">
      <?php foreach ($p['fotos'] as $f): ?>
        <div class="photo" data-keep="<?= e($f['src']) ?>">
          <img src="<?= e(photo_url($f['src'])) ?>" alt="">
          <button type="button" class="remove" title="Quitar foto">✕</button>
        </div>
      <?php endforeach; ?>
      <label class="photo add" id="add-photo">
        <input type="file" id="file-input" accept="image/*" multiple>
        <span>＋<br>Agregar fotos</span>
      </label>
    </div>
  </fieldset>

  <p id="form-error" class="alert error" hidden></p>
  <div class="form-actions">
    <a href="index.php" class="btn">Cancelar</a>
    <button type="submit" id="save-btn" class="btn primary">Guardar proyecto</button>
  </div>
</form>

<script>window.ESTADOS = <?= json_encode($estadosJs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>; window.MAX_FOTOS = <?= MAX_FOTOS ?>;</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script src="admin.js?v=1"></script>
<?php page_footer();
