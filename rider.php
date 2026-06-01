<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['puesto']) || $_SESSION['puesto'] != 'Motorizado') {
    header("Location: index.php"); exit;
}
require_once 'config.php';
$rider_id = $_SESSION['user_id'];
if (isset($_POST['update_status']) && isset($_POST['idDelivery'])) {
    $idDel       = intval($_POST['idDelivery']);
    $nuevoEstado = $_POST['update_status'];
    $allowed     = ['En Camino', 'Entregado', 'No Entregado'];
    if (in_array($nuevoEstado, $allowed)) {
        $stmt = $conn->prepare("UPDATE delivery SET estadoEntrega = :estado WHERE idDelivery = :id AND idMotorizado = :rider");
        $stmt->execute([':estado' => $nuevoEstado, ':id' => $idDel, ':rider' => $rider_id]);
    }
    header("Location: rider.php"); exit;
}
$stmt = $conn->prepare("
    SELECT d.idDelivery AS iddelivery, d.direccionEscrita AS direccionescrita,
           d.estadoEntrega AS estadoentrega, d.ubicacionGPS AS ubicaciongps,
           c.nombre AS cliente, c.noTelefono AS notelefono,
           v.precioTotal AS preciototal
    FROM delivery d
    JOIN venta v ON d.idVenta = v.idVenta
    JOIN cliente c ON v.idCliente = c.idCliente
    WHERE d.idMotorizado = :rider AND d.estado = 'Activo'
    ORDER BY d.fechaRegistro ASC
");
$stmt->execute([':rider' => $rider_id]);
$entregas = $stmt->fetchAll();
$pendientes = $conn->query("
    SELECT d.idDelivery AS iddelivery, d.direccionEscrita AS direccionescrita,
           d.estadoEntrega AS estadoentrega, d.ubicacionGPS AS ubicaciongps,
           c.nombre AS cliente, c.noTelefono AS notelefono,
           v.precioTotal AS preciototal
    FROM delivery d
    JOIN venta v ON d.idVenta = v.idVenta
    JOIN cliente c ON v.idCliente = c.idCliente
    WHERE d.estado = 'Activo' AND d.idMotorizado IS NULL
    ORDER BY d.fechaRegistro ASC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>App Motorizado - Tienda Delivery</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        .rider-page-grid {
            display: grid;
            grid-template-columns: 380px 1fr;
            gap: 1.5rem;
            align-items: start;
        }
        .rider-list-col {
            display: flex; flex-direction: column; gap: 1.2rem;
            max-height: calc(100vh - 110px);
            overflow-y: auto; padding-right: 4px;
        }
        .rider-list-col::-webkit-scrollbar { width: 4px; }
        .rider-list-col::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 2px; }
        .rider-map-col { position: sticky; top: 80px; }
        .section-title {
            font-size: 0.75rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.08em; color: var(--text-muted);
            padding-bottom: 0.5rem; margin-bottom: 0.6rem;
            border-bottom: 1px solid rgba(255,255,255,0.07);
        }
        .del-card {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px; padding: 1rem 1.1rem;
            cursor: pointer; transition: border-color 0.2s, background 0.2s;
            margin-bottom: 0.6rem;
        }
        .del-card:hover { border-color: rgba(99,102,241,0.4); background: rgba(99,102,241,0.04); }
        .del-card.active-card { border-color: var(--accent); box-shadow: 0 0 0 1px rgba(6,182,212,0.2); }
        .del-card.pending-card:hover { border-color: rgba(34,197,94,0.4); }
        .del-card.pending-card.active-card { border-color: #22c55e; box-shadow: 0 0 0 1px rgba(34,197,94,0.2); }
        .del-card-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:0.4rem; }
        .del-card-header h4 { margin:0; font-size:0.92rem; }
        .del-card-meta { font-size:0.78rem; color:var(--text-muted); margin:0.15rem 0; }
        .del-card-actions { display:flex; flex-direction:column; gap:0.4rem; margin-top:0.7rem; }
        .del-card-actions form { margin:0; }
        .btn-accion {
            width:100%; padding:0.5rem; border:none; border-radius:8px;
            font-size:0.82rem; font-weight:600; cursor:pointer; transition:background 0.2s;
        }
        .btn-iniciar  { background:rgba(6,182,212,0.18); color:var(--accent); border:1px solid rgba(6,182,212,0.4); }
        .btn-iniciar:hover { background:rgba(6,182,212,0.32); }
        .btn-confirmar { background:rgba(34,197,94,0.18); color:var(--success); border:1px solid rgba(34,197,94,0.4); }
        .btn-confirmar:hover { background:rgba(34,197,94,0.32); }
        .btn-noentregado { background:rgba(239,68,68,0.12); color:var(--danger); border:1px solid rgba(239,68,68,0.35); }
        .btn-noentregado:hover { background:rgba(239,68,68,0.25); }
        #main-map { height:300px; width:100%; }
        .map-card { padding:0; overflow:hidden; }
        .map-card-header { padding:0.9rem 1.2rem; border-bottom:1px solid rgba(255,255,255,0.07); display:flex; justify-content:space-between; align-items:center; }
        .map-legend { padding:0.5rem 1.2rem; border-top:1px solid rgba(255,255,255,0.07); display:flex; gap:1.2rem; flex-wrap:wrap; }
        .map-legend span { font-size:0.75rem; color:var(--text-muted); }
        #gps-estado { font-size:0.78rem; padding:0.3rem 0.8rem; border-radius:1rem; background:rgba(148,163,184,0.15); color:var(--text-muted); }
        .d-none { display:none !important; }
        @media(max-width:900px) {
            .rider-page-grid { grid-template-columns:1fr; }
            .rider-list-col { max-height:none; overflow-y:visible; }
            .rider-map-col { position:static; }
        }
    </style>
</head>
<body>
<div class="app-wrapper">
    <nav class="navbar">
        <h2>Tienda Delivery <span>| Motorizado</span></h2>
        <div class="nav-user">
            <span>🏍️ Hola, <?php echo htmlspecialchars($_SESSION['nombre']); ?></span>
            <div id="gps-estado">GPS inactivo</div>
            <button onclick="activarGPS()" id="btn-gps" class="btn"
                style="padding:0.5rem 1rem;width:auto;background:rgba(6,182,212,0.2);color:var(--accent);border:1px solid rgba(6,182,212,0.4);font-size:0.85rem;">
                📡 Activar GPS
            </button>
            <a href="logout.php" class="btn"
                style="padding:0.5rem 1rem;width:auto;background:rgba(239,68,68,0.2);color:var(--danger);border:1px solid rgba(239,68,68,0.5);font-size:0.85rem;">
                Salir
            </a>
        </div>
    </nav>

    <div class="dashboard-container">
        <?php if (count($entregas) === 0 && count($pendientes) === 0): ?>
        <div class="glass-card" style="text-align:center;padding:4rem;">
            <div style="font-size:3rem;margin-bottom:1rem;">😴</div>
            <h3 style="color:var(--text-muted);font-weight:400;">Sin entregas por ahora</h3>
            <p style="color:var(--text-muted);font-size:0.9rem;margin:0;">No tienes entregas asignadas ni pedidos disponibles.</p>
        </div>
        <?php else: ?>
        <div class="rider-page-grid">

            <!-- ══ LISTA ══ -->
            <div class="rider-list-col">

                <!-- Mis Entregas -->
                <div>
                    <div class="section-title">🏍️ Mis Entregas (<?php echo count($entregas); ?>)</div>
                    <?php if (empty($entregas)): ?>
                    <p style="color:var(--text-muted);font-size:0.82rem;padding:0.3rem 0;">Sin entregas asignadas.</p>
                    <?php else: ?>
                    <?php foreach ($entregas as $i => $e): ?>
                    <div class="del-card <?php echo $i===0?'active-card':''; ?>"
                         id="card-mis-<?php echo $i; ?>"
                         onclick="focusMarker('mis',<?php echo $i; ?>)">
                        <div class="del-card-header">
                            <h4><?php echo htmlspecialchars($e['cliente']); ?></h4>
                            <span class="badge <?php echo str_replace(' ','-',$e['estadoentrega']); ?>">
                                <?php echo $e['estadoentrega']; ?>
                            </span>
                        </div>
                        <p class="del-card-meta">📍 <?php echo htmlspecialchars($e['direccionescrita']); ?></p>
                        <p class="del-card-meta">📞 <?php echo htmlspecialchars($e['notelefono']); ?> &nbsp;·&nbsp; 💰 Bs <?php echo number_format($e['preciototal'],2); ?></p>

                        <?php if ($e['estadoentrega'] === 'Pendiente'): ?>
                        <div class="del-card-actions">
                            <form method="POST">
                                <input type="hidden" name="idDelivery" value="<?php echo $e['iddelivery']; ?>">
                                <button type="submit" name="update_status" value="En Camino" class="btn-accion btn-iniciar">🛵 Iniciar Ruta</button>
                            </form>
                        </div>
                        <?php elseif ($e['estadoentrega'] === 'En Camino'): ?>
                        <div class="del-card-actions">
                            <form method="POST">
                                <input type="hidden" name="idDelivery" value="<?php echo $e['iddelivery']; ?>">
                                <button type="submit" name="update_status" value="Entregado" class="btn-accion btn-confirmar">✅ Confirmar Entrega</button>
                            </form>
                            <form method="POST">
                                <input type="hidden" name="idDelivery" value="<?php echo $e['iddelivery']; ?>">
                                <button type="submit" name="update_status" value="No Entregado" class="btn-accion btn-noentregado">✗ No Entregado</button>
                            </form>
                        </div>
                        <?php else: ?>
                        <div style="margin-top:0.6rem;padding:0.4rem 0.8rem;border-radius:8px;background:rgba(34,197,94,0.08);border:1px solid rgba(34,197,94,0.2);font-size:0.8rem;color:var(--success);font-weight:600;">
                            ✅ <?php echo $e['estadoentrega']; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Pedidos Disponibles -->
                <div>
                    <div class="section-title">📦 Pedidos Disponibles (<?php echo count($pendientes); ?>)</div>
                    <?php if (empty($pendientes)): ?>
                    <p style="color:var(--text-muted);font-size:0.82rem;padding:0.3rem 0;">No hay pedidos sin asignar.</p>
                    <?php else: ?>
                    <?php foreach ($pendientes as $j => $p): ?>
                    <div class="del-card pending-card"
                         id="card-pend-<?php echo $j; ?>"
                         onclick="focusMarker('pend',<?php echo $j; ?>)">
                        <div class="del-card-header">
                            <h4><?php echo htmlspecialchars($p['cliente']); ?></h4>
                            <span class="badge Pendiente">Pendiente</span>
                        </div>
                        <p class="del-card-meta">📍 <?php echo htmlspecialchars($p['direccionescrita']); ?></p>
                        <p class="del-card-meta">📞 <?php echo htmlspecialchars($p['notelefono']); ?> &nbsp;·&nbsp; 💰 Bs <?php echo number_format($p['preciototal'],2); ?></p>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div><!-- /rider-list-col -->

            <!-- ══ MAPA ══ -->
            <div class="rider-map-col">
                <div class="glass-card map-card">
                    <div class="map-card-header">
                        <h4 style="margin:0;font-size:1rem;">🗺️ Mapa de Entregas</h4>
                        <div id="gps-coords" style="font-size:0.75rem;color:var(--text-muted);">Sin GPS activo</div>
                    </div>
                    <div id="main-map"></div>
                    <div class="map-legend">
                        <span>🏍️ Mis entregas</span>
                        <span>📦 Disponibles</span>
                        <span>📡 Mi posición</span>
                    </div>
                </div>
            </div>

        </div>
        <?php endif; ?>
    </div>
</div>

<script>
const misEntregas       = <?php echo json_encode(array_values($entregas),   JSON_UNESCAPED_UNICODE); ?>;
const pedidosPendientes = <?php echo json_encode(array_values($pendientes), JSON_UNESCAPED_UNICODE); ?>;

let map = null, riderMarker = null;
let misMarkers = [], pendMarkers = [];
let watchId = null, gpsActivo = false;
const DEF_LAT = -17.7833, DEF_LNG = -63.1821;

function parseCoords(str) {
    if (!str) return null;
    const p = str.split(',');
    if (p.length !== 2) return null;
    const lat = parseFloat(p[0]), lng = parseFloat(p[1]);
    return (isNaN(lat)||isNaN(lng)) ? null : [lat, lng];
}

function makeMisIcon(hl) {
    const b = hl ? '#06b6d4' : 'rgba(6,182,212,0.5)';
    const s = hl ? '0 0 0 3px rgba(6,182,212,0.3)' : 'none';
    return L.divIcon({ html:`<div style="background:#0f172a;border:2px solid ${b};border-radius:50%;width:36px;height:36px;display:flex;align-items:center;justify-content:center;box-shadow:${s};"><span style="font-size:18px;">🏍️</span></div>`, iconSize:[36,36], iconAnchor:[18,18], className:'' });
}
function makePendIcon(hl) {
    const b = hl ? '#22c55e' : 'rgba(34,197,94,0.5)';
    const s = hl ? '0 0 0 4px rgba(34,197,94,0.3)' : 'none';
    return L.divIcon({ html:`<div style="background:#0f172a;border:2px solid ${b};border-radius:50% 50% 50% 0;transform:rotate(-45deg);width:32px;height:32px;display:flex;align-items:center;justify-content:center;box-shadow:${s};"><span style="transform:rotate(45deg);font-size:16px;">📦</span></div>`, iconSize:[32,32], iconAnchor:[16,32], className:'' });
}
function makeGPSIcon() {
    return L.divIcon({ html:`<div style="background:#0f172a;border:2px solid #f59e0b;border-radius:50%;width:40px;height:40px;display:flex;align-items:center;justify-content:center;box-shadow:0 0 0 4px rgba(245,158,11,0.3);"><span style="font-size:20px;">📡</span></div>`, iconSize:[40,40], iconAnchor:[20,20], className:'' });
}
function esc(s) { return s ? s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;') : ''; }

function initMap() {
    if (misEntregas.length === 0 && pedidosPendientes.length === 0) return;
    map = L.map('main-map').setView([DEF_LAT, DEF_LNG], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution:'© OpenStreetMap', maxZoom:19 }).addTo(map);

    const allLL = [];

    misEntregas.forEach((e, i) => {
        const pos = parseCoords(e.ubicaciongps) || [DEF_LAT + i*0.003, DEF_LNG + i*0.002];
        allLL.push(pos);
        const m = L.marker(pos, { icon: makeMisIcon(i===0) }).addTo(map);
        m.bindPopup(`<b style="color:#06b6d4;">🏍️ Mi entrega</b><br><b>${esc(e.cliente)}</b><br><span style="font-size:0.82rem;">📍 ${esc(e.direccionescrita)}</span><br><span style="font-size:0.82rem;">Estado: <b>${esc(e.estadoentrega)}</b></span>`);
        misMarkers.push(m);
    });

    pedidosPendientes.forEach((p, j) => {
        const pos = parseCoords(p.ubicaciongps) || [DEF_LAT - 0.005 + j*0.003, DEF_LNG - 0.003 + j*0.002];
        allLL.push(pos);
        const m = L.marker(pos, { icon: makePendIcon(false) }).addTo(map);
        m.bindPopup(`<b style="color:#22c55e;">📦 Disponible</b><br><b>${esc(p.cliente)}</b><br><span style="font-size:0.82rem;">📍 ${esc(p.direccionescrita)}</span><br><span style="font-size:0.82rem;">💰 Bs ${parseFloat(p.preciototal).toFixed(2)}</span>`);
        pendMarkers.push(m);
    });

    if (allLL.length === 1) map.setView(allLL[0], 15);
    else if (allLL.length > 1) map.fitBounds(L.latLngBounds(allLL), { padding:[50,50] });

    if (misMarkers.length > 0) setTimeout(() => misMarkers[0].openPopup(), 400);
    setTimeout(() => map.invalidateSize(), 300);
}

function focusMarker(type, idx) {
    document.querySelectorAll('.del-card').forEach(c => c.classList.remove('active-card'));
    if (type === 'mis') {
        document.getElementById('card-mis-'+idx)?.classList.add('active-card');
        if (!map || !misMarkers[idx]) return;
        misMarkers.forEach((m,i) => m.setIcon(makeMisIcon(i===idx)));
        map.setView(misMarkers[idx].getLatLng(), 16, { animate:true });
        misMarkers[idx].openPopup();
    } else {
        document.getElementById('card-pend-'+idx)?.classList.add('active-card');
        if (!map || !pendMarkers[idx]) return;
        pendMarkers.forEach((m,i) => m.setIcon(makePendIcon(i===idx)));
        map.setView(pendMarkers[idx].getLatLng(), 16, { animate:true });
        pendMarkers[idx].openPopup();
    }
}

function activarGPS() {
    if (!navigator.geolocation) { alert('Tu dispositivo no soporta geolocalización.'); return; }
    if (gpsActivo) {
        if (watchId !== null) navigator.geolocation.clearWatch(watchId);
        gpsActivo = false; watchId = null; setGpsUI('inactive'); return;
    }
    gpsActivo = true; setGpsUI('searching');
    watchId = navigator.geolocation.watchPosition(onGPSUpdate, onGPSError, { enableHighAccuracy:true, maximumAge:3000, timeout:10000 });
}

function setGpsUI(state) {
    const el = document.getElementById('gps-estado'), btn = document.getElementById('btn-gps');
    if (state === 'inactive') { el.textContent='GPS inactivo'; el.style.color='var(--text-muted)'; el.style.background='rgba(148,163,184,0.15)'; btn.textContent='📡 Activar GPS'; btn.style.background='rgba(6,182,212,0.2)'; btn.style.color='var(--accent)'; }
    else if (state === 'searching') { el.textContent='Obteniendo GPS...'; el.style.color='#f59e0b'; el.style.background='rgba(245,158,11,0.15)'; btn.textContent='⏹ Detener GPS'; btn.style.background='rgba(239,68,68,0.2)'; btn.style.color='var(--danger)'; }
    else if (state === 'active') { el.textContent='🟢 GPS activo'; el.style.color='#22c55e'; el.style.background='rgba(34,197,94,0.15)'; }
}

function onGPSUpdate(pos) {
    const lat = pos.coords.latitude, lng = pos.coords.longitude, acc = Math.round(pos.coords.accuracy);
    setGpsUI('active');
    const coordEl = document.getElementById('gps-coords');
    if (coordEl) coordEl.textContent = `📡 ${lat.toFixed(5)}, ${lng.toFixed(5)} (±${acc}m)`;
    if (map) {
        if (!riderMarker) { riderMarker = L.marker([lat,lng],{icon:makeGPSIcon()}).addTo(map); riderMarker.bindPopup('<b>Tu posición actual</b>'); }
        else riderMarker.setLatLng([lat,lng]);
    }
    fetch('api_actualizar_gps.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({lat,lng}) }).catch(()=>{});
}

function onGPSError(err) {
    const msgs = {1:'Permiso denegado. Actívalo en la configuración.',2:'No se pudo obtener ubicación.',3:'Tiempo de espera agotado.'};
    setGpsUI('inactive'); gpsActivo = false;
    document.getElementById('gps-estado').textContent = '❌ Error GPS';
    document.getElementById('gps-estado').style.color = 'var(--danger)';
    alert(msgs[err.code] || 'Error de geolocalización.');
}

document.addEventListener('DOMContentLoaded', initMap);
</script>
</body>
</html>
