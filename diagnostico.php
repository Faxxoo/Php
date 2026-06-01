<?php
// ARCHIVO TEMPORAL DE DIAGNÓSTICO - BORRAR DESPUÉS
require_once 'config.php';

echo "<h2>Diagnóstico de Base de Datos</h2>";
echo "<style>body{font-family:monospace;padding:20px;background:#1e293b;color:#e2e8f0;}
      table{border-collapse:collapse;margin:10px 0;}
      th,td{border:1px solid #475569;padding:8px 12px;}
      th{background:#334155;}
      .ok{color:#22c55e;} .err{color:#ef4444;} .warn{color:#f59e0b;}
      h3{color:#60a5fa;margin-top:20px;}</style>";

// 1. Verificar conexión
echo "<h3>1. Conexión a MySQL</h3>";
echo "<p class='ok'>✅ Conexión exitosa a la base de datos <b>tienda_delivery</b></p>";

// 2. Verificar tablas existentes
echo "<h3>2. Tablas en la base de datos</h3>";
$tablas = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
if (count($tablas) == 0) {
    echo "<p class='err'>❌ No hay tablas. Debes importar database.sql en phpMyAdmin.</p>";
} else {
    echo "<p class='ok'>✅ Tablas encontradas: " . implode(', ', $tablas) . "</p>";
}

// 3. Verificar columnas de tabla trabajador
echo "<h3>3. Columnas de tabla 'trabajador'</h3>";
try {
    $cols = $conn->query("DESCRIBE trabajador")->fetchAll();
    echo "<table><tr><th>Campo</th><th>Tipo</th><th>Null</th></tr>";
    foreach ($cols as $c) {
        echo "<tr><td>{$c['Field']}</td><td>{$c['Type']}</td><td>{$c['Null']}</td></tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "<p class='err'>❌ Tabla 'trabajador' no existe: " . $e->getMessage() . "</p>";
}

// 4. Verificar columnas de tabla cliente
echo "<h3>4. Columnas de tabla 'cliente'</h3>";
try {
    $cols = $conn->query("DESCRIBE cliente")->fetchAll();
    echo "<table><tr><th>Campo</th><th>Tipo</th><th>Null</th></tr>";
    $tieneUsername = false;
    $tienePassword = false;
    foreach ($cols as $c) {
        echo "<tr><td>{$c['Field']}</td><td>{$c['Type']}</td><td>{$c['Null']}</td></tr>";
        if ($c['Field'] == 'username') $tieneUsername = true;
        if ($c['Field'] == 'password') $tienePassword = true;
    }
    echo "</table>";
    if (!$tieneUsername || !$tienePassword) {
        echo "<p class='err'>❌ Faltan columnas username/password en tabla cliente. Ejecuta el ALTER TABLE de abajo.</p>";
    } else {
        echo "<p class='ok'>✅ Tabla cliente tiene username y password</p>";
    }
} catch (Exception $e) {
    echo "<p class='err'>❌ Tabla 'cliente' no existe: " . $e->getMessage() . "</p>";
}

// 5. Mostrar trabajadores
echo "<h3>5. Trabajadores registrados</h3>";
try {
    $rows = $conn->query("SELECT idTrabajador, nombre, apellido, username, password, puesto FROM trabajador")->fetchAll();
    if (count($rows) == 0) {
        echo "<p class='err'>❌ No hay trabajadores. Importa database.sql.</p>";
    } else {
        echo "<table><tr><th>ID</th><th>Nombre</th><th>Username</th><th>Password (MD5)</th><th>Puesto</th><th>MD5('12345') correcto?</th></tr>";
        $md5_12345 = md5('12345');
        foreach ($rows as $r) {
            $ok = $r['password'] == $md5_12345 ? "<span class='ok'>✅ Sí</span>" : "<span class='err'>❌ No coincide</span>";
            echo "<tr><td>{$r['idTrabajador']}</td><td>{$r['nombre']} {$r['apellido']}</td><td>{$r['username']}</td><td style='font-size:0.8em'>{$r['password']}</td><td>{$r['puesto']}</td><td>$ok</td></tr>";
        }
        echo "</table>";
        echo "<p class='warn'>MD5 esperado para '12345': <b>$md5_12345</b></p>";
    }
} catch (Exception $e) {
    echo "<p class='err'>❌ Error: " . $e->getMessage() . "</p>";
}

// 6. Mostrar clientes
echo "<h3>6. Clientes registrados</h3>";
try {
    $rows = $conn->query("SELECT * FROM cliente")->fetchAll();
    if (count($rows) == 0) {
        echo "<p class='warn'>⚠️ No hay clientes registrados.</p>";
    } else {
        echo "<table><tr>";
        foreach (array_keys($rows[0]) as $k) echo "<th>$k</th>";
        echo "</tr>";
        foreach ($rows as $r) {
            echo "<tr>";
            foreach ($r as $v) echo "<td>" . htmlspecialchars($v ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<p class='err'>❌ Error: " . $e->getMessage() . "</p>";
}

// 7. Botón para insertar datos de prueba
echo "<h3>7. Acciones de reparación</h3>";

if (isset($_POST['accion'])) {
    if ($_POST['accion'] == 'insertar_trabajadores') {
        try {
            $conn->exec("INSERT IGNORE INTO trabajador(nombre, apellido, ci, username, password, fechaNacimiento, puesto) VALUES
                ('Carlos', 'Perez', '1234567', 'admin', MD5('12345'), '1995-02-10', 'Admin'),
                ('Luis', 'Rider', '9876543', 'rider1', MD5('12345'), '1990-08-20', 'Motorizado')");
            echo "<p class='ok'>✅ Trabajadores insertados. Recarga la página.</p>";
        } catch (Exception $e) {
            echo "<p class='err'>❌ Error: " . $e->getMessage() . "</p>";
        }
    }
    if ($_POST['accion'] == 'fix_passwords') {
        try {
            $conn->exec("UPDATE trabajador SET password = MD5('12345') WHERE username IN ('admin','rider1')");
            echo "<p class='ok'>✅ Contraseñas actualizadas. Recarga la página.</p>";
        } catch (Exception $e) {
            echo "<p class='err'>❌ Error: " . $e->getMessage() . "</p>";
        }
    }
    if ($_POST['accion'] == 'add_cliente_cols') {
        try {
            $conn->exec("ALTER TABLE cliente ADD COLUMN IF NOT EXISTS username VARCHAR(50) UNIQUE");
            $conn->exec("ALTER TABLE cliente ADD COLUMN IF NOT EXISTS password VARCHAR(255)");
            $conn->exec("INSERT IGNORE INTO cliente(nombre, apellido, nit_ci, noTelefono, username, password) VALUES ('Juan','Mamani','778899','70000001','juan',MD5('12345'))");
            echo "<p class='ok'>✅ Columnas y cliente de prueba agregados. Recarga la página.</p>";
        } catch (Exception $e) {
            echo "<p class='err'>❌ Error: " . $e->getMessage() . "</p>";
        }
    }
}

echo "<form method='POST' style='display:inline-block;margin-right:10px;'>
    <input type='hidden' name='accion' value='insertar_trabajadores'>
    <button type='submit' style='padding:10px 16px;background:#3b82f6;color:white;border:none;border-radius:8px;cursor:pointer;'>
        ➕ Insertar admin y rider1
    </button>
</form>";

echo "<form method='POST' style='display:inline-block;margin-right:10px;'>
    <input type='hidden' name='accion' value='fix_passwords'>
    <button type='submit' style='padding:10px 16px;background:#f59e0b;color:white;border:none;border-radius:8px;cursor:pointer;'>
        🔑 Resetear contraseñas a 12345
    </button>
</form>";

echo "<form method='POST' style='display:inline-block;'>
    <input type='hidden' name='accion' value='add_cliente_cols'>
    <button type='submit' style='padding:10px 16px;background:#22c55e;color:white;border:none;border-radius:8px;cursor:pointer;'>
        🛠️ Agregar columnas login a cliente
    </button>
</form>";

echo "<br><br><p style='color:#94a3b8;font-size:0.85rem;'>⚠️ Borra este archivo (diagnostico.php) cuando termines.</p>";
?>
