<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['puesto']) || $_SESSION['puesto'] == 'Motorizado') {
    header("Location: index.php"); exit;
}
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion'])) {
    $accion = $_POST['accion'];
    $idDelivery = intval($_POST['idDelivery'] ?? 0);

    if ($accion === 'asignar_rider') {
        $idMotorizado = $_POST['idMotorizado'] ?? '';
        if ($idMotorizado === '') {
            $idMotorizado = null;
        } else {
            $idMotorizado = intval($idMotorizado);
        }

        $nombreRider = null;
        if ($idMotorizado !== null) {
            $rStmt = $conn->prepare("SELECT nombre, apellido FROM trabajador WHERE idTrabajador = :id");
            $rStmt->execute([':id' => $idMotorizado]);
            $r = $rStmt->fetch();
            if ($r) {
                $nombreRider = $r['nombre'] . ' ' . $r['apellido'];
            }
        }

        $stmt = $conn->prepare("UPDATE delivery SET idMotorizado = :rider WHERE idDelivery = :id");
        $stmt->execute([':rider' => $idMotorizado, ':id' => $idDelivery]);

    } elseif ($accion === 'cerrar_entrega') {
        $stmt = $conn->prepare("UPDATE delivery SET estado = 'Finalizado', estadoEntrega = 'Entregado' WHERE idDelivery = :id");
        $stmt->execute([':id' => $idDelivery]);
    }
}

header("Location: admin.php");
exit;
?>
