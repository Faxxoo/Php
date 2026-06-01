<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No autorizado']);
    exit;
}
require_once 'config.php';

$idDelivery = intval($_GET['idDelivery'] ?? 0);

if ($idDelivery > 0) {
    $stmt = $conn->prepare("
        SELECT d.ubicacionGPS, d.estadoEntrega, t.nombre, t.apellido
        FROM delivery d
        LEFT JOIN trabajador t ON d.idMotorizado = t.idTrabajador
        WHERE d.idDelivery = :id AND d.estado = 'Activo'
    ");
    $stmt->execute([':id' => $idDelivery]);
    $row = $stmt->fetch();

    if ($row && !empty($row['ubicaciongps'])) {
        $coords = explode(',', $row['ubicaciongps']);
        if (count($coords) === 2) {
            header('Content-Type: application/json');
            echo json_encode([
                'ok' => true,
                'lat' => trim($coords[0]),
                'lng' => trim($coords[1]),
                'rider' => $row['nombre'] ? ($row['nombre'] . ' ' . $row['apellido']) : 'No asignado',
                'estado' => $row['estadoentrega']
            ]);
            exit;
        }
    }
}

header('Content-Type: application/json');
echo json_encode(['ok' => false]);
exit;
?>
