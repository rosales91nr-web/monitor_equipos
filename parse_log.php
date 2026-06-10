<?php
/**
 * LensWare Log Parser — v4.1 (Optimizado para ZIPs grandes)
 * Soporta archivos .log sueltos + ZIPs mensuales (YYYYMM.zip)
 * + filtro de rango de fechas (from / to en formato YYYY-MM-DD)
 * + seguimiento de pipeline de Surfacing: Bloqueado→Generado→Pulido→Grabado
 * + detección de reprocesos, errores B;601/B;701, Waiting for trays
 * + metadatos de lentes (LNAM, LTYP, LMFR)
 */

// Aumentar límites para procesar ZIPs grandes (mayo 2026 tiene 21MB)
ini_set('memory_limit', '512M');
ini_set('max_execution_time', 300);
ini_set('max_input_time', 300);

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

function tsToDate(string $ts): string {
    if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})/', $ts, $m)) {
        return "{$m[3]}-{$m[2]}-{$m[1]}";
    }
    return '';
}

function fileCouldMatchRange(string $basename, ?string $from, ?string $to): bool {
    if ($from === null && $to === null) return true;

    if (preg_match('/^(\d{4})(\d{2})(\d{2})(?:_\d+)?\.log$/i', $basename, $m)) {
        $fileDate = "{$m[1]}-{$m[2]}-{$m[3]}";
        if ($to   !== null && $fileDate > $to)   return false;
        if ($from !== null && $fileDate < $from)  return false;
        return true;
    }

    if (preg_match('/^(\d{4})(\d{2})\.zip$/i', $basename, $m)) {
        $zipMonthStart = "{$m[1]}-{$m[2]}-01";
        $zipMonthEnd   = date('Y-m-t', strtotime($zipMonthStart));
        if ($to   !== null && $zipMonthStart > $to)   return false;
        if ($from !== null && $zipMonthEnd   < $from)  return false;
        return true;
    }

    return true;
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

    // Pipeline de Surfacing (excluye EDGE/biseladoras)
    $SURF_PIPELINE = ['SBLK' => 1, 'SGEN' => 2, 'SPOL' => 3, 'SENG' => 4];

    $jobs     = [];
    $devices  = [];
    $timeouts = 0;

    // Patrones existentes
    $jobPatIP  = '/^(\d{2}\.\d{2}\.\d{4} \d{2}:\d{2}:\d{2}): (\d+\.\d+\.\d+\.\d+): Data found for JOB = (\d+), OrdNumbH = \d+, OrdNumb = (\d+)/';
    $jobPatDev = '/^(\d{2}\.\d{2}\.\d{4} \d{2}:\d{2}:\d{2}): ([A-Z0-9]+): Data found for JOB = (\d+), OrdNumbH = \d+, OrdNumb = (\d+)/';
    $devPatIP  = '/^(\d{2}\.\d{2}\.\d{4} \d{2}:\d{2}:\d{2}): (\d+\.\d+\.\d+\.\d+): Device: (\w+) - DeviceUser: (\w*) - Status: (\w+) - devMode: (\w+) - prodType: (\w+) - Model \+ MID: (\w+)/';
    $devPatDev = '/^(\d{2}\.\d{2}\.\d{4} \d{2}:\d{2}:\d{2}): ([A-Z0-9]+): Device: (\w+) - DeviceUser: (\w*) - Status: (\w+) - devMode: (\w+) - prodType: (\w+) - Model \+ MID: (\w+)/';

    // Nuevos patrones
    $cmd1024Pat = '/^(\d{2}\.\d{2}\.\d{4} \d{2}:\d{2}:\d{2}): (\d+\.\d+\.\d+\.\d+): Command: 1024/';
    $tsPfx      = '/^\d{2}\.\d{2}\.\d{4} \d{2}:\d{2}:\d{2}: /';

    // Tracking de contexto IP→job (para step tracking)
    $ipJobCtx = [];
    $stepSeen = [];

    // Metadatos de lentes por job
    $lensData = [];

    // Errores por dispositivo
    $deviceErrors = [];

    // Estado de máquina de continuación
    $cmdCtxType = null;
    $cmdCtxIP   = null;
    $cmdCtxTS   = null;
    $cmdCtxDate = null;

    $ipToDevice = [];
    $logDate    = '';

    foreach (array_slice($lines, 0, 10) as $l) {
        if (preg_match('/Log start (\d{2}\.\d{2}\.\d{4})/', $l, $m)) {
            $logDate = $m[1];
        }
    }

    // Pasada previa: construir mapa IP→device completo
    foreach ($lines as $line) {
        if (preg_match($devPatIP, $line, $m)) {
            $ipToDevice[$m[2]] = $m[3];
        }
    }

    // Pasada principal
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '*') continue;

        // Líneas de continuación (sin timestamp)
        if (!preg_match($tsPfx, $line)) {
            if ($cmdCtxType === '1024' && $cmdCtxIP !== null) {
                $devId = $ipToDevice[$cmdCtxIP] ?? null;
                if ($devId) {
                    $d       = $cmdCtxDate ?? '';
                    $inRange = (!$from || $d >= $from) && (!$to || $d <= $to);
                    if ($inRange) {
                        if (!isset($deviceErrors[$devId])) {
                            $deviceErrors[$devId] = [
                                'B601' => 0, 'B701' => 0, 'waitingTrays' => 0,
                                'firstTs' => $cmdCtxTS, 'lastTs' => $cmdCtxTS,
                            ];
                        }
                        if (str_starts_with($line, 'XSTATUS=Waiting for tray')) {
                            $deviceErrors[$devId]['waitingTrays']++;
                            $deviceErrors[$devId]['lastTs'] = $cmdCtxTS;
                        } elseif (str_starts_with($line, 'XSTATUS=B;601;')) {
                            $deviceErrors[$devId]['B601']++;
                            $deviceErrors[$devId]['lastTs'] = $cmdCtxTS;
                        } elseif (str_starts_with($line, 'XSTATUS=B;701;')) {
                            $deviceErrors[$devId]['B701']++;
                            $deviceErrors[$devId]['lastTs'] = $cmdCtxTS;
                        }
                    }
                }
            }
            continue;
        }

        // Línea con timestamp: resetear contexto de continuación
        $cmdCtxType = null;

        // Detectar Command:1024
        if (str_contains($line, 'Command: 1024') && preg_match($cmd1024Pat, $line, $m)) {
            $cmdCtxType = '1024';
            $cmdCtxIP   = $m[2];
            $cmdCtxTS   = $m[1];
            $cmdCtxDate = tsToDate($m[1]);
            continue;
        }

        // Timeout counter
        if (str_contains($line, 'Timeout after')) {
            $timeouts++;
            continue;
        }

        // Device status
        $dm = null;
        if (preg_match($devPatIP, $line, $m)) {
            $dm = ['ts'=>$m[1],'id'=>$m[2],'dev'=>$m[3],'user'=>$m[4],
                   'status'=>$m[5],'mode'=>$m[6],'ptype'=>$m[7],'model'=>$m[8],'byip'=>true];
        } elseif (preg_match($devPatDev, $line, $m)) {
            $dm = ['ts'=>$m[1],'id'=>$m[2],'dev'=>$m[3],'user'=>$m[4],
                   'status'=>$m[5],'mode'=>$m[6],'ptype'=>$m[7],'model'=>$m[8],'byip'=>false];
        }
        if ($dm) {
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

            // Añadir step al job si hay contexto IP activo
            if ($dm['byip'] && isset($SURF_PIPELINE[$dm['status']])) {
                $ip = $dm['id'];
                if (isset($ipJobCtx[$ip])) {
                    $ctx      = $ipJobCtx[$ip];
                    $jobId    = $ctx['job'];
                    $devId    = $dm['dev'];
                    $stepType = $dm['status'];
                    $minute   = $ctx['minute'];
                    $seenKey  = $jobId . '|' . $devId . '|' . $stepType . '|' . $minute;
                    if (!isset($stepSeen[$seenKey]) && isset($jobs[$jobId])) {
                        $stepSeen[$seenKey] = true;
                        $jobs[$jobId]['steps'][] = [
                            'ts'        => $dm['ts'],
                            'dev'       => $devId,
                            'stepType'  => $stepType,
                            'stepLabel' => $statusLabels[$stepType] ?? $stepType,
                            'prodType'  => $dm['ptype'],
                        ];
                    }
                }
            }
            continue;
        }

        // Job events
        $jm = null;
        if (preg_match($jobPatIP, $line, $m)) {
            $jm = ['ts'=>$m[1],'src'=>$m[2],'job'=>$m[3],'ord'=>$m[4],'byip'=>true];
        } elseif (preg_match($jobPatDev, $line, $m)) {
            $jm = ['ts'=>$m[1],'src'=>$m[2],'job'=>$m[3],'ord'=>$m[4],'byip'=>false];
        }
        if ($jm) {
            $d = tsToDate($jm['ts']);
            if ($from && $d && $d < $from) continue;
            if ($to   && $d && $d > $to)   continue;

            $devName = $jm['byip'] ? ($ipToDevice[$jm['src']] ?? $jm['src']) : $jm['src'];
            $job     = $jm['job'];
            if (!isset($jobs[$job])) {
                $jobs[$job] = [
                    'job'        => $job,
                    'order'      => $jm['ord'],
                    'firstSeen'  => $jm['ts'],
                    'lastSeen'   => $jm['ts'],
                    'devices'    => [],
                    'events'     => [],
                    'steps'      => [],
                    'stepCounts' => [],
                    'complete'   => false,
                    'reproceso'  => false,
                    'reachedStep'=> null,
                    'lens'       => null,
                ];
            }
            if ($jm['ts'] > $jobs[$job]['lastSeen']) $jobs[$job]['lastSeen'] = $jm['ts'];
            if (!in_array($devName, $jobs[$job]['devices'], true))
                $jobs[$job]['devices'][] = $devName;
            $jobs[$job]['events'][] = ['ts' => $jm['ts'], 'dev' => $devName];

            if ($jm['byip']) {
                $ipJobCtx[$jm['src']] = [
                    'job'    => $job,
                    'minute' => substr($jm['ts'], 0, 16),
                ];
            }
            continue;
        }

        // Línea Data: con metadatos VCA
        if (str_contains($line, ': Data: ') && str_contains($line, 'JOB=') && str_contains($line, 'LNAM=')) {
            if (preg_match('/^(\d{2}\.\d{2}\.\d{4} \d{2}:\d{2}:\d{2}): (\d+\.\d+\.\d+\.\d+): Data: /', $line)) {
                if (preg_match('/JOB=(\d+)/', $line, $jm2)) {
                    $jobId = $jm2[1];
                    if (!isset($lensData[$jobId])) {
                        $lensName = null; $lensType = null; $lensMfr = null;
                        if (preg_match('/LNAM=([^;<\x0d\r\n]+)/', $line, $lm)) $lensName = explode(';', $lm[1])[0];
                        if (preg_match('/LTYP=([^;<\x0d\r\n]+)/', $line, $lm)) $lensType = explode(';', $lm[1])[0];
                        if (preg_match('/LMFR=([^;<\x0d\r\n]+)/', $line, $lm)) $lensMfr  = explode(';', $lm[1])[0];
                        if ($lensName || $lensType || $lensMfr) {
                            $lensData[$jobId] = ['name' => $lensName, 'type' => $lensType, 'mfr' => $lensMfr];
                        }
                    }
                }
            }
        }
    }

    // Post-procesado
    foreach ($jobs as $jobId => &$job) {
        if (isset($lensData[$jobId])) {
            $job['lens'] = $lensData[$jobId];
        }
        if (!empty($job['steps'])) {
            usort($job['steps'], fn($a, $b) => strcmp($a['ts'], $b['ts']));
            $stepTypes        = array_column($job['steps'], 'stepType');
            $job['stepCounts']= array_count_values($stepTypes);
            $job['complete']  = in_array('SENG', $stepTypes);
            $job['reproceso'] = max($job['stepCounts']) > 1;
            $maxStep = 0;
            $SURF_PIPELINE2   = ['SBLK' => 1, 'SGEN' => 2, 'SPOL' => 3, 'SENG' => 4];
            foreach ($stepTypes as $st) {
                if (isset($SURF_PIPELINE2[$st]) && $SURF_PIPELINE2[$st] > $maxStep) $maxStep = $SURF_PIPELINE2[$st];
            }
            $job['reachedStep'] = $maxStep > 0 ? array_search($maxStep, $SURF_PIPELINE2) : null;
        }
    }
    unset($job);

    // Vincular jobs a devices
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
        'deviceErrors' => $deviceErrors,
    ];
}

// ─── UTILIDADES DE ZIP ───────────────────────────────────────────────────────

function findMonthlyZips(string $dir): array {
    $result = ['current' => null, 'historical' => []];
    if (!is_dir($dir)) return $result;

    $currentMonth = date('Ym');

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
        if (!preg_match('/^\d{6}$/', $name)) continue;

        if ($name === $currentMonth) {
            if ($result['current'] === null) $result['current'] = $zipPath;
        } else {
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

function parseZip(string $zipPath, array $alreadyParsed, string $label, string $zipTag,
                  ?string $from, ?string $to): array {
    $results    = [];
    $sourceInfo = ['zip' => $zipTag, 'files' => [], 'jobs' => 0, 'devices' => 0, 'skipped' => [], 'error' => null];

    if (!class_exists('ZipArchive')) {
        $sourceInfo['error'] = 'ZipArchive no disponible';
        return [$results, $sourceInfo];
    }

    // Verificar tamaño del ZIP antes de procesar
    $zipSize = filesize($zipPath);
    if ($zipSize > 25 * 1024 * 1024) { // 25MB
        $sourceInfo['error'] = 'ZIP demasiado grande (' . round($zipSize/1024/1024, 1) . 'MB)';
        return [$results, $sourceInfo];
    }

    $zip = new ZipArchive();
    $opened = $zip->open($zipPath);

    if ($opened !== true) {
        $sourceInfo['error'] = "No se pudo abrir ZIP: código $opened";
        return [$results, $sourceInfo];
    }

    // Limitar archivos a procesar por ZIP (evita timeout)
    $maxFilesPerZip = 35;
    $processed = 0;

    for ($i = 0; $i < $zip->numFiles && $processed < $maxFilesPerZip; $i++) {
        $entryName = $zip->getNameIndex($i);
        if (strtolower(pathinfo($entryName, PATHINFO_EXTENSION)) !== 'log') continue;

        $basename = basename($entryName);

        if (in_array($basename, $alreadyParsed, true)) {
            $sourceInfo['skipped'][] = $basename;
            continue;
        }

        if (!fileCouldMatchRange($basename, $from, $to)) {
            $sourceInfo['skipped'][] = $basename . ' (fuera de rango)';
            continue;
        }

        // Intentar leer con supresión de errores
        $content = @$zip->getFromIndex($i);
        if ($content === false) {
            $sourceInfo['skipped'][] = $basename . ' (error lectura)';
            continue;
        }

        $data = parseLensLog(null, $content, $from, $to);
        if (isset($data['error'])) {
            $sourceInfo['skipped'][] = $basename . ' (parse error)';
            continue;
        }

        $sourceInfo['files'][]  = $basename . ' (zip)';
        $sourceInfo['jobs']    += $data['totalJobs'];
        $sourceInfo['devices'] += $data['totalDevices'];
        $results[] = $data;
        $processed++;
    }

    if ($processed === 0 && $zip->numFiles > 0 && empty($sourceInfo['error'])) {
        $sourceInfo['error'] = 'No se pudo procesar ningún archivo del ZIP (límite: ' . $maxFilesPerZip . ' archivos)';
    }

    $zip->close();
    return [$results, $sourceInfo];
}

// ─── FUNCIÓN PRINCIPAL ───────────────────────────────────────────────────────

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

        $looseFiles  = glob($dir . DIRECTORY_SEPARATOR . '*.log') ?: [];
        $parsedLoose = [];

        sort($looseFiles);
        foreach ($looseFiles as $logFile) {
            $basename = basename($logFile);
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

        $zips = findMonthlyZips($dir);

        $zipsToProcess = [];
        if ($zips['current'] !== null) {
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

        if (!$includeOldZips && !empty($zips['historical'])) {
            $sources[$label]['historicalZipsAvailable'] = array_map('basename', $zips['historical']);
        }
    }

    // Post-merge
    $SURF_PIPELINE = ['SBLK' => 1, 'SGEN' => 2, 'SPOL' => 3, 'SENG' => 4];

    foreach ($allJobs as &$job) {
        if (!empty($job['steps'])) {
            usort($job['steps'], fn($a, $b) => strcmp($a['ts'], $b['ts']));
            $stepTypes        = array_column($job['steps'], 'stepType');
            $job['stepCounts']= array_count_values($stepTypes);
            $job['complete']  = in_array('SENG', $stepTypes);
            $job['reproceso'] = max($job['stepCounts']) > 1;
            $maxStep = 0;
            foreach ($stepTypes as $st) {
                if (isset($SURF_PIPELINE[$st]) && $SURF_PIPELINE[$st] > $maxStep) $maxStep = $SURF_PIPELINE[$st];
            }
            $job['reachedStep'] = $maxStep > 0 ? array_search($maxStep, $SURF_PIPELINE) : null;
        }

        if (isset($job['steps'])) unset($job['steps']);
    }
    unset($job);

    // Estadísticas de proceso
    $processStats = [
        'withSteps'       => 0,
        'complete'        => 0,
        'incomplete'      => 0,
        'reprocesos'      => 0,
        'completePct'     => 0.0,
        'byStep'          => ['SBLK' => 0, 'SGEN' => 0, 'SPOL' => 0, 'SENG' => 0],
        'waitingForTrays' => 0,
        'errB601'         => 0,
        'errB701'         => 0,
    ];

    foreach ($allJobs as $job) {
        if (empty($job['stepCounts'])) continue;
        $processStats['withSteps']++;
        if ($job['complete']  ?? false) $processStats['complete']++;
        else                            $processStats['incomplete']++;
        if ($job['reproceso'] ?? false) $processStats['reprocesos']++;
        foreach (array_keys($job['stepCounts']) as $st) {
            if (isset($processStats['byStep'][$st])) $processStats['byStep'][$st]++;
        }
    }

    foreach ($allDevices as $dev) {
        $processStats['waitingForTrays'] += $dev['errors']['waitingTrays'] ?? 0;
        $processStats['errB601']         += $dev['errors']['B601']        ?? 0;
        $processStats['errB701']         += $dev['errors']['B701']        ?? 0;
    }

    if ($processStats['withSteps'] > 0) {
        $processStats['completePct'] = round(
            $processStats['complete'] / $processStats['withSteps'] * 100, 1
        );
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
        'processStats' => $processStats,
    ];
}

// ─── HELPER INTERNO ──────────────────────────────────────────────────────────

function _mergeData(array $data, string $label, string $fileTag,
                    array &$allJobs, array &$allDevices): void {

    // Merge devices
    foreach ($data['devices'] as $devKey => $dev) {
        $dev['server']  = $label;
        $dev['logFile'] = $fileTag;
        if (!isset($allDevices[$devKey])) {
            $allDevices[$devKey] = $dev;
        } else {
            $existingJobs = $allDevices[$devKey]['jobs'] ?? [];
            foreach ($dev['jobs'] as $j) {
                if (!in_array($j, $existingJobs, true)) $existingJobs[] = $j;
            }
            if ($dev['lastSeen'] > $allDevices[$devKey]['lastSeen']) {
                $keepErrors = $allDevices[$devKey]['errors'] ?? null;
                $allDevices[$devKey] = $dev;
                if ($keepErrors !== null) $allDevices[$devKey]['errors'] = $keepErrors;
            }
            $allDevices[$devKey]['jobs']     = $existingJobs;
            $allDevices[$devKey]['jobCount'] = count($existingJobs);
        }
    }

    // Merge device errors
    foreach ($data['deviceErrors'] ?? [] as $devId => $errs) {
        if (!isset($allDevices[$devId])) continue;
        if (!isset($allDevices[$devId]['errors'])) {
            $allDevices[$devId]['errors'] = [
                'B601' => 0, 'B701' => 0, 'waitingTrays' => 0,
                'firstTs' => null, 'lastTs' => null,
            ];
        }
        $allDevices[$devId]['errors']['B601']         += $errs['B601']         ?? 0;
        $allDevices[$devId]['errors']['B701']         += $errs['B701']         ?? 0;
        $allDevices[$devId]['errors']['waitingTrays'] += $errs['waitingTrays'] ?? 0;
        $lt = $errs['lastTs']  ?? null;
        $ft = $errs['firstTs'] ?? null;
        if ($lt && (!$allDevices[$devId]['errors']['lastTs']  || $lt > $allDevices[$devId]['errors']['lastTs']))  $allDevices[$devId]['errors']['lastTs']  = $lt;
        if ($ft && (!$allDevices[$devId]['errors']['firstTs'] || $ft < $allDevices[$devId]['errors']['firstTs'])) $allDevices[$devId]['errors']['firstTs'] = $ft;
    }

    // Merge jobs
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

            if (!empty($job['steps'])) {
                if (!isset($allJobs[$j]['steps'])) $allJobs[$j]['steps'] = [];
                $existingKeys = [];
                foreach ($allJobs[$j]['steps'] as $step) {
                    $existingKeys[$step['ts'] . '|' . $step['dev'] . '|' . $step['stepType']] = true;
                }
                foreach ($job['steps'] as $step) {
                    $key = $step['ts'] . '|' . $step['dev'] . '|' . $step['stepType'];
                    if (!isset($existingKeys[$key])) {
                        $allJobs[$j]['steps'][] = $step;
                        $existingKeys[$key] = true;
                    }
                }
            }

            if (!isset($allJobs[$j]['lens']) && isset($job['lens'])) {
                $allJobs[$j]['lens'] = $job['lens'];
            }

            if (!empty($job['stepCounts'])) {
                if (!isset($allJobs[$j]['stepCounts'])) $allJobs[$j]['stepCounts'] = [];
                foreach ($job['stepCounts'] as $st => $cnt) {
                    $allJobs[$j]['stepCounts'][$st] = max(
                        $allJobs[$j]['stepCounts'][$st] ?? 0, $cnt
                    );
                }
            }
        }
    }
}
?>