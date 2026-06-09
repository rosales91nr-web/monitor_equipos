<?php
/**
 * ╔══════════════════════════════════════════════════════════════╗
 * ║     LENSWARE MONITOR — API DE SINCRONIZACIÓN AUTOMÁTICA      ║
 * ║  Recibe archivos .log y .zip desde el agente Windows         ║
 * ╚══════════════════════════════════════════════════════════════╝
 *
 * Uso: POST /sync_api.php
 *   Header:  X-Sync-Key: <SYNC_API_KEY>
 *   Campo:   server  = "Surfacing" | "Edging"
 *   Campo:   logfile = <archivo .log o .zip>
 *
 * GET /sync_api.php?status=1&key=<SYNC_API_KEY>
 *   Devuelve JSON con el estado de la última sincronización.
 */

// Buffer de salida: captura cualquier warning/notice de PHP para que no
// corrompa la respuesta JSON (p.ej. cuando el POST excede post_max_size)
ob_start();

require_once 'config.php';

$surfDir    = LOG_DIR_SURFACING;
$edgDir     = LOG_DIR_EDGING;
$statusFile = __DIR__ . '/logs/sync_status.json';
$apiKey     = defined('SYNC_API_KEY') ? SYNC_API_KEY : '';

// ─── Helper: respuesta JSON ──────────────────────────────────────────────────
function respond(bool $ok, string $message, array $extra = []): void {
    ob_end_clean();  // descarta cualquier warning que PHP haya impreso antes
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: X-Sync-Key, Content-Type');
    echo json_encode(array_merge([
        'ok'      => $ok,
        'message' => $message,
        'time'    => date('d.m.Y H:i:s'),
    ], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

// ─── Helper: leer estado guardado ───────────────────────────────────────────
function readStatus(string $file): array {
    if (!file_exists($file)) return [];
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

// ─── Helper: guardar estado ─────────────────────────────────────────────────
function writeStatus(string $file, array $data): void {
    $dir = dirname($file);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

// ─── Detectar POST descartado por post_max_size ──────────────────────────────
// Cuando el body excede post_max_size PHP vacía $_POST y $_FILES pero conserva
// el header X-Sync-Key, por lo que llegaríamos a respond() con datos vacíos.
// Detectamos esto antes de validar la API key para dar un mensaje claro.
$isPost = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
$contentLen = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
$postMaxBytes = (function() {
    $v = ini_get('post_max_size');
    $u = strtolower(substr($v, -1));
    $n = (int)$v;
    return match($u) { 'g' => $n * 1073741824, 'm' => $n * 1048576, 'k' => $n * 1024, default => $n };
})();
if ($isPost && $contentLen > 0 && $contentLen > $postMaxBytes && empty($_FILES)) {
    $limit = round($postMaxBytes / 1048576, 0) . ' MB';
    respond(false, 'Archivo demasiado grande para el servidor. Limite: ' . $limit . '. Contacta al administrador.');
}

// ─── Validar API key ────────────────────────────────────────────────────────
$providedKey = $_SERVER['HTTP_X_SYNC_KEY']
    ?? $_POST['api_key']
    ?? $_GET['key']
    ?? '';

if (!$apiKey || !hash_equals($apiKey, $providedKey)) {
    http_response_code(401);
    respond(false, 'API key invalida o no configurada.');
}

// ─── GET: estado de sincronización ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['status'])) {
    respond(true, 'Estado OK', ['sync' => readStatus($statusFile)]);
}

// ─── OPTIONS: preflight CORS ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    http_response_code(204);
    exit;
}

// ─── POST: recibir archivo ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    respond(false, 'Metodo no permitido. Usa POST.');
}

if (!isset($_FILES['logfile']) || $_FILES['logfile']['error'] === UPLOAD_ERR_NO_FILE) {
    http_response_code(400);
    respond(false, 'No se recibio ningun archivo.');
}

$server = trim($_POST['server'] ?? '');
if (!in_array($server, ['Surfacing', 'Edging'], true)) {
    http_response_code(400);
    respond(false, 'Campo "server" debe ser "Surfacing" o "Edging".');
}

$destDir = $server === 'Surfacing' ? $surfDir : $edgDir;

if ($_FILES['logfile']['error'] !== UPLOAD_ERR_OK) {
    $codes = [
        UPLOAD_ERR_INI_SIZE   => 'Archivo demasiado grande (limite PHP ini)',
        UPLOAD_ERR_FORM_SIZE  => 'Archivo demasiado grande (limite form)',
        UPLOAD_ERR_PARTIAL    => 'Carga incompleta',
        UPLOAD_ERR_NO_TMP_DIR => 'Sin carpeta temporal',
        UPLOAD_ERR_CANT_WRITE => 'Error de escritura en disco',
    ];
    $errCode = $_FILES['logfile']['error'];
    respond(false, 'Error de carga: ' . ($codes[$errCode] ?? 'Codigo ' . $errCode));
}

$origName = basename($_FILES['logfile']['name']);
$ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

if (!in_array($ext, ['log', 'zip'], true)) {
    http_response_code(400);
    respond(false, 'Solo se permiten archivos .log y .zip.');
}

// Validar nombre: YYYYMMDD.log o YYYYMM.zip
if ($ext === 'log' && !preg_match('/^\d{8}\.log$/i', $origName)) {
    http_response_code(400);
    respond(false, 'Nombre de log invalido. Se esperaba formato YYYYMMDD.log');
}
if ($ext === 'zip' && !preg_match('/^\d{6}\.zip$/i', $origName)) {
    http_response_code(400);
    respond(false, 'Nombre de ZIP invalido. Se esperaba formato YYYYMM.zip');
}

if (!is_dir($destDir)) {
    mkdir($destDir, 0755, true);
}

$dest = $destDir . DIRECTORY_SEPARATOR . $origName;
$size = $_FILES['logfile']['size'];

if (!move_uploaded_file($_FILES['logfile']['tmp_name'], $dest)) {
    http_response_code(500);
    respond(false, 'No se pudo guardar el archivo. Verifica permisos.');
}

// ─── Actualizar estado de sincronización ────────────────────────────────────
$status = readStatus($statusFile);
$status[$server] = [
    'last_sync' => date('d.m.Y H:i:s'),
    'last_file' => $origName,
    'bytes'     => $size,
    'status'    => 'ok',
];
writeStatus($statusFile, $status);

// FIX: usar concatenacion en lugar de interpolacion con caracteres multibyte
// para evitar el bug "Undefined variable" cuando PHP parsea «$origName»
respond(true, 'Archivo recibido correctamente: ' . $origName . ' (' . $server . ')', [
    'file'   => $origName,
    'server' => $server,
    'bytes'  => $size,
]);
