<?php
/**
 * ╔══════════════════════════════════════════════════════════════╗
 * ║           LENSWARE MONITOR — CONFIGURACIÓN v2.0              ║
 * ╚══════════════════════════════════════════════════════════════╝
 */

// ─── SERVIDOR DE SURFACING ──────────────────────────────────────────────────
define('LOG_DIR_SURFACING', '\\\\172.16.8.32\\Lensware\\LensDeviceServer_Surfacing\\Log');

// ─── SERVIDOR DE EDGING ─────────────────────────────────────────────────────
define('LOG_DIR_EDGING', '\\\\172.16.8.32\\Lensware\\LensDeviceServer_Edging\\Log');

// ─── INTERVALO DE ACTUALIZACIÓN (segundos) ─────────────────────────────────
define('REFRESH_INTERVAL', 10);

// ─── ZONA HORARIA ──────────────────────────────────────────────────────────
date_default_timezone_set('America/Costa_Rica');