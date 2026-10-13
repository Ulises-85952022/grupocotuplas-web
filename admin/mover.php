<?php
declare(strict_types=1);
require __DIR__ . '/_app.php';
require_login();
check_csrf();

$projects = load_projects();
$i = find_project($projects, (string)($_POST['id'] ?? ''));
$j = $i === null ? null : $i + (($_POST['dir'] ?? '') === 'up' ? -1 : 1);
if ($i !== null && isset($projects[$j])) {
  [$projects[$i], $projects[$j]] = [$projects[$j], $projects[$i]];
  save_projects($projects);
}
header('Location: index.php');
