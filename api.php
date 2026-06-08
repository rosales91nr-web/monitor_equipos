<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
require_once 'parse_log.php';
require_once 'config.php';

$surfDir = defined('LOG_DIR_SURFACING') ? LOG_DIR_SURFACING : __DIR__.'/logs/surfacing';
$edgDir  = defined('LOG_DIR_EDGING')   ? LOG_DIR_EDGING    : __DIR__.'/logs/edging';

// ─── Filtro de fecha opcional ────────────────────────────────────────────────
// Recibe ?from=YYYY-MM-DD&to=YYYY-MM-DD (ambos opcionales)
// Si no se envían, retorna todo lo disponible (comportamiento anterior)
$from = isset($_GET['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from']) ? $_GET['from'] : null;
$to   = isset($_GET['to'])   && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'])   ? $_GET['to']   : null;

// Con rango de fechas → incluir ZIPs históricos que lo cubran (historial).
// Sin rango (monitor en vivo) → solo logs del mes actual, más rápido.
$includeOldZips = ($from !== null || $to !== null);

$data = parseAllLogs($surfDir, $edgDir, $includeOldZips, $from, $to);
$data['serverTime']  = date('d.m.Y H:i:s');
$data['filterFrom']  = $from;
$data['filterTo']    = $to;

echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);