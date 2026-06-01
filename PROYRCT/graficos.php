<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['puesto']) || $_SESSION['puesto'] == 'Motorizado') {
    header("Location: index.php");
    exit;
}

require_once 'config.php';

// ================= GRÁFICO 1: ENTREGAS POR ESTADO =================
$estados = [];
$totalEstados = [];
try {
    $resEstados = $conn->query("SELECT estadoEntrega, COUNT(*) AS total FROM delivery GROUP BY estadoEntrega");
    if ($resEstados) {
        foreach ($resEstados->fetchAll() as $row) {
            $estados[] = $row['estadoEntrega'] ?? $row['estadoentrega'] ?? '';
            $totalEstados[] = (int)$row['total'];
        }
    }
} catch (Exception $e) { /* tabla vacía o inexistente */ }

// ================= GRÁFICO 2: GANANCIAS POR TIPO DE ENTREGA =================
$tiposEntrega = [];
$ganancias = [];
try {
    $resGanancias = $conn->query("SELECT tipoEntrega, SUM(precioTotal) AS total FROM venta GROUP BY tipoEntrega");
    if ($resGanancias) {
        foreach ($resGanancias->fetchAll() as $row) {
            $tiposEntrega[] = $row['tipoEntrega'] ?? $row['tipoentrega'] ?? '';
            $ganancias[] = (float)$row['total'];
        }
    }
} catch (Exception $e) { /* tabla vacía o inexistente */ }

// ================= GRÁFICO 3: ENTREGAS POR MOTORIZADO =================
$riders = [];
$totalRiders = [];
try {
    $resRiders = $conn->query("SELECT 
                    CONCAT(t.nombre, ' ', t.apellido) AS motorizado,
                    COUNT(d.idDelivery) AS total
                  FROM delivery d
                  LEFT JOIN trabajador t ON d.idMotorizado = t.idTrabajador
                  GROUP BY d.idMotorizado, t.nombre, t.apellido");
    if ($resRiders) {
        foreach ($resRiders->fetchAll() as $row) {
            $riders[] = $row['motorizado'] ? $row['motorizado'] : 'No asignado';
            $totalRiders[] = (int)$row['total'];
        }
    }
} catch (Exception $e) { /* tabla vacía o inexistente */ }
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gráficos del Sistema</title>
    <link rel="stylesheet" href="style.css">

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        .graficos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
            gap: 25px;
        }

        .grafico-card {
            background: rgba(30, 41, 59, 0.95);
            padding: 25px;
            border-radius: 18px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.35);
        }

        .grafico-card h3 {
            text-align: center;
            margin-bottom: 20px;
            color: #ffffff;
            font-size: 1.1rem;
        }

        .grafico-box {
            position: relative;
            width: 100%;
            height: 350px;
        }

        .btn-volver {
            display: inline-block;
            margin-bottom: 25px;
            padding: 10px 18px;
            background: var(--primary);
            color: white;
            text-decoration: none;
            border-radius: 10px;
        }

        .btn-volver:hover {
            opacity: 0.85;
        }
    </style>
</head>

<body>
<div class="app-wrapper">

    <nav class="navbar">
        <h2>Tienda Delivery <span>| Gráficos</span></h2>

        <div class="nav-user">
            <span>👤 Hola, <?php echo htmlspecialchars($_SESSION['nombre']); ?></span>

            <a href="logout.php" class="btn" style="
                padding: 0.5rem 1.5rem;
                width: auto;
                background: rgba(239,68,68,0.2);
                color: var(--danger);
                border:1px solid rgba(239,68,68,0.5);
            ">
                Cerrar Sesión
            </a>
        </div>
    </nav>

    <div class="dashboard-container">

        <a href="admin.php" class="btn-volver">← Volver al Panel Admin</a>

        <div class="graficos-grid">

            <div class="grafico-card">
                <h3>Entregas por Estado</h3>
                <?php if (empty($estados)): ?>
                    <p style="text-align:center; color:#94a3b8; padding:2rem 0;">Sin datos aún</p>
                <?php else: ?>
                <div class="grafico-box">
                    <canvas id="graficoEstados"></canvas>
                </div>
                <?php endif; ?>
            </div>

            <div class="grafico-card">
                <h3>Ganancias por Tipo de Entrega</h3>
                <?php if (empty($tiposEntrega)): ?>
                    <p style="text-align:center; color:#94a3b8; padding:2rem 0;">Sin datos aún</p>
                <?php else: ?>
                <div class="grafico-box">
                    <canvas id="graficoGanancias"></canvas>
                </div>
                <?php endif; ?>
            </div>

            <div class="grafico-card">
                <h3>Entregas por Motorizado</h3>
                <?php if (empty($riders)): ?>
                    <p style="text-align:center; color:#94a3b8; padding:2rem 0;">Sin datos aún</p>
                <?php else: ?>
                <div class="grafico-box">
                    <canvas id="graficoRiders"></canvas>
                </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<script>
Chart.defaults.color = '#ffffff';
Chart.defaults.font.family = 'Inter, Arial, sans-serif';
Chart.defaults.font.size = 13;

<?php if (!empty($estados)): ?>
const estados = <?php echo json_encode($estados); ?>;
const totalEstados = <?php echo json_encode($totalEstados); ?>;

new Chart(document.getElementById('graficoEstados'), {
    type: 'doughnut',
    data: {
        labels: estados,
        datasets: [{
            label: 'Cantidad',
            data: totalEstados,
            backgroundColor: ['#38bdf8','#6366f1','#22c55e','#f59e0b','#ef4444'],
            borderColor: '#ffffff',
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top',
                labels: { color: '#ffffff', font: { size: 13 } }
            }
        }
    }
});
<?php endif; ?>

<?php if (!empty($tiposEntrega)): ?>
const tiposEntrega = <?php echo json_encode($tiposEntrega); ?>;
const ganancias = <?php echo json_encode($ganancias); ?>;

new Chart(document.getElementById('graficoGanancias'), {
    type: 'bar',
    data: {
        labels: tiposEntrega,
        datasets: [{
            label: 'Ganancias Bs',
            data: ganancias,
            backgroundColor: '#38bdf8',
            borderColor: '#7dd3fc',
            borderWidth: 2,
            borderRadius: 10
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { labels: { color: '#ffffff', font: { size: 13 } } },
            tooltip: {
                callbacks: {
                    label: function(context) { return 'Ganancias Bs: ' + context.raw; }
                }
            }
        },
        scales: {
            x: { ticks: { color: '#ffffff', font: { size: 12 } }, grid: { color: 'rgba(255,255,255,0.08)' } },
            y: { beginAtZero: true, ticks: { color: '#ffffff', font: { size: 12 } }, grid: { color: 'rgba(255,255,255,0.08)' } }
        }
    }
});
<?php endif; ?>

<?php if (!empty($riders)): ?>
const riders = <?php echo json_encode($riders); ?>;
const totalRiders = <?php echo json_encode($totalRiders); ?>;

new Chart(document.getElementById('graficoRiders'), {
    type: 'bar',
    data: {
        labels: riders,
        datasets: [{
            label: 'Entregas realizadas',
            data: totalRiders,
            backgroundColor: '#6366f1',
            borderColor: '#a5b4fc',
            borderWidth: 2,
            borderRadius: 10
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { labels: { color: '#ffffff', font: { size: 13 } } }
        },
        scales: {
            x: { ticks: { color: '#ffffff', font: { size: 12 } }, grid: { color: 'rgba(255,255,255,0.08)' } },
            y: { beginAtZero: true, ticks: { color: '#ffffff', stepSize: 1, font: { size: 12 } }, grid: { color: 'rgba(255,255,255,0.08)' } }
        }
    }
});
<?php endif; ?>
</script>

</body>
</html>