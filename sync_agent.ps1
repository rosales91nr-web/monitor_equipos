# ==============================================================================
#  LensWare Monitor — Agente de Sincronización Automática v1.1
#  Corre en Windows (misma red que 172.16.8.32) y sube los logs a Replit
# ==============================================================================
#
#  INSTALACIÓN RÁPIDA:
#  1. Edita las 2 variables de configuración de abajo ($REPLIT_URL y $API_KEY).
#  2. Clic derecho sobre este archivo → "Ejecutar con PowerShell"
#     (o programa una tarea en el Programador de tareas de Windows).
#
#  PROGRAMAR COMO TAREA AUTOMÁTICA (recomendado):
#  Abre PowerShell como Administrador y ejecuta:
#
#    $action  = New-ScheduledTaskAction -Execute "powershell.exe" `
#                 -Argument "-WindowStyle Hidden -ExecutionPolicy Bypass -File `"C:\ruta\sync_agent.ps1`""
#    $trigger = New-ScheduledTaskTrigger -RepetitionInterval (New-TimeSpan -Minutes 2) -Once -At (Get-Date)
#    Register-ScheduledTask -TaskName "LensWare Sync" -Action $action -Trigger $trigger -RunLevel Highest
#
# ==============================================================================

# ── CONFIGURACIÓN ─────────────────────────────────────────────────────────────

# URL de tu Replit (sin barra final, debe ser https://)
$REPLIT_URL  = "https://TU-REPLIT.replit.app"

# API Key — debe coincidir con SYNC_API_KEY en config.php de Replit
$API_KEY     = "c5ec4d6540940ac370da676eded10a44"

# Segundos entre cada sincronización del .log del día (60 = cada 1 minuto)
$INTERVAL    = 60

# Rutas UNC de los servidores LensWare (ajusta si cambiaron)
$SURF_PATH   = "\\172.16.8.32\Lensware\LensDeviceServer_Surfacing\Log"
$EDGE_PATH   = "\\172.16.8.32\Lensware\LensDeviceServer_Edging\Log"

# Carpeta local donde se guarda el log del agente y el estado de ZIPs
$LOG_FILE    = "$PSScriptRoot\sync_agent.log"
$STATE_FILE  = "$PSScriptRoot\sync_state.json"

# ── FIN DE CONFIGURACIÓN ──────────────────────────────────────────────────────

$ENDPOINT = "$REPLIT_URL/sync_api.php"

function Write-Log {
    param([string]$Message, [string]$Level = "INFO")
    $ts   = Get-Date -Format "dd.MM.yyyy HH:mm:ss"
    $line = "[$ts] [$Level] $Message"
    Write-Host $line
    Add-Content -Path $LOG_FILE -Value $line -Encoding UTF8
}

# ─── Estado de ZIPs ya subidos (evita re-subir el mismo ZIP cada ciclo) ───────
function Read-State {
    if (Test-Path $STATE_FILE) {
        try { return (Get-Content $STATE_FILE -Raw | ConvertFrom-Json -AsHashtable) }
        catch { return @{} }
    }
    return @{}
}

function Save-State {
    param([hashtable]$State)
    $State | ConvertTo-Json -Depth 3 | Set-Content $STATE_FILE -Encoding UTF8
}

function Upload-File {
    param(
        [string]$FilePath,
        [string]$Server
    )

    $fileName = [System.IO.Path]::GetFileName($FilePath)
    $fileSize = (Get-Item $FilePath).Length

    try {
        $boundary    = [System.Guid]::NewGuid().ToString("N")
        $contentType = "multipart/form-data; boundary=$boundary"

        $fileBytes = [System.IO.File]::ReadAllBytes($FilePath)

        $body   = [System.IO.MemoryStream]::new()
        $writer = [System.IO.StreamWriter]::new($body)

        # Campo: server
        $writer.Write("--$boundary`r`n")
        $writer.Write("Content-Disposition: form-data; name=`"server`"`r`n`r`n")
        $writer.Write("$Server`r`n")

        # Campo: logfile (binario)
        $writer.Write("--$boundary`r`n")
        $writer.Write("Content-Disposition: form-data; name=`"logfile`"; filename=`"$fileName`"`r`n")
        $writer.Write("Content-Type: application/octet-stream`r`n`r`n")
        $writer.Flush()
        $body.Write($fileBytes, 0, $fileBytes.Length)
        $writer.Write("`r`n")
        $writer.Write("--$boundary--`r`n")
        $writer.Flush()

        $bodyBytes = $body.ToArray()

        $response = Invoke-WebRequest `
            -Uri             $ENDPOINT `
            -Method          POST `
            -Body            $bodyBytes `
            -ContentType     $contentType `
            -Headers         @{ "X-Sync-Key" = $API_KEY } `
            -UseBasicParsing `
            -TimeoutSec      60

        # Extraer solo el JSON (ignorar cualquier texto antes de '{')
        $raw  = $response.Content
        $idx  = $raw.IndexOf('{')
        $json = if ($idx -ge 0) { $raw.Substring($idx) | ConvertFrom-Json } else { $null }

        if ($json -and $json.ok) {
            $kb = [math]::Round($fileSize / 1KB, 1)
            Write-Log "OK  [$Server] $fileName ($kb KB) subido correctamente"
            return $true
        } else {
            $msg = if ($json) { $json.message } else { $raw.Substring(0, [Math]::Min(200, $raw.Length)) }
            Write-Log "ERR [$Server] $fileName - $msg" "WARN"
            return $false
        }
    }
    catch {
        Write-Log "ERR [$Server] $fileName - $($_.Exception.Message)" "ERROR"
        return $false
    }
}

function Sync-Server {
    param(
        [string]$UncPath,
        [string]$ServerName,
        [hashtable]$State
    )

    if (-not (Test-Path $UncPath)) {
        Write-Log "Ruta no accesible: $UncPath" "WARN"
        return
    }

    $today = Get-Date -Format "yyyyMMdd"
    $month = Get-Date -Format "yyyyMM"

    # ── Archivo .log del dia (siempre se sube: crece con cada trabajo) ─────────
    $logFile = Join-Path $UncPath "$today.log"
    if (Test-Path $logFile) {
        Upload-File -FilePath $logFile -Server $ServerName | Out-Null
    } else {
        Write-Log "Sin log de hoy en $ServerName ($today.log)"
    }

    # ── ZIP del mes actual: solo si el archivo cambio desde la ultima subida ───
    $zipFile = Join-Path $UncPath "$month.zip"
    if (Test-Path $zipFile) {
        $zipMtime = (Get-Item $zipFile).LastWriteTimeUtc.ToString("o")
        $stateKey = "${ServerName}_${month}"

        if ($State[$stateKey] -ne $zipMtime) {
            Write-Log "ZIP modificado, subiendo $month.zip para $ServerName..."
            if (Upload-File -FilePath $zipFile -Server $ServerName) {
                $State[$stateKey] = $zipMtime
            }
        } else {
            Write-Log "ZIP $month.zip sin cambios, omitiendo ($ServerName)"
        }
    }
}

# ── BUCLE PRINCIPAL ───────────────────────────────────────────────────────────

Write-Log "========================================================"
Write-Log "LensWare Sync Agent v1.1 iniciado"
Write-Log "Endpoint : $ENDPOINT"
Write-Log "Intervalo: $INTERVAL segundos"
Write-Log "========================================================"

$state = Read-State

while ($true) {
    Write-Log "--- Ciclo de sincronizacion ---"

    Sync-Server -UncPath $SURF_PATH -ServerName "Surfacing" -State $state
    Sync-Server -UncPath $EDGE_PATH -ServerName "Edging"    -State $state

    Save-State -State $state

    Write-Log "Proxima sincronizacion en $INTERVAL segundos"
    Start-Sleep -Seconds $INTERVAL
}
