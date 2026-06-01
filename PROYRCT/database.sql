-- =========================================
-- CREACION DE BASE DE DATOS
-- =========================================
CREATE DATABASE IF NOT EXISTS tienda_delivery;
USE tienda_delivery;

-- =========================================
-- TABLA PRODUCTOS
-- =========================================
CREATE TABLE productos (
    idProducto INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    precioUnitario DECIMAL(10,2) NOT NULL
);

-- =========================================
-- TABLA TRABAJADOR
-- =========================================
CREATE TABLE trabajador (
    idTrabajador INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    apellido VARCHAR(50) NOT NULL,
    ci VARCHAR(20) UNIQUE NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    fechaNacimiento DATE,
    puesto ENUM('Caja', 'Supervisor', 'Admin', 'Motorizado') NOT NULL
);

-- =========================================
-- TABLA CLIENTE (con login)
-- =========================================
CREATE TABLE cliente (
    idCliente INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    apellido VARCHAR(50) NOT NULL,
    nit_ci VARCHAR(30),
    noTelefono VARCHAR(20),
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL
);

-- =========================================
-- TABLA VENTA
-- =========================================
CREATE TABLE venta (
    idVenta INT AUTO_INCREMENT PRIMARY KEY,
    idTrabajador INT NOT NULL,
    idCliente INT NOT NULL,
    precioTotal DECIMAL(10,2) NOT NULL,
    tipoEntrega ENUM('Tienda', 'Delivery', 'Recojo') NOT NULL,
    fechaVenta DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_venta_trabajador FOREIGN KEY (idTrabajador) REFERENCES trabajador(idTrabajador),
    CONSTRAINT fk_venta_cliente FOREIGN KEY (idCliente) REFERENCES cliente(idCliente)
);

-- =========================================
-- TABLA DETALLE VENTA
-- =========================================
CREATE TABLE ventaDetalle (
    idDetalle INT AUTO_INCREMENT PRIMARY KEY,
    idVenta INT NOT NULL,
    idProducto INT NOT NULL,
    cantidad INT NOT NULL CHECK (cantidad > 0),
    precioUnitario DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    CONSTRAINT fk_detalle_venta FOREIGN KEY (idVenta) REFERENCES venta(idVenta),
    CONSTRAINT fk_detalle_producto FOREIGN KEY (idProducto) REFERENCES productos(idProducto)
);

-- =========================================
-- TABLA DELIVERY
-- =========================================
CREATE TABLE delivery (
    idDelivery INT AUTO_INCREMENT PRIMARY KEY,
    idVenta INT NOT NULL,
    idMotorizado INT,
    estadoEntrega ENUM('Pendiente', 'En Camino', 'Entregado', 'No Entregado') DEFAULT 'Pendiente',
    ubicacionGPS VARCHAR(255),
    direccionEscrita TEXT NOT NULL,
    estado ENUM('Activo', 'Finalizado') DEFAULT 'Activo',
    fechaRegistro DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_delivery_venta FOREIGN KEY (idVenta) REFERENCES venta(idVenta),
    CONSTRAINT fk_delivery_motorizado FOREIGN KEY (idMotorizado) REFERENCES trabajador(idTrabajador)
);

-- =========================================
-- INDICES
-- =========================================
CREATE INDEX idx_producto_nombre ON productos(nombre);
CREATE INDEX idx_cliente_nombre ON cliente(nombre);
CREATE INDEX idx_venta_fecha ON venta(fechaVenta);

-- =========================================
-- DATOS DE PRUEBA
-- Contraseña de todos: 12345 (MD5)
-- =========================================
INSERT INTO productos(nombre, descripcion, precioUnitario) VALUES
('Hamburguesa', 'Hamburguesa doble carne', 35.50),
('Pizza Familiar', 'Pizza peperoni', 80.00),
('Gaseosa', 'Coca Cola 2L', 15.00);

INSERT INTO trabajador(nombre, apellido, ci, username, password, fechaNacimiento, puesto) VALUES
('Carlos', 'Perez', '1234567', 'admin', MD5('12345'), '1995-02-10', 'Admin'),
('Luis', 'Rider', '9876543', 'rider1', MD5('12345'), '1990-08-20', 'Motorizado');

-- Cliente con login: usuario=juan, contraseña=12345
INSERT INTO cliente(nombre, apellido, nit_ci, noTelefono, username, password) VALUES
('Juan', 'Mamani', '778899', '70000001', 'juan', MD5('12345'));

INSERT INTO venta(idTrabajador, idCliente, precioTotal, tipoEntrega) VALUES
(1, 1, 130.50, 'Delivery');

INSERT INTO ventaDetalle(idVenta, idProducto, cantidad, precioUnitario, subtotal) VALUES
(1, 1, 2, 35.50, 71.00),
(1, 2, 1, 80.00, 80.00);

INSERT INTO delivery(idVenta, idMotorizado, estadoEntrega, ubicacionGPS, direccionEscrita, estado) VALUES
(1, 2, 'En Camino', '-17.7833,-63.1821', 'Av. Banzer 3er anillo', 'Activo');

-- ── SEGUNDO PEDIDO Y DELIVERY DE PRUEBA ─────────────────────────────────────
INSERT INTO venta(idTrabajador, idCliente, precioTotal, tipoEntrega) VALUES
(1, 1, 95.00, 'Delivery');

INSERT INTO ventaDetalle(idVenta, idProducto, cantidad, precioUnitario, subtotal) VALUES
(2, 2, 1, 80.00, 80.00),
(2, 3, 1, 15.00, 15.00);

-- Este delivery está Pendiente (sin rider asignado aún) para probar ese estado
INSERT INTO delivery(idVenta, idMotorizado, estadoEntrega, ubicacionGPS, direccionEscrita, estado) VALUES
(2, NULL, 'Pendiente', NULL, 'Calle Murillo #450, Barrio Las Palmas', 'Activo');
