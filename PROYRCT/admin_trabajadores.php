<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['puesto']) || $_SESSION['puesto'] !== 'Admin') {
    header("Location: index.php"); exit;
}
require_once 'config.php';

$msg = null; $error = null;

// ── AGREGAR ──────────────────────────────────────────────────────────────────
if (isset($_POST['accion']) && $_POST['accion'] === 'agregar') {
    $nombre   = trim($_POST['nombre']   ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $ci       = trim($_POST['ci']       ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $puesto   = $_POST['puesto']   ?? '';
    $fnac     = $_POST['fnac']     ?? null;
    $puestos_validos = ['Admin','Supervisor','Caja','Motorizado'];

    if (empty($nombre) || empty($apellido) || empty($ci) || empty($username) || empty($password) || !in_array($puesto, $puestos_validos)) {
        $error = "Completa todos los campos obligatorios.";
    } else {
        $chk = $conn->prepare("SELECT idTrabajador FROM trabajador WHERE username=:u OR ci=:c");
        $chk->execute([':u'=>$username,':c'=>$ci]);
        if ($chk->fetch()) {
            $error = "El usuario o CI ya está registrado.";
        } else {
            $stmt = $conn->prepare("INSERT INTO trabajador (nombre,apellido,ci,username,password,fechaNacimiento,puesto) VALUES (:n,:a,:c,:u,MD5(:p),:f,:pu)");
            $stmt->execute([':n'=>$nombre,':a'=>$apellido,':c'=>$ci,':u'=>$username,':p'=>$password,':f'=>($fnac?:null),':pu'=>$puesto]);
            $msg = "Trabajador '$nombre $apellido' agregado correctamente.";
        }
    }
}

// ── EDITAR ───────────────────────────────────────────────────────────────────
if (isset($_POST['accion']) && $_POST['accion'] === 'editar') {
    $id       = intval($_POST['id'] ?? 0);
    $nombre   = trim($_POST['nombre']   ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $puesto   = $_POST['puesto'] ?? '';
    $password = trim($_POST['password'] ?? '');

    if (empty($nombre) || empty($apellido)) {
        $error = "Nombre y apellido son obligatorios.";
    } else {
        if ($password !== '') {
            $conn->prepare("UPDATE trabajador SET nombre=:n,apellido=:a,puesto=:pu,password=MD5(:p) WHERE idTrabajador=:id")
                 ->execute([':n'=>$nombre,':a'=>$apellido,':pu'=>$puesto,':p'=>$password,':id'=>$id]);
        } else {
            $conn->prepare("UPDATE trabajador SET nombre=:n,apellido=:a,puesto=:pu WHERE idTrabajador=:id")
                 ->execute([':n'=>$nombre,':a'=>$apellido,':pu'=>$puesto,':id'=>$id]);
        }
        $msg = "Trabajador actualizado.";
    }
}

// ── ELIMINAR ─────────────────────────────────────────────────────────────────
if (isset($_POST['accion']) && $_POST['accion'] === 'eliminar') {
    $id = intval($_POST['id'] ?? 0);
    if ($id === intval($_SESSION['user_id'])) {
        $error = "No puedes eliminar tu propia cuenta.";
    } else {
        $chk = $conn->prepare("SELECT COUNT(*) AS t FROM venta WHERE idTrabajador=:id");
        $chk->execute([':id'=>$id]);
        if ((int)$chk->fetch()['t'] > 0) {
            $error = "No se puede eliminar: tiene ventas registradas.";
        } else {
            $conn->prepare("DELETE FROM trabajador WHERE idTrabajador=:id")->execute([':id'=>$id]);
            $msg = "Trabajador eliminado.";
        }
    }
}

// ── CARGAR LISTA ─────────────────────────────────────────────────────────────
$trabajadores = $conn->query("
    SELECT idTrabajador AS id, nombre, apellido, ci, username, puesto,
           fechaNacimiento AS fechanacimiento
    FROM trabajador ORDER BY puesto, nombre
")->fetchAll();

$colores_puesto = [
    'Admin'      => ['bg'=>'rgba(239,68,68,0.15)',    'color'=>'#f87171',  'border'=>'rgba(239,68,68,0.4)'],
    'Supervisor' => ['bg'=>'rgba(245,158,11,0.15)',   'color'=>'#fbbf24',  'border'=>'rgba(245,158,11,0.4)'],
    'Caja'       => ['bg'=>'rgba(99,102,241,0.15)',   'color'=>'#a5b4fc',  'border'=>'rgba(99,102,241,0.4)'],
    'Motorizado' => ['bg'=>'rgba(6,182,212,0.15)',    'color'=>'#67e8f9',  'border'=>'rgba(6,182,212,0.4)'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Trabajadores</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .trab-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:1.2rem; }
        .trab-card {
            background:rgba(255,255,255,0.03); border:1.5px solid rgba(255,255,255,0.08);
            border-radius:16px; padding:1.4rem; transition:border-color 0.2s;
        }
        .trab-card:hover { border-color:rgba(255,255,255,0.18); }
        .avatar {
            width:48px; height:48px; border-radius:50%;
            display:flex; align-items:center; justify-content:center;
            font-size:1.3rem; font-weight:700; flex-shrink:0;
        }
        .badge-puesto {
            display:inline-block; padding:0.25rem 0.7rem; border-radius:20px;
            font-size:0.75rem; font-weight:600; border:1px solid;
        }
        .form-row { display:grid; grid-template-columns:1fr 1fr; gap:0.8rem; }
        @media(max-width:600px){ .form-row { grid-template-columns:1fr; } }
    </style>
</head>
<body>
<div class="app-wrapper">
    <nav class="navbar">
        <h2>Tienda Delivery <span>| Trabajadores</span></h2>
        <div class="nav-user">
            <span>👤 <?php echo htmlspecialchars($_SESSION['nombre']); ?></span>
            <a href="admin.php" class="btn" style="padding:0.5rem 1.2rem;width:auto;background:rgba(99,102,241,0.2);color:#a5b4fc;border:1px solid rgba(99,102,241,0.4);">← Panel Admin</a>
            <a href="logout.php" class="btn" style="padding:0.5rem 1rem;width:auto;background:rgba(239,68,68,0.2);color:var(--danger);border:1px solid rgba(239,68,68,0.5);">Salir</a>
        </div>
    </nav>

    <div class="dashboard-container">

        <?php if ($msg): ?>
        <div style="margin-bottom:1.5rem;padding:1rem 1.5rem;background:rgba(34,197,94,0.12);border:1px solid rgba(34,197,94,0.3);border-radius:12px;color:#4ade80;">✅ <?php echo htmlspecialchars($msg); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="error" style="margin-bottom:1.5rem;">❌ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Stats rápidas -->
        <div style="display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:2rem;">
            <?php
            $conteos = ['Admin'=>0,'Supervisor'=>0,'Caja'=>0,'Motorizado'=>0];
            foreach ($trabajadores as $t) $conteos[$t['puesto']] = ($conteos[$t['puesto']] ?? 0) + 1;
            $iconos = ['Admin'=>'👑','Supervisor'=>'🔍','Caja'=>'💰','Motorizado'=>'🏍️'];
            foreach ($conteos as $p => $n):
                $c = $colores_puesto[$p];
            ?>
            <div style="padding:0.8rem 1.4rem;border-radius:12px;background:<?php echo $c['bg']; ?>;border:1px solid <?php echo $c['border']; ?>;display:flex;align-items:center;gap:0.6rem;">
                <span style="font-size:1.3rem;"><?php echo $iconos[$p]; ?></span>
                <div>
                    <p style="margin:0;font-weight:700;font-size:1.2rem;color:<?php echo $c['color']; ?>"><?php echo $n; ?></p>
                    <p style="margin:0;font-size:0.75rem;color:var(--text-muted);"><?php echo $p; ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1.8fr;gap:2rem;align-items:start;">

            <!-- FORMULARIO -->
            <div class="glass-card" id="form-card" style="position:sticky;top:1.5rem;">
                <h3 style="margin:0 0 1.5rem;font-size:1.1rem;" id="form-titulo">➕ Agregar Trabajador</h3>
                <form method="POST" id="form-trab">
                    <input type="hidden" name="accion" value="agregar" id="f-accion">
                    <input type="hidden" name="id" value="" id="f-id">

                    <div class="form-row">
                        <div class="form-group">
                            <label>Nombre *</label>
                            <input type="text" name="nombre" id="f-nombre" class="form-control" required placeholder="Carlos">
                        </div>
                        <div class="form-group">
                            <label>Apellido *</label>
                            <input type="text" name="apellido" id="f-apellido" class="form-control" required placeholder="Pérez">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>CI *</label>
                            <input type="text" name="ci" id="f-ci" class="form-control" placeholder="1234567">
                        </div>
                        <div class="form-group">
                            <label>Fecha Nac.</label>
                            <input type="date" name="fnac" id="f-fnac" class="form-control">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Puesto *</label>
                        <select name="puesto" id="f-puesto" class="form-control">
                            <option value="Motorizado">🏍️ Motorizado</option>
                            <option value="Caja">💰 Caja</option>
                            <option value="Supervisor">🔍 Supervisor</option>
                            <option value="Admin">👑 Admin</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Usuario *</label>
                        <input type="text" name="username" id="f-username" class="form-control" placeholder="rider2" autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label id="lbl-pass">Contraseña *</label>
                        <input type="password" name="password" id="f-password" class="form-control" placeholder="••••••••" autocomplete="new-password">
                        <small id="hint-pass" style="color:var(--text-muted);font-size:0.78rem;display:none;">Dejar vacío para no cambiar la contraseña</small>
                    </div>

                    <div style="display:flex;gap:0.8rem;margin-top:0.5rem;">
                        <button type="submit" class="btn btn-glow" style="flex:1;">💾 Guardar</button>
                        <button type="button" onclick="resetForm()" class="btn" style="width:auto;padding:0.8rem 1rem;background:rgba(255,255,255,0.05);color:var(--text-muted);border:1px solid rgba(255,255,255,0.1);">✕</button>
                    </div>
                </form>
            </div>

            <!-- LISTA DE TRABAJADORES -->
            <div>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.2rem;">
                    <h3 style="margin:0;font-size:1.1rem;">👥 Equipo (<?php echo count($trabajadores); ?>)</h3>
                    <input type="text" id="buscador-trab" placeholder="🔍 Buscar..." oninput="filtrarTrab()"
                        style="padding:0.5rem 1rem;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.12);border-radius:8px;color:white;font-size:0.85rem;width:180px;">
                </div>
                <div class="trab-grid" id="trab-grid">
    <?php foreach ($trabajadores as $t):
        $c = $colores_puesto[$t['puesto']] ?? $colores_puesto['Caja'];
        $iniciales = strtoupper(substr($t['nombre'],0,1) . substr($t['apellido'],0,1));
        $esMiCuenta = ($t['id'] == $_SESSION['user_id']);
        // SVG avatar según puesto
        $svgIconos = [
            'Admin'      => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
            'Supervisor' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>',
            'Caja'       => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>',
            'Motorizado' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="5.5" cy="17.5" r="3.5"/><circle cx="18.5" cy="17.5" r="3.5"/><path d="M15 6h-5l-3 7h11l-3-7z"/><path d="M15 6l2 4"/></svg>',
        ];
        $svgIcon = $svgIconos[$t['puesto']] ?? $svgIconos['Caja'];
    ?>
                    <div class="trab-card" data-nombre="<?php echo strtolower($t['nombre'].' '.$t['apellido'].' '.$t['puesto']); ?>">
                        <div style="display:flex;align-items:center;gap:0.9rem;margin-bottom:1rem;">
                            <div class="avatar" style="background:<?php echo $c['bg']; ?>;color:<?php echo $c['color']; ?>;border:1.5px solid <?php echo $c['border']; ?>;">
                                <?php echo $svgIcon; ?>
                            </div>
                            <div style="flex:1;min-width:0;">
                                <p style="margin:0;font-weight:600;font-size:0.95rem;"><?php echo htmlspecialchars($t['nombre'].' '.$t['apellido']); ?>
                                    <?php if ($esMiCuenta): ?><span style="font-size:0.7rem;color:var(--accent);"> (tú)</span><?php endif; ?>
                                </p>
                                <p style="margin:0.2rem 0 0;color:var(--text-muted);font-size:0.8rem;">@<?php echo htmlspecialchars($t['username']); ?></p>
                            </div>
                            <span class="badge-puesto" style="background:<?php echo $c['bg']; ?>;color:<?php echo $c['color']; ?>;border-color:<?php echo $c['border']; ?>;">
                                <?php echo $t['puesto']; ?>
                            </span>
                        </div>
                        <div style="font-size:0.82rem;color:var(--text-muted);margin-bottom:1rem;">
                            <span>🪪 CI: <?php echo htmlspecialchars($t['ci']); ?></span>
                            <?php if ($t['fechanacimiento']): ?>
                            <span style="margin-left:0.8rem;">🎂 <?php echo date('d/m/Y', strtotime($t['fechanacimiento'])); ?></span>
                            <?php endif; ?>
                        </div>
                        <div style="display:flex;gap:0.5rem;">
                            <button onclick="editarTrab(<?php echo $t['id']; ?>,'<?php echo addslashes($t['nombre']); ?>','<?php echo addslashes($t['apellido']); ?>','<?php echo $t['puesto']; ?>')"
                                class="btn admin-btn-sm" style="flex:1;background:rgba(59,130,246,0.15);color:#60a5fa;border:1px solid rgba(59,130,246,0.35);">
                                ✏️ Editar
                            </button>
                            <?php if (!$esMiCuenta): ?>
                            <form method="POST" onsubmit="return confirm('¿Eliminar a <?php echo addslashes($t['nombre']); ?>?');" style="flex:1;">
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="id" value="<?php echo $t['id']; ?>">
                                <button type="submit" class="btn admin-btn-sm" style="width:100%;background:rgba(239,68,68,0.15);color:var(--danger);border:1px solid rgba(239,68,68,0.35);">
                                    🗑️ Eliminar
                                </button>
                            </form>
                            <?php else: ?>
                            <button disabled class="btn admin-btn-sm" style="flex:1;opacity:0.3;cursor:not-allowed;">🔒 Tu cuenta</button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div><!-- /trab-grid -->
            </div><!-- /lista col -->
        </div><!-- /grid -->
    </div><!-- /dashboard -->
</div><!-- /app-wrapper -->

<script>
function editarTrab(id, nombre, apellido, puesto) {
    document.getElementById('form-titulo').textContent = '✏️ Editar Trabajador';
    document.getElementById('f-accion').value  = 'editar';
    document.getElementById('f-id').value      = id;
    document.getElementById('f-nombre').value  = nombre;
    document.getElementById('f-apellido').value = apellido;
    document.getElementById('f-puesto').value  = puesto;
    document.getElementById('f-ci').closest('.form-row').style.display = 'none';
    document.getElementById('f-username').closest('.form-group').style.display = 'none';
    document.getElementById('lbl-pass').textContent = 'Nueva contraseña';
    document.getElementById('hint-pass').style.display = 'block';
    document.getElementById('f-password').required = false;
    document.getElementById('form-card').scrollIntoView({ behavior:'smooth' });
}

function resetForm() {
    document.getElementById('form-titulo').textContent = '➕ Agregar Trabajador';
    document.getElementById('f-accion').value = 'agregar';
    document.getElementById('f-id').value = '';
    document.getElementById('f-ci').closest('.form-row').style.display = 'grid';
    document.getElementById('f-username').closest('.form-group').style.display = 'block';
    document.getElementById('lbl-pass').textContent = 'Contraseña *';
    document.getElementById('hint-pass').style.display = 'none';
    document.getElementById('f-password').required = true;
    document.getElementById('form-trab').reset();
}

function filtrarTrab() {
    const q = document.getElementById('buscador-trab').value.toLowerCase();
    document.querySelectorAll('.trab-card').forEach(c => {
        c.style.display = c.dataset.nombre.includes(q) ? 'block' : 'none';
    });
}
</script>
</body>
</html>
