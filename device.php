<?php
require_once 'config.php';
$refresh   = defined('REFRESH_INTERVAL') ? REFRESH_INTERVAL : 30;
$filterDev = $_GET['dev'] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>LensWare Monitor — Por Equipo<?= $filterDev ? ' — '.htmlspecialchars($filterDev) : '' ?></title>
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
body{background:var(--bg);color:var(--text);font-family:var(--sans);font-size:14px;min-height:100vh;overflow-x:hidden}
.wrap{max-width:1600px;margin:0 auto;padding:0 24px 80px}

header{display:flex;align-items:center;justify-content:space-between;padding:20px 0 18px;border-bottom:1px solid var(--border);margin-bottom:28px;flex-wrap:wrap;gap:12px}
.logo{display:flex;align-items:center;gap:12px}
.logo-icon{width:40px;height:40px;background:linear-gradient(135deg,var(--accent2),var(--accent));border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px;box-shadow:0 4px 12px rgba(37,99,235,.3)}
.logo-text h1{font-size:17px;font-weight:700;letter-spacing:-.3px}
.logo-text p{font-size:10px;color:var(--muted);font-family:var(--mono);letter-spacing:.6px;margin-top:1px}
.hright{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
nav{display:flex;gap:4px}
nav a{color:var(--muted);text-decoration:none;font-size:13px;font-weight:500;padding:6px 14px;border-radius:8px;border:1px solid transparent;transition:all .18s}
nav a:hover{background:var(--bg);border-color:var(--border2);color:var(--text)}
nav a.active{background:var(--accent);color:#fff;border-color:var(--accent)}
.live{display:flex;align-items:center;gap:6px;background:rgba(5,150,105,.08);border:1px solid rgba(5,150,105,.25);color:var(--success);padding:4px 12px;border-radius:100px;font-size:11px;font-weight:600}
.dot{width:7px;height:7px;border-radius:50%;background:var(--success);animation:pulse 1.6s infinite}
@keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.4;transform:scale(.75)}}

/* Skeleton */
.skel{background:linear-gradient(90deg,#e8ecf4 25%,#f0f3f9 50%,#e8ecf4 75%);background-size:200% 100%;animation:shimmer 1.2s infinite;border-radius:8px}
@keyframes shimmer{0%{background-position:200% 0}100%{background-position:-200% 0}}

.st{font-size:10px;letter-spacing:1.5px;text-transform:uppercase;color:var(--muted);margin-bottom:14px;display:flex;align-items:center;gap:10px;font-family:var(--mono)}
.st::after{content:'';flex:1;height:1px;background:var(--border)}

/* Selector */
.sel-group{margin-bottom:8px}
.sel-label{font-size:10px;color:var(--muted);font-family:var(--mono);letter-spacing:1px;text-transform:uppercase;margin-bottom:8px;display:flex;align-items:center;gap:8px}
.sel-label::after{content:'';flex:1;height:1px;background:var(--border)}
.dsel{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px}
.dbtn{background:var(--surface);border:1px solid var(--border);border-radius:9px;padding:8px 14px;cursor:pointer;text-decoration:none;color:var(--text);display:flex;flex-direction:column;gap:3px;transition:all .18s;font-size:13px;box-shadow:var(--shadow)}
.dbtn:hover{border-color:var(--accent);box-shadow:var(--shadow-lg)}
.dbtn.act{border-color:var(--accent);background:rgba(37,99,235,.05)}
.dbtn.act .dn{color:var(--accent)}
.dn{font-family:var(--mono);font-weight:700;font-size:12px}
.did{font-size:10px;color:var(--muted);font-family:var(--mono)}
.sdot{width:6px;height:6px;border-radius:50%;display:inline-block;margin-right:4px}

/* Device header */
.dhead{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:24px;margin-bottom:24px;box-shadow:var(--shadow);position:relative;overflow:hidden}
.dhead-bar{position:absolute;top:0;left:0;right:0;height:4px;border-radius:14px 14px 0 0}
.dh-name{font-size:22px;font-weight:700;color:var(--text);margin-bottom:4px}
.dh-sub{font-size:12px;color:var(--muted);margin-bottom:20px}
.dstats{display:flex;gap:28px;flex-wrap:wrap}
.ds-item{display:flex;flex-direction:column;gap:3px}
.ds-lbl{font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.8px;font-family:var(--mono)}
.ds-val{font-size:18px;font-family:var(--mono);font-weight:700;color:var(--text)}

/* Job cards */
.jgrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:12px;margin-bottom:30px}
.jcard{background:var(--surface);border:1px solid var(--border);border-radius:11px;box-shadow:var(--shadow);overflow:hidden;transition:all .18s}
.jcard:hover{border-color:var(--border2);box-shadow:var(--shadow-lg)}
.jcard-head{display:flex;justify-content:space-between;align-items:center;padding:14px 16px;cursor:pointer;user-select:none}
.jcard-head:hover{background:rgba(37,99,235,.03)}
.jc-left{display:flex;flex-direction:column;gap:3px}
.jc-job{font-family:var(--mono);font-size:14px;font-weight:700;color:var(--accent)}
.jc-ord{font-size:11px;color:var(--muted)}
.jc-right{display:flex;flex-direction:column;align-items:flex-end;gap:4px}
.jc-steps{font-size:10px;color:var(--muted);font-family:var(--mono);background:var(--bg);padding:2px 8px;border-radius:100px;border:1px solid var(--border)}
.jc-times{font-size:10px;font-family:var(--mono);color:var(--muted2)}
.expand-icon{font-size:11px;color:var(--muted);transition:transform .2s}
.jcard.open .expand-icon{transform:rotate(180deg)}

/* Events timeline */
.events-panel{display:none;border-top:1px solid var(--border);padding:0}
.jcard.open .events-panel{display:block}
.events-header{display:flex;align-items:center;justify-content:space-between;padding:10px 16px;background:var(--bg);font-size:10px;letter-spacing:1px;text-transform:uppercase;color:var(--muted);font-family:var(--mono);font-weight:600}
.timeline{padding:12px 16px;display:flex;flex-direction:column;gap:0;max-height:320px;overflow-y:auto}
.ev-row{display:flex;align-items:flex-start;gap:12px;padding:5px 0;position:relative}
.ev-row:not(:last-child)::after{content:'';position:absolute;left:7px;top:20px;bottom:-5px;width:1px;background:var(--border)}
.ev-dot{width:15px;height:15px;border-radius:50%;background:var(--accent);border:2px solid var(--surface);box-shadow:0 0 0 2px var(--accent);flex-shrink:0;margin-top:2px}
.ev-dot.same{background:var(--muted2);box-shadow:0 0 0 2px var(--muted2)}
.ev-body{display:flex;flex-direction:column;gap:1px;flex:1}
.ev-ts{font-size:11px;font-family:var(--mono);font-weight:600;color:var(--text)}
.ev-dev{font-size:10px;font-family:var(--mono);color:var(--muted)}
.ev-delta{font-size:10px;font-family:var(--mono);color:var(--warn);margin-left:auto;align-self:center;white-space:nowrap;padding-left:8px}

/* All-devices table view */
.tw{background:var(--surface);border:1px solid var(--border);border-radius:12px;overflow:hidden;box-shadow:var(--shadow);margin-bottom:16px}
.ttbar{padding:14px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;background:var(--bg)}
.ttbar h3{font-size:13px;font-weight:600}
table{width:100%;border-collapse:collapse}
th{background:var(--bg);padding:9px 14px;font-size:10px;letter-spacing:1px;text-transform:uppercase;color:var(--muted);font-weight:600;text-align:left;font-family:var(--mono);border-bottom:1px solid var(--border);position:sticky;top:0;z-index:2}
td{padding:10px 14px;border-bottom:1px solid rgba(226,231,239,.6);font-size:12px;vertical-align:middle}
tr:last-child td{border-bottom:none}
tr:hover td{background:rgba(37,99,235,.03)}
.mono{font-family:var(--mono)}
.tag{display:inline-block;font-size:10px;padding:2px 7px;border-radius:5px;font-family:var(--mono);font-weight:600;border:1px solid}

/* Inline events in table */
.ev-inline{display:none;background:var(--bg)}
.ev-inline td{padding:0}
.ev-inline-inner{padding:12px 20px;display:flex;flex-direction:column;gap:4px;border-top:1px solid var(--border)}
.ev-inline-row{display:flex;align-items:center;gap:10px;font-size:11px;font-family:var(--mono);padding:3px 0}
.ev-inline-row:not(:last-child){border-bottom:1px dashed var(--border)}

/* Date filter bar */
.filter-bar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:12px 18px;margin-bottom:22px;box-shadow:var(--shadow)}
.filter-bar label{font-size:11px;color:var(--muted);font-family:var(--mono);letter-spacing:.6px;white-space:nowrap}
input[type=date]{background:var(--bg);border:1px solid var(--border2);color:var(--text);border-radius:8px;padding:6px 10px;font-size:13px;font-family:var(--mono);outline:none;transition:border-color .18s}
input[type=date]:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(37,99,235,.1)}
.filter-btn{background:var(--accent);color:#fff;border:none;border-radius:8px;padding:7px 16px;font-size:12px;font-family:var(--sans);font-weight:600;cursor:pointer;transition:background .18s;white-space:nowrap}
.filter-btn:hover{background:var(--accent2)}
.filter-btn.secondary{background:transparent;color:var(--muted);border:1px solid var(--border2)}
.filter-btn.secondary:hover{background:var(--bg);color:var(--text)}
.filter-sep{width:1px;height:22px;background:var(--border);margin:0 4px}
.filter-badge{font-size:10px;font-family:var(--mono);font-weight:700;background:rgba(37,99,235,.1);color:var(--accent);border:1px solid rgba(37,99,235,.25);border-radius:100px;padding:2px 10px;white-space:nowrap}

footer{position:fixed;bottom:0;left:0;right:0;background:rgba(244,246,249,.94);backdrop-filter:blur(12px);border-top:1px solid var(--border);padding:8px 24px;display:flex;align-items:center;justify-content:space-between;font-family:var(--mono);font-size:10px;color:var(--muted);z-index:100}
.hidden{display:none}
.search-wrap{position:relative}
.search-wrap svg{position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--muted);pointer-events:none}
input[type=text]{background:var(--surface);border:1px solid var(--border2);color:var(--text);border-radius:8px;padding:7px 12px 7px 32px;font-size:13px;font-family:var(--sans);width:220px;outline:none;transition:border-color .18s}
input[type=text]:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(37,99,235,.1)}
</style>
</head>
<body>
<div class="wrap">
<header>
  <div class="logo">
    <div class="logo-icon">🔬</div>
    <div class="logo-text">
      <h1>LensWare Monitor</h1>
      <p id="header-sub">PANEL POR EQUIPO<?= $filterDev ? ' — '.htmlspecialchars($filterDev) : '' ?></p>
    </div>
  </div>
  <div class="hright">
    <nav>
      <a href="index.php">📊 General</a>
      <a href="device.php" class="active">🖥️ Por Equipo</a>
    </nav>
    <div class="live"><div class="dot"></div>EN VIVO</div>
  </div>
</header>

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

<div id="selectors">
  <div class="skel" style="height:50px;margin-bottom:16px"></div>
  <div class="skel" style="height:50px;margin-bottom:24px"></div>
</div>
<div id="app">
  <div class="skel" style="height:160px;margin-bottom:24px;border-radius:14px"></div>
  <div class="jgrid">
    <?php for($i=0;$i<6;$i++): ?><div class="skel" style="height:130px"></div><?php endfor; ?>
  </div>
</div>
</div>

<footer>
  <span>🔬 LensWare Monitor v2.0</span>
  <span id="footer-time">—</span>
</footer>

<script>
const REFRESH    = <?= $refresh ?>;
const FILTER_DEV = <?= json_encode($filterDev) ?>;
const STATUS_COLORS = {SBLK:'#d97706',SGEN:'#2563eb',SPOL:'#7c3aed',SENG:'#db2777',SINP:'#0891b2',STRT:'#059669',SDIS:'#4f46e5',SEDG:'#ea580c',EDGE:'#ea580c'};
const SERVER_COLORS = {Surfacing:'#7c3aed',Edging:'#ea580c'};

function esc(s){const d=document.createElement('div');d.textContent=s??'—';return d.innerHTML}
function tag(label,bg,color,border){return `<span class="tag" style="background:${bg};color:${color};border-color:${border}">${esc(label)}</span>`}

/* Parse "dd.mm.yyyy HH:MM:SS" → ms timestamp */
function parseTs(ts){
  if(!ts) return 0;
  const [date,time]=ts.split(' ');
  if(!date||!time) return 0;
  const [d,mo,y]=date.split('.');
  return new Date(`${y}-${mo}-${d}T${time}`).getTime();
}
function fmtDelta(ms){
  if(ms<1000) return ms+'ms';
  if(ms<60000) return (ms/1000).toFixed(1)+'s';
  const m=Math.floor(ms/60000),s=Math.round((ms%60000)/1000);
  return s>0?`${m}m ${s}s`:`${m}m`;
}

/* Timeline for a job's events — muestra nombre completo del equipo */
function renderTimeline(events, filterDev, devMap){
  if(!events||!events.length) return '<div style="padding:12px 16px;color:var(--muted);font-size:12px">Sin eventos registrados.</div>';
  const sorted=[...events].sort((a,b)=>parseTs(a.ts)-parseTs(b.ts));
  return `<div class="timeline">`+sorted.map((ev,i)=>{
    const prev = i>0 ? sorted[i-1] : null;
    const delta = prev ? parseTs(ev.ts)-parseTs(prev.ts) : 0;
    const isSameDev = filterDev && ev.dev!==filterDev;
    const dotClass = isSameDev ? 'ev-dot same' : 'ev-dot';
    const devInfo = devMap && devMap[ev.dev];
    const devLabel = devInfo ? devInfo.deviceName||devInfo.device : ev.dev;
    const devSub   = devInfo && devInfo.deviceName ? ev.dev : '';
    return `<div class="ev-row">
      <div class="${dotClass}"></div>
      <div class="ev-body">
        <span class="ev-ts">${esc(ev.ts)}</span>
        <span class="ev-dev">📡 ${esc(devLabel)}${devSub?` <span style="opacity:.55;font-size:9px">(${esc(devSub)})</span>`:''}</span>
      </div>
      ${delta>0?`<span class="ev-delta">+${fmtDelta(delta)}</span>`:''}
    </div>`;
  }).join('')+`</div>`;
}

/* Device detail view: compact table (no cards) */
function renderDeviceTable(devKey, devJobs, devMap){
  if(!devJobs.length) return `<div style="color:var(--muted);font-family:var(--mono);font-size:13px;padding:20px 0">Sin órdenes registradas.</div>`;

  // Mini search state per table
  const tableId = 'dtbl-'+devKey;
  const searchId = 'dsrch-'+devKey;

  const rows = devJobs.map((job,ri)=>{
    const evCount=(job.events||[]).length;
    const rowId=`dev-evrow-${devKey}-${ri}`;
    // devices that participated (with names)
    const devTags=(job.devices||[]).map(dk=>{
      const di=devMap&&devMap[dk];
      const nm=di?di.deviceName||dk:dk;
      const col=di?SERVER_COLORS[di.server]||'#7a8aaa':'#7a8aaa';
      return `<span class="tag" style="background:${col}18;color:${col};border-color:${col}40">${esc(nm)}</span>`;
    }).join(' ');
    const tl=renderTimeline(job.events, devKey, devMap);
    return `<tr class="job-row" data-search="${esc(job.job)} ${esc(job.order)}" onclick="toggleEvRow('${rowId}')" style="cursor:pointer">
      <td>${tag(job.job,'rgba(37,99,235,.12)','#3b82f6','rgba(59,130,246,.3)')}</td>
      <td class="mono">${esc(job.order)}</td>
      <td class="mono" style="color:var(--muted);font-size:11px">${esc(job.firstSeen)}</td>
      <td class="mono" style="color:var(--muted);font-size:11px">${esc(job.lastSeen)}</td>
      <td>${devTags}</td>
      <td class="mono" style="color:var(--accent);font-weight:600">${evCount} <span style="font-size:10px;color:var(--muted)">▼</span></td>
    </tr>
    <tr class="ev-inline" id="${rowId}"><td colspan="6"><div class="ev-inline-inner">${tl}</div></td></tr>`;
  }).join('');

  return `<div class="tw" style="margin-bottom:0">
    <div class="ttbar">
      <h3>${devJobs.length} órdenes</h3>
      <div class="search-wrap">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        <input type="text" id="${searchId}" placeholder="Buscar JOB / Orden…" oninput="filterDevTable('${tableId}','${searchId}')" style="width:190px">
      </div>
    </div>
    <div style="max-height:55vh;overflow-y:auto">
    <table id="${tableId}">
      <thead><tr><th>JOB</th><th>Nº Orden</th><th>Primer evento</th><th>Último evento</th><th>Equipos</th><th>Eventos ▼</th></tr></thead>
      <tbody>${rows}</tbody>
    </table>
    </div>
  </div>`;
}

function filterDevTable(tableId, searchId){
  const q=document.getElementById(searchId).value.toLowerCase();
  document.querySelectorAll('#'+tableId+' .job-row').forEach(r=>{
    const match=r.dataset.search.toLowerCase().includes(q);
    r.classList.toggle('hidden',!match);
    // also hide its inline events row when filtered out
    const next=r.nextElementSibling;
    if(next&&next.classList.contains('ev-inline')&&!match) next.style.display='none';
  });
}

function toggleCard(id){
  const el=document.getElementById(id);
  if(el) el.classList.toggle('open');
}

/* Inline events row for all-devices table */
function renderInlineEvents(events, devMap){
  if(!events||!events.length) return `<div class="ev-inline-inner"><span style="color:var(--muted);font-size:11px">Sin eventos</span></div>`;
  const sorted=[...events].sort((a,b)=>parseTs(a.ts)-parseTs(b.ts));
  const rows=sorted.map((ev,i)=>{
    const prev=i>0?sorted[i-1]:null;
    const delta=prev?parseTs(ev.ts)-parseTs(prev.ts):0;
    const di=devMap&&devMap[ev.dev];
    const nm=di?di.deviceName||ev.dev:ev.dev;
    const showId=di&&di.deviceName?` <span style="opacity:.5;font-size:9px">(${esc(ev.dev)})</span>`:'';
    return `<div class="ev-inline-row">
      <span style="color:var(--accent)">●</span>
      <span style="color:var(--muted2);min-width:160px;font-size:11px">${esc(ev.ts)}</span>
      <span style="color:var(--text)">${esc(nm)}${showId}</span>
      ${delta>0?`<span style="color:var(--warn);margin-left:auto">+${fmtDelta(delta)}</span>`:'<span style="color:var(--muted2);margin-left:auto;font-size:10px">inicio</span>'}
    </div>`;
  }).join('');
  return `<div class="ev-inline-inner">${rows}</div>`;
}

/* Selectors */
function renderSelectors(data){
  const byServer={};
  Object.entries(data.devices).forEach(([k,dev])=>{(byServer[dev.server]=byServer[dev.server]||{})[k]=dev;});
  let html='';
  Object.entries(byServer).forEach(([srv,devs])=>{
    const col=SERVER_COLORS[srv]||'#7a8aaa';
    const icon=srv==='Surfacing'?'⚙️':'✂️';
    html+=`<div class="sel-group"><div class="sel-label" style="color:${col}">${icon} ${esc(srv)}</div><div class="dsel">`;
    Object.entries(devs).forEach(([dk,dev])=>{
      const sCol=STATUS_COLORS[dev.status]||'#7a8aaa';
      const act=FILTER_DEV===dk?'act':'';
      const nm=dev.deviceName||dev.device;
      html+=`<a href="device.php?dev=${encodeURIComponent(dk)}" class="dbtn ${act}">
        <span class="dn"><span class="sdot" style="background:${sCol}"></span>${esc(nm)}</span>
        <span class="did">${esc(dev.device)} · ${dev.jobCount} órdenes · ${esc(dev.status)}</span>
      </a>`;
    });
    html+=`</div></div>`;
  });
  html+=`<a href="device.php" class="dbtn ${!FILTER_DEV?'act':''}" style="display:inline-flex;flex-direction:row;gap:8px;align-items:center;margin-bottom:24px">
    <span class="dn">🔁 Todos los equipos</span>
  </a>`;
  return html;
}

/* Device detail view */
function renderDeviceDetail(dev,devKey,devJobs,devMap){
  const sCol=STATUS_COLORS[dev.status]||'#7a8aaa';
  const srvCol=SERVER_COLORS[dev.server]||'#7a8aaa';
  return `<div class="dhead">
    <div class="dhead-bar" style="background:linear-gradient(90deg,${srvCol},${sCol})"></div>
    <div class="dh-name" style="padding-top:6px">${esc(dev.deviceName||dev.device)}</div>
    <div class="dh-sub">
      ${esc(dev.id)} &nbsp;·&nbsp; Modelo: ${esc(dev.model)} &nbsp;·&nbsp; Operador: ${esc(dev.user)}
      &nbsp;·&nbsp; ${tag(dev.server,srvCol+'18',srvCol,srvCol+'44')}
    </div>
    <div class="dstats">
      <div class="ds-item"><span class="ds-lbl">Estado</span><span class="ds-val" style="color:${sCol}">${esc(dev.statusLabel)} <span style="font-size:11px;color:var(--muted)">(${esc(dev.status)})</span></span></div>
      <div class="ds-item"><span class="ds-lbl">Producción</span><span class="ds-val">${esc(dev.prodLabel)}</span></div>
      <div class="ds-item"><span class="ds-lbl">Órdenes</span><span class="ds-val" style="color:var(--accent)">${devJobs.length}</span></div>
      <div class="ds-item"><span class="ds-lbl">Modo</span><span class="ds-val" style="font-size:14px">${esc(dev.devMode)}</span></div>
      <div class="ds-item"><span class="ds-lbl">Último evento</span><span class="ds-val" style="font-size:12px;color:var(--muted)">${esc(dev.lastSeen)}</span></div>
    </div>
  </div>
  <div class="st" style="margin-bottom:10px">Órdenes en ${esc(dev.device)} (${devJobs.length}) — <span style="font-size:9px;text-transform:none;letter-spacing:0">clic en una fila para ver su timeline</span></div>
  ${renderDeviceTable(devKey, devJobs, devMap)}`;
}

/* All-devices view: table with expandable events per row */
function renderAllDevices(data){
  const devMap=data.devices||{};
  const byServer={};
  Object.entries(devMap).forEach(([k,dev])=>{(byServer[dev.server]=byServer[dev.server]||{})[k]=dev;});
  let html='';
  Object.entries(byServer).forEach(([srv,devs])=>{
    const srvCol=SERVER_COLORS[srv]||'#7a8aaa';
    const icon=srv==='Surfacing'?'⚙️':'✂️';
    html+=`<div class="st" style="color:${srvCol}">${icon} ${esc(srv)}</div>`;
    Object.entries(devs).forEach(([dk,dev])=>{
      const djobs=(data.jobs||[]).filter(j=>(j.devices||[]).includes(dk));
      const col=STATUS_COLORS[dev.status]||'#7a8aaa';
      const rows=djobs.length
        ? djobs.map((job,ri)=>{
            const evCount=(job.events||[]).length;
            const rowId=`evrow-${esc(dk)}-${ri}`;
            return `<tr onclick="toggleEvRow('${rowId}')" style="cursor:pointer">
              <td>${tag(job.job,'rgba(37,99,235,.12)','#3b82f6','rgba(59,130,246,.3)')}</td>
              <td class="mono">${esc(job.order)}</td>
              <td class="mono" style="color:var(--muted);font-size:11px">${esc(job.firstSeen)}</td>
              <td class="mono" style="color:var(--muted);font-size:11px">${esc(job.lastSeen)}</td>
              <td class="mono" style="color:var(--accent);font-weight:600">${evCount} <span style="font-size:10px;color:var(--muted)">▼</span></td>
            </tr>
            <tr class="ev-inline" id="${rowId}"><td colspan="5">${renderInlineEvents(job.events,devMap)}</td></tr>`;
          }).join('')
        : `<tr><td colspan="5" style="color:var(--muted);text-align:center;padding:16px">Sin órdenes hoy</td></tr>`;
      html+=`<div class="tw">
        <div class="ttbar">
          <h3><span style="color:${col};font-family:var(--mono);font-weight:700">${esc(dev.device)}</span> &nbsp;·&nbsp; ${esc(dev.id)} &nbsp;·&nbsp; ${esc(dev.statusLabel)} &nbsp;·&nbsp; Operador: ${esc(dev.user)}</h3>
          ${tag(dev.status+' · '+djobs.length+' órdenes',col+'22',col,col+'44')}
        </div>
        <div style="max-height:400px;overflow-y:auto">
        <table><thead><tr><th>JOB</th><th>Nº Orden</th><th>Primer evento</th><th>Último evento</th><th>Eventos ▼</th></tr></thead>
        <tbody>${rows}</tbody></table>
        </div>
      </div>`;
    });
  });
  return html;
}

function toggleEvRow(id){
  const el=document.getElementById(id);
  if(el) el.style.display=el.style.display==='table-row'?'none':'table-row';
}

/* ── State preservation ─────────────────────────────────────────────────── */
function getOpenState(){
  const open=new Set();
  // Cards (device detail view): jcard-{job}
  document.querySelectorAll('.jcard.open').forEach(el=>open.add(el.id));
  // Inline rows (all-devices view): ev-inline visible
  document.querySelectorAll('.ev-inline').forEach(el=>{
    if(el.style.display==='table-row') open.add(el.id);
  });
  return open;
}
function restoreOpenState(open){
  if(!open.size) return;
  open.forEach(id=>{
    const el=document.getElementById(id);
    if(!el) return;
    if(el.classList.contains('jcard')) el.classList.add('open');
    else if(el.classList.contains('ev-inline')) el.style.display='table-row';
  });
}

/* ── Main render ─────────────────────────────────────────────────────────── */
function render(data){
  // 1. Snapshot what's open BEFORE touching the DOM
  const open = getOpenState();

  // 2. Re-render selectors (lightweight, no open state needed)
  document.getElementById('selectors').innerHTML=renderSelectors(data);

  // 3. Re-render main content
  let appHtml='';
  if(FILTER_DEV && data.devices[FILTER_DEV]){
    const dev=data.devices[FILTER_DEV];
    const devJobs=(data.jobs||[]).filter(j=>(j.devices||[]).includes(FILTER_DEV));
    appHtml=renderDeviceDetail(dev,FILTER_DEV,devJobs,data.devices||{});
    document.title=`LensWare Monitor — ${dev.device}`;
    document.getElementById('header-sub').textContent=`PANEL POR EQUIPO — ${dev.device}`;
  } else {
    appHtml=renderAllDevices(data);
  }
  document.getElementById('app').innerHTML=appHtml;

  // 4. Restore open panels immediately after DOM is written
  restoreOpenState(open);
}

/* ── Date filter ─────────────────────────────────────────────────────────── */
let activeFrom=null, activeTo=null;

/* ── Persistencia del filtro en sessionStorage ──────────────────────────── */
const FILTER_KEY='lw_filter';

function saveFilter(from,to){
  if(from||to) sessionStorage.setItem(FILTER_KEY,JSON.stringify({from,to}));
  else         sessionStorage.removeItem(FILTER_KEY);
}

function loadFilter(){
  try{
    const saved=sessionStorage.getItem(FILTER_KEY);
    if(!saved) return;
    const {from,to}=JSON.parse(saved);
    if(from){document.getElementById('f-from').value=from; activeFrom=from;}
    if(to)  {document.getElementById('f-to').value=to;     activeTo=to;}
    if(from||to) updateBadge(from||null,to||null);
  }catch(e){/* ignorar */}
}

function apiUrl(){
  const p=new URLSearchParams({cache:Date.now()});
  if(activeFrom) p.set('from',activeFrom);
  if(activeTo)   p.set('to',activeTo);
  return 'api.php?'+p.toString();
}

function tsDate(ts){
  if(!ts) return '';
  const [dt]=ts.split(' ');
  const [d,m,y]=dt.split('.');
  return `${y}-${m}-${d}`;
}

function applyDateFilter(data,from,to){
  if(!from&&!to) return data;
  const jobs=(data.jobs||[]).filter(job=>{
    const first=tsDate(job.firstSeen), last=tsDate(job.lastSeen);
    if(from&&last<from) return false;
    if(to&&first>to)    return false;
    return true;
  }).map(job=>({
    ...job,
    events:(job.events||[]).filter(ev=>{
      const d=tsDate(ev.ts);
      if(from&&d<from) return false;
      if(to&&d>to)     return false;
      return true;
    })
  }));
  return {...data,jobs,totalJobs:jobs.length};
}

function onFilterChange(){
  const from=document.getElementById('f-from').value||null;
  const to=document.getElementById('f-to').value||null;
  if(!lastData) return;
  const filtered=applyDateFilter(lastData,from,to);
  render(filtered);
  updateBadge(from,to);
  saveFilter(from,to);
}

async function applyServerFilter(){
  const from=document.getElementById('f-from').value||null;
  const to=document.getElementById('f-to').value||null;
  activeFrom=from; activeTo=to; countdown=REFRESH;
  saveFilter(from,to);
  await fetchData();
  updateBadge(from,to);
}

function clearFilter(){
  document.getElementById('f-from').value='';
  document.getElementById('f-to').value='';
  activeFrom=null; activeTo=null;
  saveFilter(null,null);
  updateBadge(null,null);
  if(lastData) render(lastData); else fetchData();
}

function updateBadge(from,to){
  const badge=document.getElementById('filter-badge');
  if(!from&&!to){badge.style.display='none';return;}
  badge.style.display='inline-block';
  const f=from?from.split('-').reverse().join('/'):'…';
  const t=to?to.split('-').reverse().join('/'):'hoy';
  badge.textContent=`📅 ${f} → ${t}`;
}

/* ── Fetch & countdown ───────────────────────────────────────────────────── */
let countdown=REFRESH, lastData=null;
async function fetchData(){
  try{
    const res=await fetch(apiUrl(),{cache:'no-store'});
    if(!res.ok) throw new Error('HTTP '+res.status);
    const data=await res.json();
    lastData=data;
    const from=document.getElementById('f-from').value||null;
    const to=document.getElementById('f-to').value||null;
    const display=(from||to)?applyDateFilter(data,from,to):data;
    render(display);
    const ft=document.getElementById('footer-time');
    if(ft) ft.textContent=`● ${data.serverTime} · Próxima actualización: ${REFRESH}s`;
  }catch(e){
    const app=document.getElementById('app');
    if(app&&!lastData) app.innerHTML=`<div style="padding:40px;text-align:center;color:var(--danger)">⚠️ Error al cargar datos: ${e.message}</div>`;
  }
}
loadFilter();
fetchData();
setInterval(()=>{
  countdown--;
  const ft=document.getElementById('footer-time');
  if(ft&&lastData) ft.textContent=`● ${lastData.serverTime} · Próxima actualización: ${countdown}s`;
  if(countdown<=0){countdown=REFRESH;fetchData();}
},1000);
</script>
</body>
</html>