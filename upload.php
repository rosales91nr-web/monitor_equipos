<?php
require_once 'config.php';

$surfDir = defined('LOG_DIR_SURFACING') ? LOG_DIR_SURFACING : __DIR__ . '/logs/surfacing';
$edgDir  = defined('LOG_DIR_EDGING')   ? LOG_DIR_EDGING    : __DIR__ . '/logs/edging';

$msg     = '';
$msgType = '';

// ─── UPLOAD ──────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['logfile'])) {
    $server  = $_POST['server'] ?? '';
    $destDir = $server === 'Surfacing' ? $surfDir : ($server === 'Edging' ? $edgDir : '');

    if (!$destDir) {
        $msg = 'Selecciona un servidor (Surfacing o Edging).';
        $msgType = 'err';
    } elseif ($_FILES['logfile']['error'] !== UPLOAD_ERR_OK) {
        $codes = [1=>'Archivo muy grande (php.ini)',2=>'Archivo muy grande (form)',3=>'Carga incompleta',4=>'No se seleccionó archivo',6=>'Sin carpeta temporal',7=>'Error de escritura',8=>'Bloqueado por extensión'];
        $msg = 'Error de carga: ' . ($codes[$_FILES['logfile']['error']] ?? 'Código '.$_FILES['logfile']['error']);
        $msgType = 'err';
    } else {
        $origName = basename($_FILES['logfile']['name']);
        $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        if (!in_array($ext, ['log', 'zip'], true)) {
            $msg = 'Solo se permiten archivos .log y .zip.';
            $msgType = 'err';
        } else {
            if (!is_dir($destDir)) {
                mkdir($destDir, 0755, true);
            }
            $dest = $destDir . DIRECTORY_SEPARATOR . $origName;
            if (move_uploaded_file($_FILES['logfile']['tmp_name'], $dest)) {
                $msg = "✓ «$origName» subido correctamente a $server.";
                $msgType = 'ok';
            } else {
                $msg = 'No se pudo mover el archivo. Verifica permisos de escritura.';
                $msgType = 'err';
            }
        }
    }
}

// ─── DELETE ──────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_file'], $_POST['delete_server'])) {
    $server  = $_POST['delete_server'];
    $delDir  = $server === 'Surfacing' ? $surfDir : ($server === 'Edging' ? $edgDir : '');
    $fname   = basename($_POST['delete_file']);
    $ext     = strtolower(pathinfo($fname, PATHINFO_EXTENSION));

    if ($delDir && in_array($ext, ['log', 'zip'], true)) {
        $path = $delDir . DIRECTORY_SEPARATOR . $fname;
        if (file_exists($path) && unlink($path)) {
            $msg = "🗑️ «$fname» eliminado de $server.";
            $msgType = 'ok';
        } else {
            $msg = 'No se pudo eliminar el archivo.';
            $msgType = 'err';
        }
    }
}

// ─── LISTAR ARCHIVOS ─────────────────────────────────────────────────────────
function listFiles(string $dir): array {
    if (!is_dir($dir)) return [];
    $files = array_merge(
        glob($dir . DIRECTORY_SEPARATOR . '*.log') ?: [],
        glob($dir . DIRECTORY_SEPARATOR . '*.zip') ?: []
    );
    usort($files, fn($a, $b) => strcmp(basename($b), basename($a)));
    return $files;
}

$surfFiles = listFiles($surfDir);
$edgFiles  = listFiles($edgDir);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>LensWare Monitor — Cargar Logs</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
<style>
:root {
  --bg:#f4f6f9; --surface:#fff; --border:#e2e7ef; --border2:#d0d8e8;
  --text:#1a2236; --muted:#7a8aaa; --muted2:#a0adbe;
  --accent:#2563eb; --accent2:#1e40af;
  --success:#059669; --warn:#d97706; --danger:#dc2626;
  --surf:#7c3aed; --edge:#ea580c;
  --mono:'JetBrains Mono',monospace; --sans:'Sora',sans-serif;
  --shadow:0 1px 4px rgba(30,50,100,.07),0 4px 16px rgba(30,50,100,.06);
  --shadow-lg:0 4px 24px rgba(30,50,100,.12);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{background:var(--bg);color:var(--text);font-family:var(--sans);font-size:14px;min-height:100vh;overflow-x:hidden}
.wrap{max-width:1200px;margin:0 auto;padding:0 24px 80px}

header{display:flex;align-items:center;justify-content:space-between;padding:20px 0 18px;border-bottom:1px solid var(--border);margin-bottom:28px;flex-wrap:wrap;gap:12px}
.logo{display:flex;align-items:center;gap:12px}
.logo-icon{width:40px;height:40px;background:linear-gradient(135deg,var(--accent2),var(--accent));border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px;box-shadow:0 4px 12px rgba(37,99,235,.3)}
.logo-text h1{font-size:17px;font-weight:700;letter-spacing:-.3px;color:var(--text)}
.logo-text p{font-size:10px;color:var(--muted);font-family:var(--mono);letter-spacing:.6px;margin-top:1px}
.hright{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
nav{display:flex;gap:4px}
nav a{color:var(--muted);text-decoration:none;font-size:13px;font-weight:500;padding:6px 14px;border-radius:8px;border:1px solid transparent;transition:all .18s}
nav a:hover{background:var(--bg);border-color:var(--border2);color:var(--text)}
nav a.active{background:var(--accent);color:#fff;border-color:var(--accent)}

.st{font-size:10px;letter-spacing:1.5px;text-transform:uppercase;color:var(--muted);margin-bottom:16px;display:flex;align-items:center;gap:10px;font-family:var(--mono);font-weight:500}
.st::after{content:'';flex:1;height:1px;background:var(--border)}

/* ── Mensaje flash ── */
.flash{padding:12px 18px;border-radius:10px;font-size:13px;font-weight:500;margin-bottom:24px;display:flex;align-items:center;gap:10px;border:1px solid}
.flash.ok{background:rgba(5,150,105,.08);border-color:rgba(5,150,105,.25);color:var(--success)}
.flash.err{background:rgba(220,38,38,.07);border-color:rgba(220,38,38,.2);color:var(--danger)}

/* ── Upload zone ── */
.upload-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:32px}
@media(max-width:700px){.upload-grid{grid-template-columns:1fr}}

.upload-card{background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden;box-shadow:var(--shadow)}
.uc-header{padding:16px 20px;display:flex;align-items:center;gap:10px;border-bottom:1px solid var(--border)}
.uc-icon{font-size:18px}
.uc-title{font-size:14px;font-weight:700}
.uc-subtitle{font-size:11px;color:var(--muted);font-family:var(--mono);margin-top:2px}
.uc-body{padding:20px}

.drop-zone{
  border:2px dashed var(--border2);border-radius:10px;
  padding:32px 20px;text-align:center;cursor:pointer;
  transition:all .2s;background:var(--bg);margin-bottom:16px;
  position:relative;
}
.drop-zone:hover,.drop-zone.drag-over{border-color:var(--accent);background:rgba(37,99,235,.04)}
.drop-zone input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
.drop-icon{font-size:32px;margin-bottom:10px}
.drop-text{font-size:13px;color:var(--muted);line-height:1.5}
.drop-text strong{color:var(--text)}
.drop-hint{font-size:11px;color:var(--muted2);font-family:var(--mono);margin-top:6px}
.drop-selected{font-size:12px;color:var(--accent);font-weight:600;margin-top:8px;font-family:var(--mono);display:none}

.upload-btn{
  width:100%;padding:10px;border:none;border-radius:9px;
  font-size:13px;font-family:var(--sans);font-weight:600;
  cursor:pointer;transition:all .18s;
}
.upload-btn.surf{background:var(--surf);color:#fff}
.upload-btn.surf:hover{background:#6d28d9}
.upload-btn.edge{background:var(--edge);color:#fff}
.upload-btn.edge:hover{background:#c2410c}
.upload-btn:disabled{opacity:.5;cursor:not-allowed}

/* ── Progress bar ── */
.progress-wrap{display:none;margin-top:12px}
.progress-bar{height:6px;border-radius:3px;background:var(--border);overflow:hidden}
.progress-fill{height:100%;border-radius:3px;width:0%;transition:width .3s}
.progress-fill.surf{background:var(--surf)}
.progress-fill.edge{background:var(--edge)}
.progress-label{font-size:11px;color:var(--muted);font-family:var(--mono);margin-top:6px}

/* ── Rutas configuradas ── */
.path-info{font-size:11px;color:var(--muted2);font-family:var(--mono);background:var(--bg);border:1px solid var(--border);border-radius:7px;padding:8px 12px;margin-top:12px;word-break:break-all}

/* ── File list ── */
.files-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}
@media(max-width:700px){.files-grid{grid-template-columns:1fr}}

.file-panel{background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden;box-shadow:var(--shadow)}
.fp-header{padding:14px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
.fp-title{font-size:13px;font-weight:700;display:flex;align-items:center;gap:8px}
.fp-count{font-size:10px;font-family:var(--mono);font-weight:600;padding:2px 8px;border-radius:100px}
.fp-count.surf{background:rgba(124,58,237,.12);color:var(--surf)}
.fp-count.edge{background:rgba(234,88,12,.12);color:var(--edge)}

.file-list{padding:8px 0}
.file-item{display:flex;align-items:center;justify-content:space-between;padding:9px 18px;transition:background .15s}
.file-item:hover{background:rgba(37,99,235,.03)}
.fi-left{display:flex;align-items:center;gap:10px;min-width:0}
.fi-icon{font-size:14px;flex-shrink:0}
.fi-name{font-family:var(--mono);font-size:12px;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.fi-size{font-size:10px;color:var(--muted2);font-family:var(--mono)}
.fi-del{background:none;border:1px solid var(--border);border-radius:6px;padding:3px 8px;color:var(--muted);font-size:11px;cursor:pointer;transition:all .18s;white-space:nowrap;flex-shrink:0}
.fi-del:hover{background:rgba(220,38,38,.07);border-color:rgba(220,38,38,.3);color:var(--danger)}
.empty-msg{padding:24px 20px;text-align:center;color:var(--muted);font-size:12px}

/* ── Instrucciones ── */
.info-box{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:20px 24px;margin-bottom:24px;box-shadow:var(--shadow)}
.info-box h3{font-size:13px;font-weight:700;margin-bottom:12px;display:flex;align-items:center;gap:8px}
.info-box ul{list-style:none;display:flex;flex-direction:column;gap:7px}
.info-box li{font-size:12px;color:var(--muted);display:flex;align-items:flex-start;gap:8px;line-height:1.5}
.info-box li::before{content:'→';color:var(--accent);font-family:var(--mono);flex-shrink:0;margin-top:1px}
.info-box code{font-family:var(--mono);background:var(--bg);border:1px solid var(--border);border-radius:4px;padding:1px 5px;font-size:11px;color:var(--text)}

footer{position:fixed;bottom:0;left:0;right:0;background:rgba(244,246,249,.94);backdrop-filter:blur(12px);border-top:1px solid var(--border);padding:8px 24px;display:flex;align-items:center;justify-content:space-between;font-family:var(--mono);font-size:10px;color:var(--muted);z-index:100}
</style>
</head>
<body>
<div class="wrap">
<header>
  <div class="logo">
    <div class="logo-icon">🔬</div>
    <div class="logo-text">
      <h1>LensWare Monitor</h1>
      <p>SURFACING &amp; EDGING — CARGAR LOGS</p>
    </div>
  </div>
  <div class="hright">
    <nav>
      <a href="index.php">📊 General</a>
      <a href="device.php">🖥️ Por Equipo</a>
      <a href="upload.php" class="active">📂 Logs</a>
    </nav>
  </div>
</header>

<?php if ($msg): ?>
<div class="flash <?= $msgType ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="st">Cómo cargar tus logs</div>
<div class="info-box">
  <h3>📋 Instrucciones</h3>
  <ul>
    <li>Los archivos de log del servidor <strong>Surfacing</strong> van en el panel izquierdo; los de <strong>Edging</strong> en el panel derecho.</li>
    <li>Formatos aceptados: <code>.log</code> (archivo diario, ej. <code>20260608.log</code>) y <code>.zip</code> (archivo mensual, ej. <code>202606.zip</code>).</li>
    <li>El monitor detecta los archivos automáticamente — tras subirlos el dashboard se actualiza en el siguiente ciclo de refresco.</li>
    <li>Sube el <code>.log</code> del día actual para ver el estado en vivo. Sube <code>.zip</code> mensuales para tener historial disponible en el filtro de fechas.</li>
    <li>Si subes un archivo con el mismo nombre que uno existente, se sobreescribe automáticamente.</li>
  </ul>
</div>

<div class="st">Subir archivos</div>
<div class="upload-grid">

  <!-- SURFACING -->
  <div class="upload-card">
    <div class="uc-header" style="border-left:4px solid var(--surf)">
      <div class="uc-icon">⚙️</div>
      <div>
        <div class="uc-title" style="color:var(--surf)">Surfacing</div>
        <div class="uc-subtitle">CCU004 · CCU003 · CCP002 · CCP004 · CCL004 · HSE001 · HSE003 · HSS004</div>
      </div>
    </div>
    <div class="uc-body">
      <form method="POST" enctype="multipart/form-data" id="form-surf">
        <input type="hidden" name="server" value="Surfacing">
        <div class="drop-zone" id="dz-surf">
          <input type="file" name="logfile" accept=".log,.zip" id="file-surf" onchange="onFile('surf',this)">
          <div class="drop-icon">📄</div>
          <div class="drop-text"><strong>Haz clic o arrastra</strong> un archivo aquí</div>
          <div class="drop-hint">Acepta: .log · .zip</div>
          <div class="drop-selected" id="sel-surf"></div>
        </div>
        <button type="submit" class="upload-btn surf" id="btn-surf" disabled>⬆ Subir a Surfacing</button>
        <div class="progress-wrap" id="prog-surf">
          <div class="progress-bar"><div class="progress-fill surf" id="fill-surf"></div></div>
          <div class="progress-label" id="plbl-surf">Subiendo…</div>
        </div>
      </form>
      <div class="path-info">📁 <?= htmlspecialchars($surfDir) ?></div>
    </div>
  </div>

  <!-- EDGING -->
  <div class="upload-card">
    <div class="uc-header" style="border-left:4px solid var(--edge)">
      <div class="uc-icon">✂️</div>
      <div>
        <div class="uc-title" style="color:var(--edge)">Edging</div>
        <div class="uc-subtitle">ESF001 · ESF002 · ESF004 · 4RA001</div>
      </div>
    </div>
    <div class="uc-body">
      <form method="POST" enctype="multipart/form-data" id="form-edge">
        <input type="hidden" name="server" value="Edging">
        <div class="drop-zone" id="dz-edge">
          <input type="file" name="logfile" accept=".log,.zip" id="file-edge" onchange="onFile('edge',this)">
          <div class="drop-icon">📄</div>
          <div class="drop-text"><strong>Haz clic o arrastra</strong> un archivo aquí</div>
          <div class="drop-hint">Acepta: .log · .zip</div>
          <div class="drop-selected" id="sel-edge"></div>
        </div>
        <button type="submit" class="upload-btn edge" id="btn-edge" disabled>⬆ Subir a Edging</button>
        <div class="progress-wrap" id="prog-edge">
          <div class="progress-bar"><div class="progress-fill edge" id="fill-edge"></div></div>
          <div class="progress-label" id="plbl-edge">Subiendo…</div>
        </div>
      </form>
      <div class="path-info">📁 <?= htmlspecialchars($edgDir) ?></div>
    </div>
  </div>

</div>

<div class="st">Archivos cargados</div>
<div class="files-grid">

  <!-- Lista Surfacing -->
  <div class="file-panel">
    <div class="fp-header">
      <div class="fp-title" style="color:var(--surf)">⚙️ Surfacing</div>
      <span class="fp-count surf"><?= count($surfFiles) ?> archivo<?= count($surfFiles) !== 1 ? 's' : '' ?></span>
    </div>
    <div class="file-list">
    <?php if (empty($surfFiles)): ?>
      <div class="empty-msg">Sin archivos — sube un .log o .zip para comenzar</div>
    <?php else: foreach ($surfFiles as $f):
      $name = basename($f);
      $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
      $icon = $ext === 'zip' ? '🗜️' : '📄';
      $size = filesize($f);
      $sizeStr = $size > 1048576 ? round($size/1048576,1).' MB' : round($size/1024,1).' KB';
    ?>
      <div class="file-item">
        <div class="fi-left">
          <span class="fi-icon"><?= $icon ?></span>
          <div>
            <div class="fi-name" title="<?= htmlspecialchars($name) ?>"><?= htmlspecialchars($name) ?></div>
            <div class="fi-size"><?= $sizeStr ?></div>
          </div>
        </div>
        <form method="POST" style="margin:0" onsubmit="return confirm('¿Eliminar <?= htmlspecialchars(addslashes($name)) ?>?')">
          <input type="hidden" name="delete_file" value="<?= htmlspecialchars($name) ?>">
          <input type="hidden" name="delete_server" value="Surfacing">
          <button type="submit" class="fi-del">🗑 Eliminar</button>
        </form>
      </div>
    <?php endforeach; endif; ?>
    </div>
  </div>

  <!-- Lista Edging -->
  <div class="file-panel">
    <div class="fp-header">
      <div class="fp-title" style="color:var(--edge)">✂️ Edging</div>
      <span class="fp-count edge"><?= count($edgFiles) ?> archivo<?= count($edgFiles) !== 1 ? 's' : '' ?></span>
    </div>
    <div class="file-list">
    <?php if (empty($edgFiles)): ?>
      <div class="empty-msg">Sin archivos — sube un .log o .zip para comenzar</div>
    <?php else: foreach ($edgFiles as $f):
      $name = basename($f);
      $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
      $icon = $ext === 'zip' ? '🗜️' : '📄';
      $size = filesize($f);
      $sizeStr = $size > 1048576 ? round($size/1048576,1).' MB' : round($size/1024,1).' KB';
    ?>
      <div class="file-item">
        <div class="fi-left">
          <span class="fi-icon"><?= $icon ?></span>
          <div>
            <div class="fi-name" title="<?= htmlspecialchars($name) ?>"><?= htmlspecialchars($name) ?></div>
            <div class="fi-size"><?= $sizeStr ?></div>
          </div>
        </div>
        <form method="POST" style="margin:0" onsubmit="return confirm('¿Eliminar <?= htmlspecialchars(addslashes($name)) ?>?')">
          <input type="hidden" name="delete_file" value="<?= htmlspecialchars($name) ?>">
          <input type="hidden" name="delete_server" value="Edging">
          <button type="submit" class="fi-del">🗑 Eliminar</button>
        </form>
      </div>
    <?php endforeach; endif; ?>
    </div>
  </div>

</div>
</div>

<footer>
  <span>🔬 LensWare Monitor v2.0</span>
  <span>Gestión de archivos de log</span>
</footer>

<script>
function onFile(id, input) {
  const file = input.files[0];
  const sel  = document.getElementById('sel-' + id);
  const btn  = document.getElementById('btn-' + id);
  if (file) {
    const sz = file.size > 1048576 ? (file.size/1048576).toFixed(1)+' MB' : (file.size/1024).toFixed(1)+' KB';
    sel.textContent = '📎 ' + file.name + ' (' + sz + ')';
    sel.style.display = 'block';
    btn.disabled = false;
  } else {
    sel.style.display = 'none';
    btn.disabled = true;
  }
}

// Drag & drop visual
['surf','edge'].forEach(id => {
  const dz   = document.getElementById('dz-' + id);
  const form = document.getElementById('form-' + id);

  dz.addEventListener('dragover',  e => { e.preventDefault(); dz.classList.add('drag-over'); });
  dz.addEventListener('dragleave', () => dz.classList.remove('drag-over'));
  dz.addEventListener('drop',      e => { e.preventDefault(); dz.classList.remove('drag-over'); });

  // Progress bar on submit
  form.addEventListener('submit', () => {
    const btn  = document.getElementById('btn-' + id);
    const prog = document.getElementById('prog-' + id);
    const fill = document.getElementById('fill-' + id);
    const lbl  = document.getElementById('plbl-' + id);

    btn.disabled = true;
    prog.style.display = 'block';

    let pct = 0;
    const iv = setInterval(() => {
      pct = Math.min(pct + Math.random() * 18, 90);
      fill.style.width = pct + '%';
      lbl.textContent = 'Subiendo… ' + Math.round(pct) + '%';
    }, 150);

    // Dejamos que el form haga el POST normal
  });
});
</script>
</body>
</html>
