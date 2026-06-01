# Php
Aquí va la explicación completa del proyecto.

---

# Explicación completa del proyecto — Tienda Delivery

## Arquitectura general

```
NAVEGADOR (HTML + CSS + JS)
        ↕  HTTP requests
SERVIDOR (PHP en WAMP/Apache)
        ↕  PDO / SQL
BASE DE DATOS (MySQL)
```

No hay framework. Todo es PHP puro con PDO para la BD, Leaflet.js para mapas y la API de geolocalización del navegador para GPS. El servidor corre en `localhost` con WAMP.

---

## La base de datos — estructura y relaciones

```
productos
    idProducto, nombre, descripcion, precioUnitario

trabajador
    idTrabajador, nombre, apellido, ci, username, password(MD5), puesto(Admin/Supervisor/Caja/Motorizado)

cliente
    idCliente, nombre, apellido, nit_ci, noTelefono, username, password(MD5)

venta
    idVenta, idTrabajador→trabajador, idCliente→cliente, precioTotal, tipoEntrega(Tienda/Delivery/Recojo), fechaVenta

ventaDetalle
    idDetalle, idVenta→venta, idProducto→productos, cantidad, precioUnitario, subtotal

delivery
    idDelivery, idVenta→venta, idMotorizado→trabajador, estadoEntrega(Pendiente/En Camino/Entregado/No Entregado),
    ubicacionGPS (texto "lat,lng"), direccionEscrita, estado(Activo/Finalizado), fechaRegistro
```

La columna clave para el GPS es `ubicacionGPS` en la tabla `delivery`. Se guarda como texto simple: `"-17.783300,-63.182100"`. No hay coordenadas del destino — solo la posición actual del rider.

---

## Los archivos PHP — qué hace cada uno

### `config.php`
Crea la conexión PDO a MySQL y la expone como `$conn`. Todos los demás archivos hacen `require_once 'config.php'` para usarla.

```php
$conn = new PDO("mysql:host=localhost;dbname=tienda_delivery;charset=utf8mb4", "root", "");
PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC  // claves en minúsculas
```

**Importante:** PDO con MySQL devuelve los nombres de columna en minúsculas cuando usas `FETCH_ASSOC`. Por eso todas las queries usan aliases explícitos como `idVenta AS idventa`.

---

### `index.php` — Login
- Verifica si ya hay sesión activa y redirige al panel correcto
- Conecta a la BD para obtener stats (pedidos, riders, entregas hoy, clientes) para mostrar en la gráfica
- Muestra dos pestañas: Portal Cliente y Acceso Sistema
- Tiene animaciones CSS (blobs, partículas, fadeSlideUp), contadores JS animados y frases aleatorias

---

### `login_process.php` — Procesa el login
```
POST: username + password + tipo
        ↓
Si tipo=cliente → SELECT en tabla cliente WHERE username=:u AND password=MD5(:p)
Si tipo=sistema → SELECT en tabla trabajador WHERE username=:u AND password=MD5(:p)
        ↓
Si encuentra fila → guarda en $_SESSION y redirige
Si no → redirige con ?error=...
```

Las sesiones guardan:
- Cliente: `$_SESSION['user_id']`, `$_SESSION['nombre']`, `$_SESSION['tipo']='cliente'`
- Trabajador: `$_SESSION['user_id']`, `$_SESSION['nombre']`, `$_SESSION['puesto']`

---

### `registro_cliente.php`
Formulario para crear cuenta de cliente. Valida que el username no exista, hashea la contraseña con MD5 e inserta en la tabla `cliente`.

---

### `nuevo_pedido.php` — Hacer un pedido
1. Carga todos los productos de la BD
2. El cliente elige cantidades con botones +/−
3. JS actualiza el carrito lateral en tiempo real (sin recargar)
4. Al confirmar (POST):
   - Busca un trabajador Admin/Caja/Supervisor para registrar la venta
   - Inserta en `venta` con `beginTransaction()`
   - Inserta cada producto en `ventaDetalle`
   - Si es Delivery, inserta en `delivery` con `estadoEntrega='Pendiente'`
   - `commit()` — si algo falla, `rollBack()` deshace todo

---

### `cliente.php` — Portal del cliente
- Carga todos los pedidos del cliente con JOIN a `delivery` y `trabajador` (rider)
- Para cada pedido con delivery, pre-carga los datos en un objeto JS `pedidosData`
- Al hacer clic en un pedido → `seleccionarPedido()` inicializa el mapa
- El mapa siempre muestra el marcador 📦 del destino
- Si el rider tiene GPS activo → muestra marcador 🏍️ y línea de ruta
- Polling cada 5 segundos a `api_ubicacion.php` para actualizar la posición

---

### `admin.php` — Panel Admin/Supervisor
- Muestra tabla de entregas activas con datos de cliente, rider y estado
- Mapa compacto (280px) con un marcador por entrega
- Admin puede asignar rider (dropdown → POST a `admin_acciones.php`)
- Admin puede finalizar entregas
- Historial de las últimas 10 entregas finalizadas

---

### `admin_acciones.php`
Recibe POST del panel admin:
- `asignar_rider`: `UPDATE delivery SET idMotorizado = X WHERE idDelivery = Y`
- `cerrar_entrega`: `UPDATE delivery SET estado='Finalizado', estadoEntrega='Entregado'`

---

### `rider.php` — App del motorizado
- Carga las entregas asignadas al rider (`WHERE idMotorizado = :rider AND estado='Activo'`)
- Carga los pedidos disponibles sin rider (`WHERE idMotorizado IS NULL AND estado='Activo'`)
- Mapa único con marcadores para ambas listas
- Botones de estado: Pendiente → En Camino → Entregado/No Entregado
- Botón GPS que activa el rastreo en tiempo real

---

### `admin_productos.php` — CRUD de productos
Agregar, editar y eliminar productos. No permite eliminar si el producto tiene ventas.

### `admin_trabajadores.php` — CRUD de trabajadores
Agregar, editar y eliminar trabajadores. No permite eliminar si tiene ventas registradas ni eliminar tu propia cuenta.

### `graficos.php` — Dashboard
3 gráficos con Chart.js: entregas por estado (dona), ganancias por tipo (barras), entregas por rider (barras).

### `logout.php`
`session_destroy()` + redirect a `index.php`.

---

## El sistema de mapas y GPS — explicación detallada

### La librería: Leaflet.js

Leaflet es una librería JavaScript open source para mapas interactivos. Se carga desde CDN, no requiere API key ni pago. Los tiles (imágenes del mapa) vienen de OpenStreetMap.

```html
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
```

**Crear un mapa básico:**
```javascript
// 1. Crear el mapa en un div con id="mapa-cliente"
const map = L.map('mapa-cliente').setView([-17.7833, -63.1821], 15);
//                                         lat        lng          zoom (1=mundo, 20=edificio)

// 2. Agregar la capa de tiles (las imágenes del mapa)
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap'
}).addTo(map);

// 3. Agregar un marcador
const marker = L.marker([-17.7833, -63.1821]).addTo(map);
marker.bindPopup('<b>Texto del popup</b>').openPopup();

// 4. Marcador con ícono personalizado (div HTML)
const icono = L.divIcon({
    html: '<div style="font-size:28px;">🏍️</div>',
    iconSize: [30, 30],
    iconAnchor: [15, 15],  // punto del ícono que toca el mapa
    className: ''
});
const marker2 = L.marker([lat, lng], { icon: icono }).addTo(map);

// 5. Línea entre dos puntos (ruta punteada)
const linea = L.polyline(
    [[lat1, lng1], [lat2, lng2]],
    { color: '#06b6d4', weight: 3, dashArray: '8, 5' }
).addTo(map);

// 6. Ajustar zoom para ver todos los marcadores
const bounds = L.latLngBounds([[lat1,lng1], [lat2,lng2]]);
map.fitBounds(bounds, { padding: [50, 50] });
```

---

### El flujo completo del GPS en tiempo real

```
RIDER (celular/PC)                 SERVIDOR MySQL              CLIENTE (navegador)
      │                                  │                           │
      │ Clic "Activar GPS"               │                           │
      │                                  │                           │
      │ navigator.geolocation            │                           │
      │ .watchPosition(callback)         │                           │
      │                                  │                           │
      │ Cada vez que se mueve:           │                           │
      │──POST api_actualizar_gps.php────▶│                           │
      │  { lat: -17.783, lng: -63.182 }  │                           │
      │                                  │ UPDATE delivery           │
      │                                  │ SET ubicacionGPS=         │
      │                                  │ '-17.783,-63.182'         │
      │                                  │ WHERE idMotorizado=2      │
      │                                  │                           │
      │                                  │◀──GET api_ubicacion.php───│
      │                                  │   ?idDelivery=1           │
      │                                  │   (cada 5 segundos)       │
      │                                  │                           │
      │                                  │──JSON { ok,lat,lng }─────▶│
      │                                  │                           │ mover marcador 🏍️
      │                                  │                           │ redibujar línea
```

---

### `api_actualizar_gps.php` — recibe la posición del rider

```php
// Solo acepta riders autenticados
if ($_SESSION['puesto'] != 'Motorizado') { http_response_code(403); exit; }

// Lee el JSON del body del POST
$data = json_decode(file_get_contents('php://input'), true);
$lat = floatval($data['lat']);
$lng = floatval($data['lng']);
$coordStr = $lat . ',' . $lng;  // "-17.783300,-63.182100"

// Actualiza TODAS las entregas activas del rider
UPDATE delivery SET ubicacionGPS = '-17.783300,-63.182100'
WHERE idMotorizado = 2 AND estado = 'Activo'
```

El rider puede tener varias entregas activas — se actualiza la misma coordenada en todas.

---

### `api_ubicacion.php` — sirve la posición al cliente

```php
// El cliente pregunta: ¿dónde está el rider de mi delivery #1?
SELECT d.ubicacionGPS, d.estadoEntrega, t.nombre, t.apellido
FROM delivery d
LEFT JOIN trabajador t ON d.idMotorizado = t.idTrabajador
WHERE d.idDelivery = 1 AND d.estado = 'Activo'

// Parsea el string "lat,lng"
$coords = explode(',', $row['ubicaciongps']);  // ["-17.7833", "-63.1821"]

// Devuelve JSON
{ "ok": true, "lat": "-17.7833", "lng": "-63.1821",
  "rider": "Luis Rider", "estado": "En Camino" }
```

---

### El GPS en `rider.php` — `navigator.geolocation`

```javascript
function activarGPS() {
    // watchPosition llama al callback CADA VEZ que el dispositivo se mueve
    // (a diferencia de getCurrentPosition que solo llama una vez)
    watchId = navigator.geolocation.watchPosition(
        onGPSUpdate,   // callback de éxito
        onGPSError,    // callback de error
        {
            enableHighAccuracy: true,  // usa GPS del hardware, no solo WiFi/celular
            maximumAge: 3000,          // acepta posición de hasta 3 segundos de antigüedad
            timeout: 10000             // espera máximo 10 segundos por una posición
        }
    );
}

function onGPSUpdate(pos) {
    const lat = pos.coords.latitude;
    const lng = pos.coords.longitude;
    const acc = pos.coords.accuracy;  // precisión en metros

    // 1. Actualizar marcador en el mapa del rider
    if (!riderMarker) {
        riderMarker = L.marker([lat, lng], { icon: makeGPSIcon() }).addTo(map);
    } else {
        riderMarker.setLatLng([lat, lng]);  // mover marcador existente
    }

    // 2. Enviar al servidor (fetch = AJAX moderno)
    fetch('api_actualizar_gps.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ lat, lng })
    });
}
```

**Errores posibles de GPS:**
- Código 1: El usuario denegó el permiso de ubicación
- Código 2: No se pudo obtener la posición (sin señal)
- Código 3: Timeout — tardó más de 10 segundos

---

### El polling en `cliente.php`

```javascript
// Cada 5 segundos pregunta al servidor dónde está el rider
timerPoll = setInterval(() => pollRider(idDelivery), 5000);

function pollRider(idDelivery) {
    fetch(`api_ubicacion.php?idDelivery=${idDelivery}`)
        .then(r => r.json())
        .then(data => {
            if (!data.ok) return;  // rider sin GPS activo

            const lat = parseFloat(data.lat);
            const lng = parseFloat(data.lng);

            // Mover o crear marcador del rider
            ponerMarcadorRider(lat, lng, data.rider, data.estado);

            // Redibujar línea de ruta rider → destino
            if (rutaLine) leafletMap.removeLayer(rutaLine);
            rutaLine = L.polyline(
                [[lat, lng], [destinoMarker.getLatLng().lat, destinoMarker.getLatLng().lng]],
                { color: '#06b6d4', weight: 3, dashArray: '8, 5' }
            ).addTo(leafletMap);
        });
}
```

El polling se detiene automáticamente cuando el cliente selecciona otro pedido (`clearInterval(timerPoll)`).

---

### Por qué el mapa del destino no es exacto

El sistema guarda la dirección como texto (`"Av. Banzer 3er anillo"`), no como coordenadas. Para mostrar el marcador 📦 del destino en el mapa, se usa la última posición conocida del rider como referencia, o las coordenadas por defecto de Santa Cruz. Para tener el destino exacto se necesitaría **geocodificación** (convertir texto de dirección a lat/lng), lo cual requiere una API externa como Google Maps o Nominatim.

---

### Resumen del flujo completo de un pedido

```
1. Cliente hace pedido en nuevo_pedido.php
        ↓ INSERT en venta + ventaDetalle + delivery(Pendiente)
2. Admin ve el pedido en admin.php
        ↓ Asigna rider → UPDATE delivery SET idMotorizado=2
3. Rider ve la entrega en rider.php
        ↓ Clic "Iniciar Ruta" → UPDATE estadoEntrega='En Camino'
        ↓ Activa GPS → watchPosition envía coords cada movimiento
        ↓ api_actualizar_gps.php guarda coords en delivery.ubicacionGPS
4. Cliente ve el mapa en cliente.php
        ↓ Polling cada 5s a api_ubicacion.php
        ↓ Marcador 🏍️ se mueve en tiempo real
5. Rider llega → "Confirmar Entrega" → estadoEntrega='Entregado'
6. Admin finaliza → estado='Finalizado' → desaparece de activos
```
------------------------------------------------------------------------------------------------------------------>


config.php
Es el único archivo que no tiene HTML. Solo crea la conexión a MySQL:

$conn = new PDO("mysql:host=localhost;dbname=tienda_delivery;charset=utf8mb4", "root", "");
PDO es la capa de acceso a datos. Todos los demás archivos hacen require_once 'config.php' para obtener $conn. La opción FETCH_ASSOC hace que los resultados vengan como arrays con claves en minúsculas ($row['idventa'] no $row['idVenta']).

index.php — Login
PHP (arriba):

Verifica si ya hay sesión activa y redirige al panel correcto
Consulta 6 estadísticas de la BD (pedidos, riders, entregas hoy, clientes, en camino, productos)
Elige una frase motivadora aleatoria de un array
HTML:

Layout de dos columnas: panel informativo izquierdo + card de login derecho
En móvil el panel izquierdo se oculta
Panel izquierdo:

6 tarjetas de stats con contadores animados en JS
Gráfica de barras mini (4 barras animadas)
Lista de características
Botón "ℹ️ Información" que abre un modal
Card de login (derecho):

Dos pestañas: Portal Cliente y Acceso Sistema
Formularios que hacen POST a login_process.php
Toggle para mostrar/ocultar contraseña
JS:

animarContador() — cuenta desde 0 hasta el valor real con ease-out
construirGrafica() — crea las barras y las anima con CSS transition
crearParticulas() — genera 18 puntos flotantes en el fondo
abrirModal() / cerrarModal() — controla el modal de información
login_process.php
Recibe el POST del formulario de login:

Si tipo=cliente → busca en tabla cliente WHERE username=:u AND password=MD5(:p)
Si tipo=sistema → busca en tabla trabajador WHERE username=:u AND password=MD5(:p)
Si encuentra el usuario guarda en $_SESSION y redirige. Si no, redirige con ?error=... en la URL.

registro_cliente.php
Formulario de registro para nuevos clientes. Valida que el username no exista, hashea la contraseña con MD5 e inserta en la tabla cliente. Al terminar redirige al login con mensaje de éxito.

nuevo_pedido.php — Hacer un pedido
PHP:

Carga todos los productos de la BD
Si es POST: valida, calcula totales, inserta en venta + ventaDetalle + delivery usando una transacción
HTML:

Layout de dos columnas: productos a la izquierda, carrito a la derecha
Buscador de productos en tiempo real
Botones +/− para cantidades
Tres opciones de tipo de entrega (Delivery/Recojo/Tienda)
Campo de dirección que aparece solo si es Delivery
JS:

cambiarCantidad() — suma o resta 1 al input
actualizarTodo() — recalcula subtotales, actualiza el carrito lateral y habilita/deshabilita el botón confirmar
selTipo() — cambia el estilo de las tarjetas de tipo y muestra/oculta el campo de dirección
filtrarProductos() — filtra la lista según lo que escribe el usuario
Transacción PHP:

$conn->beginTransaction();
// INSERT en venta
// INSERT en ventaDetalle (uno por producto)
// INSERT en delivery (si es Delivery)
$conn->commit();
// Si algo falla: $conn->rollBack()
cliente.php — Portal del cliente
PHP:

Carga todos los pedidos del cliente con JOIN a delivery y trabajador
Para cada pedido con delivery, construye el objeto $pedidosJS con lat/lng del rider, nombre del rider, estado, dirección y productos
Ese objeto se pasa a JavaScript como JSON
HTML:

Columna izquierda: lista de pedidos con detalles y totales
Columna derecha: info del rider + mapa (sticky)
Botón "📱 Ver QR / Recibo" en pedidos con estado "Entregado"
JS:

seleccionarPedido() — al hacer clic en una tarjeta, actualiza la info del rider e inicializa el mapa
inicializarMapa() — crea el mapa Leaflet si no existe, o limpia los marcadores si ya existe
ponerMarcadorRider() — coloca/mueve el marcador 🏍️ y dibuja la línea de ruta
pollRider() — cada 5 segundos llama a api_ubicacion.php para actualizar la posición
centrarMapa() — centra el mapa en el rider
limpiarMapa() — destruye el mapa y muestra mensaje
admin.php — Panel Admin/Supervisor
PHP:

Carga entregas activas con datos de cliente, rider y estado
Carga lista de riders para el dropdown de asignación
Construye $markersJson para el mapa
HTML:

Stats bar (total activas, riders, en camino, sin rider)
Mapa compacto con marcadores de todas las entregas activas
Tabla de entregas con formularios de asignación y finalización
Historial de las últimas 10 entregas finalizadas
JS del mapa admin:

Crea el mapa con L.map('mapa-admin')
Itera deliveries y coloca un marcador por entrega con offset para que no se superpongan
Cada marcador tiene popup con: # orden, cliente, estado, rider, dirección
admin_acciones.php
Recibe POST del panel admin:

asignar_rider: UPDATE delivery SET idMotorizado = X
cerrar_entrega: UPDATE delivery SET estado='Finalizado', estadoEntrega='Entregado'
Siempre redirige de vuelta a admin.php.

rider.php — App del motorizado
PHP:

Carga las entregas asignadas al rider (WHERE idMotorizado = :rider AND estado='Activo')
Carga los pedidos disponibles sin rider (WHERE idMotorizado IS NULL AND estado='Activo')
Maneja el POST de cambio de estado con lista blanca de valores permitidos
HTML:

Layout de dos columnas: lista scrollable izquierda + mapa sticky derecha
Sección "Mis Entregas" con botones de acción
Sección "Pedidos Disponibles" (solo lectura)
Botón GPS en el navbar
JS:

initMap() — crea el mapa con marcadores 🏍️ (azul) para mis entregas y 📦 (verde) para disponibles
focusMarker() — al hacer clic en una tarjeta, centra el mapa en ese marcador y lo resalta
activarGPS() — toggle que activa/desactiva navigator.geolocation.watchPosition
onGPSUpdate() — recibe la posición, mueve el marcador 📡 en el mapa y hace POST a api_actualizar_gps.php
setGpsUI() — actualiza el indicador visual del GPS en el navbar
api_actualizar_gps.php
Solo acepta POST de usuarios con puesto='Motorizado'. Lee el JSON del body, extrae lat/lng y actualiza ubicacionGPS en todas las entregas activas del rider:

UPDATE delivery SET ubicacionGPS = '-17.783300,-63.182100'
WHERE idMotorizado = 2 AND estado = 'Activo'
api_ubicacion.php
Acepta GET con ?idDelivery=X. Lee ubicacionGPS de la BD y devuelve JSON:

{ "ok": true, "lat": "-17.7833", "lng": "-63.1821", "rider": "Luis Rider", "estado": "En Camino" }
Si no hay GPS activo devuelve { "ok": false }.

admin_productos.php y admin_trabajadores.php
CRUD completo (crear, leer, actualizar, eliminar) para productos y trabajadores respectivamente. Ambos tienen protecciones: no se puede eliminar un producto con ventas, ni un trabajador con ventas, ni tu propia cuenta.

graficos.php
Carga datos de la BD y los pasa a Chart.js para mostrar 3 gráficos: entregas por estado (dona), ganancias por tipo de entrega (barras), entregas por rider (barras).

generar_qr.php
Verifica que el pedido pertenezca al cliente logueado, construye el texto del recibo y llama a la API de QR Server para generar la imagen. Tiene dos modos: pagina (HTML completo con el recibo visual) e imagen (solo el PNG del QR).

logout.php
session_destroy() + redirect a index.php. Simple.
