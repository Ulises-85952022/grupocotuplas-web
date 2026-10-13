<?php
declare(strict_types=1);
require __DIR__ . '/_app.php';
check_csrf();
$_SESSION = [];
session_destroy();
header('Location: index.php');
