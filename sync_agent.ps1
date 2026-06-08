# ==============================================================================
#  LensWare Monitor — Agente de Sincronización Automática v1.0
#  Corre en Windows (misma red que 172.16.8.32) y sube los logs a Replit
# ==============================================================================
#
#  INSTALACIÓN RÁPIDA:
#  1. Edita las 3 variables de configuración de abajo.
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

# URL de tu Replit (sin barra final)
$REPLIT_URL  = "https://TU-REPLIT.replit.app"

# API Key — debe coincidir con SYNC_API_KEY en config.php de Replit
$API_KEY     = "c5ec4d6540940ac370da676eded10a44"

# Segundos entre cada sincronización (60 = cada 1 minuto)
$INTERVAL    = 60

# Rutas UNC de los servidores LensWare (ajusta si cambiaron)
$SURF_PATH   = "\\172.16.8.32\Lensware\LensDeviceServer_Surfacing\Log"
$EDGE_PATH   = "\\172.16.8.32\Lensware\LensDeviceServer_Edging\Log"

# Carpeta local donde se guarda el log del agente
$LOG_FILE    = "$PSScriptRoot\sync_agent.log"

# ── FIN DE CONFIGURACIÓN ──────────────────────────────────────────────────────

$ENDPOINT = "$REPLIT_URL/sync_api.php"

function Write-Log {
    param([string]$Message, [string]$Level = "INFO")
    $ts  = Get-Date -Format "dd.MM.yyyy HH:mm:ss"
    $line = "[$ts] [$Level] $Message"
    Write-Host $line
    Add-Content -Path $LOG_FILE -Value $line -Encoding UTF8
}

function Upload-File {
    param(
        [string]$FilePath,
        [string]$Server
    )

    $fileName = [System.IO.Path]::GetFileName($FilePath)
    $fileSize = (Get-Item $FilePath).Length

    try {
        # Construir multipart/form-data manualmente
        $boundary  = [System.Guid]::NewGuid().ToString("N")
        $contentType = "multipart/form-data; boundary=$boundary"

        $fileBytes = [System.IO.File]::ReadAllBytes($FilePath)
        $encoding  = [System.Text.Encoding]::UTF8

        $body  = [System.IO.MemoryStream]::new()
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
            -Uri          $ENDPOINT `
            -Method       POST `
            -Body         $bodyBytes `
            -ContentType  $contentType `
            -Headers      @{ "X-Sync-Key" = $API_KEY } `
            -UseBasicParsing `
            -TimeoutSec   30

        $json = $response.Content | ConvertFrom-Json

        if ($json.ok) {
            Write-Log "OK  [$Server] $fileName ($([math]::Round($fileSize/1KB, 1)) KB) → subido correctamente"
            return $true
        } else {
            Write-Log "ERR [$Server] $fileName → $($json.message)" "WARN"
            return $false
        }
    }
    catch {
        Write-Log "ERR [$Server] $fileName → $($_.Exception.Message)" "ERROR"
        return $false
    }
}

function Sync-Server {
    param(
        [string]$UncPath,
        [string]$ServerName
    )

    if (-not (Test-Path $UncPath)) {
        Write-Log "Ruta no accesible: $UncPath" "WARN"
        return
    }

    $today    = Get-Date -Format "yyyyMMdd"
    $month    = Get-Date -Format "yyyyMM"
    $uploaded = 0

    # ── Archivo .log del día ──────────────────────────────────────────────────
    $logFile = Join-Path $UncPath "$today.log"
    if (Test-Path $logFile) {
        if (Upload-File -FilePath $logFile -Server $ServerName) {
            $uploaded++
        }
    } else {
        Write-Log "Sin log de hoy en $ServerName ($today.log)" "INFO"
    }

    # ── ZIP del mes actual (si existe) ────────────────────────────────────────
    $zipFile = Join-Path $UncPath "$month.zip"
    if (Test-Path $zipFile) {
        if (Upload-File -FilePath $zipFile -Server $ServerName) {
            $uploaded++
        }
    }

    # ── ZIPs históricos nuevos (últimos 3 meses, para historial inicial) ──────
    $cutoff = (Get-Date).AddMonths(-3)
    Get-ChildItem -Path $UncPath -Filter "*.zip" -ErrorAction SilentlyContinue |
        Where-Object { $_.Name -match '^\d{6}\.zip$' -and $_.Name -ne "$month.zip" } |
        Where-Object { $_.LastWriteTime -gt $cutoff } |
        ForEach-Object {
            if (Upload-File -FilePath $_.FullName -Server $ServerName) {
                $uploaded++
            }
        }
}

# ── BUCLE PRINCIPAL ───────────────────────────────────────────────────────────

Write-Log "========================================================"
Write-Log "LensWare Sync Agent iniciado"
Write-Log "Endpoint : $ENDPOINT"
Write-Log "Intervalo: $INTERVAL segundos"
Write-Log "========================================================"

while ($true) {
    Write-Log "--- Ciclo de sincronización ---"

    Sync-Server -UncPath $SURF_PATH -ServerName "Surfacing"
    Sync-Server -UncPath $EDGE_PATH -ServerName "Edging"

    Write-Log "Próxima sincronización en $INTERVAL segundos"
    Start-Sleep -Seconds $INTERVAL
}
