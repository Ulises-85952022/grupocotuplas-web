<?php
declare(strict_types=1);
require __DIR__ . '/_app.php';

if (credentials() === null) {
  header('Location: setup.php');
  exit;
}

if (!is_logged_in()) {
  $error = null;
  $locked = seconds_locked();
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && $locked === 0) {
    check_csrf();
    $cred = credentials();
    if (password_verify((string)($_POST['password'] ?? ''), $cred['hash'])) {
      clear_failures();
      login_as($cred);
      header('Location: index.php');
      exit;
    }
    register_failure();
    $locked = seconds_locked();
    $error = 'Contraseña incorrecta.';
  }
  if ($locked > 0) $error = 'Demasiados intentos fallidos. Espera ' . ceil($locked / 60) . ' minutos.';

  page_header('Entrar', false);
  ?>
  <div class="card narrow">
    <h1>Entrar al panel</h1>
    <?php if ($error): ?><p class="alert error"><?= e($error) ?></p><?php endif; ?>
    <form method="post">
      <?= csrf_field() ?>
      <label for="password">Contraseña</label>
      <input type="password" id="password" name="password" required autofocus autocomplete="current-password">
      <button type="submit" class="btn primary block">Entrar</button>
    </form>
  </div>
  <?php
  page_footer();
  exit;
}

$projects = load_projects();
$msg = flash();
page_header('Proyectos');
?>
<div class="page-title">
  <div>
    <h1>Proyectos</h1>
    <p class="muted">Estos proyectos aparecen en el mapa y en la sección "Proyectos que transforman comunidades" del sitio, en este orden.</p>
  </div>
  <a href="proyecto.php" class="btn primary">+ Nuevo proyecto</a>
</div>
<?php if ($msg): ?><p class="alert ok"><?= e($msg) ?></p><?php endif; ?>

<?php if (!$projects): ?>
  <div class="card empty">Aún no hay proyectos. Usa <b>+ Nuevo proyecto</b> para agregar el primero.</div>
<?php endif; ?>

<ul class="proj-list">
<?php foreach ($projects as $i => $p): $foto = $p['fotos'][0]['src'] ?? null; ?>
  <li class="card proj-row">
    <div class="thumb"><?php if ($foto): ?><img src="<?= e(photo_url($foto)) ?>" alt="" loading="lazy"><?php endif; ?></div>
    <div class="info">
      <span class="badge"><?= e($p['tipo']) ?></span>
      <h2><?= e($p['nombre']) ?></h2>
      <p class="muted">📍 <?= e(project_place($p)) ?> · <?= count($p['fotos']) ?> foto(s)</p>
    </div>
    <div class="actions">
      <form method="post" action="mover.php">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($p['id']) ?>">
        <button name="dir" value="up" class="btn icon" title="Subir" <?= $i === 0 ? 'disabled' : '' ?>>↑</button>
        <button name="dir" value="down" class="btn icon" title="Bajar" <?= $i === count($projects) - 1 ? 'disabled' : '' ?>>↓</button>
      </form>
      <a href="proyecto.php?id=<?= e(urlencode($p['id'])) ?>" class="btn">Editar</a>
      <form method="post" action="eliminar.php" onsubmit="return confirm('¿Eliminar «<?= e(addslashes($p['nombre'])) ?>»? Esta acción no se puede deshacer.');">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($p['id']) ?>">
        <button type="submit" class="btn danger">Eliminar</button>
      </form>
    </div>
  </li>
<?php endforeach; ?>
</ul>
<?php page_footer();
