<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['puesto']) || $_SESSION['puesto'] != 'Motorizado') {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No autorizado']);
    exit;
}
require_once 'config.php';

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (isset($data['lat']) && isset($data['lng'])) {
    $lat = floatval($data['lat']);
    $lng = floatval($data['lng']);
    $coordStr = $lat . ',' . $lng;

    $rider_id = $_SESSION['user_id'];

    $stmt = $conn->prepare("UPDATE delivery SET ubicacionGPS = :coords WHERE idMotorizado = :rider AND estado = 'Activo'");
    $stmt->execute([':coords' => $coordStr, ':rider' => $rider_id]);

    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(400);
header('Content-Type: application/json');
echo json_encode(['error' => 'Datos inválidos']);
exit;
?>
