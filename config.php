<?php
/**
 * ╔══════════════════════════════════════════════════════════════╗
 * ║           LENSWARE MONITOR — CONFIGURACIÓN v2.0              ║
 * ╚══════════════════════════════════════════════════════════════╝
 */

// ─── SERVIDOR DE SURFACING ──────────────────────────────────────────────────
define('LOG_DIR_SURFACING', __DIR__ . '/logs/surfacing');

// ─── SERVIDOR DE EDGING ─────────────────────────────────────────────────────
define('LOG_DIR_EDGING', __DIR__ . '/logs/edging');

// ─── INTERVALO DE ACTUALIZACIÓN (segundos) ─────────────────────────────────
define('REFRESH_INTERVAL', 10);

// ─── ZONA HORARIA ──────────────────────────────────────────────────────────
date_default_timezone_set('America/Costa_Rica');

// ─── API KEY PARA SINCRONIZACIÓN AUTOMÁTICA ─────────────────────────────────
// Cambia este valor por uno secreto y ponlo igual en sync_agent.ps1
define('SYNC_API_KEY', 'c5ec4d6540940ac370da676eded10a44');

// ─── RUTAS UNC ORIGINALES (referencia para el agente Windows) ───────────────
define('UNC_SURFACING', '\\\\172.16.8.32\\Lensware\\LensDeviceServer_Surfacing\\Log');
define('UNC_EDGING',    '\\\\172.16.8.32\\Lensware\\LensDeviceServer_Edging\\Log');
