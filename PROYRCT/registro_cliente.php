<?php
session_start();
require_once 'config.php';

$error = null;
$ok = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $nit_ci = trim($_POST['nit_ci'] ?? '');
    $noTelefono = trim($_POST['noTelefono'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($nombre) || empty($apellido) || empty($noTelefono) || empty($username) || empty($password)) {
        $error = "Por favor, complete todos los campos obligatorios.";
    } else {
        // Verificar si el usuario ya existe
        $stmt = $conn->prepare("SELECT idcliente FROM cliente WHERE username = :username");
        $stmt->execute([':username' => $username]);
        if ($stmt->fetch()) {
            $error = "El nombre de usuario ya está registrado.";
        } else {
            // Insertar cliente
            $hash = md5($password);
            $stmt = $conn->prepare("
                INSERT INTO cliente (nombre, apellido, nit_ci, noTelefono, username, password)
                VALUES (:nombre, :apellido, :nit_ci, :noTelefono, :username, :password)
            ");
            $stmt->execute([
                ':nombre' => $nombre,
                ':apellido' => $apellido,
                ':nit_ci' => $nit_ci,
                ':noTelefono' => $noTelefono,
                ':username' => $username,
                ':password' => $hash
            ]);

            header("Location: index.php?tab=cliente&ok=" . urlencode("¡Registro exitoso! Inicie sesión con su nueva cuenta."));
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tienda Delivery - Registro de Cliente</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="bg-shape bg-shape-1"></div>
    <div class="bg-shape bg-shape-2"></div>

    <div class="login-container">
        <div class="glass-card login-card" style="max-width:500px; padding: 2.5rem; width: 100%;">
            <div class="login-header">
                <div class="logo-circle">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="8.5" cy="7" r="4"></circle>
                        <line x1="20" y1="8" x2="20" y2="14"></line>
                        <line x1="23" y1="11" x2="17" y2="11"></line>
                    </svg>
                </div>
                <h2>Crear Cuenta</h2>
                <p>Regístrate para realizar y rastrear tus pedidos</p>
            </div>

            <?php if ($error): ?>
                <div class="error">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:8px;flex-shrink:0"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label>Nombre *</label>
                        <input type="text" name="nombre" class="form-control" required placeholder="Juan" value="<?php echo htmlspecialchars($nombre ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Apellido *</label>
                        <input type="text" name="apellido" class="form-control" required placeholder="Mamani" value="<?php echo htmlspecialchars($apellido ?? ''); ?>">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label>NIT / CI</label>
                        <input type="text" name="nit_ci" class="form-control" placeholder="778899" value="<?php echo htmlspecialchars($nit_ci ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Teléfono *</label>
                        <input type="text" name="noTelefono" class="form-control" required placeholder="70000001" value="<?php echo htmlspecialchars($noTelefono ?? ''); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Nombre de Usuario *</label>
                    <div class="input-container">
                        <svg class="input-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <input type="text" name="username" class="form-control with-icon" required placeholder="juan" value="<?php echo htmlspecialchars($username ?? ''); ?>" autocomplete="username">
                    </div>
                </div>

                <div class="form-group">
                    <label>Contraseña *</label>
                    <div class="input-container">
                        <svg class="input-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <input type="password" name="password" class="form-control with-icon" required placeholder="••••••••" autocomplete="new-password">
                    </div>
                </div>

                <button type="submit" class="btn btn-glow">
                    Registrarse
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-left:8px"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                </button>
            </form>

            <p style="text-align:center; margin-top:1.5rem; color:var(--text-muted); font-size:0.9rem;">
                ¿Ya tienes una cuenta? <a href="index.php?tab=cliente" style="color:var(--accent); font-weight:600;">Inicia sesión aquí</a>
            </p>
        </div>
    </div>
</body>
</html>
