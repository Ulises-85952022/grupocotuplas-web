<?php
declare(strict_types=1);
require __DIR__ . '/_app.php';
require_login();

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  check_csrf();
  if (!password_verify((string)($_POST['actual'] ?? ''), credentials()['hash'])) {
    $error = 'La contraseña actual no es correcta.';
  } else {
    $pass = (string)($_POST['password'] ?? '');
    $error = password_problem($pass, (string)($_POST['confirm'] ?? ''));
    if ($error === null) {
      $cred = ['hash' => password_hash($pass, PASSWORD_DEFAULT), 'creado' => date('c')];
      write_json('admin.json', $cred);
      login_as($cred);
      flash('Contraseña actualizada.');
      header('Location: index.php');
      exit;
    }
  }
}

page_header('Cambiar contraseña');
?>
<div class="card narrow">
  <h1>Cambiar contraseña</h1>
  <?php if ($error): ?><p class="alert error"><?= e($error) ?></p><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label for="actual">Contraseña actual</label>
    <input type="password" id="actual" name="actual" required autocomplete="current-password">
    <label for="password">Nueva contraseña (mínimo 10 caracteres)</label>
    <input type="password" id="password" name="password" minlength="10" required autocomplete="new-password">
    <label for="confirm">Repite la nueva contraseña</label>
    <input type="password" id="confirm" name="confirm" minlength="10" required autocomplete="new-password">
    <button type="submit" class="btn primary block">Guardar</button>
  </form>
</div>
<?php page_footer();
