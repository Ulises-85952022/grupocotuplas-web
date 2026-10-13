<?php
declare(strict_types=1);
require __DIR__ . '/_app.php';

if (credentials() !== null) {
  header('Location: index.php');
  exit;
}

// Código de activación entregado al propietario por separado; evita que un tercero cree la contraseña primero
const CODIGO_HASH = '$2y$10$fjfvb3mtXeLG0y8dcQdQEO46hhBTrhQTx6zQm2m0yi7TFK.Wn7VAO';

$error = null;
$locked = seconds_locked();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $locked === 0) {
  check_csrf();
  $pass = (string)($_POST['password'] ?? '');
  if (!password_verify(strtoupper(trim((string)($_POST['codigo'] ?? ''))), CODIGO_HASH)) {
    register_failure();
    $locked = seconds_locked();
    $error = 'El código de activación no es correcto.';
  } else {
    $error = password_problem($pass, (string)($_POST['confirm'] ?? ''));
  }
  if ($error === null) {
    clear_failures();
    $cred = ['hash' => password_hash($pass, PASSWORD_DEFAULT), 'creado' => date('c')];
    write_json('admin.json', $cred);
    load_projects();
    login_as($cred);
    header('Location: index.php');
    exit;
  }
}

if ($locked > 0) $error = 'Demasiados intentos fallidos. Espera ' . ceil($locked / 60) . ' minutos.';

page_header('Configuración inicial', false);
?>
<div class="card narrow">
  <h1>Bienvenido</h1>
  <p class="muted">Crea la contraseña de acceso al panel. Solo se pide una vez.</p>
  <?php if ($error): ?><p class="alert error"><?= e($error) ?></p><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label for="codigo">Código de activación</label>
    <input id="codigo" name="codigo" required autocomplete="off" placeholder="XXXX-XXXX-XXXX">
    <label for="password">Contraseña (mínimo 10 caracteres)</label>
    <input type="password" id="password" name="password" minlength="10" required autocomplete="new-password">
    <label for="confirm">Repite la contraseña</label>
    <input type="password" id="confirm" name="confirm" minlength="10" required autocomplete="new-password">
    <button type="submit" class="btn primary block">Crear contraseña y entrar</button>
  </form>
</div>
<?php page_footer();
