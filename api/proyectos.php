<?php
declare(strict_types=1);
require __DIR__ . '/../admin/_store.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');
header('X-Content-Type-Options: nosniff');

$out = [];
foreach (load_projects() as $p) {
  $out[] = [
    'id'          => $p['id'],
    'nombre'      => $p['nombre'],
    'tipo'        => $p['tipo'],
    'etiqueta'    => $p['etiqueta'] !== '' ? $p['etiqueta'] : $p['tipo'] . ' · ' . $p['municipio'],
    'lugar'       => project_place($p),
    'lat'         => $p['lat'],
    'lng'         => $p['lng'],
    'que_se_hizo' => $p['que_se_hizo'],
    'resultado'   => $p['resultado'],
    'beneficio'   => $p['beneficio'],
    'fotos'       => $p['fotos'],
  ];
}
echo json_encode(['proyectos' => $out], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
