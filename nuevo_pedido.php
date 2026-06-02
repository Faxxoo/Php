<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['tipo']) || $_SESSION['tipo'] != 'cliente') {
    header("Location: index.php?tab=cliente"); exit;
}
require_once 'config.php';
$cliente_id = $_SESSION['user_id'];
$error = null;

// ── PROCESAR PEDIDO ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $productos_ids = $_POST['producto_id'] ?? [];
    $cantidades    = $_POST['cantidad']    ?? [];
    $tipo_entrega  = $_POST['tipo_entrega'] ?? 'Delivery';
    $direccion     = trim($_POST['direccion'] ?? '');
    $notas         = trim($_POST['notas'] ?? '');

    if ($tipo_entrega == 'Delivery' && empty($direccion)) {
        $error = "Debes ingresar una dirección de entrega.";
    } else {
        $total = 0;
        $items = [];
        foreach ($productos_ids as $idx => $pid) {
            $pid  = intval($pid);
            $cant = intval($cantidades[$idx] ?? 0);
            if ($cant <= 0) continue;
            $pStmt = $conn->prepare("SELECT idProducto AS id, nombre, precioUnitario AS precio FROM productos WHERE idProducto = :id");
            $pStmt->execute([':id' => $pid]);
            $prod = $pStmt->fetch();
            if ($prod) {
                $sub    = $prod['precio'] * $cant;
                $total += $sub;
                $items[] = ['id' => $prod['id'], 'nombre' => $prod['nombre'],
                            'precio' => $prod['precio'], 'cant' => $cant, 'sub' => $sub];
            }
        }
        if (empty($items)) {
            $error = "Agrega al menos un producto con cantidad mayor a 0.";
        } else {
            $tStmt = $conn->query("SELECT idTrabajador AS id FROM trabajador WHERE puesto IN ('Admin','Caja','Supervisor') LIMIT 1");
            $trab  = $tStmt->fetch();
            if (!$trab) {
                $error = "No hay personal disponible. Contacta al administrador.";
            } else {
                $conn->beginTransaction();
                try {
                    $vStmt = $conn->prepare("INSERT INTO venta (idTrabajador,idCliente,precioTotal,tipoEntrega) VALUES (:t,:c,:p,:e)");
                    $vStmt->execute([':t'=>$trab['id'],':c'=>$cliente_id,':p'=>$total,':e'=>$tipo_entrega]);
                    $idVenta = $conn->lastInsertId();

                    $dStmt = $conn->prepare("INSERT INTO ventaDetalle (idVenta,idProducto,cantidad,precioUnitario,subtotal) VALUES (:v,:p,:c,:pu,:s)");
                    foreach ($items as $it) {
                        $dStmt->execute([':v'=>$idVenta,':p'=>$it['id'],':c'=>$it['cant'],':pu'=>$it['precio'],':s'=>$it['sub']]);
                    }
                    if ($tipo_entrega == 'Delivery') {
                        $dir = $direccion . ($notas ? " | Notas: $notas" : '');
                        $conn->prepare("INSERT INTO delivery (idVenta,estadoEntrega,direccionEscrita,estado) VALUES (:v,'Pendiente',:d,'Activo')")
                             ->execute([':v'=>$idVenta,':d'=>$dir]);
                    }
                    $conn->commit();
                    $num = str_pad($idVenta, 4, '0', STR_PAD_LEFT);
                    header("Location: cliente.php?ok=" . urlencode("¡Pedido #$num realizado! El admin asignará un rider pronto."));
                    exit;
                } catch (Exception $e) {
                    $conn->rollBack();
                    $error = "Error al procesar: " . $e->getMessage();
                }
            }
        }
    }
}

// ── CARGAR PRODUCTOS ─────────────────────────────────────────────────────────
$productos = $conn->query("SELECT idProducto AS id, nombre, descripcion, precioUnitario AS precio FROM productos ORDER BY nombre")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Pedido - Tienda Delivery</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .pedido-layout { display: grid; grid-template-columns: 1fr 360px; gap: 2rem; align-items: start; }
        @media (max-width: 900px) { .pedido-layout { grid-template-columns: 1fr; } }

        .prod-card {
            display: flex; align-items: center; gap: 1rem;
            padding: 1rem 1.2rem; border-radius: 14px;
            border: 1.5px solid rgba(255,255,255,0.07);
            background: rgba(255,255,255,0.03);
            transition: border-color 0.2s, background 0.2s;
        }
        .prod-card.activo {
            border-color: rgba(6,182,212,0.5);
            background: rgba(6,182,212,0.07);
        }
        .qty-btn {
            width: 34px; height: 34px; border-radius: 50%;
            border: 1.5px solid rgba(255,255,255,0.2);
            background: rgba(255,255,255,0.06);
            color: white; font-size: 1.2rem; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: background 0.15s;
        }
        .qty-btn:hover { background: rgba(6,182,212,0.25); border-color: rgba(6,182,212,0.6); }
        .qty-input {
            width: 48px; text-align: center;
            background: rgba(255,255,255,0.08);
            border: 1.5px solid rgba(255,255,255,0.15);
            border-radius: 8px; color: white;
            padding: 0.3rem; font-size: 1rem; font-weight: 600;
        }
        .tipo-card {
            padding: 1.1rem; border-radius: 12px; text-align: center; cursor: pointer;
            border: 2px solid rgba(255,255,255,0.1);
            background: rgba(255,255,255,0.03);
            transition: all 0.2s;
        }
        .tipo-card.sel { border-color: rgba(6,182,212,0.6); background: rgba(6,182,212,0.1); }
        .carrito-item { display:flex; justify-content:space-between; align-items:center;
            padding: 0.6rem 0; border-bottom: 1px solid rgba(255,255,255,0.06); font-size:0.9rem; }
        .carrito-item:last-child { border-bottom: none; }
        .badge-cant {
            display:inline-flex; align-items:center; justify-content:center;
            width:22px; height:22px; border-radius:50%;
            background: rgba(6,182,212,0.3); color: var(--accent);
            font-size:0.75rem; font-weight:700; margin-right:0.4rem;
        }
        .step-num {
            display:inline-flex; align-items:center; justify-content:center;
            width:28px; height:28px; border-radius:50%;
            background: rgba(6,182,212,0.2); color: var(--accent);
            font-weight:700; font-size:0.9rem; margin-right:0.6rem; flex-shrink:0;
        }
        #btn-confirmar:disabled { opacity:0.4; cursor:not-allowed; }
        #btn-confirmar:disabled:hover { transform:none; }
    </style>
</head>
<body>
<div class="app-wrapper">
    <nav class="navbar">
        <h2>Tienda Delivery <span>| Nuevo Pedido</span></h2>
        <div class="nav-user">
            <span>🛒 <?php echo htmlspecialchars($_SESSION['nombre']); ?></span>
            <a href="cliente.php" class="btn" style="padding:0.5rem 1.2rem;width:auto;background:rgba(99,102,241,0.2);color:#a5b4fc;border:1px solid rgba(99,102,241,0.4);">← Mis Pedidos</a>
            <a href="logout.php" class="btn" style="padding:0.5rem 1rem;width:auto;background:rgba(239,68,68,0.2);color:var(--danger);border:1px solid rgba(239,68,68,0.5);">Salir</a>
        </div>
    </nav>

    <div class="dashboard-container">
        <?php if ($error): ?>
        <div class="error" style="margin-bottom:1.5rem;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:8px;flex-shrink:0"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <form method="POST" id="form-pedido">
        <div class="pedido-layout">

        <!-- ══ COLUMNA IZQUIERDA ══════════════════════════════════════════════ -->
        <div>

            <!-- PASO 1: Productos -->
            <div class="glass-card" style="margin-bottom:1.5rem;">
                <h3 style="margin:0 0 1.5rem; font-size:1.2rem; display:flex; align-items:center;">
                    <span class="step-num">1</span> Elige tus productos
                </h3>

                <?php if (empty($productos)): ?>
                <p style="color:var(--text-muted); text-align:center; padding:2rem;">
                    No hay productos disponibles. El administrador debe agregarlos.
                </p>
                <?php else: ?>

                <!-- Buscador de productos -->
                 <div style="margin-bottom:1rem; display:flex; gap:10px;">

                    <input
                        type="text"
                        id="buscador"
                        placeholder="🔍 Buscar producto..."
                        oninput="filtrarProductos()"
                        style="
                            flex:1;
                            padding:0.7rem 1rem;
                            background:rgba(255,255,255,0.06);
                            border:1.5px solid rgba(255,255,255,0.12);
                            border-radius:10px;
                            color:white;
                            font-size:0.95rem;
                        ">

                    <button
                        type="button"
                        id="btnMicrofono"
                        title="Buscar por voz"
                        style="
                            width:55px;
                            border:none;
                            border-radius:10px;
                            cursor:pointer;
                            font-size:1.3rem;
                            background:rgba(6,182,212,0.2);
                        ">
                        🎤
                    </button>

                </div>

                <div id="lista-productos" style="display:flex; flex-direction:column; gap:0.8rem;">
                    <?php foreach ($productos as $prod): ?>
                    <div class="prod-card"
     id="prod-<?php echo $prod['id']; ?>"
     data-nombre="<?php echo strtolower(htmlspecialchars($prod['nombre'])); ?>"
     data-descripcion="<?php echo strtolower(htmlspecialchars($prod['descripcion'] ?? '')); ?>">
                        <!-- Emoji / ícono -->
                        <div style="font-size:2rem; min-width:44px; text-align:center;">
                            <?php
                            $n = strtolower($prod['nombre']);
                            if (str_contains($n,'hambur') || str_contains($n,'burger')) echo '🍔';
                            elseif (str_contains($n,'pizza')) echo '🍕';
                            elseif (str_contains($n,'pollo') || str_contains($n,'chicken')) echo '🍗';
                            elseif (str_contains($n,'gaseosa') || str_contains($n,'refresco') || str_contains($n,'coca')) echo '🥤';
                            elseif (str_contains($n,'agua')) echo '💧';
                            elseif (str_contains($n,'jugo')) echo '🧃';
                            elseif (str_contains($n,'ensalada') || str_contains($n,'salad')) echo '🥗';
                            elseif (str_contains($n,'pasta') || str_contains($n,'spagueti')) echo '🍝';
                            elseif (str_contains($n,'arroz')) echo '🍚';
                            elseif (str_contains($n,'postre') || str_contains($n,'torta') || str_contains($n,'pastel')) echo '🎂';
                            elseif (str_contains($n,'helado')) echo '🍦';
                            elseif (str_contains($n,'cafe') || str_contains($n,'café')) echo '☕';
                            else echo '🍽️';
                            ?>
                        </div>
                        <!-- Info -->
                        <div style="flex:1; min-width:0;">
                            <p style="margin:0; font-weight:600; font-size:0.95rem;"><?php echo htmlspecialchars($prod['nombre']); ?></p>
                            <?php if ($prod['descripcion']): ?>
                            <p style="margin:0.2rem 0 0; color:var(--text-muted); font-size:0.8rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                <?php echo htmlspecialchars($prod['descripcion']); ?>
                            </p>
                            <?php endif; ?>
                            <p style="margin:0.3rem 0 0; font-weight:700; color:var(--accent); font-size:1rem;">
                                Bs <?php echo number_format($prod['precio'], 2); ?>
                            </p>
                        </div>
                        <!-- Controles cantidad -->
                        <div style="display:flex; align-items:center; gap:0.5rem; flex-shrink:0;">
                            <input type="hidden" name="producto_id[]" value="<?php echo $prod['id']; ?>">
                            <button type="button" class="qty-btn" onclick="cambiarCantidad(this,-1)">−</button>
                            <input type="number" name="cantidad[]" value="0" min="0" max="99"
                                   class="qty-input" oninput="actualizarTodo()" onchange="actualizarTodo()">
                            <button type="button" class="qty-btn" onclick="cambiarCantidad(this,1)">+</button>
                        </div>
                        <!-- Subtotal -->
                        <div style="min-width:72px; text-align:right; flex-shrink:0;">
                            <span class="subtotal-prod" data-precio="<?php echo $prod['precio']; ?>"
                                  style="color:var(--text-muted); font-size:0.88rem; font-weight:600;">Bs 0.00</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- PASO 2: Tipo de entrega -->
            <div class="glass-card" style="margin-bottom:1.5rem;">
                <h3 style="margin:0 0 1.5rem; font-size:1.2rem; display:flex; align-items:center;">
                    <span class="step-num">2</span> Tipo de entrega
                </h3>
                <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:0.8rem; margin-bottom:1.2rem;">
                    <?php
                    $tipos = [
                        ['val'=>'Delivery', 'ico'=>'🏍️', 'label'=>'Delivery',  'sub'=>'Te lo llevamos'],
                        ['val'=>'Recojo',   'ico'=>'🏃', 'label'=>'Recojo',    'sub'=>'Pasas a recoger'],
                        ['val'=>'Tienda',   'ico'=>'🏪', 'label'=>'En Tienda', 'sub'=>'Comes aquí'],
                    ];
                    foreach ($tipos as $t):
                    ?>
                    <div class="tipo-card <?php echo $t['val']=='Delivery'?'sel':''; ?>"
                         id="tipo-<?php echo $t['val']; ?>"
                         onclick="selTipo('<?php echo $t['val']; ?>')">
                        <input type="radio" name="tipo_entrega" value="<?php echo $t['val']; ?>"
                               <?php echo $t['val']=='Delivery'?'checked':''; ?> style="display:none;">
                        <div style="font-size:1.8rem;"><?php echo $t['ico']; ?></div>
                        <p style="margin:0.4rem 0 0; font-weight:600; font-size:0.95rem;"><?php echo $t['label']; ?></p>
                        <p style="margin:0.2rem 0 0; font-size:0.75rem; color:var(--text-muted);"><?php echo $t['sub']; ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Dirección (solo Delivery) -->
                <div id="campo-direccion">
                    <div class="form-group" style="margin-bottom:0.8rem;">
                        <label style="font-size:0.85rem; color:var(--text-muted);">📍 Dirección de entrega *</label>
                        <input type="text" name="direccion" id="input-direccion" class="form-control"
                               placeholder="Ej: Av. Banzer 3er anillo, entre calles..."
                               value="<?php echo htmlspecialchars($_POST['direccion'] ?? ''); ?>">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label style="font-size:0.85rem; color:var(--text-muted);">📝 Notas adicionales (opcional)</label>
                        <input type="text" name="notas" class="form-control"
                               placeholder="Ej: Timbre roto, llamar al llegar, piso 2..."
                               value="<?php echo htmlspecialchars($_POST['notas'] ?? ''); ?>">
                    </div>
                </div>
            </div>

        </div><!-- fin columna izquierda -->

        <!-- ══ COLUMNA DERECHA: CARRITO ══════════════════════════════════════ -->
        <div style="position:sticky; top:1.5rem;">
            <div class="glass-card">
                <h3 style="margin:0 0 1.2rem; font-size:1.1rem; display:flex; align-items:center; justify-content:space-between;">
                    <span>🛒 Tu pedido</span>
                    <span id="carrito-count" style="font-size:0.8rem; color:var(--text-muted); font-weight:400;">0 items</span>
                </h3>

                <!-- Items del carrito -->
                <div id="carrito-items" style="min-height:60px;">
                    <p id="carrito-vacio" style="color:var(--text-muted); font-size:0.88rem; text-align:center; padding:1rem 0;">
                        Aún no has agregado productos
                    </p>
                </div>

                <!-- Separador -->
                <div style="border-top:1px solid rgba(255,255,255,0.1); margin:1rem 0;"></div>

                <!-- Desglose -->
                <div style="display:flex; justify-content:space-between; font-size:0.88rem; color:var(--text-muted); margin-bottom:0.4rem;">
                    <span>Subtotal</span>
                    <span id="subtotal-display">Bs 0.00</span>
                </div>
                <div style="display:flex; justify-content:space-between; font-size:0.88rem; color:var(--text-muted); margin-bottom:1rem;">
                    <span id="label-envio">Envío (Delivery)</span>
                    <span style="color:#22c55e;">Gratis</span>
                </div>
                <div style="display:flex; justify-content:space-between; font-weight:700; font-size:1.2rem; margin-bottom:1.5rem;">
                    <span>Total</span>
                    <span id="total-display" style="color:var(--accent);">Bs 0.00</span>
                </div>

                <!-- Tipo seleccionado -->
                <div id="resumen-tipo" style="padding:0.7rem 1rem; border-radius:10px; background:rgba(6,182,212,0.1); border:1px solid rgba(6,182,212,0.3); margin-bottom:1.2rem; font-size:0.88rem; color:var(--accent);">
                    🏍️ Delivery — Te lo llevamos a domicilio
                </div>

                <!-- Botón confirmar -->
                <button type="submit" id="btn-confirmar" class="btn btn-glow" disabled
                        style="font-size:1rem; padding:0.9rem 1.5rem; width:100%;">
                    ✅ Confirmar Pedido
                </button>
                <p id="hint-confirmar" style="text-align:center; color:var(--text-muted); font-size:0.78rem; margin-top:0.6rem;">
                    Agrega al menos un producto para continuar
                </p>
            </div>
        </div><!-- fin carrito -->

        </div><!-- fin pedido-layout -->
        </form>
    </div><!-- fin dashboard-container -->
</div><!-- fin app-wrapper -->

<script>
// ── DATOS DE PRODUCTOS ────────────────────────────────────────────────────────
const productosData = {};
document.querySelectorAll('.prod-card').forEach(card => {
    const id    = card.id.replace('prod-', '');
    const sub   = card.querySelector('.subtotal-prod');
    const input = card.querySelector('input[name="cantidad[]"]');
    const nombre = card.querySelector('p').textContent.trim();
    productosData[id] = { precio: parseFloat(sub.dataset.precio), nombre, input, sub, card };
});

// ── CAMBIAR CANTIDAD CON BOTONES +/- ─────────────────────────────────────────
function cambiarCantidad(btn, delta) {
    const row   = btn.closest('.prod-card');
    const input = row.querySelector('input[name="cantidad[]"]');
    let val = parseInt(input.value || 0) + delta;
    if (val < 0) val = 0;
    if (val > 99) val = 99;
    input.value = val;
    actualizarTodo();
}

// ── ACTUALIZAR SUBTOTALES, CARRITO Y TOTAL ────────────────────────────────────
function actualizarTodo() {
    let total = 0;
    let itemsCarrito = [];

    Object.entries(productosData).forEach(([id, p]) => {
        const cant = parseInt(p.input.value) || 0;
        const sub  = p.precio * cant;
        total += sub;

        // Subtotal en la fila del producto
        p.sub.textContent = 'Bs ' + sub.toFixed(2);
        p.sub.style.color = cant > 0 ? 'var(--accent)' : 'var(--text-muted)';

        // Resaltar card si tiene cantidad
        p.card.classList.toggle('activo', cant > 0);

        if (cant > 0) itemsCarrito.push({ nombre: p.nombre, cant, sub });
    });

    // Actualizar carrito lateral
    const carritoDiv  = document.getElementById('carrito-items');
    const vacioMsg    = document.getElementById('carrito-vacio');
    const countEl     = document.getElementById('carrito-count');
    const totalEl     = document.getElementById('total-display');
    const subtotalEl  = document.getElementById('subtotal-display');
    const btnConfirm  = document.getElementById('btn-confirmar');
    const hintEl      = document.getElementById('hint-confirmar');

    if (itemsCarrito.length === 0) {
        carritoDiv.innerHTML = '';
        vacioMsg.style.display = 'block';
        carritoDiv.appendChild(vacioMsg);
        countEl.textContent = '0 items';
        btnConfirm.disabled = true;
        hintEl.textContent  = 'Agrega al menos un producto para continuar';
    } else {
        vacioMsg.style.display = 'none';
        carritoDiv.innerHTML = itemsCarrito.map(it => `
            <div class="carrito-item">
                <span><span class="badge-cant">${it.cant}</span>${it.nombre}</span>
                <span style="color:var(--accent); font-weight:600;">Bs ${it.sub.toFixed(2)}</span>
            </div>
        `).join('');
        const total_items = itemsCarrito.reduce((s, i) => s + i.cant, 0);
        countEl.textContent = total_items + (total_items === 1 ? ' item' : ' items');
        btnConfirm.disabled = false;
        hintEl.textContent  = '¡Listo para confirmar!';
        hintEl.style.color  = '#22c55e';
    }

    totalEl.textContent    = 'Bs ' + total.toFixed(2);
    subtotalEl.textContent = 'Bs ' + total.toFixed(2);
}

// ── SELECCIONAR TIPO DE ENTREGA ───────────────────────────────────────────────
const tipoLabels = {
    'Delivery': '🏍️ Delivery — Te lo llevamos a domicilio',
    'Recojo':   '🏃 Recojo — Pasas a recoger al local',
    'Tienda':   '🏪 En Tienda — Consumes en el local'
};

function selTipo(val) {
    // Marcar radio
    document.querySelector(`input[name="tipo_entrega"][value="${val}"]`).checked = true;

    // Estilos de tarjetas
    document.querySelectorAll('.tipo-card').forEach(c => c.classList.remove('sel'));
    document.getElementById('tipo-' + val).classList.add('sel');

    // Mostrar/ocultar dirección
    document.getElementById('campo-direccion').style.display = val === 'Delivery' ? 'block' : 'none';

    // Actualizar resumen
    document.getElementById('resumen-tipo').textContent = tipoLabels[val];
    document.getElementById('label-envio').textContent  =
        val === 'Delivery' ? 'Envío (Delivery)' : 'Tipo de entrega';
}

// ── BUSCADOR DE PRODUCTOS ─────────────────────────────────────────────────────

function normalizarTexto(texto)
{
    return texto
        .toLowerCase()
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .replace(/-/g, " ")
        .trim();
}

function filtrarProductos()
{
    const q = normalizarTexto(
        document.getElementById("buscador").value
    );

    document.querySelectorAll(".prod-card").forEach(card =>
    {
        const nombre = normalizarTexto(
            card.dataset.nombre || ""
        );

        const descripcion = normalizarTexto(
            card.dataset.descripcion || ""
        );

        if (
            nombre.includes(q) ||
            descripcion.includes(q)
        ) {
            card.style.display = "flex";
        } else {
            card.style.display = "none";
        }
    });
}

// ── VALIDAR DIRECCIÓN ANTES DE ENVIAR ────────────────────────────────────────
document.getElementById('form-pedido').addEventListener('submit', function(e) {
    const tipo = document.querySelector('input[name="tipo_entrega"]:checked').value;
    if (tipo === 'Delivery') {
        const dir = document.getElementById('input-direccion').value.trim();
        if (!dir) {
            e.preventDefault();
            document.getElementById('input-direccion').focus();
            document.getElementById('input-direccion').style.borderColor = 'var(--danger)';
            setTimeout(() => document.getElementById('input-direccion').style.borderColor = '', 2000);
        }
    }
});
// ===================================
// BUSQUEDA POR VOZ
// ===================================

const btnMicrofono =
document.getElementById("btnMicrofono");

if ('webkitSpeechRecognition' in window)
{
    btnMicrofono.addEventListener("click", () =>
    {
        const reconocimiento =
            new webkitSpeechRecognition();

        reconocimiento.lang = "es-ES";

        reconocimiento.start();

        btnMicrofono.innerHTML = "🔴";

        reconocimiento.onresult = (event) =>
        {
            let texto =
                event.results[0][0].transcript;

            document.getElementById("buscador")
                    .value = texto;

            filtrarProductos();
        };

        reconocimiento.onend = () =>
        {
            btnMicrofono.innerHTML = "🎤";
        };
    });
}
else
{
    btnMicrofono.style.display = "none";
}
</script>
</body>
</html>
