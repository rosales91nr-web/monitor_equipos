<?php
/**
 * LensWare Log Parser — v3.1
 * Soporta archivos .log sueltos + ZIPs mensuales (YYYYMM.zip)
 * + filtro de rango de fechas (from / to en formato YYYY-MM-DD)
 *
 * Lógica de ZIPs:
 *  - ZIP del mes actual  → siempre incluido
 *  - ZIPs de meses anteriores → incluidos solo si $includeOldZips = true
 *    (y solo si el rango de fechas solicitado los cubre)
 *  - Si un .log aparece tanto suelto como dentro de un ZIP, el suelto tiene prioridad
 *
 * Filtro de fechas:
 *  - $from y $to son strings "YYYY-MM-DD" o null
 *  - Los jobs cuyo lastSeen < from o firstSeen > to se descartan
 *  - Los eventos individuales fuera del rango se filtran del timeline
 *  - Los archivos .log y ZIPs cuyo mes no puede contener el rango se saltan
 *    para no parsear datos que serán descartados de todos modos
 */

// ─── MAPA DE NOMBRES DE EQUIPOS ─────────────────────────────────────────────
$DEVICE_NAMES = [
    '4RA001' => '4Racer TBA',
    'CCL004' => 'LASER CCL MÓDULO',
    'CCP002' => 'CCP MODULO ONE',
    'CCP004' => 'CCP MODULO',
    'CCU001' => 'BLOQUEADORA BOND 1B',
    'CCU003' => 'BLOQUEADORA BOND-E Automática',
    'CCU004' => 'BLOQUEADORA BOND 2B',
    'ESF001' => 'EASYFIT 4',
    'ESF002' => 'EASYFIT 5',
    'ESF003' => 'EASYFIT 6',
    'ESF004' => 'EASYFIT 2',
    'HSE001' => 'HSC MODULO XTS',
    'HSE002' => 'HSC SMART',
    'HSE003' => 'HSC SMART XP2',
    'HSS004' => 'GENERADOR SMART 1',
];

// ─── HELPERS DE FECHA ────────────────────────────────────────────────────────

/**
 * Convierte "dd.mm.yyyy HH:MM:SS" a "yyyy-mm-dd" para comparación.
 * Retorna "" si el formato no se reconoce.
 */
function tsToDate(string $ts): string {
    // Formato esperado: "06.06.2025 14:32:11"
    if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})/', $ts, $m)) {
        return "{$m[3]}-{$m[2]}-{$m[1]}";
    }
    return '';
}

/**
 * Decide si un archivo de log (identificado por su nombre, ej. "20250606.log"
 * o el ZIP "202506.zip") podría contener datos dentro del rango [from, to].
 * Si no se puede inferir la fecha del nombre, retorna true (incluir por defecto).
 */
function fileCouldMatchRange(string $basename, ?string $from, ?string $to): bool {
    if ($from === null && $to === null) return true;

    // Nombre tipo YYYYMMDD.log o YYYYMMDD_NN.log (log por puerto) → fecha exacta del archivo
    if (preg_match('/^(\d{4})(\d{2})(\d{2})(?:_\d+)?\.log$/i', $basename, $m)) {
        $fileDate = "{$m[1]}-{$m[2]}-{$m[3]}";
        if ($to   !== null && $fileDate > $to)   return false;
        if ($from !== null && $fileDate < $from)  return false;
        return true;
    }

    // Nombre tipo YYYYMM.zip → mes completo
    if (preg_match('/^(\d{4})(\d{2})\.zip$/i', $basename, $m)) {
        $zipMonthStart = "{$m[1]}-{$m[2]}-01";
        // Último día del mes
        $zipMonthEnd   = date('Y-m-t', strtotime($zipMonthStart));
        if ($to   !== null && $zipMonthStart > $to)   return false;
        if ($from !== null && $zipMonthEnd   < $from)  return false;
        return true;
    }

    return true; // nombre desconocido → incluir
}

// ─── PARSER DE UN ÚNICO ARCHIVO .log ────────────────────────────────────────

function parseLensLog($logPath, ?string $contentOverride = null, ?string $from = null, ?string $to = null): array {
    if ($contentOverride !== null) {
        $content = $contentOverride;
    } else {
        if (!file_exists($logPath)) {
            return ['error' => 'Log file not found: ' . $logPath];
        }
        $content = file_get_contents($logPath);
        if ($content === false) {
            return ['error' => 'Cannot read log file.'];
        }
    }

    $content = str_replace("\r\n", "\n", $content);
    $lines   = explode("\n", $content);

    $statusLabels = [
        'SBLK' => 'Bloqueando',     'SGEN' => 'Generando',
        'SPOL' => 'Puliendo',       'SENG' => 'Grabando',
        'SINP' => 'Inspeccionando', 'STRT' => 'Tratamiento',
        'SDIS' => 'Despachando',    'SEDG' => 'Biselando',
        'EDGE' => 'Biselando',
    ];
    $prodTypeLabels = [
        'SBK' => 'Bloqueado',  'FSP' => 'Freeform SP',
        'FSE' => 'Freeform SE','FED' => 'Freeform ED',
        'FSG' => 'Freeform SG','FIX' => 'Fixed',
    ];

    $jobs     = [];
    $devices  = [];
    $timeouts = 0;

    $jobPatIP  = '/^(\d{2}\.\d{2}\.\d{4} \d{2}:\d{2}:\d{2}): (\d+\.\d+\.\d+\.\d+): Data found for JOB = (\d+), OrdNumbH = \d+, OrdNumb = (\d+)/';
    $jobPatDev = '/^(\d{2}\.\d{2}\.\d{4} \d{2}:\d{2}:\d{2}): ([A-Z0-9]+): Data found for JOB = (\d+), OrdNumbH = \d+, OrdNumb = (\d+)/';
    $devPatIP  = '/^(\d{2}\.\d{2}\.\d{4} \d{2}:\d{2}:\d{2}): (\d+\.\d+\.\d+\.\d+): Device: (\w+) - DeviceUser: (\w*) - Status: (\w+) - devMode: (\w+) - prodType: (\w+) - Model \+ MID: (\w+)/';
    $devPatDev = '/^(\d{2}\.\d{2}\.\d{4} \d{2}:\d{2}:\d{2}): ([A-Z0-9]+): Device: (\w+) - DeviceUser: (\w*) - Status: (\w+) - devMode: (\w+) - prodType: (\w+) - Model \+ MID: (\w+)/';

    $ipToDevice = [];

    $logDate = '';
    foreach (array_slice($lines, 0, 10) as $l) {
        if (preg_match('/Log start (\d{2}\.\d{2}\.\d{4})/', $l, $m)) {
            $logDate = $m[1];
        }
    }

    // ── Pasada previa: construir mapa IP→device completo ─────────────────────
    // Sin esto, un job cuya línea aparece ANTES de la línea Device: del mismo
    // bloque queda huérfano bajo la IP en vez del ID del equipo (ej. HSS004).
    foreach ($lines as $line) {
        if (preg_match($devPatIP, $line, $m)) {
            $ipToDevice[$m[2]] = $m[3]; // IP → device ID
        }
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '*') continue;

        if (strpos($line, 'Timeout after') !== false) {
            $timeouts++;
            continue;
        }

        // ── Device status ────────────────────────────────────────────────────
        $dm = null;
        if (preg_match($devPatIP, $line, $m)) {
            $dm = ['ts'=>$m[1],'id'=>$m[2],'dev'=>$m[3],'user'=>$m[4],
                   'status'=>$m[5],'mode'=>$m[6],'ptype'=>$m[7],'model'=>$m[8],'byip'=>true];
        } elseif (preg_match($devPatDev, $line, $m)) {
            $dm = ['ts'=>$m[1],'id'=>$m[2],'dev'=>$m[3],'user'=>$m[4],
                   'status'=>$m[5],'mode'=>$m[6],'ptype'=>$m[7],'model'=>$m[8],'byip'=>false];
        }
        if ($dm) {
            // Filtro de fecha para el estado del dispositivo
            $d = tsToDate($dm['ts']);
            if ($from && $d && $d < $from) { if ($dm['byip']) $ipToDevice[$dm['id']] = $dm['dev']; continue; }
            if ($to   && $d && $d > $to)   { if ($dm['byip']) $ipToDevice[$dm['id']] = $dm['dev']; continue; }

            global $DEVICE_NAMES;
            $key      = $dm['dev'];
            $fullName = $DEVICE_NAMES[$key] ?? $key;
            if (!isset($devices[$key]) || $dm['ts'] > $devices[$key]['lastSeen']) {
                $devices[$key] = [
                    'id'          => $dm['id'],
                    'device'      => $key,
                    'deviceName'  => $fullName,
                    'user'        => $dm['user'] ?: '—',
                    'status'      => $dm['status'],
                    'statusLabel' => $statusLabels[$dm['status']] ?? $dm['status'],
                    'devMode'     => $dm['mode'],
                    'prodType'    => $dm['ptype'],
                    'prodLabel'   => $prodTypeLabels[$dm['ptype']] ?? $dm['ptype'],
                    'model'       => $dm['model'],
                    'lastSeen'    => $dm['ts'],
                    'jobCount'    => 0,
                    'jobs'        => [],
                ];
            }
            if ($dm['byip']) $ipToDevice[$dm['id']] = $key;
            continue;
        }

        // ── Job events ───────────────────────────────────────────────────────
        $jm = null;
        if (preg_match($jobPatIP, $line, $m)) {
            $jm = ['ts'=>$m[1],'src'=>$m[2],'job'=>$m[3],'ord'=>$m[4],'byip'=>true];
        } elseif (preg_match($jobPatDev, $line, $m)) {
            $jm = ['ts'=>$m[1],'src'=>$m[2],'job'=>$m[3],'ord'=>$m[4],'byip'=>false];
        }
        if ($jm) {
            // Filtro de fecha para eventos
            $d = tsToDate($jm['ts']);
            if ($from && $d && $d < $from) continue;
            if ($to   && $d && $d > $to)   continue;

            $devName = $jm['byip'] ? ($ipToDevice[$jm['src']] ?? $jm['src']) : $jm['src'];
            $job     = $jm['job'];
            if (!isset($jobs[$job])) {
                $jobs[$job] = [
                    'job'       => $job,
                    'order'     => $jm['ord'],
                    'firstSeen' => $jm['ts'],
                    'lastSeen'  => $jm['ts'],
                    'devices'   => [],
                    'events'    => [],
                ];
            }
            if ($jm['ts'] > $jobs[$job]['lastSeen']) $jobs[$job]['lastSeen'] = $jm['ts'];
            if (!in_array($devName, $jobs[$job]['devices'], true))
                $jobs[$job]['devices'][] = $devName;
            $jobs[$job]['events'][] = ['ts' => $jm['ts'], 'dev' => $devName];
        }
    }

    foreach ($jobs as $job => $jdata) {
        foreach ($jdata['devices'] as $devName) {
            if (isset($devices[$devName]) && !in_array($job, $devices[$devName]['jobs'], true)) {
                $devices[$devName]['jobs'][]  = $job;
                $devices[$devName]['jobCount']++;
            }
        }
    }

    uasort($jobs, fn($a, $b) => strcmp($b['lastSeen'], $a['lastSeen']));

    return [
        'logDate'      => $logDate,
        'totalJobs'    => count($jobs),
        'totalDevices' => count($devices),
        'timeouts'     => $timeouts,
        'jobs'         => array_values($jobs),
        'devices'      => $devices,
    ];
}

// ─── UTILIDADES DE ZIP ───────────────────────────────────────────────────────

/**
 * Retorna los ZIPs mensuales (YYYYMM.zip) encontrados en un directorio.
 * @return array ['current' => string|null, 'historical' => string[]]
 */
function findMonthlyZips(string $dir): array {
    $result = ['current' => null, 'historical' => []];
    if (!is_dir($dir)) return $result;

    $currentMonth = date('Ym'); // ej. "202606"

    // Buscar ZIPs en la raiz del directorio Y en subcarpetas inmediatas.
    // Cubre: Log\202606.zip  y  Log\SubCarpeta\202606.zip
    $sep      = DIRECTORY_SEPARATOR;
    $patterns = [
        $dir . $sep . '*.zip',
        $dir . $sep . '*' . $sep . '*.zip',
    ];

    $zips = [];
    foreach ($patterns as $pattern) {
        $found = glob($pattern) ?: [];
        $zips  = array_merge($zips, $found);
    }
    $zips = array_unique($zips);

    if (empty($zips)) return $result;

    foreach ($zips as $zipPath) {
        $name = pathinfo($zipPath, PATHINFO_FILENAME);
        if (!preg_match('/^\d{6}$/', $name)) continue; // solo YYYYMM

        if ($name === $currentMonth) {
            // Si hay duplicados del mes actual en raiz y subcarpeta, el primero gana
            if ($result['current'] === null) $result['current'] = $zipPath;
        } else {
            // Evitar duplicados del mismo mes en ubicaciones distintas
            $alreadyHave = array_filter($result['historical'],
                fn($p) => pathinfo($p, PATHINFO_FILENAME) === $name);
            if (empty($alreadyHave)) {
                $result['historical'][] = $zipPath;
            }
        }
    }
    sort($result['historical']);
    return $result;
}

/**
 * Parsea todos los .log dentro de un ZipArchive con filtro de fecha.
 * Omite los que ya están en $alreadyParsed.
 */
function parseZip(string $zipPath, array $alreadyParsed, string $label, string $zipTag,
                  ?string $from, ?string $to): array {
    $results    = [];
    $sourceInfo = ['zip' => $zipTag, 'files' => [], 'jobs' => 0, 'devices' => 0, 'skipped' => []];

    if (!class_exists('ZipArchive')) {
        $sourceInfo['error'] = 'La extensión ZipArchive no está disponible en PHP.';
        return [$results, $sourceInfo];
    }

    $zip = new ZipArchive();
    $opened = $zip->open($zipPath);
    if ($opened !== true) {
        $sourceInfo['error'] = "No se pudo abrir el ZIP: $zipPath (código $opened)";
        return [$results, $sourceInfo];
    }

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entryName = $zip->getNameIndex($i);
        if (strtolower(pathinfo($entryName, PATHINFO_EXTENSION)) !== 'log') continue;
        $basename = basename($entryName);

        if (in_array($basename, $alreadyParsed, true)) {
            $sourceInfo['skipped'][] = $basename;
            continue;
        }

        // Saltar archivos cuya fecha no pueda estar en el rango
        if (!fileCouldMatchRange($basename, $from, $to)) {
            $sourceInfo['skipped'][] = $basename . ' (fuera de rango)';
            continue;
        }

        $content = $zip->getFromIndex($i);
        if ($content === false) continue;

        $data = parseLensLog(null, $content, $from, $to);
        if (isset($data['error'])) continue;

        $sourceInfo['files'][]  = $basename . ' (zip)';
        $sourceInfo['jobs']    += $data['totalJobs'];
        $sourceInfo['devices'] += $data['totalDevices'];
        $results[] = $data;
    }

    $zip->close();
    return [$results, $sourceInfo];
}

// ─── FUNCIÓN PRINCIPAL ───────────────────────────────────────────────────────

/**
 * Parsea todos los logs de ambos servidores con filtro de rango de fechas.
 *
 * @param bool        $includeOldZips  Si true incluye ZIPs históricos que cubran el rango
 * @param string|null $from            Fecha inicio "YYYY-MM-DD" (inclusive), null = sin límite
 * @param string|null $to             Fecha fin   "YYYY-MM-DD" (inclusive), null = sin límite
 */
function parseAllLogs(string $surfacingDir, string $edgingDir,
                      bool $includeOldZips = false,
                      ?string $from = null, ?string $to = null): array {
    $allJobs       = [];
    $allDevices    = [];
    $sources       = [];
    $totalTimeouts = 0;

    $dirs = [
        'Surfacing' => $surfacingDir,
        'Edging'    => $edgingDir,
    ];

    foreach ($dirs as $label => $dir) {
        if (!is_dir($dir)) {
            $sources[$label] = ['error' => 'Directorio no encontrado: ' . $dir];
            continue;
        }

        $sources[$label] = ['files' => [], 'jobs' => 0, 'devices' => 0];

        // ── 1. Archivos .log sueltos ─────────────────────────────────────────
        $looseFiles  = glob($dir . DIRECTORY_SEPARATOR . '*.log') ?: [];
        $parsedLoose = [];

        sort($looseFiles);
        foreach ($looseFiles as $logFile) {
            $basename = basename($logFile);

            // Saltar archivos cuya fecha no pueda estar en el rango
            if (!fileCouldMatchRange($basename, $from, $to)) continue;

            $data = parseLensLog($logFile, null, $from, $to);
            if (isset($data['error'])) continue;

            $parsedLoose[] = $basename;
            $sources[$label]['files'][]  = $basename;
            $sources[$label]['jobs']    += $data['totalJobs'];
            $sources[$label]['devices'] += $data['totalDevices'];
            $totalTimeouts += $data['timeouts'] ?? 0;

            _mergeData($data, $label, $basename, $allJobs, $allDevices);
        }

        // ── 2. ZIPs mensuales ────────────────────────────────────────────────
        $zips = findMonthlyZips($dir);

        $zipsToProcess = [];
        if ($zips['current'] !== null) {
            // ZIP del mes actual: incluir solo si puede cubrir el rango
            if (fileCouldMatchRange(basename($zips['current']), $from, $to)) {
                $zipsToProcess[] = $zips['current'];
            }
        }
        if ($includeOldZips) {
            foreach ($zips['historical'] as $hz) {
                if (fileCouldMatchRange(basename($hz), $from, $to)) {
                    $zipsToProcess[] = $hz;
                }
            }
        }

        foreach ($zipsToProcess as $zipPath) {
            $zipTag = basename($zipPath);
            [$zipResults, $sourceInfo] = parseZip($zipPath, $parsedLoose, $label, $zipTag, $from, $to);

            $sources[$label]['zips'][$zipTag] = $sourceInfo;
            $sources[$label]['jobs']    += $sourceInfo['jobs'];
            $sources[$label]['devices'] += $sourceInfo['devices'];
            if (!empty($sourceInfo['files'])) {
                $sources[$label]['files'] = array_merge($sources[$label]['files'], $sourceInfo['files']);
            }

            foreach ($zipResults as $data) {
                $totalTimeouts += $data['timeouts'] ?? 0;
                _mergeData($data, $label, $zipTag, $allJobs, $allDevices);
            }
        }

        // ── 3. Indicar ZIPs históricos disponibles aunque no se procesen ────
        if (!$includeOldZips && !empty($zips['historical'])) {
            $sources[$label]['historicalZipsAvailable'] = array_map('basename', $zips['historical']);
        }
    }

    uasort($allJobs, fn($a, $b) => strcmp($b['lastSeen'], $a['lastSeen']));

    return [
        'totalJobs'    => count($allJobs),
        'totalDevices' => count($allDevices),
        'timeouts'     => $totalTimeouts,
        'jobs'         => array_values($allJobs),
        'devices'      => $allDevices,
        'sources'      => $sources,
        'logDate'      => date('d.m.Y'),
        'serverTime'   => date('d.m.Y H:i:s'),
    ];
}

// ─── HELPER INTERNO ──────────────────────────────────────────────────────────

function _mergeData(array $data, string $label, string $fileTag,
                    array &$allJobs, array &$allDevices): void {
    foreach ($data['devices'] as $devKey => $dev) {
        $dev['server']  = $label;
        $dev['logFile'] = $fileTag;
        if (!isset($allDevices[$devKey])) {
            $allDevices[$devKey] = $dev;
        } else {
            // Acumular jobs únicos de todos los archivos del período
            $existingJobs = $allDevices[$devKey]['jobs'] ?? [];
            foreach ($dev['jobs'] as $j) {
                if (!in_array($j, $existingJobs, true)) {
                    $existingJobs[] = $j;
                }
            }
            // Conservar el estado más reciente (lastSeen) pero con jobs acumulados
            if ($dev['lastSeen'] > $allDevices[$devKey]['lastSeen']) {
                $allDevices[$devKey] = $dev;
            }
            $allDevices[$devKey]['jobs']     = $existingJobs;
            $allDevices[$devKey]['jobCount'] = count($existingJobs);
        }
    }

    foreach ($data['jobs'] as $job) {
        $j = $job['job'];
        if (!isset($allJobs[$j])) {
            $allJobs[$j]           = $job;
            $allJobs[$j]['server'] = $label;
        } else {
            if ($job['lastSeen'] > $allJobs[$j]['lastSeen'])
                $allJobs[$j]['lastSeen'] = $job['lastSeen'];
            foreach ($job['devices'] as $d) {
                if (!in_array($d, $allJobs[$j]['devices'], true))
                    $allJobs[$j]['devices'][] = $d;
            }
            $allJobs[$j]['events'] = array_merge($allJobs[$j]['events'], $job['events'] ?? []);
        }
    }
}