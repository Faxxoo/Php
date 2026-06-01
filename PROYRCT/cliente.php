<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['tipo']) || $_SESSION['tipo'] != 'cliente') {
    header("Location: index.php?tab=cliente"); exit;
}
require_once 'config.php';
$cliente_id = $_SESSION['user_id'];

// Cargar pedidos con info de delivery y rider
$stmt = $conn->prepare("
    SELECT v.idVenta AS idventa, v.precioTotal AS preciototal,
           v.tipoEntrega AS tipoentrega, v.fechaVenta AS fechaventa,
           d.idDelivery AS iddelivery, d.estadoEntrega AS estadoentrega,
           d.direccionEscrita AS direccionescrita, d.ubicacionGPS AS ubicaciongps,
           d.estado AS estado,
           t.nombre AS rider_nombre, t.apellido AS rider_apellido
    FROM venta v
    LEFT JOIN delivery d ON d.idVenta = v.idVenta
    LEFT JOIN trabajador t ON d.idMotorizado = t.idTrabajador
    WHERE v.idCliente = :id
    ORDER BY v.fechaVenta DESC
");
$stmt->execute([':id' => $cliente_id]);
$pedidos = $stmt->fetchAll();

// Cargar detalles de cada pedido
$stmt2 = $conn->prepare("
    SELECT vd.cantidad, vd.precioUnitario AS preciounitario,
           vd.subtotal, p.nombre AS producto
    FROM ventaDetalle vd
    JOIN productos p ON vd.idProducto = p.idProducto
    WHERE vd.idVenta = :vid
");

// Construir datos para JS (todos los pedidos con delivery)
$pedidosJS = [];
foreach ($pedidos as $p) {
    if (!$p['iddelivery']) continue;
    $coords = null;
    if ($p['ubicaciongps']) {
        $parts = explode(',', $p['ubicaciongps']);
        if (count($parts) === 2 && is_numeric(trim($parts[0]))) {
            $coords = ['lat' => trim($parts[0]), 'lng' => trim($parts[1])];
        }
    }
    // Cargar productos del pedido para mostrar en popup del mapa
    $stmt2->execute([':vid' => $p['idventa']]);
    $dets = $stmt2->fetchAll();
    $prods_str = implode(', ', array_map(fn($d) => $d['cantidad'].'x '.$d['producto'], $dets));

    $pedidosJS[$p['iddelivery']] = [
        'riderLat'  => $coords ? $coords['lat'] : null,
        'riderLng'  => $coords ? $coords['lng'] : null,
        'rider'     => $p['rider_nombre'] ? trim($p['rider_nombre'].' '.$p['rider_apellido']) : null,
        'estado'    => $p['estadoentrega'] ?? 'Pendiente',
        'direccion' => $p['direccionescrita'] ?? '',
        'activo'    => $p['estado'] === 'Activo',
        'productos' => $prods_str,
    ];
}
// Reset stmt2 para usarlo de nuevo en el HTML
$stmt2 = $conn->prepare("
    SELECT vd.cantidad, vd.precioUnitario AS preciounitario,
           vd.subtotal, p.nombre AS producto
    FROM ventaDetalle vd
    JOIN productos p ON vd.idProducto = p.idProducto
    WHERE vd.idVenta = :vid
");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Pedidos - Tienda Delivery</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        .cliente-layout { display:grid; grid-template-columns:380px 1fr; gap:1.5rem; align-items:start; }
        @media(max-width:900px){ .cliente-layout { grid-template-columns:1fr; } }
        .pedido-card { cursor:pointer; transition:border-color 0.2s, transform 0.15s; }
        .pedido-card:hover { transform:translateY(-2px); }
        .pedido-card.selected { border-color:rgba(6,182,212,0.6) !important; background:rgba(6,182,212,0.06) !important; }
        .estado-dot { width:8px; height:8px; border-radius:50%; display:inline-block; margin-right:5px; }
        @keyframes pulseGPS {
            0%,100% { opacity:1; } 50% { opacity:0.4; }
        }
        .gps-live { animation: pulseGPS 1.5s infinite; }
    </style>
</head>
<body>
<div class="app-wrapper">
    <nav class="navbar">
        <h2>Tienda Delivery <span>| Mi Portal</span></h2>
        <div class="nav-user">
            <span>🛒 Hola, <?php echo htmlspecialchars($_SESSION['nombre']); ?></span>
            <a href="nuevo_pedido.php" class="btn" style="padding:0.5rem 1.2rem;width:auto;background:rgba(6,182,212,0.2);color:var(--accent);border:1px solid rgba(6,182,212,0.4);">
                ➕ Nuevo Pedido
            </a>
            <a href="logout.php" class="btn" style="padding:0.5rem 1.5rem;width:auto;background:rgba(239,68,68,0.2);color:var(--danger);border:1px solid rgba(239,68,68,0.5);">
                Cerrar Sesión
            </a>
        </div>
    </nav>

    <div class="dashboard-container">
        <?php if (isset($_GET['ok'])): ?>
        <div style="margin-bottom:1.5rem;padding:1rem 1.5rem;background:rgba(34,197,94,0.12);border:1px solid rgba(34,197,94,0.3);border-radius:12px;color:#4ade80;display:flex;align-items:center;gap:0.8rem;">
            ✅ <?php echo htmlspecialchars($_GET['ok']); ?>
        </div>
        <?php endif; ?>

        <div class="cliente-layout">

            <!-- ══ COLUMNA IZQUIERDA: Lista de pedidos ══ -->
            <div>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.2rem;">
                    <h3 style="font-size:1.3rem;margin:0;">📦 Mis Pedidos</h3>
                    <span class="badge" style="background:rgba(6,182,212,0.2);color:var(--accent);border:1px solid rgba(6,182,212,0.3);">
                        <?php echo count($pedidos); ?> pedido<?php echo count($pedidos)!=1?'s':''; ?>
                    </span>
                </div>

                <?php if (empty($pedidos)): ?>
                <div class="glass-card" style="text-align:center;padding:3rem;">
                    <div style="font-size:3rem;margin-bottom:1rem;">🛒</div>
                    <p style="color:var(--text-muted);margin:0 0 1rem;">Aún no tienes pedidos.</p>
                    <a href="nuevo_pedido.php" class="btn btn-glow" style="width:auto;padding:0.7rem 1.5rem;display:inline-flex;">
                        ➕ Hacer mi primer pedido
                    </a>
                </div>
                <?php endif; ?>

                <?php foreach ($pedidos as $i => $p):
                    $stmt2->execute([':vid' => $p['idventa']]);
                    $detalle = $stmt2->fetchAll();
                    $tieneDelivery = !empty($p['iddelivery']);
                    $tieneGPS = $tieneDelivery && !empty($p['ubicaciongps']);
                    $estadoClass = str_replace(' ', '-', $p['estadoentrega'] ?? '');
                    $colEstado = [
                        'Pendiente'    => '#f59e0b',
                        'En Camino'    => '#06b6d4',
                        'Entregado'    => '#22c55e',
                        'No Entregado' => '#ef4444',
                    ][$p['estadoentrega'] ?? ''] ?? '#94a3b8';
                ?>
                <div class="glass-card pedido-card <?php echo $i===0?'selected':''; ?>"
                     style="margin-bottom:0.8rem; padding:1.2rem;"
                     onclick="seleccionarPedido(this,'<?php echo $p['iddelivery']??''; ?>','<?php echo addslashes($p['rider_nombre']?$p['rider_nombre'].' '.$p['rider_apellido']:'No asignado'); ?>','<?php echo addslashes($p['direccionescrita']??''); ?>','<?php echo addslashes($p['estadoentrega']??'Sin delivery'); ?>',<?php echo $tieneGPS?'true':'false'; ?>)"
                     id="pedido-<?php echo $p['idventa']; ?>">

                    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:0.7rem;">
                        <div>
                            <strong style="font-size:1rem;">#<?php echo str_pad($p['idventa'],4,'0',STR_PAD_LEFT); ?></strong>
                            <span style="color:var(--text-muted);font-size:0.8rem;margin-left:0.5rem;">
                                <?php echo date('d/m/Y H:i', strtotime($p['fechaventa'])); ?>
                            </span>
                        </div>
                        <?php if ($p['estadoentrega']): ?>
                        <span style="font-size:0.78rem;font-weight:600;padding:0.2rem 0.6rem;border-radius:20px;background:<?php echo $colEstado; ?>22;color:<?php echo $colEstado; ?>;border:1px solid <?php echo $colEstado; ?>44;">
                            <span class="estado-dot" style="background:<?php echo $colEstado; ?>;"></span>
                            <?php echo htmlspecialchars($p['estadoentrega']); ?>
                        </span>
                        <?php else: ?>
                        <span style="font-size:0.78rem;padding:0.2rem 0.6rem;border-radius:20px;background:rgba(148,163,184,0.1);color:var(--text-muted);">
                            <?php echo $p['tipoentrega']; ?>
                        </span>
                        <?php endif; ?>
                    </div>

                    <?php if ($p['direccionescrita']): ?>
                    <p style="color:var(--text-muted);font-size:0.82rem;margin:0 0 0.6rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                        📍 <?php echo htmlspecialchars($p['direccionescrita']); ?>
                    </p>
                    <?php endif; ?>

                    <div style="border-top:1px solid rgba(255,255,255,0.06);padding-top:0.6rem;margin-top:0.2rem;">
                        <?php foreach ($detalle as $det): ?>
                        <div style="display:flex;justify-content:space-between;font-size:0.82rem;color:var(--text-muted);margin-bottom:0.2rem;">
                            <span><?php echo $det['cantidad']; ?>× <?php echo htmlspecialchars($det['producto']); ?></span>
                            <span>Bs <?php echo number_format($det['subtotal'],2); ?></span>
                        </div>
                        <?php endforeach; ?>
                        <div style="display:flex;justify-content:space-between;font-weight:700;margin-top:0.4rem;padding-top:0.4rem;border-top:1px solid rgba(255,255,255,0.06);">
                            <span style="font-size:0.88rem;">Total</span>
                            <span style="color:var(--accent);">Bs <?php echo number_format($p['preciototal'],2); ?></span>
                        </div>
                    </div>

                    <?php if ($tieneGPS): ?>
                    <div style="margin-top:0.6rem;font-size:0.75rem;color:#22c55e;display:flex;align-items:center;gap:0.3rem;">
                        <span class="gps-live">●</span> GPS activo — rider en camino
                    </div>
                    <?php elseif ($tieneDelivery && $p['estado']==='Activo'): ?>
                    <div style="margin-top:0.6rem;font-size:0.75rem;color:#f59e0b;">
                        ⏳ Esperando asignación de rider
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- ══ COLUMNA DERECHA: Mapa + Info ══ -->
            <div style="position:sticky;top:1.5rem;">

                <!-- Info del rider -->
                <div class="glass-card" style="padding:1.2rem;margin-bottom:1rem;" id="rider-info-card">
                    <div id="rider-info-content" style="color:var(--text-muted);font-size:0.9rem;">
                        Selecciona un pedido para ver los detalles.
                    </div>
                </div>

                <!-- Mapa -->
                <div class="glass-card" style="padding:0;overflow:hidden;">
                    <div style="padding:0.9rem 1.2rem;border-bottom:1px solid rgba(255,255,255,0.07);display:flex;justify-content:space-between;align-items:center;">
                        <h4 style="margin:0;font-size:1rem;">🗺️ Mapa del Pedido</h4>
                        <div style="display:flex;align-items:center;gap:0.6rem;">
                            <span id="mapa-estado" style="font-size:0.78rem;color:var(--text-muted);">Sin selección</span>
                            <button id="btn-centrar" onclick="centrarMapa()"
                                style="display:none;padding:0.25rem 0.6rem;border-radius:6px;border:1px solid rgba(6,182,212,0.4);background:rgba(6,182,212,0.12);color:var(--accent);cursor:pointer;font-size:0.75rem;">
                                🎯 Centrar
                            </button>
                        </div>
                    </div>
                    <div id="mapa-cliente" style="height:460px;width:100%;background:#0a0f1e;display:flex;align-items:center;justify-content:center;">
                        <div style="text-align:center;color:var(--text-muted);">
                            <div style="font-size:3rem;margin-bottom:0.8rem;">🗺️</div>
                            <p style="margin:0;font-size:0.9rem;">Selecciona un pedido para ver el mapa</p>
                        </div>
                    </div>
                    <!-- Leyenda -->
                    <div id="mapa-leyenda" style="display:none;padding:0.6rem 1.2rem;border-top:1px solid rgba(255,255,255,0.07);gap:1.2rem;flex-wrap:wrap;font-size:0.78rem;color:var(--text-muted);">
                        <span>🏍️ Rider</span>
                        <span>📦 Tu destino</span>
                        <span style="display:flex;align-items:center;gap:0.3rem;"><span style="display:inline-block;width:16px;height:2px;background:#06b6d4;border-radius:2px;"></span>Ruta</span>
                    </div>
                </div>
            </div>

        </div><!-- fin cliente-layout -->
    </div>
</div>

<script>
// ── Datos de todos los pedidos con delivery ───────────────────────────────────
const pedidosData = <?php echo json_encode($pedidosJS); ?>;

// ── Estado del mapa ───────────────────────────────────────────────────────────
let leafletMap    = null;
let riderMarker   = null;
let destinoMarker = null;
let rutaLine      = null;
let timerPoll     = null;
let mapaListo     = false;

// ── Seleccionar pedido ────────────────────────────────────────────────────────
function seleccionarPedido(el, idDelivery, rider, direccion, estado, tieneGPS) {
    document.querySelectorAll('.pedido-card').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    if (timerPoll) clearInterval(timerPoll);

    actualizarInfoRider(rider, estado, direccion, tieneGPS, !!idDelivery);

    if (!idDelivery) {
        document.getElementById('mapa-estado').textContent = 'Sin delivery';
        limpiarMapa('Este pedido no tiene delivery (recojo o en tienda).');
        return;
    }

    const data = pedidosData[idDelivery];
    inicializarMapa(idDelivery, data);

    if (tieneGPS) {
        document.getElementById('mapa-estado').textContent = '🔴 En vivo';
        document.getElementById('btn-centrar').style.display = 'inline-block';
        timerPoll = setInterval(() => pollRider(idDelivery), 5000);
    } else if (data && data.activo) {
        document.getElementById('mapa-estado').textContent = '⏳ Sin GPS aún';
        document.getElementById('btn-centrar').style.display = 'none';
        // Seguir haciendo polling por si el rider activa GPS
        timerPoll = setInterval(() => pollRider(idDelivery), 5000);
    } else {
        const txt = estado === 'Entregado' ? '✅ Entregado' :
                    estado === 'No Entregado' ? '❌ No entregado' : estado;
        document.getElementById('mapa-estado').textContent = txt;
        document.getElementById('btn-centrar').style.display = 'none';
    }
}

// ── Info del rider ────────────────────────────────────────────────────────────
function actualizarInfoRider(rider, estado, direccion, tieneGPS, tieneDelivery) {
    const colores = { 'En Camino':'#06b6d4','Entregado':'#22c55e','No Entregado':'#ef4444','Pendiente':'#f59e0b' };
    const col = colores[estado] || '#94a3b8';

    if (!tieneDelivery) {
        document.getElementById('rider-info-content').innerHTML =
            `<p style="margin:0;color:var(--text-muted);">Este pedido es de tipo <b>recojo</b> o <b>en tienda</b> — no tiene delivery.</p>`;
        return;
    }

    const gpsHtml = tieneGPS
        ? `<span style="color:#22c55e;font-size:0.78rem;">● GPS activo</span>`
        : `<span style="color:#f59e0b;font-size:0.78rem;">⏳ Sin GPS aún</span>`;

    document.getElementById('rider-info-content').innerHTML = `
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.8rem;margin-bottom:0.8rem;">
            <div>
                <p style="color:var(--text-muted);font-size:0.72rem;margin:0 0 0.2rem;text-transform:uppercase;letter-spacing:0.05em;">Rider</p>
                <p style="font-weight:600;margin:0;">🏍️ ${rider}</p>
                <div style="margin-top:0.3rem;">${gpsHtml}</div>
            </div>
            <div>
                <p style="color:var(--text-muted);font-size:0.72rem;margin:0 0 0.2rem;text-transform:uppercase;letter-spacing:0.05em;">Estado</p>
                <p style="font-weight:700;color:${col};margin:0;font-size:1rem;">${estado}</p>
            </div>
        </div>
        ${direccion ? `<div style="padding-top:0.7rem;border-top:1px solid rgba(255,255,255,0.07);">
            <p style="color:var(--text-muted);font-size:0.72rem;margin:0 0 0.2rem;text-transform:uppercase;letter-spacing:0.05em;">Destino</p>
            <p style="margin:0;font-size:0.88rem;">📍 ${direccion}</p>
        </div>` : ''}
        ${tieneGPS ? '<p style="color:var(--text-muted);font-size:0.72rem;margin-top:0.6rem;margin-bottom:0;">🔄 Actualiza cada 5 segundos</p>' : ''}
    `;
}

// ── Inicializar mapa ──────────────────────────────────────────────────────────
function inicializarMapa(idDelivery, data) {
    // Coordenadas: usar GPS del rider si existe, si no Santa Cruz por defecto
    const defLat = -17.7833, defLng = -63.1821;
    const lat = (data && data.riderLat) ? parseFloat(data.riderLat) : defLat;
    const lng = (data && data.riderLng) ? parseFloat(data.riderLng) : defLng;

    const mapaDiv = document.getElementById('mapa-cliente');

    if (!mapaListo) {
        mapaDiv.innerHTML = '';
        leafletMap = L.map('mapa-cliente', { zoomControl: true }).setView([lat, lng], 15);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            maxZoom: 19
        }).addTo(leafletMap);
        mapaListo = true;
        setTimeout(() => leafletMap.invalidateSize(), 300);
    } else {
        // Limpiar marcadores anteriores
        if (riderMarker)   { leafletMap.removeLayer(riderMarker);   riderMarker = null; }
        if (destinoMarker) { leafletMap.removeLayer(destinoMarker); destinoMarker = null; }
        if (rutaLine)      { leafletMap.removeLayer(rutaLine);      rutaLine = null; }
        leafletMap.setView([lat, lng], 15);
    }

    document.getElementById('mapa-leyenda').style.display = 'flex';

    // Marcador del destino (siempre visible)
    if (data && data.direccion) {
        const iconoDest = L.divIcon({
            html: `<div style="background:#0f172a;border:2px solid #22c55e;border-radius:50% 50% 50% 0;transform:rotate(-45deg);width:34px;height:34px;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 10px rgba(34,197,94,0.5);">
                       <span style="transform:rotate(45deg);font-size:16px;">📦</span>
                   </div>`,
            iconSize: [34,34], iconAnchor: [17,34], className: ''
        });
        destinoMarker = L.marker([lat, lng], { icon: iconoDest }).addTo(leafletMap);
        destinoMarker.bindPopup(`
            <div style="min-width:180px;">
                <b style="color:#22c55e;">📦 Tu destino</b><br>
                <span style="font-size:0.82rem;">${data.direccion}</span>
                ${data.productos ? `<br><span style="font-size:0.78rem;color:#94a3b8;margin-top:4px;display:block;">🛍️ ${data.productos}</span>` : ''}
            </div>
        `).openPopup();
    }

    // Marcador del rider si tiene GPS
    if (data && data.riderLat) {
        ponerMarcadorRider(parseFloat(data.riderLat), parseFloat(data.riderLng), data.rider || 'Rider', data.estado);
    }
}

// ── Poner / mover marcador del rider ─────────────────────────────────────────
function ponerMarcadorRider(lat, lng, rider, estado) {
    const iconoRider = L.divIcon({
        html: `<div style="background:#0f172a;border:2px solid #06b6d4;border-radius:50%;width:40px;height:40px;display:flex;align-items:center;justify-content:center;box-shadow:0 0 0 4px rgba(6,182,212,0.25);">
                   <span style="font-size:20px;">🏍️</span>
               </div>`,
        iconSize: [40,40], iconAnchor: [20,20], className: ''
    });

    if (!riderMarker) {
        riderMarker = L.marker([lat, lng], { icon: iconoRider }).addTo(leafletMap);
        riderMarker.bindPopup(`<b style="color:#06b6d4;">🏍️ ${rider}</b><br><span style="font-size:0.82rem;">${estado}</span>`).openPopup();
    } else {
        riderMarker.setLatLng([lat, lng]);
        riderMarker.setPopupContent(`<b style="color:#06b6d4;">🏍️ ${rider}</b><br><span style="font-size:0.82rem;">${estado}</span>`);
    }

    // Línea de ruta rider → destino
    if (destinoMarker) {
        const dest = destinoMarker.getLatLng();
        if (rutaLine) leafletMap.removeLayer(rutaLine);
        rutaLine = L.polyline([[lat,lng],[dest.lat,dest.lng]], {
            color:'#06b6d4', weight:3, opacity:0.7, dashArray:'8,5'
        }).addTo(leafletMap);
        // Ajustar zoom para ver ambos
        leafletMap.fitBounds(L.latLngBounds([[lat,lng],[dest.lat,dest.lng]]), { padding:[50,50] });
    }
}

// ── Polling GPS del rider ─────────────────────────────────────────────────────
function pollRider(idDelivery) {
    fetch(`api_ubicacion.php?idDelivery=${idDelivery}`)
        .then(r => r.json())
        .then(d => {
            if (!d.ok) return;
            const lat = parseFloat(d.lat), lng = parseFloat(d.lng);
            if (pedidosData[idDelivery]) {
                pedidosData[idDelivery].riderLat = d.lat;
                pedidosData[idDelivery].riderLng = d.lng;
            }
            ponerMarcadorRider(lat, lng, d.rider || 'Rider', d.estado);
            document.getElementById('mapa-estado').textContent =
                '🔴 En vivo · ' + new Date().toLocaleTimeString('es-BO',{hour:'2-digit',minute:'2-digit',second:'2-digit'});
            document.getElementById('btn-centrar').style.display = 'inline-block';
        })
        .catch(() => {});
}

// ── Centrar en rider ──────────────────────────────────────────────────────────
function centrarMapa() {
    if (riderMarker && leafletMap) leafletMap.setView(riderMarker.getLatLng(), 16);
}

// ── Limpiar mapa ──────────────────────────────────────────────────────────────
function limpiarMapa(msg) {
    if (leafletMap) { leafletMap.remove(); leafletMap=null; riderMarker=null; destinoMarker=null; rutaLine=null; mapaListo=false; }
    document.getElementById('mapa-cliente').innerHTML =
        `<div style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--text-muted);text-align:center;padding:2rem;">
            <div><div style="font-size:2.5rem;margin-bottom:0.8rem;">🗺️</div><p style="margin:0;">${msg}</p></div>
        </div>`;
    document.getElementById('mapa-leyenda').style.display = 'none';
    document.getElementById('btn-centrar').style.display = 'none';
}

// ── Auto-seleccionar primer pedido ────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    const primero = document.querySelector('.pedido-card');
    if (primero) primero.click();
});
</script>
</body>
</html>
