<?php
// ============================================================
// GoodEarth CMS — Database & Site Configuration
// Fill in your cPanel database credentials before running setup.php
// ============================================================

// ── Error reporting (set to 0 in production once everything works) ──
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ── Output buffering — prevents "headers already sent" errors ──
ob_start();

define('DB_HOST', 'localhost');
define('DB_NAME', 'goodearth_updates');   // e.g. cpanelusername_goodearth
define('DB_USER', 'updates');   // e.g. cpanelusername_dbuser
define('DB_PASS', 'Manju@8828');   // your database password
define('SITE_URL', 'https://updates2.goodearth.org.in/cms'); // no trailing slash

define('UPLOAD_DIR', __DIR__ . '/../uploads/blogs/');
define('UPLOAD_URL', SITE_URL . '/uploads/blogs/');

define('PROJECTS', [
    'motif'   => ['name' => 'Malhar Motif',      'location' => 'Malhar Eco-Village, Bangalore'],
    'octave'  => ['name' => 'Malhar Octave',     'location' => 'Malhar Eco-Village, Bangalore'],
    'ochre'   => ['name' => 'Malhar Ochre',      'location' => 'Malhar Eco-Village, Bangalore'],
    'cadence' => ['name' => 'Malhar Cadence',    'location' => 'Malhar Eco-Village, Bangalore'],
    'saarang' => ['name' => 'GoodEarth Saarang', 'location' => 'Kannur'],
    'umang'   => ['name' => 'GoodEarth Umang',   'location' => 'Kochi'],
]);

// Project listing page links (relative to cms/)
define('PROJECT_LINKS', [
    'motif'   => '../malhar-motif-updates.html',
    'octave'  => '../malhar-octave-updates.html',
    'ochre'   => '../malhar-ochre-updates.html',
    'cadence' => '../malhar-cadence-updates.html',
    'saarang' => '../goodearth-saarang-updates.html',
    'umang'   => '../goodearth-umang-updates.html',
]);
