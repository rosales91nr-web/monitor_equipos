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
