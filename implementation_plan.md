# Plan de Implementación - Tienda Delivery

Este plan describe los cambios necesarios para alinear la base de datos con el código del proyecto, resolver la incompatibilidad del controlador de base de datos (cambiando de MySQLi a PDO), e implementar los archivos faltantes para asegurar el correcto funcionamiento de toda la aplicación.

## User Review Required

> [!IMPORTANT]
> **Cambios en el esquema de Base de Datos:**
> Para que el sistema funcione correctamente con los archivos PHP actuales, es necesario ajustar el esquema de base de datos provisto:
> 1. Agregar las columnas `username` y `password` a las tablas `trabajador` y `cliente` para habilitar el inicio de sesión.
> 2. Agregar el valor `'Motorizado'` al enum del campo `puesto` en la tabla `trabajador`.
> 3. Agregar la columna `idMotorizado` (clave foránea a `trabajador`) en la tabla `delivery`.
> 4. Sembrar cuentas de prueba para cada rol (`admin`, `rider1`, `juan` de cliente) con contraseñas encriptadas en MD5 para permitir pruebas inmediatas.

> [!IMPORTANT]
> **Cambio a PDO (PHP Data Objects):**
> Los archivos del proyecto (`cliente.php`, `admin.php`, `rider.php`, `graficos.php`) están programados usando la interfaz PDO de PHP, pero `config.php` actualmente establece una conexión MySQLi y `login_process.php` realiza consultas con MySQLi. 
> Cambiaremos `config.php` y `login_process.php` para usar **PDO** configurado con la opción `PDO::ATTR_CASE => PDO::CASE_LOWER` para asegurar compatibilidad total con el código existente.

> [!NOTE]
> **Archivos faltantes a crear:**
> Faltan componentes interactivos en el proyecto que provocan errores de "404 Not Found" en el cliente y el panel:
> - `admin_acciones.php`: Procesa la asignación de motorizados y cierre de entregas.
> - `api_actualizar_gps.php`: Recibe las coordenadas GPS enviadas en tiempo real desde la aplicación del motorizado (`rider.php`).
> - `api_ubicacion.php`: Devuelve las coordenadas de entrega y del motorizado al cliente para el rastreo en vivo (`cliente.php`).
> - `registro_cliente.php`: Formulario de registro para nuevos clientes.

---

## Proposed Changes

### 1. Base de Datos

#### [MODIFY] [database.sql](file:///c:/wamp64/www/PROYRCT/database.sql)
Actualizaremos el script SQL de la base de datos para:
- Crear las columnas `username` y `password` en las tablas `trabajador` y `cliente`.
- Agregar `'Motorizado'` al campo `puesto` de la tabla `trabajador`.
- Agregar `idMotorizado` a la tabla `delivery` con su respectiva llave foránea.
- Modificar los datos de prueba agregando cuentas operativas:
  - Administrador: `admin` / `admin`
  - Supervisor: `ana` / `ana`
  - Cajero: `carlos` / `carlos`
  - Motorizado/Rider: `rider1` / `rider1`
  - Cliente: `juan` / `juan`

---

### 2. Configuración y Autenticación

#### [MODIFY] [config.php](file:///c:/wamp64/www/PROYRCT/config.php)
- Cambiar la inicialización de MySQLi a un objeto PDO.
- Configurar atributos de PDO: `PDO::ATTR_ERRMODE` a `PDO::ERRMODE_EXCEPTION` y `PDO::ATTR_CASE` a `PDO::CASE_LOWER` (para forzar nombres de columnas en minúscula, tal como el código existente las consume).

#### [MODIFY] [login_process.php](file:///c:/wamp64/www/PROYRCT/login_process.php)
- Adaptar para usar PDO y consultas preparadas seguras.
- Soportar el inicio de sesión tanto de clientes (buscando en la tabla `cliente`) como de trabajadores del sistema (buscando en `trabajador`).

---

### 3. Nuevos Módulos y APIs del Sistema

#### [NEW] [admin_acciones.php](file:///c:/wamp64/www/PROYRCT/admin_acciones.php)
- Procesar las solicitudes POST desde el panel administrativo:
  - `asignar_rider`: Actualizar el campo `idMotorizado` de la entrega correspondiente.
  - `cerrar_entrega`: Cambiar el estado de la entrega en `delivery` a `Finalizado`.
- Redirigir de vuelta a `admin.php`.

#### [NEW] [api_actualizar_gps.php](file:///c:/wamp64/www/PROYRCT/api_actualizar_gps.php)
- Recibir solicitudes POST con JSON `{lat, lng}`.
- Actualizar la columna `ubicacionGPS` de la entrega activa (`estado = 'Activo'`) asignada al motorizado autenticado.

#### [NEW] [api_ubicacion.php](file:///c:/wamp64/www/PROYRCT/api_ubicacion.php)
- Endpoint GET que acepta un `idDelivery` y devuelve un JSON con:
  - `ok`: bool (si se encontró la entrega)
  - `lat`: latitud del rider
  - `lng`: longitud del rider
  - `rider`: nombre completo del rider
  - `estado`: estado de entrega

#### [NEW] [registro_cliente.php](file:///c:/wamp64/www/PROYRCT/registro_cliente.php)
- Crear una interfaz premium integrada con `style.css` para el registro de clientes.
- Recibir datos, encriptar contraseña con MD5, e insertar el nuevo cliente en la base de datos de manera segura.

---

## Verification Plan

### Manual Verification
1. **Importación de base de datos:** Ejecutar el archivo `database.sql` en MySQL/MariaDB y verificar que se creen las tablas y carguen los datos.
2. **Prueba de Inicios de Sesión:**
   - Iniciar sesión como administrador (`admin`/`admin`) y comprobar acceso a `admin.php`.
   - Iniciar sesión como motorizado (`rider1`/`rider1`) y comprobar acceso a `rider.php`.
   - Iniciar sesión como cliente (`juan`/`juan`) y comprobar acceso a `cliente.php`.
3. **Prueba de Flujo Completo de Delivery:**
   - Desde `admin.php` asignar la entrega activa al motorizado "Luis Rider".
   - Abrir `rider.php` (sesión de `rider1`), verificar que la entrega esté visible.
   - Activar el GPS en el panel del motorizado y simular el inicio de ruta y avance de la ubicación.
   - En `cliente.php` (sesión del cliente `juan`), verificar que el mapa se actualice dinámicamente con la ubicación en vivo del rider.
   - Completar la entrega en `rider.php` y verificar que el estado cambie a "Entregado" y se actualice en el historial del administrador.
4. **Prueba de Registro de Clientes:**
   - Acceder a `registro_cliente.php`, registrar un usuario nuevo y verificar que pueda iniciar sesión correctamente.
