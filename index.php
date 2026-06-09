<?php
require_once 'config.php';
$refresh = defined('REFRESH_INTERVAL') ? REFRESH_INTERVAL : 30;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>LensWare Monitor — Panel General</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
<style>
:root {
  --bg:       #f4f6f9;
  --surface:  #ffffff;
  --card:     #ffffff;
  --border:   #e2e7ef;
  --border2:  #d0d8e8;
  --text:     #1a2236;
  --muted:    #7a8aaa;
  --muted2:   #a0adbe;
  --accent:   #2563eb;
  --accent2:  #1e40af;
  --success:  #059669;
  --warn:     #d97706;
  --danger:   #dc2626;
  --surf:     #7c3aed;
  --edge:     #ea580c;
  --mono:     'JetBrains Mono', monospace;
  --sans:     'Sora', sans-serif;
  --shadow:   0 1px 4px rgba(30,50,100,.07), 0 4px 16px rgba(30,50,100,.06);
  --shadow-lg:0 4px 24px rgba(30,50,100,.12);
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
body {
  background: var(--bg);
  color: var(--text);
  font-family: var(--sans);
  font-size: 14px;
  min-height: 100vh;
  overflow-x: hidden;
}

/* ── Layout ── */
.wrap { max-width: 1600px; margin: 0 auto; padding: 0 24px 80px; }

/* ── Header ── */
header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 20px 0 18px; border-bottom: 1px solid var(--border);
  margin-bottom: 28px; flex-wrap: wrap; gap: 12px;
}
.logo { display: flex; align-items: center; gap: 12px; }
.logo-icon {
  width: 40px; height: 40px;
  background: linear-gradient(135deg, var(--accent2), var(--accent));
  border-radius: 10px; display: flex; align-items: center;
  justify-content: center; font-size: 20px;
  box-shadow: 0 4px 12px rgba(37,99,235,.3);
}
.logo-text h1 { font-size: 17px; font-weight: 700; letter-spacing: -.3px; color: var(--text); }
.logo-text p { font-size: 10px; color: var(--muted); font-family: var(--mono); letter-spacing: .6px; margin-top: 1px; }
.hright { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
nav { display: flex; gap: 4px; }
nav a {
  color: var(--muted); text-decoration: none; font-size: 13px;
  font-weight: 500; padding: 6px 14px; border-radius: 8px;
  border: 1px solid transparent; transition: all .18s;
}
nav a:hover { background: var(--bg); border-color: var(--border2); color: var(--text); }
nav a.active { background: var(--accent); color: #fff; border-color: var(--accent); }
.live {
  display: flex; align-items: center; gap: 6px;
  background: rgba(5,150,105,.08); border: 1px solid rgba(5,150,105,.25);
  color: var(--success); padding: 4px 12px; border-radius: 100px; font-size: 11px; font-weight: 600;
}
.dot { width: 7px; height: 7px; border-radius: 50%; background: var(--success); animation: pulse 1.6s infinite; }
@keyframes pulse { 0%,100%{opacity:1;transform:scale(1)}50%{opacity:.4;transform:scale(.75)} }

/* ── Skeleton loader ── */
.skel { background: linear-gradient(90deg,#e8ecf4 25%,#f0f3f9 50%,#e8ecf4 75%); background-size: 200% 100%; animation: shimmer 1.2s infinite; border-radius: 8px; }
@keyframes shimmer { 0%{background-position:200% 0}100%{background-position:-200% 0} }

/* ── Section title ── */
.st {
  font-size: 10px; letter-spacing: 1.5px; text-transform: uppercase;
  color: var(--muted); margin-bottom: 14px; display: flex; align-items: center;
  gap: 10px; font-family: var(--mono); font-weight: 500;
}
.st::after { content: ''; flex: 1; height: 1px; background: var(--border); }

/* ── Source cards ── */
.sources { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px,1fr)); gap: 10px; margin-bottom: 24px; }
.src-card {
  border-radius: 10px; padding: 14px 16px; border: 1px solid var(--border);
  background: var(--surface); box-shadow: var(--shadow);
  display: flex; flex-direction: column; gap: 4px;
}
.src-title { font-size: 11px; letter-spacing: .8px; text-transform: uppercase; font-weight: 700; }
.src-info { font-size: 12px; color: var(--muted); }
.src-files { font-size: 10px; font-family: var(--mono); color: var(--muted2); }

/* ── KPI ── */
.kpi-row { display: grid; grid-template-columns: repeat(auto-fit,minmax(140px,1fr)); gap: 12px; margin-bottom: 28px; }
.kpi {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: 12px; padding: 18px 20px; box-shadow: var(--shadow);
  position: relative; overflow: hidden; transition: box-shadow .2s;
}
.kpi:hover { box-shadow: var(--shadow-lg); }
.kpi::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; }
.kpi-label { font-size: 10px; color: var(--muted); letter-spacing: .8px; text-transform: uppercase; margin-bottom: 8px; font-family: var(--mono); }
.kpi-value { font-size: 32px; font-weight: 700; font-family: var(--mono); line-height: 1; color: var(--accent); }
.kpi-sub { font-size: 11px; color: var(--muted2); margin-top: 5px; }

/* ── Device cards ── */
.dgrid { display: grid; grid-template-columns: repeat(auto-fill,minmax(260px,1fr)); gap: 12px; margin-bottom: 34px; }
.dcard {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: 12px; padding: 18px; box-shadow: var(--shadow);
  transition: all .2s; cursor: pointer; text-decoration: none;
  color: inherit; display: block; position: relative; overflow: hidden;
}
.dcard::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px; border-radius: 12px 0 0 12px; }
.dcard:hover { box-shadow: var(--shadow-lg); transform: translateY(-2px); border-color: var(--border2); }
.dc-head { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }
.dc-name { font-size: 14px; font-weight: 700; color: var(--text); line-height: 1.3; }
.dc-id { font-size: 10px; color: var(--muted); font-family: var(--mono); margin-top: 3px; }
.badge { font-size: 10px; font-family: var(--mono); font-weight: 600; padding: 3px 8px; border-radius: 6px; letter-spacing: .4px; white-space: nowrap; }
.dr { display: flex; justify-content: space-between; margin-bottom: 5px; align-items: baseline; }
.dr-l { font-size: 11px; color: var(--muted); }
.dr-v { font-size: 12px; font-family: var(--mono); color: var(--text); }
.dc-foot {
  margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--border);
  display: flex; align-items: center; justify-content: space-between;
}
.dc-cnt { font-size: 26px; font-weight: 700; font-family: var(--mono); }
.server-tag { font-size: 10px; font-family: var(--mono); padding: 2px 7px; border-radius: 5px; font-weight: 700; }

/* ── Table wrapper ── */
.tw { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; box-shadow: var(--shadow); }
.ttbar {
  padding: 14px 18px; border-bottom: 1px solid var(--border);
  display: flex; align-items: center; justify-content: space-between;
  flex-wrap: wrap; gap: 10px; background: var(--bg);
}
.ttbar h3 { font-size: 13px; font-weight: 600; color: var(--text); }
.search-wrap { position: relative; }
.search-wrap svg { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--muted); pointer-events: none; }
input[type=text] {
  background: var(--surface); border: 1px solid var(--border2); color: var(--text);
  border-radius: 8px; padding: 7px 12px 7px 32px; font-size: 13px;
  font-family: var(--sans); width: 220px; outline: none; transition: border-color .18s;
}
input[type=text]:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(37,99,235,.1); }
table { width: 100%; border-collapse: collapse; }
th {
  background: var(--bg); padding: 9px 14px; font-size: 10px;
  letter-spacing: 1px; text-transform: uppercase; color: var(--muted);
  font-weight: 600; text-align: left; font-family: var(--mono);
  border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 2;
}
td { padding: 10px 14px; border-bottom: 1px solid rgba(226,231,239,.6); font-size: 12px; vertical-align: middle; }
tr:last-child td { border-bottom: none; }
tr:hover td { background: rgba(37,99,235,.03); }
.mono { font-family: var(--mono); }
.tag { display: inline-block; font-size: 10px; padding: 2px 7px; border-radius: 5px; font-family: var(--mono); font-weight: 600; border: 1px solid; }
.hidden { display: none; }

/* ── Date filter bar ── */
.filter-bar {
  display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
  background: var(--surface); border: 1px solid var(--border);
  border-radius: 12px; padding: 12px 18px; margin-bottom: 22px;
  box-shadow: var(--shadow);
}
.filter-bar label { font-size: 11px; color: var(--muted); font-family: var(--mono); letter-spacing: .6px; white-space: nowrap; }
input[type=date] {
  background: var(--bg); border: 1px solid var(--border2); color: var(--text);
  border-radius: 8px; padding: 6px 10px; font-size: 13px;
  font-family: var(--mono); outline: none; transition: border-color .18s;
}
input[type=date]:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(37,99,235,.1); }
.filter-btn {
  background: var(--accent); color: #fff; border: none; border-radius: 8px;
  padding: 7px 16px; font-size: 12px; font-family: var(--sans); font-weight: 600;
  cursor: pointer; transition: background .18s; white-space: nowrap;
}
.filter-btn:hover { background: var(--accent2); }
.filter-btn.secondary {
  background: transparent; color: var(--muted); border: 1px solid var(--border2);
}
.filter-btn.secondary:hover { background: var(--bg); color: var(--text); }
.filter-sep { width: 1px; height: 22px; background: var(--border); margin: 0 4px; }
.filter-badge {
  font-size: 10px; font-family: var(--mono); font-weight: 700;
  background: rgba(37,99,235,.1); color: var(--accent); border: 1px solid rgba(37,99,235,.25);
  border-radius: 100px; padding: 2px 10px; white-space: nowrap;
}
/* ── Error / loading states ── */
.err { color: var(--danger); font-size: 12px; }
.state-loading { padding: 40px; text-align: center; color: var(--muted); font-size: 13px; }
.skel-kpi { height: 88px; margin-bottom: 12px; }
.skel-card { height: 170px; }

/* ── Footer ── */
footer {
  position: fixed; bottom: 0; left: 0; right: 0;
  background: rgba(244,246,249,.94); backdrop-filter: blur(12px);
  border-top: 1px solid var(--border); padding: 8px 24px;
  display: flex; align-items: center; justify-content: space-between;
  font-family: var(--mono); font-size: 10px; color: var(--muted); z-index: 100;
}
</style>
</head>
<body>
<div class="wrap">
<header>
  <div class="logo">
    <div class="logo-icon">🔬</div>
    <div class="logo-text">
      <h1>LensWare Monitor</h1>
      <p>SURFACING &amp; EDGING — PANEL GENERAL</p>
    </div>
  </div>
  <div class="hright">
    <nav>
      <a href="index.php" class="active">📊 General</a>
      <a href="device.php">🖥️ Por Equipo</a>
      <a href="upload.php">📂 Logs</a>
    </nav>
    <div class="live"><div class="dot"></div>EN VIVO</div>
    <span style="font-family:var(--mono);font-size:10px;color:var(--muted)">↺ <span id="ref-int"><?= $refresh ?></span>s</span>
  </div>
</header>

<!-- Barra de filtro de fechas -->
<div class="filter-bar" id="filter-bar">
  <label>DESDE</label>
  <input type="date" id="f-from" oninput="onFilterChange()">
  <label>HASTA</label>
  <input type="date" id="f-to" oninput="onFilterChange()">
  <div class="filter-sep"></div>
  <button class="filter-btn" onclick="applyServerFilter()">🔍 Consultar servidor</button>
  <button class="filter-btn secondary" onclick="clearFilter()">✕ Limpiar</button>
  <span id="filter-badge" style="display:none" class="filter-badge"></span>
</div>

<!-- Content injected by JS -->
<div id="app">
  <!-- Skeleton while loading -->
  <div class="sources" id="skel-sources">
    <div class="skel" style="height:70px"></div>
    <div class="skel" style="height:70px"></div>
  </div>
  <div class="kpi-row">
    <?php for ($i=0;$i<5;$i++): ?>
    <div class="skel skel-kpi"></div>
    <?php endfor; ?>
  </div>
  <div class="dgrid">
    <?php for ($i=0;$i<8;$i++): ?>
    <div class="skel skel-card"></div>
    <?php endfor; ?>
  </div>
  <div class="skel" style="height:300px;border-radius:12px"></div>
</div>

</div>
<footer>
  <span>🔬 LensWare Monitor v2.0</span>
  <span id="footer-time">—</span>
</footer>

<script>
const REFRESH = <?= $refresh ?>;
const STATUS_COLORS = {
  SBLK:'#d97706',SGEN:'#2563eb',SPOL:'#7c3aed',SENG:'#db2777',
  SINP:'#0891b2',STRT:'#059669',SDIS:'#4f46e5',SEDG:'#ea580c',EDGE:'#ea580c'
};
const SERVER_COLORS = {Surfacing:'#7c3aed',Edging:'#ea580c'};
const STATUS_DESC   = {SBLK:'Bloqueando',SGEN:'Generando',SPOL:'Puliendo',SENG:'Grabando',EDGE:'Biselando',SEDG:'Biselando'};

function esc(s){ const d=document.createElement('div');d.textContent=s??'—';return d.innerHTML; }
function tag(label,bg,color,border){return `<span class="tag" style="background:${bg};color:${color};border-color:${border}">${esc(label)}</span>`;}

function renderSources(sources){
  return Object.entries(sources).map(([label,src])=>{
    const col = SERVER_COLORS[label]||'#7a8aaa';
    const icon = label==='Surfacing'?'⚙️':'✂️';
    if(src.error){
      return `<div class="src-card" style="border-left:3px solid ${col}">
        <div class="src-title" style="color:${col}">${icon} ${esc(label)}</div>
        <div class="src-info err">⚠️ ${esc(src.error)}</div></div>`;
    }
    return `<div class="src-card" style="border-left:3px solid ${col}">
      <div class="src-title" style="color:${col}">${icon} ${esc(label)}</div>
      <div class="src-info">${src.devices} equipo(s) · ${src.jobs} órdenes</div>
      <div class="src-files">📄 ${src.files.slice(0,4).join(', ')}${src.files.length>4?' …':''}</div>
    </div>`;
  }).join('');
}

function renderKPIs(data){
  const stCount={};
  Object.values(data.devices).forEach(d=>{ stCount[d.status]=(stCount[d.status]||0)+1; });
  const bySrv={Surfacing:0,Edging:0};
  Object.values(data.devices).forEach(d=>{ bySrv[d.server]=(bySrv[d.server]||0)+1; });

  let html = `
    <div class="kpi" style="--kpi-color:${SERVER_COLORS.Surfacing}">
      <div class="kpi-label">Total Órdenes</div>
      <div class="kpi-value" style="color:var(--accent)">${data.totalJobs}</div>
      <div class="kpi-sub">hoy · ${data.logDate}</div>
    </div>
    <div class="kpi">
      <div class="kpi-label">Equipos Activos</div>
      <div class="kpi-value" style="color:var(--text)">${data.totalDevices}</div>
      <div class="kpi-sub">Surfacing + Edging</div>
    </div>`;

  Object.entries(bySrv).forEach(([srv,cnt])=>{
    const col=SERVER_COLORS[srv]||'#7a8aaa';
    html+=`<div class="kpi">
      <div class="kpi-label">${esc(srv)}</div>
      <div class="kpi-value" style="color:${col}">${cnt}</div>
      <div class="kpi-sub">equipos</div>
    </div>`;
  });

  if(data.timeouts>0){
    html+=`<div class="kpi">
      <div class="kpi-label">Timeouts</div>
      <div class="kpi-value" style="color:var(--warn)">${data.timeouts}</div>
      <div class="kpi-sub">hoy</div>
    </div>`;
  }

  Object.entries(stCount).forEach(([s,cnt])=>{
    const col=STATUS_COLORS[s]||'#7a8aaa';
    html+=`<div class="kpi">
      <div class="kpi-label">${esc(s)}</div>
      <div class="kpi-value" style="color:${col};font-size:28px">${cnt}</div>
      <div class="kpi-sub">${esc(STATUS_DESC[s]||s)}</div>
    </div>`;
  });

  return html;
}

function renderDevices(devices){
  return Object.entries(devices).map(([devKey,dev])=>{
    const sCol = STATUS_COLORS[dev.status]||'#7a8aaa';
    const srvCol = SERVER_COLORS[dev.server]||'#7a8aaa';
    return `<a class="dcard" href="device.php?dev=${encodeURIComponent(devKey)}" style="">
      <div style="position:absolute;left:0;top:0;bottom:0;width:4px;background:${srvCol};border-radius:12px 0 0 12px"></div>
      <div style="padding-left:8px">
      <div class="dc-head">
        <div>
          <div class="dc-name">${esc(dev.deviceName||dev.device)}</div>
          <div class="dc-id">${esc(devKey)} · ${esc(dev.model)}</div>
        </div>
        ${tag(dev.status,sCol+'22',sCol,sCol+'44')}
      </div>
      <div class="dr"><span class="dr-l">Estado</span><span class="dr-v" style="color:${sCol}">${esc(dev.statusLabel)}</span></div>
      <div class="dr"><span class="dr-l">Producción</span><span class="dr-v">${esc(dev.prodLabel)}</span></div>
      <div class="dr"><span class="dr-l">Operador</span><span class="dr-v">${esc(dev.user)}</span></div>
      <div class="dr"><span class="dr-l">Último evento</span><span class="dr-v mono" style="font-size:10px;color:var(--muted)">${esc((dev.lastSeen||'').substring(11))}</span></div>
      <div class="dc-foot">
        <div>
          <div class="dc-cnt" style="color:${srvCol}">${dev.jobCount}</div>
          <div style="font-size:10px;color:var(--muted)">órdenes hoy</div>
        </div>
        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px">
          <span class="server-tag" style="background:${srvCol}18;color:${srvCol}">${esc(dev.server)}</span>
          <span style="color:var(--muted);font-size:11px">Ver detalle →</span>
        </div>
      </div>
      </div>
    </a>`;
  }).join('');
}

function renderJobs(jobs, devices){
  if(!jobs.length) return '<tr><td colspan="8" style="text-align:center;padding:24px;color:var(--muted)">Sin órdenes registradas</td></tr>';
  return jobs.map((job,i)=>{
    const srv=job.server||'?';
    const sc=SERVER_COLORS[srv]||'#7a8aaa';
    const devTags=(job.devices||[]).map(dn=>{
      const di=devices[dn];
      const dc=di?SERVER_COLORS[di.server]||'#7a8aaa':'#7a8aaa';
      return `<a href="device.php?dev=${encodeURIComponent(dn)}" style="text-decoration:none">${tag(dn,dc+'18',dc,dc+'40')}</a>`;
    }).join(' ');
    return `<tr>
      <td class="mono" style="color:var(--muted2)">${i+1}</td>
      <td>${tag(job.job,'rgba(37,99,235,.12)','#3b82f6','rgba(59,130,246,.3)')}</td>
      <td class="mono">${esc(job.order)}</td>
      <td class="mono" style="color:var(--muted);font-size:11px">${esc(job.firstSeen)}</td>
      <td class="mono" style="color:var(--muted);font-size:11px">${esc(job.lastSeen)}</td>
      <td>${devTags}</td>
      <td>${tag(srv,sc+'18',sc,sc+'40')}</td>
      <td class="mono" style="color:var(--muted)">${(job.events||[]).length}</td>
    </tr>`;
  }).join('');
}

function render(data){
  // Preserve search text and table scroll before re-render
  const prevSearch = document.getElementById('s')?.value || '';
  const tableWrap  = document.querySelector('.tw .scroll-wrap');
  const prevScroll = tableWrap ? tableWrap.scrollTop : 0;

  const app = document.getElementById('app');
  app.innerHTML = `
    <div class="sources">${renderSources(data.sources||{})}</div>
    <div class="kpi-row">${renderKPIs(data)}</div>
    <div class="st">Equipos del Sistema</div>
    <div class="dgrid">${renderDevices(data.devices||{})}</div>
    <div class="st">Historial Completo de Órdenes</div>
    <div class="tw">
      <div class="ttbar">
        <h3>${data.totalJobs} órdenes registradas hoy</h3>
        <div class="search-wrap">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
          <input type="text" id="s" placeholder="Buscar JOB / Orden…" oninput="ft()" value="${prevSearch.replace(/"/g,'&quot;')}">
        </div>
      </div>
      <div class="scroll-wrap" style="max-height:480px;overflow-y:auto">
      <table id="jt">
        <thead><tr>
          <th>#</th><th>JOB</th><th>Nº Orden</th>
          <th>Primer evento</th><th>Último evento</th>
          <th>Equipos</th><th>Servidor</th><th>Pasos</th>
        </tr></thead>
        <tbody>${renderJobs(data.jobs||[], data.devices||{})}</tbody>
      </table>
      </div>
    </div>`;

  // Restore scroll position and re-apply search filter
  const newWrap = document.querySelector('.tw .scroll-wrap');
  if(newWrap && prevScroll) newWrap.scrollTop = prevScroll;
  if(prevSearch) ft();
}

// ── Helpers de fecha ──────────────────────────────────────────────────────
function todayISO() {
  const d = new Date();
  return d.getFullYear() + '-'
    + String(d.getMonth()+1).padStart(2,'0') + '-'
    + String(d.getDate()).padStart(2,'0');
}

// ── Date filter state — por defecto = hoy ─────────────────────────────────
const TODAY    = todayISO();
let activeFrom = TODAY;
let activeTo   = TODAY;

// ── Persistencia del filtro en sessionStorage ──────────────────────────────
const FILTER_KEY = 'lw_filter';

function saveFilter(from, to) {
  sessionStorage.setItem(FILTER_KEY, JSON.stringify({ from: from||TODAY, to: to||TODAY }));
}

function loadFilter() {
  try {
    const saved = sessionStorage.getItem(FILTER_KEY);
    if (saved) {
      const { from, to } = JSON.parse(saved);
      if (from) { activeFrom = from; document.getElementById('f-from').value = from; }
      if (to)   { activeTo   = to;   document.getElementById('f-to').value   = to;   }
    } else {
      // Sin dato guardado: inicializar con hoy
      document.getElementById('f-from').value = TODAY;
      document.getElementById('f-to').value   = TODAY;
    }
    updateBadge(activeFrom, activeTo);
  } catch(e) {
    document.getElementById('f-from').value = TODAY;
    document.getElementById('f-to').value   = TODAY;
  }
}

// Construye la URL de api.php con los parámetros de fecha activos
function apiUrl() {
  const p = new URLSearchParams({ cache: Date.now() });
  if (activeFrom) p.set('from', activeFrom);
  if (activeTo)   p.set('to',   activeTo);
  return 'api.php?' + p.toString();
}

// Llamado cuando el usuario cambia los inputs de fecha (filtro rápido en memoria)
function onFilterChange() {
  const from = document.getElementById('f-from').value || null;
  const to   = document.getElementById('f-to').value   || null;
  if (!lastData) return;

  // Filtro en memoria: no llama al servidor
  const filtered = applyDateFilter(lastData, from, to);
  render(filtered);
  updateBadge(from, to);
  // Persistir las fechas (sin marcarlas como "consultadas al servidor")
  saveFilter(from, to);
}

// Filtra los datos ya cargados en memoria sin nueva petición al servidor
function applyDateFilter(data, from, to) {
  if (!from && !to) return data;

  // "dd.mm.yyyy HH:MM:SS" → "YYYY-MM-DD"
  function tsDate(ts) {
    if (!ts) return '';
    const [dt] = ts.split(' ');
    const [d, m, y] = dt.split('.');
    return `${y}-${m}-${d}`;
  }

  const jobs = (data.jobs || []).filter(job => {
    const first = tsDate(job.firstSeen);
    const last  = tsDate(job.lastSeen);
    if (from && last  < from) return false;
    if (to   && first > to)   return false;
    return true;
  }).map(job => ({
    ...job,
    events: (job.events || []).filter(ev => {
      const d = tsDate(ev.ts);
      if (from && d < from) return false;
      if (to   && d > to)   return false;
      return true;
    })
  }));

  return { ...data, jobs, totalJobs: jobs.length };
}

// "Consultar servidor": re-fetcha con el rango pedido como parámetros
async function applyServerFilter() {
  const from = document.getElementById('f-from').value || null;
  const to   = document.getElementById('f-to').value   || null;
  activeFrom = from;
  activeTo   = to;
  countdown  = REFRESH;
  saveFilter(from, to);
  await fetchData();
  updateBadge(from, to);
}

function clearFilter() {
  document.getElementById('f-from').value = TODAY;
  document.getElementById('f-to').value   = TODAY;
  activeFrom = TODAY;
  activeTo   = TODAY;
  saveFilter(TODAY, TODAY);
  updateBadge(TODAY, TODAY);
  fetchData();
}

function updateBadge(from, to) {
  const badge = document.getElementById('filter-badge');
  badge.style.display = 'inline-block';
  if (from === TODAY && to === TODAY) {
    badge.textContent = '📅 Hoy';
    return;
  }
  if (!from && !to) { badge.style.display = 'none'; return; }
  const f = from ? from.split('-').reverse().join('/') : '…';
  const t = to   ? to.split('-').reverse().join('/')   : '…';
  badge.textContent = `📅 ${f} → ${t}`;
}

// ── Fetch & countdown ──────────────────────────────────────────────────────
let countdown = REFRESH;
let lastData  = null;

async function fetchData(){
  try {
    const res = await fetch(apiUrl(), { cache: 'no-store' });
    if (!res.ok) throw new Error('HTTP '+res.status);
    const data = await res.json();
    lastData = data;
    // Aplica filtro de UI en memoria si hay algo seleccionado pero sin enviar al servidor
    const from = document.getElementById('f-from').value || null;
    const to   = document.getElementById('f-to').value   || null;
    const display = (from || to) ? applyDateFilter(data, from, to) : data;
    render(display);
    const ft = document.getElementById('footer-time');
    if(ft) ft.textContent = `● ${data.serverTime} · Próxima actualización: ${REFRESH}s`;
  } catch(e) {
    const app=document.getElementById('app');
    if(app && !lastData) app.innerHTML=`<div class="state-loading err">⚠️ Error al cargar datos: ${e.message}</div>`;
  }
}

function ft(){ // search filter — also used as global
  const el=document.getElementById('s');
  if(!el) return;
  const q=el.value.toLowerCase();
  document.querySelectorAll('#jt tbody tr').forEach(r=>r.classList.toggle('hidden',!r.textContent.toLowerCase().includes(q)));
}

// Immediate load + countdown — restaurar filtro guardado primero
loadFilter();
fetchData();
setInterval(()=>{
  countdown--;
  const ft=document.getElementById('footer-time');
  if(ft && lastData) ft.textContent=`● ${lastData.serverTime} · Próxima actualización: ${countdown}s`;
  if(countdown<=0){ countdown=REFRESH; fetchData(); }
}, 1000);
</script>
</body>
</html>