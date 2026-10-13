<?php
declare(strict_types=1);

// Datos privados fuera de public_html: /home/<usuario>/domains/grupocotuplas.com/cotuplas-privado
define('PRIVATE_DIR', dirname(__DIR__, 2) . '/cotuplas-privado');
define('SITE_ROOT', dirname(__DIR__));
define('UPLOAD_REL', 'uploads/proyectos');
// En admin.grupocotuplas.com las fotos y enlaces apuntan al dominio principal
define('SITE_URL', (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off' ? 'http' : 'https') . '://'
  . preg_replace('/^admin\./', '', (string)($_SERVER['HTTP_HOST'] ?? 'grupocotuplas.com')));
define('MAX_FOTOS', 5);

const ESTADOS = [
  'aguascalientes'      => ['Aguascalientes', 21.88, -102.29],
  'baja_california'     => ['Baja California', 30.84, -115.78],
  'baja_california_sur' => ['Baja California Sur', 25.19, -111.40],
  'campeche'            => ['Campeche', 19.83, -90.53],
  'chiapas'             => ['Chiapas', 16.75, -93.13],
  'chihuahua'           => ['Chihuahua', 28.64, -106.07],
  'coahuila'            => ['Coahuila', 27.05, -101.71],
  'colima'              => ['Colima', 19.12, -103.72],
  'cdmx'                => ['Ciudad de México', 19.43, -99.13],
  'durango'             => ['Durango', 24.53, -104.66],
  'guanajuato'          => ['Guanajuato', 21.02, -101.26],
  'guerrero'            => ['Guerrero', 17.45, -100.32],
  'hidalgo'             => ['Hidalgo', 20.47, -98.97],
  'jalisco'             => ['Jalisco', 20.66, -103.35],
  'mexico'              => ['Estado de México', 19.36, -99.67],
  'michoacan'           => ['Michoacán', 19.70, -101.19],
  'morelos'             => ['Morelos', 18.68, -99.10],
  'nayarit'             => ['Nayarit', 21.75, -104.85],
  'nuevo_leon'          => ['Nuevo León', 25.67, -100.31],
  'oaxaca'              => ['Oaxaca', 17.07, -96.72],
  'puebla'              => ['Puebla', 19.04, -98.21],
  'queretaro'           => ['Querétaro', 20.59, -100.39],
  'quintana_roo'        => ['Quintana Roo', 19.58, -88.05],
  'san_luis_potosi'     => ['San Luis Potosí', 22.15, -100.98],
  'sinaloa'             => ['Sinaloa', 25.17, -107.48],
  'sonora'              => ['Sonora', 29.30, -110.33],
  'tabasco'             => ['Tabasco', 17.99, -92.93],
  'tamaulipas'          => ['Tamaulipas', 24.27, -98.84],
  'tlaxcala'            => ['Tlaxcala', 19.32, -98.24],
  'veracruz'            => ['Veracruz', 19.17, -96.13],
  'yucatan'             => ['Yucatán', 20.97, -89.62],
  'zacatecas'           => ['Zacatecas', 22.77, -102.58],
];

const TIPOS = [
  'BLUDREN'    => 'BLUDREN – Drenaje',
  'Adoplastic' => 'Adoplastic – Pisos',
  'Mobiliario' => 'Mobiliario urbano',
  'Otro'       => 'Otro',
];

function private_path(string $file): string
{
  if (!is_dir(PRIVATE_DIR) && !@mkdir(PRIVATE_DIR, 0750, true)) {
    http_response_code(500);
    exit('No se pudo crear la carpeta privada de datos: ' . PRIVATE_DIR);
  }
  return PRIVATE_DIR . '/' . $file;
}

function read_json(string $file, $default)
{
  $path = private_path($file);
  if (!is_file($path)) return $default;
  $fh = fopen($path, 'rb');
  flock($fh, LOCK_SH);
  $raw = stream_get_contents($fh);
  flock($fh, LOCK_UN);
  fclose($fh);
  $data = json_decode((string)$raw, true);
  return is_array($data) ? $data : $default;
}

function write_json(string $file, array $data): void
{
  $path = private_path($file);
  $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
  file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
  rename($tmp, $path);
}

function load_projects(): array
{
  $data = read_json('proyectos.json', null);
  if ($data === null) {
    $data = json_decode((string)file_get_contents(__DIR__ . '/_seed.json'), true);
    write_json('proyectos.json', $data);
  }
  return $data['proyectos'] ?? [];
}

function save_projects(array $projects): void
{
  write_json('proyectos.json', ['proyectos' => array_values($projects)]);
}

function find_project(array $projects, string $id): ?int
{
  foreach ($projects as $i => $p) {
    if ($p['id'] === $id) return $i;
  }
  return null;
}

function project_place(array $p): string
{
  $estado = ESTADOS[$p['estado']][0] ?? '';
  return implode(', ', array_filter([$p['localidad'] ?? '', $p['municipio'] ?? '', $estado]));
}
