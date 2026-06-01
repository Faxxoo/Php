<?php
/**
 * generar_qr.php
 * Genera y muestra el QR de un pedido entregado.
 * Solo accesible para el cliente dueño del pedido.
 */
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['tipo']) || $_SESSION['tipo'] != 'cliente') {
    http_response_code(403); exit('No autorizado');
}
require_once 'config.php';

$idVenta = intval($_GET['idVenta'] ?? 0);
if ($idVenta <= 0) { http_response_code(400); exit('Pedido inválido'); }

// Verificar que el pedido pertenece al cliente y está Entregado
$stmt = $conn->prepare("
    SELECT v.idVenta AS idventa, v.precioTotal AS preciototal, v.fechaVenta AS fechaventa,
           c.nombre AS cliente, c.apellido AS apellido_cliente,
           t.nombre AS rider_nombre, t.apellido AS rider_apellido,
           d.estadoEntrega AS estadoentrega, d.direccionEscrita AS direccionescrita
    FROM venta v
    JOIN cliente c ON v.idCliente = c.idCliente
    LEFT JOIN delivery d ON d.idVenta = v.idVenta
    LEFT JOIN trabajador t ON d.idMotorizado = t.idTrabajador
    WHERE v.idVenta = :vid AND v.idCliente = :cid
    LIMIT 1
");
$stmt->execute([':vid' => $idVenta, ':cid' => $_SESSION['user_id']]);
$pedido = $stmt->fetch();

if (!$pedido) { http_response_code(404); exit('Pedido no encontrado'); }

// Cargar productos del pedido
$dStmt = $conn->prepare("
    SELECT p.nombre AS producto, vd.cantidad, vd.subtotal
    FROM ventaDetalle vd
    JOIN productos p ON vd.idProducto = p.idProducto
    WHERE vd.idVenta = :vid
");
$dStmt->execute([':vid' => $idVenta]);
$detalles = $dStmt->fetchAll();

$productos_str = implode(' | ', array_map(
    fn($d) => $d['cantidad'].'x '.$d['producto'].' Bs'.number_format($d['subtotal'],2),
    $detalles
));

// Construir el texto del QR (formato recibo)
$num = str_pad($idVenta, 4, '0', STR_PAD_LEFT);
$rider = $pedido['rider_nombre']
    ? trim($pedido['rider_nombre'].' '.$pedido['rider_apellido'])
    : 'No asignado';
$cliente_full = trim($pedido['cliente'].' '.$pedido['apellido_cliente']);
$fecha = date('d/m/Y H:i', strtotime($pedido['fechaventa']));

$texto_qr = implode("\n", [
    "=== TIENDA DELIVERY ===",
    "RECIBO DE ENTREGA",
    "-------------------",
    "Pedido: #$num",
    "Fecha: $fecha",
    "Cliente: $cliente_full",
    "Rider: $rider",
    "-------------------",
    "PRODUCTOS:",
    $productos_str,
    "-------------------",
    "TOTAL: Bs ".number_format($pedido['preciototal'], 2),
    "Estado: ".$pedido['estadoentrega'],
    "=== GRACIAS POR SU COMPRA ===",
]);

// Modo: 'imagen' devuelve solo el PNG, 'pagina' devuelve HTML completo
$modo = $_GET['modo'] ?? 'pagina';

if ($modo === 'imagen') {
    // Proxy de la imagen QR para evitar problemas CORS
    $url = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($texto_qr);
    $img = @file_get_contents($url);
    if ($img) {
        header('Content-Type: image/png');
        header('Content-Disposition: inline; filename="qr_pedido_'.$num.'.png"');
        echo $img;
    } else {
        http_response_code(503);
        echo 'No se pudo generar el QR';
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Recibo #<?php echo $num; ?> - Tienda Delivery</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .qr-page { min-height:100vh; display:flex; align-items:center; justify-content:center; padding:2rem; }
        .qr-card {
            background: rgba(15,23,42,0.9); backdrop-filter:blur(20px);
            border: 1px solid rgba(255,255,255,0.1); border-radius:24px;
            padding: 2.5rem; max-width:480px; width:100%;
            box-shadow: 0 30px 60px rgba(0,0,0,0.5);
            animation: fadeSlideUp 0.5s ease;
        }
        @keyframes fadeSlideUp {
            from { opacity:0; transform:translateY(20px); }
            to   { opacity:1; transform:translateY(0); }
        }
        .qr-header { text-align:center; margin-bottom:1.8rem; }
        .qr-logo { font-size:2.5rem; margin-bottom:0.5rem; }
        .qr-header h2 { margin:0 0 0.3rem; font-size:1.4rem; }
        .qr-header p { color:var(--text-muted); font-size:0.85rem; margin:0; }

        .qr-img-wrap {
            display:flex; justify-content:center; margin:1.5rem 0;
            background: white; border-radius:16px; padding:1rem;
        }
        .qr-img-wrap img { width:220px; height:220px; display:block; border-radius:8px; }

        .recibo-grid {
            display:grid; grid-template-columns:1fr 1fr; gap:0.8rem;
            margin-bottom:1.2rem;
        }
        .recibo-item {
            background: rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08);
            border-radius:10px; padding:0.7rem 0.9rem;
        }
        .recibo-item .ri-label {
            font-size:0.68rem; color:var(--text-muted); text-transform:uppercase;
            letter-spacing:0.05em; margin-bottom:0.2rem;
        }
        .recibo-item .ri-val { font-size:0.9rem; font-weight:600; }

        .recibo-productos {
            background: rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.07);
            border-radius:10px; padding:0.9rem; margin-bottom:1.2rem;
        }
        .recibo-productos .rp-title {
            font-size:0.72rem; color:var(--text-muted); text-transform:uppercase;
            letter-spacing:0.05em; margin-bottom:0.6rem;
        }
        .recibo-prod-row {
            display:flex; justify-content:space-between; font-size:0.85rem;
            color:var(--text-muted); padding:0.25rem 0;
            border-bottom:1px solid rgba(255,255,255,0.05);
        }
        .recibo-prod-row:last-child { border-bottom:none; }

        .recibo-total {
            display:flex; justify-content:space-between; align-items:center;
            background: linear-gradient(135deg, rgba(99,102,241,0.15), rgba(6,182,212,0.15));
            border:1px solid rgba(99,102,241,0.3); border-radius:12px;
            padding:1rem 1.2rem; margin-bottom:1.5rem;
        }
        .recibo-total .rt-label { font-size:0.85rem; color:var(--text-muted); }
        .recibo-total .rt-val {
            font-size:1.5rem; font-weight:800;
            background: linear-gradient(90deg,#6366f1,#06b6d4);
            -webkit-background-clip:text; background-clip:text; -webkit-text-fill-color:transparent;
        }

        .estado-badge {
            display:inline-flex; align-items:center; gap:0.4rem;
            padding:0.3rem 0.8rem; border-radius:20px; font-size:0.8rem; font-weight:600;
        }
        .estado-Entregado { background:rgba(34,197,94,0.15); color:#22c55e; border:1px solid rgba(34,197,94,0.3); }

        .btn-actions { display:flex; gap:0.8rem; }
        .btn-dl {
            flex:1; padding:0.75rem; border:none; border-radius:10px;
            font-size:0.9rem; font-weight:600; cursor:pointer; transition:all 0.2s;
            display:flex; align-items:center; justify-content:center; gap:0.4rem;
            text-decoration:none;
        }
        .btn-dl-primary {
            background: linear-gradient(135deg,#6366f1,#06b6d4); color:white;
            box-shadow:0 4px 15px rgba(99,102,241,0.3);
        }
        .btn-dl-primary:hover { opacity:0.9; transform:translateY(-1px); }
        .btn-dl-secondary {
            background:rgba(255,255,255,0.06); color:var(--text-muted);
            border:1px solid rgba(255,255,255,0.1);
        }
        .btn-dl-secondary:hover { background:rgba(255,255,255,0.1); color:white; }

        .qr-footer { text-align:center; margin-top:1.2rem; color:var(--text-muted); font-size:0.75rem; }
    </style>
</head>
<body style="background:#0a0f1e; margin:0;">
<div class="qr-page">
    <div class="qr-card">

        <!-- Header -->
        <div class="qr-header">
            <div class="qr-logo">🏍️</div>
            <h2>Recibo de Entrega</h2>
            <p>Pedido #<?php echo $num; ?> · <?php echo $fecha; ?></p>
        </div>

        <!-- QR Image -->
        <div class="qr-img-wrap">
            <img src="generar_qr.php?idVenta=<?php echo $idVenta; ?>&modo=imagen"
                 alt="QR Recibo #<?php echo $num; ?>"
                 id="qr-img"
                 onerror="this.src='https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=<?php echo urlencode($texto_qr); ?>'">
        </div>

        <!-- Info grid -->
        <div class="recibo-grid">
            <div class="recibo-item">
                <div class="ri-label">Cliente</div>
                <div class="ri-val">👤 <?php echo htmlspecialchars($cliente_full); ?></div>
            </div>
            <div class="recibo-item">
                <div class="ri-label">Rider</div>
                <div class="ri-val">🏍️ <?php echo htmlspecialchars($rider); ?></div>
            </div>
            <div class="recibo-item">
                <div class="ri-label">Estado</div>
                <div class="ri-val">
                    <span class="estado-badge estado-<?php echo str_replace(' ','-',$pedido['estadoentrega']); ?>">
                        ✅ <?php echo htmlspecialchars($pedido['estadoentrega']); ?>
                    </span>
                </div>
            </div>
            <div class="recibo-item">
                <div class="ri-label">Dirección</div>
                <div class="ri-val" style="font-size:0.78rem;font-weight:400;color:var(--text-muted);">
                    📍 <?php echo htmlspecialchars(substr($pedido['direccionescrita'] ?? 'N/A', 0, 40)); ?>
                </div>
            </div>
        </div>

        <!-- Productos -->
        <div class="recibo-productos">
            <div class="rp-title">🛍️ Productos</div>
            <?php foreach ($detalles as $det): ?>
            <div class="recibo-prod-row">
                <span><?php echo $det['cantidad']; ?>× <?php echo htmlspecialchars($det['producto']); ?></span>
                <span style="color:var(--accent);">Bs <?php echo number_format($det['subtotal'],2); ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Total -->
        <div class="recibo-total">
            <span class="rt-label">Total pagado</span>
            <span class="rt-val">Bs <?php echo number_format($pedido['preciototal'],2); ?></span>
        </div>

        <!-- Botones -->
        <div class="btn-actions">
            <a href="generar_qr.php?idVenta=<?php echo $idVenta; ?>&modo=imagen"
               download="recibo_<?php echo $num; ?>.png"
               class="btn-dl btn-dl-primary">
                ⬇️ Descargar QR
            </a>
            <a href="cliente.php" class="btn-dl btn-dl-secondary">
                ← Mis Pedidos
            </a>
        </div>

        <div class="qr-footer">
            Escanea el QR para ver los detalles de tu pedido<br>
            Tienda Delivery · <?php echo date('Y'); ?>
        </div>

    </div>
</div>
</body>
</html>
