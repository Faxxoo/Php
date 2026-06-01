<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['puesto']) || $_SESSION['puesto'] == 'Motorizado') {
    header("Location: index.php"); exit;
}
require_once 'config.php';

$msg = null;
$error = null;

// Agregar producto
if (isset($_POST['accion']) && $_POST['accion'] == 'agregar') {
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $precio = floatval($_POST['precio'] ?? 0);

    if (empty($nombre) || $precio <= 0) {
        $error = "El nombre y un precio mayor a 0 son obligatorios.";
    } else {
        $stmt = $conn->prepare("INSERT INTO productos (nombre, descripcion, precioUnitario) VALUES (:n, :d, :p)");
        $stmt->execute([':n' => $nombre, ':d' => $descripcion, ':p' => $precio]);
        $msg = "Producto '$nombre' agregado correctamente.";
    }
}

// Eliminar producto
if (isset($_POST['accion']) && $_POST['accion'] == 'eliminar') {
    $id = intval($_POST['idProducto'] ?? 0);
    // Verificar que no tenga ventas asociadas
    $check = $conn->prepare("SELECT COUNT(*) as total FROM ventaDetalle WHERE idProducto = :id");
    $check->execute([':id' => $id]);
    $row = $check->fetch();
    $count = $row ? (int)$row['total'] : 0;
    if ($count > 0) {
        $error = "No se puede eliminar: el producto tiene $count venta(s) registrada(s).";
    } else {
        $conn->prepare("DELETE FROM productos WHERE idProducto = :id")->execute([':id' => $id]);
        $msg = "Producto eliminado.";
    }
}

// Editar producto
if (isset($_POST['accion']) && $_POST['accion'] == 'editar') {
    $id = intval($_POST['idProducto'] ?? 0);
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $precio = floatval($_POST['precio'] ?? 0);

    if (empty($nombre) || $precio <= 0) {
        $error = "El nombre y un precio mayor a 0 son obligatorios.";
    } else {
        $stmt = $conn->prepare("UPDATE productos SET nombre=:n, descripcion=:d, precioUnitario=:p WHERE idProducto=:id");
        $stmt->execute([':n' => $nombre, ':d' => $descripcion, ':p' => $precio, ':id' => $id]);
        $msg = "Producto actualizado correctamente.";
    }
}

// Cargar productos
$productos = $conn->query("
    SELECT idProducto AS idproducto,
           nombre,
           descripcion,
           precioUnitario AS preciounitario
    FROM productos
    ORDER BY nombre
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Productos</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
<div class="app-wrapper">
    <nav class="navbar">
        <h2>Tienda Delivery <span>| Productos</span></h2>
        <div class="nav-user">
            <span>👤 <?php echo htmlspecialchars($_SESSION['nombre']); ?></span>
            <a href="admin.php" class="btn" style="padding:0.5rem 1.2rem;width:auto;background:rgba(99,102,241,0.2);color:#a5b4fc;border:1px solid rgba(99,102,241,0.4);">
                ← Panel Admin
            </a>
            <a href="logout.php" class="btn" style="padding:0.5rem 1rem;width:auto;background:rgba(239,68,68,0.2);color:var(--danger);border:1px solid rgba(239,68,68,0.5);">
                Salir
            </a>
        </div>
    </nav>

    <div class="dashboard-container">

        <?php if ($msg): ?>
        <div style="margin-bottom:1.5rem; padding:1rem 1.5rem; background:rgba(34,197,94,0.15); border:1px solid rgba(34,197,94,0.3); border-radius:12px; color:#22c55e;">
            ✅ <?php echo htmlspecialchars($msg); ?>
        </div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="error" style="margin-bottom:1.5rem;">
            ❌ <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <div style="display:grid; grid-template-columns:1fr 1.6fr; gap:2rem; align-items:start;">

            <!-- Formulario agregar -->
            <div class="glass-card" id="form-card">
                <h3 style="margin:0 0 1.5rem; font-size:1.2rem;" id="form-titulo">➕ Agregar Producto</h3>
                <form method="POST" id="form-producto">
                    <input type="hidden" name="accion" value="agregar" id="form-accion">
                    <input type="hidden" name="idProducto" value="" id="form-id">

                    <div class="form-group">
                        <label>Nombre del producto *</label>
                        <input type="text" name="nombre" id="form-nombre" class="form-control" required placeholder="Ej: Hamburguesa">
                    </div>
                    <div class="form-group">
                        <label>Descripción</label>
                        <input type="text" name="descripcion" id="form-descripcion" class="form-control" placeholder="Ej: Doble carne con queso">
                    </div>
                    <div class="form-group">
                        <label>Precio (Bs) *</label>
                        <input type="number" name="precio" id="form-precio" class="form-control" required min="0.01" step="0.01" placeholder="0.00">
                    </div>

                    <div style="display:flex; gap:0.8rem;">
                        <button type="submit" class="btn btn-glow" style="flex:1;">
                            💾 Guardar
                        </button>
                        <button type="button" onclick="resetForm()" class="btn" style="width:auto;padding:0.8rem 1.2rem;background:rgba(255,255,255,0.05);color:var(--text-muted);border:1px solid rgba(255,255,255,0.1);">
                            ✕ Cancelar
                        </button>
                    </div>
                </form>
            </div>

            <!-- Lista de productos -->
            <div class="glass-card" style="padding:0;">
                <div style="padding:1.2rem 1.5rem; border-bottom:1px solid rgba(255,255,255,0.07); display:flex; justify-content:space-between; align-items:center;">
                    <h3 style="margin:0; font-size:1.2rem;">📦 Productos disponibles</h3>
                    <span class="badge" style="background:var(--primary);color:white;"><?php echo count($productos); ?> productos</span>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Nombre</th>
                                <th>Descripción</th>
                                <th>Precio</th>
                                <th style="text-align:center;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($productos) == 0): ?>
                            <tr><td colspan="5" style="text-align:center;padding:2rem;color:var(--text-muted);">No hay productos. Agrega el primero.</td></tr>
                            <?php else: ?>
                            <?php foreach ($productos as $p): ?>
                            <tr>
                                <td style="color:var(--text-muted);"><?php echo $p['idproducto']; ?></td>
                                <td style="font-weight:600;"><?php echo htmlspecialchars($p['nombre']); ?></td>
                                <td style="color:var(--text-muted); font-size:0.85rem;"><?php echo htmlspecialchars($p['descripcion'] ?? '—'); ?></td>
                                <td style="color:var(--accent); font-weight:600;">Bs <?php echo number_format($p['preciounitario'], 2); ?></td>
                                <td>
                                    <div style="display:flex; gap:0.5rem; justify-content:center;">
                                        <button onclick="editarProducto(<?php echo $p['idproducto']; ?>, '<?php echo addslashes($p['nombre']); ?>', '<?php echo addslashes($p['descripcion'] ?? ''); ?>', <?php echo $p['preciounitario']; ?>)"
                                            class="btn admin-btn-sm" style="background:rgba(59,130,246,0.2);color:#60a5fa;border:1px solid rgba(59,130,246,0.4);">
                                            ✏️ Editar
                                        </button>
                                        <form method="POST" onsubmit="return confirm('¿Eliminar este producto?');" style="display:inline;">
                                            <input type="hidden" name="accion" value="eliminar">
                                            <input type="hidden" name="idProducto" value="<?php echo $p['idproducto']; ?>">
                                            <button type="submit" class="btn admin-btn-sm" style="background:rgba(239,68,68,0.2);color:var(--danger);border:1px solid rgba(239,68,68,0.4);">
                                                🗑️ Borrar
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function editarProducto(id, nombre, descripcion, precio) {
    document.getElementById('form-titulo').textContent = '✏️ Editar Producto';
    document.getElementById('form-accion').value = 'editar';
    document.getElementById('form-id').value = id;
    document.getElementById('form-nombre').value = nombre;
    document.getElementById('form-descripcion').value = descripcion;
    document.getElementById('form-precio').value = precio;
    document.getElementById('form-card').scrollIntoView({ behavior: 'smooth' });
}

function resetForm() {
    document.getElementById('form-titulo').textContent = '➕ Agregar Producto';
    document.getElementById('form-accion').value = 'agregar';
    document.getElementById('form-id').value = '';
    document.getElementById('form-producto').reset();
}
</script>
</body>
</html>
