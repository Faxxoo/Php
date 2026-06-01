<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['puesto']) || $_SESSION['puesto'] == 'Motorizado') {
    header("Location: index.php"); exit;
}
require_once 'config.php';
$esAdmin = ($_SESSION['puesto'] == 'Admin');

// Fetch active deliveries with lowercase aliases
$rows = $conn->query("
    SELECT d.idDelivery AS iddelivery, v.idVenta AS idventa,
           c.nombre AS cliente, c.noTelefono AS notelefono,
           d.direccionEscrita AS direccionescrita,
           d.estadoEntrega AS estadoentrega, d.estado,
           t.nombre AS rider, t.idTrabajador AS idrider
    FROM delivery d
    JOIN venta v ON d.idVenta = v.idVenta
    JOIN cliente c ON v.idCliente = c.idCliente
    LEFT JOIN trabajador t ON d.idMotorizado = t.idTrabajador
    WHERE d.estado = 'Activo'
    ORDER BY d.fechaRegistro DESC
")->fetchAll();

$riders = [];
if ($esAdmin) {
    $riders = $conn->query("
        SELECT idTrabajador AS idtrabajador, nombre, apellido
        FROM trabajador WHERE puesto = 'Motorizado' ORDER BY nombre
    ")->fetchAll();
}

// Build marker data for map
$markersJson = json_encode(array_map(fn($r) => [
    'idventa'       => (int)$r['idventa'],
    'cliente'       => $r['cliente'],
    'direccion'     => $r['direccionescrita'],
    'estadoentrega' => $r['estadoentrega'],
    'rider'         => $r['rider'] ?? '',
], $rows), JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel <?php echo $esAdmin ? 'Administrador' : 'Supervisor'; ?></title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        #mapa-admin { height:280px; width:100%; }
        .map-wrap { padding:0; overflow:hidden; margin-bottom:1.5rem; }
        .map-wrap-header { padding:0.9rem 1.2rem; border-bottom:1px solid rgba(255,255,255,0.07); display:flex; justify-content:space-between; align-items:center; }
    </style>
</head>
<body>
<div class="app-wrapper">

    <nav class="navbar">
        <h2>Tienda Delivery <span>| <?php echo $esAdmin ? 'Admin' : 'Supervisor'; ?></span></h2>
        <div class="nav-user">
            <span>👤 Hola, <?php echo htmlspecialchars($_SESSION['nombre']); ?></span>
            <?php if ($esAdmin): ?>
            <a href="graficos.php" class="btn" style="padding:0.5rem 1.2rem;width:auto;background:rgba(59,130,246,0.2);color:#60a5fa;border:1px solid rgba(59,130,246,0.5);margin-right:4px;">📊 Gráficos</a>
            <a href="admin_productos.php" class="btn" style="padding:0.5rem 1.2rem;width:auto;background:rgba(34,197,94,0.2);color:#4ade80;border:1px solid rgba(34,197,94,0.5);margin-right:4px;">📦 Productos</a>
            <a href="admin_trabajadores.php" class="btn" style="padding:0.5rem 1.2rem;width:auto;background:rgba(245,158,11,0.2);color:#fbbf24;border:1px solid rgba(245,158,11,0.5);margin-right:4px;">👥 Trabajadores</a>
            <?php endif; ?>
            <a href="logout.php" class="btn" style="padding:0.5rem 1.2rem;width:auto;background:rgba(239,68,68,0.2);color:var(--danger);border:1px solid rgba(239,68,68,0.5);">Cerrar Sesión</a>
        </div>
    </nav>

    <div class="dashboard-container">

        <?php if ($esAdmin): ?>
        <div class="admin-stats-bar">
            <div class="stat-chip">
                <span class="stat-num"><?php echo count($rows); ?></span>
                <span class="stat-label">Entregas activas</span>
            </div>
            <div class="stat-chip">
                <span class="stat-num"><?php echo count($riders); ?></span>
                <span class="stat-label">Riders</span>
            </div>
            <div class="stat-chip">
                <?php $enCamino = count(array_filter($rows, fn($r) => $r['estadoentrega'] === 'En Camino')); ?>
                <span class="stat-num"><?php echo $enCamino; ?></span>
                <span class="stat-label">En camino</span>
            </div>
            <div class="stat-chip">
                <?php $sinRider = count(array_filter($rows, fn($r) => !$r['rider'])); ?>
                <span class="stat-num"><?php echo $sinRider; ?></span>
                <span class="stat-label">Sin rider</span>
            </div>
        </div>
        <?php endif; ?>

        <!-- ══ MAPA COMPACTO ══ -->
        <div class="glass-card map-wrap">
            <div class="map-wrap-header">
                <h4 style="margin:0;font-size:1rem;">📍 Mapa de Entregas Activas</h4>
                <span style="font-size:0.78rem;color:var(--text-muted);"><?php echo count($rows); ?> entrega<?php echo count($rows)!=1?'s':''; ?> activa<?php echo count($rows)!=1?'s':''; ?></span>
            </div>
            <div id="mapa-admin"></div>
        </div>

        <script>
        (function(){
            var map = L.map('mapa-admin').setView([-17.7833,-63.1821], 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:'© OpenStreetMap',maxZoom:19}).addTo(map);
            var deliveries = <?php echo $markersJson; ?>;
            var colores = {'Pendiente':'#f59e0b','En Camino':'#06b6d4','Entregado':'#22c55e','No Entregado':'#ef4444'};
            var bounds = [];
            deliveries.forEach(function(d,i){
                var lat = -17.7833 + (i*0.003) - (deliveries.length*0.0015);
                var lng = -63.1821 + ((i%3-1)*0.004);
                var col = colores[d.estadoentrega] || '#94a3b8';
                var icon = L.divIcon({
                    html:'<div style="background:#0f172a;border:2px solid '+col+';border-radius:50%;width:30px;height:30px;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 6px rgba(0,0,0,0.4);font-size:14px;">📦</div>',
                    iconSize:[30,30],iconAnchor:[15,15],className:''
                });
                var rider = d.rider ? '🏍️ '+d.rider : '<em style="color:#94a3b8">Sin rider</em>';
                L.marker([lat,lng],{icon:icon}).addTo(map)
                 .bindPopup('<b>#'+String(d.idventa).padStart(4,'0')+'</b> — '+d.cliente+'<br>'+
                            '<span style="font-size:0.82rem;color:'+col+'">● '+d.estadoentrega+'</span><br>'+
                            rider+'<br><span style="font-size:0.78rem;color:#94a3b8;">'+d.direccion+'</span>');
                bounds.push([lat,lng]);
            });
            if(bounds.length>1) map.fitBounds(bounds,{padding:[30,30],maxZoom:15});
            else if(bounds.length===1) map.setView(bounds[0],15);
            setTimeout(function(){ map.invalidateSize(); },300);
        })();
        </script>

        <!-- ══ TABLA DE ENTREGAS ══ -->
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.2rem;">
            <h3 style="font-size:1.4rem;margin:0;">Entregas Activas</h3>
            <span class="badge" style="background:var(--primary);color:white;"><?php echo count($rows); ?></span>
        </div>

        <div class="glass-card" style="padding:0;">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th># Orden</th>
                            <th>Cliente</th>
                            <th>Teléfono</th>
                            <th>Dirección</th>
                            <th>Rider</th>
                            <th>Estado</th>
                            <?php if ($esAdmin): ?><th style="text-align:center;">Acciones</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($rows) > 0): ?>
                        <?php foreach ($rows as $row): ?>
                        <tr>
                            <td style="font-weight:600;">#<?php echo str_pad($row['idventa'],4,'0',STR_PAD_LEFT); ?></td>
                            <td><?php echo htmlspecialchars($row['cliente']); ?></td>
                            <td><?php echo htmlspecialchars($row['notelefono']); ?></td>
                            <td style="max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo htmlspecialchars($row['direccionescrita']); ?></td>
                            <td><?php echo $row['rider'] ? '🏍️ '.htmlspecialchars($row['rider']) : '<span style="color:var(--text-muted);font-style:italic;">No asignado</span>'; ?></td>
                            <td><span class="badge <?php echo str_replace(' ','-',$row['estadoentrega']); ?>"><?php echo htmlspecialchars($row['estadoentrega']); ?></span></td>
                            <?php if ($esAdmin): ?>
                            <td>
                                <div class="admin-acciones">
                                    <form action="admin_acciones.php" method="POST" style="display:flex;gap:0.4rem;align-items:center;">
                                        <input type="hidden" name="accion" value="asignar_rider">
                                        <input type="hidden" name="idDelivery" value="<?php echo $row['iddelivery']; ?>">
                                        <select name="idMotorizado" class="form-control" style="padding:0.4rem 0.6rem;font-size:0.8rem;height:auto;width:auto;min-width:130px;">
                                            <option value="">Sin rider</option>
                                            <?php foreach ($riders as $r): ?>
                                            <option value="<?php echo $r['idtrabajador']; ?>" <?php echo $r['idtrabajador'] == $row['idrider'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($r['nombre'].' '.$r['apellido']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn admin-btn-sm" style="background:rgba(59,130,246,0.25);color:#60a5fa;border:1px solid rgba(59,130,246,0.4);">Asignar</button>
                                    </form>
                                    <form action="admin_acciones.php" method="POST" onsubmit="return confirm('¿Finalizar esta entrega?');">
                                        <input type="hidden" name="accion" value="cerrar_entrega">
                                        <input type="hidden" name="idDelivery" value="<?php echo $row['iddelivery']; ?>">
                                        <button type="submit" class="btn admin-btn-sm" style="background:rgba(34,197,94,0.2);color:var(--success);border:1px solid rgba(34,197,94,0.4);">✓ Finalizar</button>
                                    </form>
                                </div>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <tr><td colspan="<?php echo $esAdmin?7:6; ?>" style="text-align:center;padding:3rem;color:var(--text-muted);">No hay entregas activas.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($esAdmin): ?>
        <h3 style="font-size:1.2rem;margin:2rem 0 1rem;">📋 Historial Reciente</h3>
        <div class="glass-card" style="padding:0;">
            <div class="table-wrapper">
                <?php
                $historial = $conn->query("
                    SELECT d.idDelivery, v.idVenta AS idventa, c.nombre AS cliente,
                           d.direccionEscrita AS direccionescrita,
                           d.estadoEntrega AS estadoentrega,
                           t.nombre AS rider, d.fechaRegistro AS fecharegistro
                    FROM delivery d
                    JOIN venta v ON d.idVenta = v.idVenta
                    JOIN cliente c ON v.idCliente = c.idCliente
                    LEFT JOIN trabajador t ON d.idMotorizado = t.idTrabajador
                    WHERE d.estado = 'Finalizado'
                    ORDER BY d.fechaRegistro DESC LIMIT 10
                ")->fetchAll();
                ?>
                <table>
                    <thead>
                        <tr><th># Orden</th><th>Cliente</th><th>Dirección</th><th>Rider</th><th>Estado</th><th>Fecha</th></tr>
                    </thead>
                    <tbody>
                        <?php if (count($historial) > 0): ?>
                        <?php foreach ($historial as $h): ?>
                        <tr style="opacity:0.7;">
                            <td style="font-weight:600;">#<?php echo str_pad($h['idventa'],4,'0',STR_PAD_LEFT); ?></td>
                            <td><?php echo htmlspecialchars($h['cliente']); ?></td>
                            <td><?php echo htmlspecialchars($h['direccionescrita']); ?></td>
                            <td><?php echo $h['rider'] ? '🏍️ '.htmlspecialchars($h['rider']) : '<span style="color:var(--text-muted)">—</span>'; ?></td>
                            <td><span class="badge <?php echo str_replace(' ','-',$h['estadoentrega']); ?>"><?php echo $h['estadoentrega']; ?></span></td>
                            <td style="color:var(--text-muted);font-size:0.82rem;"><?php echo date('d/m/Y H:i', strtotime($h['fecharegistro'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <tr><td colspan="6" style="text-align:center;padding:2rem;color:var(--text-muted);">Sin historial aún.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>
</body>
</html>
