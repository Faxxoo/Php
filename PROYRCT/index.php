<?php
session_start();
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['tipo']) && $_SESSION['tipo'] == 'cliente') header("Location: cliente.php");
    elseif (isset($_SESSION['puesto']) && $_SESSION['puesto'] == 'Motorizado') header("Location: rider.php");
    else header("Location: admin.php");
    exit;
}
$tab = $_GET['tab'] ?? 'cliente';

// Stats reales de la BD
$stats = ['pedidos'=>0,'riders'=>0,'entregas_hoy'=>0,'clientes'=>0,'en_camino'=>0,'productos'=>0];
try {
    require_once 'config.php';
    $stats['pedidos']      = (int)$conn->query("SELECT COUNT(*) FROM venta")->fetchColumn();
    $stats['riders']       = (int)$conn->query("SELECT COUNT(*) FROM trabajador WHERE puesto='Motorizado'")->fetchColumn();
    $stats['entregas_hoy'] = (int)$conn->query("SELECT COUNT(*) FROM delivery WHERE DATE(fechaRegistro)=CURDATE()")->fetchColumn();
    $stats['clientes']     = (int)$conn->query("SELECT COUNT(*) FROM cliente")->fetchColumn();
    $stats['en_camino']    = (int)$conn->query("SELECT COUNT(*) FROM delivery WHERE estadoEntrega='En Camino' AND estado='Activo'")->fetchColumn();
    $stats['productos']    = (int)$conn->query("SELECT COUNT(*) FROM productos")->fetchColumn();
} catch (Exception $e) { /* BD no disponible */ }

// Frases motivadoras
$frases = [
    ['txt'=>'Tu pedido, <b>en minutos</b>. Tu satisfacción, siempre.','icon'=>'⚡'],
    ['txt'=>'Cada entrega es una promesa <b>cumplida</b>.','icon'=>'🤝'],
    ['txt'=>'Rápido, seguro y <b>en tiempo real</b>.','icon'=>'🚀'],
    ['txt'=>'Porque tu tiempo <b>vale oro</b>.','icon'=>'⏱️'],
    ['txt'=>'Del local a tu puerta, <b>sin complicaciones</b>.','icon'=>'🏠'],
    ['txt'=>'Seguimiento en vivo. Tranquilidad <b>garantizada</b>.','icon'=>'📍'],
];
$frase = $frases[array_rand($frases)];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tienda Delivery — Acceso</title>
<style>
/* ===== RESET & BASE ===== */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',system-ui,sans-serif;background:#0a0f1e;color:#f8fafc;min-height:100vh;overflow-x:hidden}

/* ===== BLOBS ===== */
.blob{position:fixed;border-radius:50%;filter:blur(90px);opacity:.35;pointer-events:none;z-index:0}
.blob-1{width:520px;height:520px;background:radial-gradient(circle,#6366f1,transparent);top:-160px;left:-160px;animation:blobMove1 12s ease-in-out infinite alternate}
.blob-2{width:420px;height:420px;background:radial-gradient(circle,#06b6d4,transparent);bottom:-120px;right:-120px;animation:blobMove2 14s ease-in-out infinite alternate}
.blob-3{width:300px;height:300px;background:radial-gradient(circle,#8b5cf6,transparent);top:50%;left:50%;transform:translate(-50%,-50%);animation:blobMove3 10s ease-in-out infinite alternate}
@keyframes blobMove1{to{transform:translate(40px,30px) scale(1.1)}}
@keyframes blobMove2{to{transform:translate(-30px,20px) scale(1.08)}}
@keyframes blobMove3{to{transform:translate(-40%,-60%) scale(1.15)}}

/* ===== PARTICLES ===== */
.particle{position:fixed;border-radius:50%;pointer-events:none;z-index:0;opacity:0;animation:particleFloat linear infinite}
@keyframes particleFloat{0%{opacity:0;transform:translateY(0) scale(0)}20%{opacity:.6}80%{opacity:.4}100%{opacity:0;transform:translateY(-100vh) scale(1.5)}}

/* ===== TWO-COLUMN LAYOUT ===== */
.page-wrapper{display:flex;min-height:100vh;position:relative;z-index:1}
.left-panel{flex:0 0 55%;display:flex;flex-direction:column;justify-content:center;padding:3rem 3.5rem;gap:2rem;overflow:hidden}
.right-panel{flex:0 0 45%;display:flex;align-items:center;justify-content:center;padding:2rem;min-height:100vh}

/* ===== LEFT PANEL ELEMENTS ===== */
.brand-block{display:flex;align-items:center;gap:1.2rem;animation:fadeSlideUp .6s ease both}
.brand-logo{width:72px;height:72px;border-radius:20px;background:linear-gradient(135deg,#6366f1,#06b6d4);display:flex;align-items:center;justify-content:center;font-size:2.2rem;box-shadow:0 12px 30px rgba(99,102,241,.45);flex-shrink:0}
.brand-text h1{font-size:2rem;font-weight:800;background:linear-gradient(90deg,#6366f1,#06b6d4);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;line-height:1.1}
.brand-text p{color:#94a3b8;font-size:1rem;margin-top:.3rem}
.tagline{font-size:1.1rem;color:#cbd5e1;line-height:1.6;animation:fadeSlideUp .7s .1s ease both}
.tagline .tag-icon{font-size:1.4rem;margin-right:.4rem}

/* ===== STAT CARDS GRID ===== */
.stats-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:.9rem;animation:fadeSlideUp .8s .2s ease both}
.stat-card{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:14px;padding:1rem 1.1rem;text-align:center;transition:transform .25s,box-shadow .25s;backdrop-filter:blur(8px)}
.stat-card:hover{transform:translateY(-3px);box-shadow:0 12px 28px rgba(0,0,0,.35)}
.stat-card .sc-icon{font-size:1.5rem;margin-bottom:.3rem}
.stat-card .sc-num{font-size:1.7rem;font-weight:800;background:linear-gradient(90deg,#6366f1,#06b6d4);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;line-height:1}
.stat-card .sc-label{font-size:.72rem;color:#94a3b8;margin-top:.25rem;text-transform:uppercase;letter-spacing:.04em}

/* ===== MINI BAR CHART ===== */
.chart-block{animation:fadeSlideUp .9s .3s ease both}
.chart-title{font-size:.8rem;color:#64748b;text-transform:uppercase;letter-spacing:.06em;margin-bottom:.7rem}
.bar-chart{display:flex;align-items:flex-end;gap:.7rem;height:80px}
.bar-wrap{display:flex;flex-direction:column;align-items:center;gap:.4rem;flex:1}
.bar{width:100%;border-radius:6px 6px 0 0;background:linear-gradient(180deg,#6366f1,#06b6d4);min-height:4px;transition:height 1.2s cubic-bezier(.34,1.56,.64,1)}
.bar-lbl{font-size:.68rem;color:#64748b;white-space:nowrap}

/* ===== FEATURES LIST ===== */
.features-list{display:flex;flex-direction:column;gap:.7rem;animation:fadeSlideUp 1s .4s ease both}
.feature-item{display:flex;align-items:center;gap:.8rem;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.07);border-radius:10px;padding:.7rem 1rem;font-size:.9rem;color:#cbd5e1}
.feature-item .fi-icon{font-size:1.2rem;flex-shrink:0}

/* ===== INFO BUTTON ===== */
.info-btn{align-self:flex-start;display:flex;align-items:center;gap:.5rem;background:rgba(99,102,241,.15);border:1px solid rgba(99,102,241,.35);color:#a5b4fc;padding:.55rem 1.1rem;border-radius:50px;font-size:.85rem;cursor:pointer;transition:all .25s;animation:fadeSlideUp 1.1s .5s ease both}
.info-btn:hover{background:rgba(99,102,241,.3);transform:translateY(-2px)}

/* ===== RIGHT PANEL / LOGIN CARD ===== */
.login-card{width:100%;max-width:420px;background:rgba(15,23,42,.75);backdrop-filter:blur(18px);border:1px solid rgba(255,255,255,.1);border-radius:20px;padding:2.5rem;box-shadow:0 30px 60px rgba(0,0,0,.5);animation:fadeSlideUp .6s ease both}
.card-header{text-align:center;margin-bottom:1.8rem}
.card-logo{width:60px;height:60px;border-radius:16px;background:linear-gradient(135deg,#6366f1,#06b6d4);display:flex;align-items:center;justify-content:center;font-size:1.8rem;margin:0 auto 1rem;box-shadow:0 10px 25px rgba(99,102,241,.4)}
.card-header h2{font-size:1.6rem;font-weight:700;margin-bottom:.3rem}
.card-header p{color:#64748b;font-size:.9rem}

/* ===== TABS ===== */
.tabs{display:flex;background:rgba(15,23,42,.6);border-radius:10px;padding:4px;margin-bottom:1.6rem;border:1px solid rgba(255,255,255,.08)}
.tab-btn{flex:1;text-align:center;padding:.55rem .4rem;border-radius:7px;color:#64748b;font-size:.88rem;font-weight:500;cursor:pointer;text-decoration:none;transition:all .25s;border:none;background:transparent}
.tab-btn.active{background:#6366f1;color:#fff;box-shadow:0 4px 12px rgba(99,102,241,.4)}

/* ===== FORM ELEMENTS ===== */
.form-group{margin-bottom:1.2rem}
.form-group label{display:block;margin-bottom:.45rem;color:#94a3b8;font-size:.85rem}
.field-input{width:100%;padding:.72rem 1rem;background:rgba(15,23,42,.7);border:1px solid rgba(255,255,255,.15);border-radius:8px;color:#f8fafc;font-size:.95rem;transition:all .3s}
.field-input:focus{outline:none;border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,.2)}
.field-input::placeholder{color:#475569}
.pass-wrap{position:relative}
.pass-wrap .field-input{padding-right:2.8rem}
.toggle-pass{position:absolute;right:.8rem;top:50%;transform:translateY(-50%);background:none;border:none;color:#64748b;cursor:pointer;font-size:1rem;padding:.2rem;transition:color .2s}
.toggle-pass:hover{color:#a5b4fc}

/* ===== ALERTS ===== */
.alert{padding:.8rem 1rem;border-radius:8px;margin-bottom:1.2rem;font-size:.88rem;display:flex;align-items:center;gap:.5rem}
.alert-error{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#fca5a5}
.alert-ok{background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);color:#86efac}

/* ===== SUBMIT BUTTON ===== */
.btn-submit{width:100%;padding:.8rem;background:linear-gradient(90deg,#6366f1,#06b6d4);color:#fff;border:none;border-radius:8px;font-size:1rem;font-weight:600;cursor:pointer;transition:opacity .25s,transform .15s;margin-top:.5rem}
.btn-submit:hover{opacity:.9;transform:translateY(-1px)}
.btn-submit:active{transform:translateY(1px)}

/* ===== ROLES GRID ===== */
.roles-grid{display:grid;grid-template-columns:1fr 1fr;gap:.6rem;margin-bottom:1.2rem}
.role-chip{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:8px;padding:.55rem .7rem;font-size:.8rem;color:#94a3b8;display:flex;align-items:center;gap:.4rem}
.role-chip .rc-icon{font-size:1rem}

/* ===== REGISTER LINK ===== */
.register-link{text-align:center;margin-top:1rem;font-size:.85rem;color:#64748b}
.register-link a{color:#818cf8;text-decoration:none;font-weight:500}
.register-link a:hover{text-decoration:underline}

/* ===== MODAL ===== */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(6px);z-index:1000;display:none;align-items:center;justify-content:center}
.modal-overlay.open{display:flex;animation:fadeIn .25s ease}
.modal-box{background:rgba(15,23,42,.95);border:1px solid rgba(255,255,255,.12);border-radius:20px;padding:2rem;max-width:420px;width:90%;box-shadow:0 40px 80px rgba(0,0,0,.6);animation:fadeSlideUp .35s ease}
.modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem}
.modal-header h3{font-size:1.2rem;font-weight:700}
.modal-close{background:none;border:none;color:#64748b;font-size:1.4rem;cursor:pointer;line-height:1;transition:color .2s}
.modal-close:hover{color:#f8fafc}
.modal-stats{display:grid;grid-template-columns:1fr 1fr;gap:.8rem}
.modal-stat{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);border-radius:12px;padding:1rem;text-align:center}
.modal-stat .ms-num{font-size:1.8rem;font-weight:800;background:linear-gradient(90deg,#6366f1,#06b6d4);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}
.modal-stat .ms-label{font-size:.75rem;color:#64748b;margin-top:.2rem;text-transform:uppercase;letter-spacing:.04em}

/* ===== ANIMATION ===== */
@keyframes fadeSlideUp{from{opacity:0;transform:translateY(22px)}to{opacity:1;transform:translateY(0)}}
@keyframes fadeIn{from{opacity:0}to{opacity:1}}

/* ===== RESPONSIVE ===== */
@media(max-width:900px){
  .left-panel{display:none}
  .right-panel{flex:1;padding:1.5rem}
  .page-wrapper{justify-content:center}
}
</style>
</head>
<body>

<!-- Background blobs -->
<div class="blob blob-1"></div>
<div class="blob blob-2"></div>
<div class="blob blob-3"></div>
<!-- Particles container -->
<div id="particles"></div>

<!-- ===== MODAL ===== -->
<div class="modal-overlay" id="infoModal">
  <div class="modal-box">
    <div class="modal-header">
      <h3>ℹ️ Información del Sistema</h3>
      <button class="modal-close" onclick="cerrarModal()">✕</button>
    </div>
    <div class="modal-stats">
      <div class="modal-stat">
        <div class="ms-num" id="m-pedidos">0</div>
        <div class="ms-label">Total Pedidos</div>
      </div>
      <div class="modal-stat">
        <div class="ms-num" id="m-riders">0</div>
        <div class="ms-label">Riders Disponibles</div>
      </div>
      <div class="modal-stat">
        <div class="ms-num" id="m-clientes">0</div>
        <div class="ms-label">Clientes Registrados</div>
      </div>
      <div class="modal-stat">
        <div class="ms-num" id="m-productos">0</div>
        <div class="ms-label">Productos Disponibles</div>
      </div>
    </div>
  </div>
</div>

<!-- ===== PAGE WRAPPER ===== -->
<div class="page-wrapper">

  <!-- ===== LEFT PANEL ===== -->
  <div class="left-panel">

    <!-- Brand -->
    <div class="brand-block">
      <div class="brand-logo">🏍️</div>
      <div class="brand-text">
        <h1>Tienda Delivery</h1>
        <p>Sistema de gestión de entregas</p>
      </div>
    </div>

    <!-- Tagline -->
    <!--div class="tagline">
      <span class="tag-icon"><?php echo $frase['icon']; ?></span>
      <?php echo $frase['txt']; ?>
    </div>

    <!-- Stats grid -->
    <!--div class="stats-grid">
      <div class="stat-card">
        <div class="sc-icon">📦</div>
        <div class="sc-num" id="sc-pedidos">0</div>
        <div class="sc-label">Pedidos totales</div>
      </div>
      <div class="stat-card">
        <div class="sc-icon">🏍️</div>
        <div class="sc-num" id="sc-riders">0</div>
        <div class="sc-label">Riders activos</div>
      </div>
      <div class="stat-card">
        <div class="sc-icon">✅</div>
        <div class="sc-num" id="sc-hoy">0</div>
        <div class="sc-label">Entregas hoy</div>
      </div>
      <div class="stat-card">
        <div class="sc-icon">👥</div>
        <div class="sc-num" id="sc-clientes">0</div>
        <div class="sc-label">Clientes</div>
      </div>
      <div class="stat-card">
        <div class="sc-icon">🚀</div>
        <div class="sc-num" id="sc-camino">0</div>
        <div class="sc-label">En camino</div>
      </div>
      <div class="stat-card">
        <div class="sc-icon">🛍️</div>
        <div class="sc-num" id="sc-productos">0</div>
        <div class="sc-label">Productos</div>
      </div>
    </div>

    <!-- Mini bar chart -->
    <!--div class="chart-block">
      <div class="chart-title">Resumen visual</div>
      <div class="bar-chart">
        <div class="bar-wrap">
          <div class="bar" id="bar-pedidos" style="height:4px"></div>
          <div class="bar-lbl">Pedidos</div>
        </div>
        <div class="bar-wrap">
          <div class="bar" id="bar-riders" style="height:4px"></div>
          <div class="bar-lbl">Riders</div>
        </div>
        <div class="bar-wrap">
          <div class="bar" id="bar-hoy" style="height:4px"></div>
          <div class="bar-lbl">Hoy</div>
        </div>
        <div class="bar-wrap">
          <div class="bar" id="bar-clientes" style="height:4px"></div>
          <div class="bar-lbl">Clientes</div>
        </div>
      </div>
    </div>

    <!-- Features list -->
    <div class="features-list">
      <div class="feature-item"><span class="fi-icon">🛒</span> Lo que quieres, donde estés. Compra fácil, recibe rápido.</div>
      <div class="feature-item"><span class="fi-icon">📊</span> Super completo todo a solo un click de distancia</div>
    </div>

    <!-- Info button -->
    <button class="info-btn" onclick="abrirModal()">ℹ️ Información del sistema</button>

  </div><!-- /left-panel -->

  <!-- ===== RIGHT PANEL ===== -->
  <div class="right-panel">
    <div class="login-card">

      <!-- Card header -->
      <div class="card-header">
        <!--div class="card-logo">🏍️</div-->
        <h2>Bienvenido</h2>
        <p>Accede a tu cuenta para continuar</p>
      </div>

      <!-- Tabs -->
      <div class="tabs">
        <a href="?tab=cliente" class="tab-btn <?php echo $tab==='cliente'?'active':''; ?>">Portal Cliente</a>
        <a href="?tab=sistema" class="tab-btn <?php echo $tab==='sistema'?'active':''; ?>">Acceso Sistema</a>
      </div>

      <!-- Alerts -->
      <?php if (!empty($_GET['error'])): ?>
        <div class="alert alert-error">⚠️ <?php echo htmlspecialchars($_GET['error']); ?></div>
      <?php endif; ?>
      <?php if (!empty($_GET['ok'])): ?>
        <div class="alert alert-ok">✅ <?php echo htmlspecialchars($_GET['ok']); ?></div>
      <?php endif; ?>

      <!-- ===== TAB: CLIENTE ===== -->
      <?php if ($tab === 'cliente'): ?>
      <form method="POST" action="login_process.php">
        <input type="hidden" name="tipo" value="cliente">
        <div class="form-group">
          <label for="uname_c">Usuario</label>
          <input type="text" id="uname_c" name="username" class="field-input" placeholder="Tu usuario de cliente" required autocomplete="username">
        </div>
        <div class="form-group">
          <label for="pass_c">Contraseña</label>
          <div class="pass-wrap">
            <input type="password" id="pass_c" name="password" class="field-input" placeholder="••••••••" required autocomplete="current-password">
            <button type="button" class="toggle-pass" onclick="togglePass('pass_c',this)">👁️</button>
          </div>
        </div>
        <button type="submit" class="btn-submit">Ingresar como Cliente</button>
      </form>
      <div class="register-link">¿No tienes cuenta? <a href="registro_cliente.php">Regístrate aquí</a></div>

      <!-- ===== TAB: SISTEMA ===== -->
      <?php else: ?>
      <!--div class="roles-grid">
        <div class="role-chip"><span class="rc-icon">👑</span> Admin</div>
        <div class="role-chip"><span class="rc-icon">🔍</span> Supervisor</div>
        <div class="role-chip"><span class="rc-icon">🏍️</span> Motorizado</div>
        <div class="role-chip"><span class="rc-icon">💰</span> Caja</div>
      </div-->
      <form method="POST" action="login_process.php">
        <input type="hidden" name="tipo" value="sistema">
        <div class="form-group">
          <label for="usuario">Usuario del sistema</label>
          <input type="text" id="usuario" name="username" class="field-input" placeholder="admin / rider1" required autocomplete="username">
        </div>
        <div class="form-group">
          <label for="pass_s">Contraseña</label>
          <div class="pass-wrap">
            <input type="password" id="pass_s" name="password" class="field-input" placeholder="••••••••" required>
            <button type="button" class="toggle-pass" onclick="togglePass('pass_s',this)">👁️</button>
          </div>
        </div>
        <button type="submit" class="btn-submit">Ingresar al Sistema</button>
      </form>
      <?php endif; ?>

    </div><!-- /login-card -->
  </div><!-- /right-panel -->

</div><!-- /page-wrapper -->

<script>
// ===== STATS DATA FROM PHP =====
const statsData = {
  pedidos:     <?php echo $stats['pedidos']; ?>,
  riders:      <?php echo $stats['riders']; ?>,
  entregas_hoy:<?php echo $stats['entregas_hoy']; ?>,
  clientes:    <?php echo $stats['clientes']; ?>,
  en_camino:   <?php echo $stats['en_camino']; ?>,
  productos:   <?php echo $stats['productos']; ?>
};

// ===== TOGGLE PASSWORD =====
function togglePass(id, btn) {
  const inp = document.getElementById(id);
  if (inp.type === 'password') { inp.type = 'text'; btn.textContent = '🙈'; }
  else { inp.type = 'password'; btn.textContent = '👁️'; }
}

// ===== ANIMATED COUNTER =====
function animarContador(el, target, duration) {
  if (!el) return;
  const start = performance.now();
  function step(now) {
    const elapsed = now - start;
    const progress = Math.min(elapsed / duration, 1);
    const ease = 1 - Math.pow(1 - progress, 3);
    el.textContent = Math.round(ease * target);
    if (progress < 1) requestAnimationFrame(step);
  }
  requestAnimationFrame(step);
}

// ===== BUILD BAR CHART =====
function construirGrafica() {
  const vals = [statsData.pedidos, statsData.riders, statsData.entregas_hoy, statsData.clientes];
  const ids  = ['bar-pedidos','bar-riders','bar-hoy','bar-clientes'];
  const max  = Math.max(...vals, 1);
  const maxH = 72; // px
  setTimeout(() => {
    ids.forEach((id, i) => {
      const bar = document.getElementById(id);
      if (bar) bar.style.height = Math.max(4, Math.round((vals[i] / max) * maxH)) + 'px';
    });
  }, 300);
}

// ===== CREATE PARTICLES =====
function crearParticulas() {
  const container = document.getElementById('particles');
  if (!container) return;
  const colors = ['#6366f1','#06b6d4','#8b5cf6','#a78bfa','#67e8f9'];
  for (let i = 0; i < 18; i++) {
    const p = document.createElement('div');
    p.className = 'particle';
    const size = Math.random() * 5 + 3;
    p.style.cssText = [
      `width:${size}px`, `height:${size}px`,
      `left:${Math.random()*100}vw`,
      `top:${Math.random()*100}vh`,
      `background:${colors[Math.floor(Math.random()*colors.length)]}`,
      `animation-duration:${Math.random()*12+8}s`,
      `animation-delay:${Math.random()*8}s`
    ].join(';');
    container.appendChild(p);
  }
}

// ===== MODAL =====
function abrirModal() {
  const overlay = document.getElementById('infoModal');
  overlay.classList.add('open');
  animarContador(document.getElementById('m-pedidos'),  statsData.pedidos,      900);
  animarContador(document.getElementById('m-riders'),   statsData.riders,       900);
  animarContador(document.getElementById('m-clientes'), statsData.clientes,     900);
  animarContador(document.getElementById('m-productos'),statsData.productos,    900);
}
function cerrarModal() {
  document.getElementById('infoModal').classList.remove('open');
}
document.getElementById('infoModal').addEventListener('click', function(e) {
  if (e.target === this) cerrarModal();
});

// ===== INIT =====
document.addEventListener('DOMContentLoaded', () => {
  crearParticulas();
  construirGrafica();
  animarContador(document.getElementById('sc-pedidos'),  statsData.pedidos,      1200);
  animarContador(document.getElementById('sc-riders'),   statsData.riders,       1200);
  animarContador(document.getElementById('sc-hoy'),      statsData.entregas_hoy, 1200);
  animarContador(document.getElementById('sc-clientes'), statsData.clientes,     1200);
  animarContador(document.getElementById('sc-camino'),   statsData.en_camino,    1200);
  animarContador(document.getElementById('sc-productos'),statsData.productos,    1200);
});
</script>
</body>
</html>
