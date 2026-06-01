<?php
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = md5($_POST['password'] ?? '');
    $tipo = $_POST['tipo'] ?? 'sistema';

    if ($tipo === 'cliente') {
        // Consultar en la tabla cliente
        $stmt = $conn->prepare("SELECT idcliente, nombre FROM cliente WHERE username = :username AND password = :password");
        $stmt->execute([':username' => $username, ':password' => $password]);
        $row = $stmt->fetch();

        if ($row) {
            $_SESSION['user_id'] = $row['idcliente'];
            $_SESSION['nombre'] = $row['nombre'];
            $_SESSION['tipo'] = 'cliente';
            header("Location: cliente.php");
            exit;
        } else {
            header("Location: index.php?tab=cliente&error=Usuario o contraseña de cliente incorrectos");
            exit;
        }
    } else {
        // Consultar en la tabla trabajador
        $stmt = $conn->prepare("SELECT idtrabajador, nombre, puesto FROM trabajador WHERE username = :username AND password = :password");
        $stmt->execute([':username' => $username, ':password' => $password]);
        $row = $stmt->fetch();

        if ($row) {
            $_SESSION['user_id'] = $row['idtrabajador'];
            $_SESSION['nombre'] = $row['nombre'];
            $_SESSION['puesto'] = $row['puesto'];
            // Remove client session if it existed
            unset($_SESSION['tipo']);

            if ($row['puesto'] == 'Motorizado') {
                header("Location: rider.php");
            } else {
                header("Location: admin.php");
            }
            exit;
        } else {
            header("Location: index.php?tab=sistema&error=Usuario o contraseña de sistema incorrectos");
            exit;
        }
    }
}
?>
