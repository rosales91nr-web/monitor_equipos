<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
require_once 'parse_log.php';
require_once 'config.php';

$surfDir = defined('LOG_DIR_SURFACING') ? LOG_DIR_SURFACING : __DIR__.'/logs/surfacing';
$edgDir  = defined('LOG_DIR_EDGING')   ? LOG_DIR_EDGING    : __DIR__.'/logs/edging';

// ─── Filtro de fecha ─────────────────────────────────────────────────────────
// Recibe ?from=YYYY-MM-DD&to=YYYY-MM-DD
// Sin parámetros → por defecto muestra solo HOY (fecha actual del servidor)
$today = date('Y-m-d');

$fromParam = isset($_GET['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from']) ? $_GET['from'] : null;
$toParam   = isset($_GET['to'])   && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'])   ? $_GET['to']   : null;

// ?all=1 → sin filtro de fecha (para análisis completo del mes)
$showAll = isset($_GET['all']) && $_GET['all'] === '1';

if ($showAll) {
    $from = null;
    $to   = null;
} else {
    // Default: hoy. El usuario puede cambiar el rango desde la UI.
    $from = $fromParam ?? $today;
    $to   = $toParam   ?? $today;
}

// Con rango histórico (no solo hoy) → incluir ZIPs para cubrir fechas pasadas.
$includeOldZips = ($from !== null && $from < $today) || ($to !== null && $to < $today) || $showAll;

$data = parseAllLogs($surfDir, $edgDir, $includeOldZips, $from, $to);
$data['serverTime']  = date('d.m.Y H:i:s');
$data['filterFrom']  = $from;
$data['filterTo']    = $to;

echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);