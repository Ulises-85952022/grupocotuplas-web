<?php
declare(strict_types=1);
require __DIR__ . '/_app.php';
require_login();
check_csrf();

$projects = load_projects();
$i = find_project($projects, (string)($_POST['id'] ?? ''));
if ($i !== null) {
  foreach ($projects[$i]['fotos'] as $f) delete_photo_file($f['src']);
  $nombre = $projects[$i]['nombre'];
  array_splice($projects, $i, 1);
  save_projects($projects);
  flash('Se eliminó «' . $nombre . '».');
}
header('Location: index.php');
