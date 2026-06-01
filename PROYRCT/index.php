<?php
session_start();
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['tipo']) && $_SESSION['tipo'] == 'cliente') header("Location: cliente.php");
    elseif (isset($_SESSION['puesto']) && $_SESSION['puesto'] == 'Motorizado') header("Location: rider.php");
    else header("Location: admin.php");
    exit;
}
$tab = $_GET['tab'] ?? 'cliente';

// Stats para mostrar en el login
$stats = ['pedidos' => 0, 'riders' => 0, 'entregas_hoy' => 0, 'clientes' => 0];
try {
    require_once 'config.php';
    $stats['pedidos']       = (int)$conn->query("SELECT COUNT(*) FROM venta")->fetchColumn();
    $stats['riders']        = (int)$conn->query("SELECT COUNT(*) FROM trabajador WHERE puesto='Motorizado'")->fetchColumn();
    $stats['entregas_hoy']  = (int)$conn->query("SELECT COUNT(*) FROM delivery WHERE DATE(fechaRegistro)=CURDATE()")->fetchColumn();
    $stats['clientes']      = (int)$conn->query("SELECT COUNT(*) FROM cliente")->fetchColumn();
} catch (Exception $e) { /* BD no disponible aún */ }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tienda Delivery - Ingresar</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ── Login mejorado ── */
        .login-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
            position: relative;
            overflow: hidden;
        }
        .login-blob {
            position: fixed;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.18;
            pointer-events: none;
            animation: blobMove 12s ease-in-out infinite alternate;
        }
        .blob-1 { width:500px; height:500px; background:#6366f1; top:-120px; left:-150px; }
        .blob-2 { width:400px; height:400px; background:#06b6d4; bottom:-100px; right:-100px; animation-delay:-6s; }
        .blob-3 { width:300px; height:300px; background:#8b5cf6; top:40%; left:60%; animation-delay:-3s; }
        @keyframes blobMove {
            0%   { transform: translate(0,0) scale(1); }
            100% { transform: translate(30px,20px) scale(1.08); }
        }

        .login-wrapper {
            width: 100%;
            max-width: 480px;
            position: relative;
            z-index: 1;
        }

        /* Marca / logo */
        .brand-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .brand-logo {
            width: 72px; height: 72px;
            border-radius: 20px;
            background: linear-gradient(135deg, #6366f1, #06b6d4);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1rem;
            box-shadow: 0 8px 32px rgba(99,102,241,0.4);
            font-size: 2rem;
        }
        .brand-header h1 {
            font-size: 1.8rem; font-weight: 700; margin: 0 0 0.3rem;
            background: linear-gradient(135deg, #e2e8f0, #94a3b8);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .brand-header p { color: var(--text-muted); font-size: 0.9rem; margin: 0; }

        /* Card del login */
        .login-box {
            background: rgba(15,23,42,0.85);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 24px;
            padding: 2rem;
            box-shadow: 0 24px 64px rgba(0,0,0,0.5);
        }

        /* Tabs */
        .tab-row {
            display: grid; grid-template-columns: 1fr 1fr;
            background: rgba(255,255,255,0.04);
            border-radius: 12px; padding: 4px;
            margin-bottom: 1.8rem;
            border: 1px solid rgba(255,255,255,0.07);
        }
        .tab-btn {
            padding: 0.65rem; border-radius: 9px; text-align: center;
            font-size: 0.88rem; font-weight: 600; cursor: pointer;
            color: var(--text-muted); text-decoration: none;
            transition: all 0.2s;
        }
        .tab-btn.active {
            background: linear-gradient(135deg, rgba(99,102,241,0.4), rgba(6,182,212,0.3));
            color: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
        }
        .tab-btn:hover:not(.active) { color: white; background: rgba(255,255,255,0.06); }

        /* Inputs */
        .field-group { margin-bottom: 1.1rem; }
        .field-group label {
            display: block; font-size: 0.8rem; font-weight: 600;
            color: var(--text-muted); margin-bottom: 0.4rem;
            text-transform: uppercase; letter-spacing: 0.05em;
        }
        .field-wrap { position: relative; }
        .field-wrap svg {
            position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
            color: var(--text-muted); pointer-events: none;
        }
        .field-input {
            width: 100%; padding: 0.75rem 1rem 0.75rem 2.8rem;
            background: rgba(255,255,255,0.06);
            border: 1.5px solid rgba(255,255,255,0.1);
            border-radius: 12px; color: white; font-size: 0.95rem;
            transition: border-color 0.2s, background 0.2s;
            box-sizing: border-box;
        }
        .field-input:focus {
            outline: none;
            border-color: rgba(6,182,212,0.6);
            background: rgba(6,182,212,0.06);
        }
        .field-input::placeholder { color: rgba(148,163,184,0.5); }

        /* Toggle password */
        .toggle-pass {
            position: absolute; right: 14px; top: 50%; transform: translateY(-50%);
            background: none; border: none; cursor: pointer; color: var(--text-muted);
            padding: 0; display: flex; align-items: center;
        }
        .toggle-pass:hover { color: white; }

        /* Botón submit */
        .submit-btn {
            width: 100%; padding: 0.85rem;
            background: linear-gradient(135deg, #6366f1, #06b6d4);
            border: none; border-radius: 12px; color: white;
            font-size: 1rem; font-weight: 700; cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: 0.5rem;
            transition: opacity 0.2s, transform 0.15s;
            box-shadow: 0 4px 20px rgba(99,102,241,0.4);
            margin-top: 0.5rem;
        }
        .submit-btn:hover { opacity: 0.92; transform: translateY(-1px); }
        .submit-btn:active { transform: translateY(0); }

        /* Alertas */
        .alert {
            padding: 0.8rem 1rem; border-radius: 10px;
            font-size: 0.88rem; margin-bottom: 1.2rem;
            display: flex; align-items: center; gap: 0.6rem;
        }
        .alert-error { background: rgba(239,68,68,0.12); border: 1px solid rgba(239,68,68,0.3); color: #fca5a5; }
        .alert-ok    { background: rgba(34,197,94,0.12);  border: 1px solid rgba(34,197,94,0.3);  color: #86efac; }

        /* Divider */
        .divider {
            display: flex; align-items: center; gap: 0.8rem;
            margin: 1.2rem 0; color: var(--text-muted); font-size: 0.8rem;
        }
        .divider::before, .divider::after {
            content: ''; flex: 1; height: 1px; background: rgba(255,255,255,0.08);
        }

        /* Roles info */
        .roles-grid {
            display: grid; grid-template-columns: 1fr 1fr; gap: 0.6rem; margin-top: 1rem;
        }
        .role-chip {
            padding: 0.5rem 0.8rem; border-radius: 8px;
            background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08);
            font-size: 0.78rem; color: var(--text-muted); text-align: center;
        }
        .role-chip strong { display: block; color: white; font-size: 0.82rem; }

        /* ── Frase motivadora ── */
        .frase-wrap {
            text-align: center; margin-bottom: 1.8rem;
            animation: fadeSlideUp 0.8s ease both;
        }
        .frase-texto {
            font-size: 0.92rem; color: #94a3b8; font-style: italic; line-height: 1.5;
        }
        .frase-texto span { color: var(--accent); font-style: normal; font-weight: 600; }

        /* ── Stats mini ── */
        .stats-mini {
            display: grid; grid-template-columns: repeat(4,1fr); gap: 0.5rem;
            margin-bottom: 1.8rem;
        }
        .stat-mini-item {
            background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.07);
            border-radius: 10px; padding: 0.6rem 0.4rem; text-align: center;
            animation: fadeSlideUp 0.6s ease both;
        }
        .stat-mini-item:nth-child(1) { animation-delay: 0.1s; }
        .stat-mini-item:nth-child(2) { animation-delay: 0.2s; }
        .stat-mini-item:nth-child(3) { animation-delay: 0.3s; }
        .stat-mini-item:nth-child(4) { animation-delay: 0.4s; }
        .stat-mini-num {
            font-size: 1.3rem; font-weight: 700;
            background: linear-gradient(135deg, #6366f1, #06b6d4);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            display: block;
        }
        .stat-mini-label { font-size: 0.68rem; color: #64748b; margin-top: 0.1rem; display: block; }

        /* ── Gráfica de barras mini ── */
        .chart-wrap {
            margin-bottom: 1.5rem;
            animation: fadeSlideUp 0.7s ease 0.3s both;
        }
        .chart-title {
            font-size: 0.72rem; color: #64748b; text-transform: uppercase;
            letter-spacing: 0.06em; margin-bottom: 0.6rem; font-weight: 600;
        }
        .chart-bars { display: flex; align-items: flex-end; gap: 6px; height: 60px; }
        .chart-bar-wrap { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 4px; }
        .chart-bar {
            width: 100%; border-radius: 4px 4px 0 0;
            background: linear-gradient(180deg, #6366f1, #06b6d4);
            transition: height 1s cubic-bezier(0.34,1.56,0.64,1);
            min-height: 4px;
        }
        .chart-bar-label { font-size: 0.62rem; color: #64748b; white-space: nowrap; }
        .chart-bar-val { font-size: 0.68rem; color: #94a3b8; font-weight: 600; }

        /* ── Animaciones de entrada ── */
        @keyframes fadeSlideUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeIn {
            from { opacity: 0; } to { opacity: 1; }
        }
        .login-wrapper { animation: fadeIn 0.5s ease; }
        .login-box { animation: fadeSlideUp 0.6s ease 0.1s both; }
        .brand-header { animation: fadeSlideUp 0.6s ease both; }

        /* ── Partículas flotantes ── */
        .particle {
            position: fixed; border-radius: 50%; pointer-events: none;
            opacity: 0; animation: floatUp linear infinite;
        }
        @keyframes floatUp {
            0%   { opacity: 0; transform: translateY(0) scale(0.5); }
            10%  { opacity: 0.6; }
            90%  { opacity: 0.3; }
            100% { opacity: 0; transform: translateY(-100vh) scale(1.2); }
        }
    </style>
</head>
<body style="background:#0a0f1e; margin:0;">

    <!-- Blobs de fondo -->
    <div class="login-blob blob-1"></div>
    <div class="login-blob blob-2"></div>
    <div class="login-blob blob-3"></div>

    <!-- Partículas flotantes generadas por JS -->
    <div id="particles"></div>

    <div class="login-page">
        <div class="login-wrapper">

            <!-- Marca -->
            <div class="brand-header">
                <div class="brand-logo">🏍️</div>
                <h1>Tienda Delivery</h1>
                <p>Pedidos rápidos, seguimiento en tiempo real</p>
            </div>

            <!-- Frase motivadora -->
            <div class="frase-wrap">
                <p class="frase-texto" id="frase-dinamica">
                    Cargando frase...
                </p>
            </div>

            <!-- Stats mini -->
            <div class="stats-mini">
                <div class="stat-mini-item">
                    <span class="stat-mini-num" id="cnt-pedidos">0</span>
                    <span class="stat-mini-label">Pedidos</span>
                </div>
                <div class="stat-mini-item">
                    <span class="stat-mini-num" id="cnt-riders">0</span>
                    <span class="stat-mini-label">Riders</span>
                </div>
                <div class="stat-mini-item">
                    <span class="stat-mini-num" id="cnt-hoy">0</span>
                    <span class="stat-mini-label">Hoy</span>
                </div>
                <div class="stat-mini-item">
                    <span class="stat-mini-num" id="cnt-clientes">0</span>
                    <span class="stat-mini-label">Clientes</span>
                </div>
            </div>

            <!-- Gráfica mini de barras -->
            <div class="chart-wrap">
                <div class="chart-title">📊 Actividad del sistema</div>
                <div class="chart-bars" id="chart-bars">
                    <!-- generado por JS -->
                </div>
            </div>

            <!-- Card -->
            <div class="login-box">

                <!-- Tabs -->
                <div class="tab-row">
                    <a href="?tab=cliente" class="tab-btn <?php echo $tab=='cliente'?'active':''; ?>">🛒 Portal Cliente</a>
                    <a href="?tab=sistema" class="tab-btn <?php echo $tab=='sistema'?'active':''; ?>">⚙️ Acceso Sistema</a>
                </div>

                <!-- Alertas -->
                <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-error">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <?php echo htmlspecialchars($_GET['error']); ?>
                </div>
                <?php endif; ?>
                <?php if (isset($_GET['ok'])): ?>
                <div class="alert alert-ok">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                    <?php echo htmlspecialchars($_GET['ok']); ?>
                </div>
                <?php endif; ?>

                <?php if ($tab == 'cliente'): ?>
                <!-- ── FORM CLIENTE ── -->
                <form action="login_process.php" method="POST">
                    <input type="hidden" name="tipo" value="cliente">

                    <div class="field-group">
                        <label>Usuario</label>
                        <div class="field-wrap">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            <input type="text" name="username" class="field-input" required placeholder="Tu usuario" autocomplete="username">
                        </div>
                    </div>
                    <div class="field-group">
                        <label>Contraseña</label>
                        <div class="field-wrap">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            <input type="password" name="password" id="pass-cliente" class="field-input" required placeholder="••••••••" autocomplete="current-password">
                            <button type="button" class="toggle-pass" onclick="togglePass('pass-cliente',this)">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="submit-btn">
                        Ingresar al Portal
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                    </button>
                </form>

                <div class="divider">o</div>
                <p style="text-align:center; color:var(--text-muted); font-size:0.88rem; margin:0;">
                    ¿No tienes cuenta?
                    <a href="registro_cliente.php" style="color:var(--accent); font-weight:600; text-decoration:none;"> Regístrate gratis</a>
                </p>

                <?php else: ?>
                <!-- ── FORM SISTEMA ── -->
                <form action="login_process.php" method="POST">
                    <input type="hidden" name="tipo" value="sistema">

                    <div class="field-group">
                        <label>Usuario del sistema</label>
                        <div class="field-wrap">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            <input type="text" name="username" class="field-input" required placeholder="admin / rider1" autocomplete="username">
                        </div>
                    </div>
                    <div class="field-group">
                        <label>Contraseña</label>
                        <div class="field-wrap">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            <input type="password" name="password" id="pass-sistema" class="field-input" required placeholder="••••••••" autocomplete="current-password">
                            <button type="button" class="toggle-pass" onclick="togglePass('pass-sistema',this)">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="submit-btn">
                        Ingresar al Sistema
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                    </button>
                </form>

                <div class="divider">roles disponibles</div>
                <div class="roles-grid">
                    <div class="role-chip"><strong> $  Contador</strong>Gestión total</div>
                    <div class="role-chip"><strong>🔍 Supervisor</strong>Ver entregas</div>
                    <div class="role-chip"><strong>🏍️ Motorizado</strong>App de ruta</div>
                    <div class="role-chip"><strong>💰 Caja</strong>Registrar ventas</div>
                </div>
                <?php endif; ?>

            </div><!-- fin login-box -->
        </div><!-- fin login-wrapper -->
    </div><!-- fin login-page -->

<script>
function togglePass(id, btn) {
    const input = document.getElementById(id);
    const isPass = input.type === 'password';
    input.type = isPass ? 'text' : 'password';
    btn.innerHTML = isPass
        ? '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>'
        : '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
}

// ── Frases motivadoras ────────────────────────────────────────────────────────
const frases = [
    'Tu pedido, <span>en minutos</span>. Tu satisfacción, siempre.',
    'Cada entrega es una promesa <span>cumplida</span>.',
    'Rápido, seguro y <span>en tiempo real</span>.',
    'Porque tu tiempo <span>vale oro</span>.',
    'Del local a tu puerta, <span>sin complicaciones</span>.',
    'Seguimiento en vivo. Tranquilidad <span>garantizada</span>.',
];
document.getElementById('frase-dinamica').innerHTML = frases[Math.floor(Math.random() * frases.length)];

// ── Contador animado ──────────────────────────────────────────────────────────
function animarContador(el, target, duracion = 1200) {
    const start = performance.now();
    const update = (now) => {
        const t = Math.min((now - start) / duracion, 1);
        const ease = 1 - Math.pow(1 - t, 3);
        el.textContent = Math.round(ease * target);
        if (t < 1) requestAnimationFrame(update);
    };
    requestAnimationFrame(update);
}

const statsData = {
    pedidos:  <?php echo $stats['pedidos']; ?>,
    riders:   <?php echo $stats['riders']; ?>,
    hoy:      <?php echo $stats['entregas_hoy']; ?>,
    clientes: <?php echo $stats['clientes']; ?>
};

window.addEventListener('load', () => {
    animarContador(document.getElementById('cnt-pedidos'),  statsData.pedidos,  1000);
    animarContador(document.getElementById('cnt-riders'),   statsData.riders,   800);
    animarContador(document.getElementById('cnt-hoy'),      statsData.hoy,      600);
    animarContador(document.getElementById('cnt-clientes'), statsData.clientes, 1100);
    construirGrafica();
    crearParticulas();
});

// ── Gráfica de barras mini ────────────────────────────────────────────────────
function construirGrafica() {
    const datos = [
        { label: 'Pedidos', val: statsData.pedidos,  color: 'linear-gradient(180deg,#6366f1,#818cf8)' },
        { label: 'Riders',  val: statsData.riders,   color: 'linear-gradient(180deg,#06b6d4,#38bdf8)' },
        { label: 'Hoy',     val: statsData.hoy,      color: 'linear-gradient(180deg,#8b5cf6,#a78bfa)' },
        { label: 'Clientes',val: statsData.clientes, color: 'linear-gradient(180deg,#10b981,#34d399)' },
    ];
    const maxVal = Math.max(...datos.map(d => d.val), 1);
    const container = document.getElementById('chart-bars');
    container.innerHTML = '';
    datos.forEach(d => {
        const pct = Math.max((d.val / maxVal) * 52, 4);
        const wrap = document.createElement('div');
        wrap.className = 'chart-bar-wrap';
        wrap.innerHTML = `
            <span class="chart-bar-val">${d.val}</span>
            <div class="chart-bar" style="height:4px;background:${d.color};" data-h="${pct}"></div>
            <span class="chart-bar-label">${d.label}</span>
        `;
        container.appendChild(wrap);
    });
    // Animar barras
    setTimeout(() => {
        container.querySelectorAll('.chart-bar').forEach(bar => {
            bar.style.height = bar.dataset.h + 'px';
        });
    }, 200);
}

// ── Partículas flotantes ──────────────────────────────────────────────────────
function crearParticulas() {
    const container = document.getElementById('particles');
    const colores = ['#6366f1','#06b6d4','#8b5cf6','#10b981','#f59e0b'];
    for (let i = 0; i < 18; i++) {
        const p = document.createElement('div');
        p.className = 'particle';
        const size = Math.random() * 6 + 3;
        p.style.cssText = `
            width:${size}px; height:${size}px;
            left:${Math.random()*100}vw;
            bottom:${Math.random()*20}vh;
            background:${colores[Math.floor(Math.random()*colores.length)]};
            animation-duration:${Math.random()*12+8}s;
            animation-delay:${Math.random()*8}s;
        `;
        container.appendChild(p);
    }
}
</script>
</body>
</html>
