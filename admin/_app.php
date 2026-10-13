<?php
declare(strict_types=1);

require __DIR__ . '/_store.php';

header('X-Robots-Tag: noindex, nofollow');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

session_name('cotuplas_admin');
session_set_cookie_params([
  'lifetime' => 0,
  'path'     => '/',
  'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
  'httponly' => true,
  'samesite' => 'Strict',
]);
session_start();

const MAX_INTENTOS = 5;
const BLOQUEO_SEG = 900;
const SESION_SEG = 8 * 3600;

function e($s): string
{
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function credentials(): ?array
{
  return read_json('admin.json', null);
}

function is_logged_in(): bool
{
  if (empty($_SESSION['admin']) || (time() - ($_SESSION['since'] ?? 0)) > SESION_SEG) return false;
  $cred = credentials();
  // Cambiar la contraseña invalida las demás sesiones abiertas
  return $cred !== null && hash_equals((string)$cred['hash'], (string)($_SESSION['hash'] ?? ''));
}

function require_login(): void
{
  if (credentials() === null) {
    header('Location: setup.php');
    exit;
  }
  if (!is_logged_in()) {
    header('Location: index.php');
    exit;
  }
}

function login_as(array $cred): void
{
  session_regenerate_id(true);
  $_SESSION['admin'] = true;
  $_SESSION['since'] = time();
  $_SESSION['hash'] = $cred['hash'];
}

function csrf_token(): string
{
  if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
  return $_SESSION['csrf'];
}

function csrf_field(): string
{
  return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function check_csrf(): void
{
  if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals(csrf_token(), (string)($_POST['csrf'] ?? ''))) {
    http_response_code(400);
    exit('Solicitud inválida. Recarga la página e intenta de nuevo.');
  }
}

function client_ip(): string
{
  return (string)($_SERVER['REMOTE_ADDR'] ?? 'desconocida');
}

function seconds_locked(): int
{
  $rec = read_json('intentos.json', [])[client_ip()] ?? null;
  if (!$rec || $rec['n'] < MAX_INTENTOS) return 0;
  return max(0, BLOQUEO_SEG - (time() - $rec['t']));
}

function register_failure(): void
{
  $all = read_json('intentos.json', []);
  foreach ($all as $ip => $rec) {
    if (time() - $rec['t'] > BLOQUEO_SEG) unset($all[$ip]);
  }
  $rec = $all[client_ip()] ?? ['n' => 0, 't' => time()];
  $all[client_ip()] = ['n' => $rec['n'] + 1, 't' => time()];
  write_json('intentos.json', $all);
}

function clear_failures(): void
{
  $all = read_json('intentos.json', []);
  unset($all[client_ip()]);
  write_json('intentos.json', $all);
}

function password_problem(string $pass, string $confirm): ?string
{
  if (mb_strlen($pass) < 10) return 'La contraseña debe tener al menos 10 caracteres.';
  if ($pass !== $confirm) return 'Las contraseñas no coinciden.';
  return null;
}

function photo_url(string $src): string
{
  return preg_match('~^https?://~', $src) ? $src : SITE_URL . '/' . ltrim($src, '/');
}

// Solo borra archivos subidos desde el panel, nunca las imágenes originales del sitio en img/
function delete_photo_file(string $src): void
{
  if (strpos($src, UPLOAD_REL . '/') !== 0) return;
  $path = SITE_ROOT . '/' . $src;
  if (is_file($path)) unlink($path);
}

// Re-codifica la imagen con GD: valida que sea una imagen real y elimina metadatos
function store_uploaded_photo(array $file, string $projectId): string
{
  if ($file['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('No se pudo subir una de las fotos.');
  if ($file['size'] > 15 * 1024 * 1024) throw new RuntimeException('Cada foto debe pesar menos de 15 MB.');
  $info = @getimagesize($file['tmp_name']);
  $loaders = [IMAGETYPE_JPEG => 'imagecreatefromjpeg', IMAGETYPE_PNG => 'imagecreatefrompng', IMAGETYPE_WEBP => 'imagecreatefromwebp'];
  if (!$info || !isset($loaders[$info[2]])) throw new RuntimeException('Solo se aceptan fotos JPG, PNG o WEBP.');

  $src = @$loaders[$info[2]]($file['tmp_name']);
  if (!$src) throw new RuntimeException('Una de las fotos está dañada.');
  [$w, $h] = [imagesx($src), imagesy($src)];
  $scale = min(1, 1600 / max($w, $h));
  $nw = (int)round($w * $scale);
  $nh = (int)round($h * $scale);
  $dst = imagecreatetruecolor($nw, $nh);
  imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
  imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

  $dir = SITE_ROOT . '/' . UPLOAD_REL;
  if (!is_dir($dir)) mkdir($dir, 0755, true);
  $name = $projectId . '-' . bin2hex(random_bytes(6)) . '.jpg';
  imagejpeg($dst, $dir . '/' . $name, 82);
  imagedestroy($src);
  imagedestroy($dst);
  return UPLOAD_REL . '/' . $name;
}

function page_header(string $title, bool $nav = true): void
{
  ?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> · Panel Grupo Cotuplas</title>
<link rel="icon" href="<?= e(SITE_URL) ?>/favicon.svg">
<link rel="stylesheet" href="admin.css?v=1">
</head>
<body>
<header class="topbar">
  <a href="index.php" class="brand"><img src="<?= e(SITE_URL) ?>/img/img_18_01657ac3.png" alt="Grupo Cotuplas"><span>Panel de proyectos</span></a>
  <?php if ($nav): ?>
  <nav>
    <a href="<?= e(SITE_URL) ?>/#mapa" target="_blank" rel="noopener">Ver sitio</a>
    <a href="password.php">Contraseña</a>
    <form method="post" action="salir.php"><?= csrf_field() ?><button type="submit" class="link">Salir</button></form>
  </nav>
  <?php endif; ?>
</header>
<main class="wrap">
<?php
}

function page_footer(): void
{
  echo "</main>\n</body>\n</html>\n";
}

function flash(?string $msg = null): ?string
{
  if ($msg !== null) {
    $_SESSION['flash'] = $msg;
    return null;
  }
  $m = $_SESSION['flash'] ?? null;
  unset($_SESSION['flash']);
  return $m;
}
